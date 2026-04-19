#!/bin/bash

set -euo pipefail

PBS_NAMESPACE="${PBS_NAMESPACE:-AKEB}"
PBS_ENV_FILE="${PBS_ENV_FILE:-/etc/clock-alarm/pbs.env}"
PBS_CLIENT_DIR="${PBS_CLIENT_DIR:-/usr/local/bin/pbs_client}"
PBS_BACKUP_SCRIPT="${PBS_BACKUP_SCRIPT:-/usr/local/bin/pbs_backup.sh}"
PBS_CRON_FILE="${PBS_CRON_FILE:-/etc/cron.d/pbs_backup}"
PBS_BACKUP_TIME="${PBS_BACKUP_TIME:-5 0 * * *}"
INCLUDE_NETPLAN="${INCLUDE_NETPLAN:-1}"

log() {
	printf '[clock-pbs-setup] %s\n' "$*"
}

die() {
	printf '[clock-pbs-setup] ERROR: %s\n' "$*" >&2
	exit 1
}

if [ "$(id -u)" -ne 0 ]; then
	die "run as root"
fi

if [ -n "${PBS_PASSWORD_FILE:-}" ] && [ -z "${PBS_PASSWORD:-}" ]; then
	PBS_PASSWORD="$(cat "$PBS_PASSWORD_FILE")"
	export PBS_PASSWORD
fi

[ -n "${PBS_REPOSITORY:-}" ] || die "PBS_REPOSITORY is required"
[ -n "${PBS_PASSWORD:-}" ] || die "PBS_PASSWORD or PBS_PASSWORD_FILE is required"
[ -n "${PBS_FINGERPRINT:-}" ] || die "PBS_FINGERPRINT is required"
[ -x "$PBS_CLIENT_DIR/proxmox-backup-client.sh" ] || die "$PBS_CLIENT_DIR/proxmox-backup-client.sh is missing"

mkdir -p "$(dirname "$PBS_ENV_FILE")"
{
	printf 'export PBS_REPOSITORY=%q\n' "$PBS_REPOSITORY"
	printf 'export PBS_PASSWORD=%q\n' "$PBS_PASSWORD"
	printf 'export PBS_FINGERPRINT=%q\n' "$PBS_FINGERPRINT"
	printf 'export PBS_NAMESPACE=%q\n' "$PBS_NAMESPACE"
} >"$PBS_ENV_FILE"
chmod 0600 "$PBS_ENV_FILE"

cat >"$PBS_BACKUP_SCRIPT" <<'EOF'
#!/bin/bash

set -euo pipefail

PBS_ENV_FILE="${PBS_ENV_FILE:-/etc/clock-alarm/pbs.env}"
PBS_CLIENT_DIR="${PBS_CLIENT_DIR:-/usr/local/bin/pbs_client}"
STATE_DIR="$(mktemp -d /tmp/clock-alarm-state.XXXXXX)"

cleanup() {
	rm -rf "$STATE_DIR"
}
trap cleanup EXIT

. "$PBS_ENV_FILE"

copy_path() {
	local src="$1"
	if [ -e "$src" ]; then
		mkdir -p "$STATE_DIR$(dirname "$src")"
		cp -a "$src" "$STATE_DIR$src"
	fi
}

copy_path /var/www/clock_alarm/config.php
for db in /var/www/clock_alarm/*.sqlite3; do
	[ -e "$db" ] && copy_path "$db"
done
copy_path /etc/cron.d/pbs_backup
copy_path /usr/local/bin/pbs_backup.sh
copy_path /etc/clock-alarm/pbs.env
if [ "${INCLUDE_NETPLAN:-1}" = "1" ]; then
	for file in /etc/netplan/*; do
		[ -e "$file" ] && copy_path "$file"
	done
fi

apt-get clean
cd "$PBS_CLIENT_DIR"
./proxmox-backup-client.sh backup root.pxar:/ clock-state.pxar:"$STATE_DIR" -ns "${PBS_NAMESPACE:-AKEB}"
EOF
chmod 0700 "$PBS_BACKUP_SCRIPT"

cat >"$PBS_CRON_FILE" <<EOF
# Run every day at 00:05
${PBS_BACKUP_TIME} root ${PBS_BACKUP_SCRIPT}
EOF
chmod 0644 "$PBS_CRON_FILE"

log "PBS backup script installed at $PBS_BACKUP_SCRIPT"
log "PBS environment saved at $PBS_ENV_FILE"
log "Cron installed at $PBS_CRON_FILE"
