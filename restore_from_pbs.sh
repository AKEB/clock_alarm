#!/bin/bash

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/clock_alarm}"
PBS_NAMESPACE="${PBS_NAMESPACE:-AKEB}"
PBS_BACKUP_GROUP="${PBS_BACKUP_GROUP:-host/$(hostname)}"
PBS_ARCHIVE="${PBS_ARCHIVE:-root.pxar}"
PBS_STATE_ARCHIVE="${PBS_STATE_ARCHIVE:-clock-state.pxar}"
PBS_RESTORE_SOURCE="${PBS_RESTORE_SOURCE:-auto}"
PBS_CLIENT_BIN="${PBS_CLIENT_BIN:-}"
PBS_CLIENT_TARBALL_URL="${PBS_CLIENT_TARBALL_URL:-}"
PBS_CLIENT_SHA256="${PBS_CLIENT_SHA256:-}"
PBS_SNAPSHOT="${PBS_SNAPSHOT:-latest}"
RESTORE_NETPLAN="${RESTORE_NETPLAN:-0}"
RESTORE_PBS_CONFIG="${RESTORE_PBS_CONFIG:-1}"
RESTORE_TMP_DIR="${RESTORE_TMP_DIR:-/tmp/clock-alarm-pbs-restore}"

log() {
	printf '[clock-pbs-restore] %s\n' "$*" >&2
}

die() {
	printf '[clock-pbs-restore] ERROR: %s\n' "$*" >&2
	exit 1
}

require_root() {
	if [ "$(id -u)" -ne 0 ]; then
		die "run as root"
	fi
}

load_password_file() {
	if [ -n "${PBS_PASSWORD_FILE:-}" ] && [ -z "${PBS_PASSWORD:-}" ]; then
		PBS_PASSWORD="$(cat "$PBS_PASSWORD_FILE")"
		export PBS_PASSWORD
	fi
}

install_client_from_url() {
	[ -n "$PBS_CLIENT_TARBALL_URL" ] || return 1

	log "Installing proxmox-backup-client from PBS_CLIENT_TARBALL_URL"
	mkdir -p /usr/local/bin/pbs_client
	curl -fsSL "$PBS_CLIENT_TARBALL_URL" -o /tmp/proxmox-backup-client.tgz
	if [ -n "$PBS_CLIENT_SHA256" ]; then
		printf '%s  %s\n' "$PBS_CLIENT_SHA256" /tmp/proxmox-backup-client.tgz | sha256sum -c -
	fi
	tar -xzf /tmp/proxmox-backup-client.tgz -C /usr/local/bin/pbs_client --strip-components=1
	chmod 0755 /usr/local/bin/pbs_client/proxmox-backup-client /usr/local/bin/pbs_client/proxmox-backup-client.sh 2>/dev/null || true
}

find_client() {
	if [ -n "$PBS_CLIENT_BIN" ] && [ -x "$PBS_CLIENT_BIN" ]; then
		printf '%s\n' "$PBS_CLIENT_BIN"
		return 0
	fi
	if [ -x /usr/local/bin/pbs_client/proxmox-backup-client ]; then
		printf '%s\n' /usr/local/bin/pbs_client/proxmox-backup-client
		return 0
	fi
	if command -v proxmox-backup-client >/dev/null 2>&1; then
		command -v proxmox-backup-client
		return 0
	fi
	if install_client_from_url; then
		printf '%s\n' /usr/local/bin/pbs_client/proxmox-backup-client
		return 0
	fi
	return 1
}

require_pbs_env() {
	[ -n "${PBS_REPOSITORY:-}" ] || die "PBS_REPOSITORY is required"
	[ -n "${PBS_PASSWORD:-}" ] || die "PBS_PASSWORD or PBS_PASSWORD_FILE is required"
	[ -n "${PBS_FINGERPRINT:-}" ] || die "PBS_FINGERPRINT is required"
	export PBS_REPOSITORY PBS_PASSWORD PBS_FINGERPRINT
}

latest_snapshot() {
	local client="$1"
	"$client" snapshot list "$PBS_BACKUP_GROUP" \
		--repository "$PBS_REPOSITORY" \
		--ns "$PBS_NAMESPACE" \
		--output-format json |
		python3 -c '
import datetime
import json
import sys

data = json.load(sys.stdin)
if not data:
    raise SystemExit("no PBS snapshots found")

def key(item):
    return item.get("backup-time", item.get("backup-time-string", ""))

item = sorted(data, key=key)[-1]
for field in ("backup-id", "backup-type", "backup-time"):
    if field not in item:
        break
else:
    timestamp = datetime.datetime.fromtimestamp(int(item["backup-time"]), datetime.UTC)
    backup_time = timestamp.strftime("%Y-%m-%dT%H:%M:%SZ")
    print(f"{item['backup-type']}/{item['backup-id']}/{backup_time}")
    raise SystemExit

for field in ("snapshot", "backup-dir", "backup-group"):
    if field in item:
        print(item[field])
        raise SystemExit

raise SystemExit(f"cannot determine snapshot name from: {item}")
'
}

restore_patterns() {
	local client="$1"
	local snapshot="$2"
	rm -rf "$RESTORE_TMP_DIR"
	mkdir -p "$RESTORE_TMP_DIR"

	local patterns=(
		--pattern var/www/clock_alarm/config.php
		--pattern var/www/clock_alarm/database_2024_05_13.sqlite3
	)

	if [ "$RESTORE_PBS_CONFIG" = "1" ]; then
		patterns+=(
			--pattern etc/cron.d/pbs_backup
			--pattern usr/local/bin/pbs_backup.sh
			--pattern usr/local/bin/pbs_client/**
		)
	fi

	if [ "$RESTORE_NETPLAN" = "1" ]; then
		patterns+=(--pattern etc/netplan/**)
	fi

	log "Restoring selected files from ${snapshot}"
	"$client" restore "$snapshot" "$PBS_ARCHIVE" "$RESTORE_TMP_DIR" \
		--repository "$PBS_REPOSITORY" \
		--ns "$PBS_NAMESPACE" \
		--allow-existing-dirs true \
		"${patterns[@]}"
}

restore_state_archive() {
	local client="$1"
	local snapshot="$2"
	rm -rf "$RESTORE_TMP_DIR"
	mkdir -p "$RESTORE_TMP_DIR"

	log "Trying state archive ${PBS_STATE_ARCHIVE} from ${snapshot}"
	"$client" restore "$snapshot" "$PBS_STATE_ARCHIVE" "$RESTORE_TMP_DIR" \
		--repository "$PBS_REPOSITORY" \
		--ns "$PBS_NAMESPACE" \
		--allow-existing-dirs true
}

restore_selected_files() {
	local client="$1"
	local snapshot="$2"

	case "$PBS_RESTORE_SOURCE" in
		state)
			restore_state_archive "$client" "$snapshot"
			;;
		root)
			restore_patterns "$client" "$snapshot"
			;;
		auto)
			if restore_state_archive "$client" "$snapshot"; then
				log "State archive restored"
			else
				log "State archive unavailable; falling back to selected restore from ${PBS_ARCHIVE}"
				restore_patterns "$client" "$snapshot"
			fi
			;;
		*)
			die "unknown PBS_RESTORE_SOURCE=${PBS_RESTORE_SOURCE}; expected auto, state, or root"
			;;
	esac
}

copy_first_present() {
	local dst="$1"
	shift
	local src
	for src in "$@"; do
		if [ ! -e "$src" ]; then
			continue
		fi
		mkdir -p "$(dirname "$dst")"
		cp -a "$src" "$dst"
		log "Restored $dst"
		return 0
	done
	return 1
}

install_restored_files() {
	copy_first_present "$APP_DIR/config.php" \
		"$RESTORE_TMP_DIR/var/www/clock_alarm/config.php" \
		"$RESTORE_TMP_DIR/root.pxar.didx/var/www/clock_alarm/config.php" \
		"$RESTORE_TMP_DIR/clock-state.pxar.didx/var/www/clock_alarm/config.php" || true
	copy_first_present "$APP_DIR/database_2024_05_13.sqlite3" \
		"$RESTORE_TMP_DIR/var/www/clock_alarm/database_2024_05_13.sqlite3" \
		"$RESTORE_TMP_DIR/root.pxar.didx/var/www/clock_alarm/database_2024_05_13.sqlite3" \
		"$RESTORE_TMP_DIR/clock-state.pxar.didx/var/www/clock_alarm/database_2024_05_13.sqlite3" || true

	if [ "$RESTORE_PBS_CONFIG" = "1" ]; then
		copy_first_present /etc/cron.d/pbs_backup \
			"$RESTORE_TMP_DIR/etc/cron.d/pbs_backup" \
			"$RESTORE_TMP_DIR/root.pxar.didx/etc/cron.d/pbs_backup" \
			"$RESTORE_TMP_DIR/clock-state.pxar.didx/etc/cron.d/pbs_backup" || true
		copy_first_present /usr/local/bin/pbs_backup.sh \
			"$RESTORE_TMP_DIR/usr/local/bin/pbs_backup.sh" \
			"$RESTORE_TMP_DIR/root.pxar.didx/usr/local/bin/pbs_backup.sh" \
			"$RESTORE_TMP_DIR/clock-state.pxar.didx/usr/local/bin/pbs_backup.sh" || true
		if [ -d "$RESTORE_TMP_DIR/usr/local/bin/pbs_client" ]; then
			mkdir -p /usr/local/bin
			rm -rf /usr/local/bin/pbs_client
			cp -a "$RESTORE_TMP_DIR/usr/local/bin/pbs_client" /usr/local/bin/pbs_client
			chmod 0755 /usr/local/bin/pbs_client/proxmox-backup-client /usr/local/bin/pbs_client/proxmox-backup-client.sh 2>/dev/null || true
			log "Restored /usr/local/bin/pbs_client"
		elif [ -d "$RESTORE_TMP_DIR/root.pxar.didx/usr/local/bin/pbs_client" ]; then
			mkdir -p /usr/local/bin
			rm -rf /usr/local/bin/pbs_client
			cp -a "$RESTORE_TMP_DIR/root.pxar.didx/usr/local/bin/pbs_client" /usr/local/bin/pbs_client
			chmod 0755 /usr/local/bin/pbs_client/proxmox-backup-client /usr/local/bin/pbs_client/proxmox-backup-client.sh 2>/dev/null || true
			log "Restored /usr/local/bin/pbs_client"
		elif [ -d "$RESTORE_TMP_DIR/clock-state.pxar.didx/usr/local/bin/pbs_client" ]; then
			mkdir -p /usr/local/bin
			rm -rf /usr/local/bin/pbs_client
			cp -a "$RESTORE_TMP_DIR/clock-state.pxar.didx/usr/local/bin/pbs_client" /usr/local/bin/pbs_client
			chmod 0755 /usr/local/bin/pbs_client/proxmox-backup-client /usr/local/bin/pbs_client/proxmox-backup-client.sh 2>/dev/null || true
			log "Restored /usr/local/bin/pbs_client"
		fi
	fi

	if [ "$RESTORE_NETPLAN" = "1" ] && [ -d "$RESTORE_TMP_DIR/etc/netplan" ]; then
		mkdir -p /etc/netplan
		cp -a "$RESTORE_TMP_DIR/etc/netplan/." /etc/netplan/
		chmod 0600 /etc/netplan/*.yaml 2>/dev/null || true
		log "Restored /etc/netplan"
	elif [ "$RESTORE_NETPLAN" = "1" ] && [ -d "$RESTORE_TMP_DIR/root.pxar.didx/etc/netplan" ]; then
		mkdir -p /etc/netplan
		cp -a "$RESTORE_TMP_DIR/root.pxar.didx/etc/netplan/." /etc/netplan/
		chmod 0600 /etc/netplan/*.yaml 2>/dev/null || true
		log "Restored /etc/netplan"
	elif [ "$RESTORE_NETPLAN" = "1" ] && [ -d "$RESTORE_TMP_DIR/clock-state.pxar.didx/etc/netplan" ]; then
		mkdir -p /etc/netplan
		cp -a "$RESTORE_TMP_DIR/clock-state.pxar.didx/etc/netplan/." /etc/netplan/
		chmod 0600 /etc/netplan/*.yaml 2>/dev/null || true
		log "Restored /etc/netplan"
	fi

	chown www-data:www-data "$APP_DIR/config.php" "$APP_DIR"/database_*.sqlite3 2>/dev/null || true
}

write_pbs_backup_if_missing() {
	if [ "$RESTORE_PBS_CONFIG" != "1" ]; then
		return
	fi
	if [ -f /usr/local/bin/pbs_backup.sh ] && [ -f /etc/cron.d/pbs_backup ]; then
		return
	fi

	log "Writing local PBS backup config from environment"
	mkdir -p /usr/local/bin
	{
		cat <<'EOF'
#!/bin/bash

set -euo pipefail

EOF
		printf 'export PBS_REPOSITORY=%q\n' "$PBS_REPOSITORY"
		printf 'export PBS_PASSWORD=%q\n' "$PBS_PASSWORD"
		printf 'export PBS_FINGERPRINT=%q\n' "$PBS_FINGERPRINT"
		cat <<'EOF'

apt-get clean
cd /usr/local/bin/pbs_client
EOF
		printf './proxmox-backup-client.sh backup root.pxar:/ -ns %q\n' "$PBS_NAMESPACE"
	} >/usr/local/bin/pbs_backup.sh
	chmod 0700 /usr/local/bin/pbs_backup.sh

	cat >/etc/cron.d/pbs_backup <<'EOF'
# Run every day at 00:05
5 0 * * * root /usr/local/bin/pbs_backup.sh
EOF
}

main() {
	require_root
	load_password_file
	require_pbs_env
	local client
	client="$(find_client)" || die "proxmox-backup-client not found; set PBS_CLIENT_BIN or PBS_CLIENT_TARBALL_URL"

	local snapshot="$PBS_SNAPSHOT"
	if [ "$snapshot" = "latest" ]; then
		snapshot="$(latest_snapshot "$client")"
	fi

	restore_selected_files "$client" "$snapshot"
	install_restored_files
	write_pbs_backup_if_missing
	log "PBS state restore complete"
}

main "$@"
