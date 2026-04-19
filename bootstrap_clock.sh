#!/bin/bash

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/clock_alarm}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
REPO_URL="${REPO_URL:-https://github.com/AKEB/clock_alarm.git}"
REPO_BRANCH="${REPO_BRANCH:-main}"
PHP_VERSION="${PHP_VERSION:-8.4}"
RESTORE_MODE="${RESTORE_MODE:-none}"
RUN_LOW_WRITE="${RUN_LOW_WRITE:-1}"
RUN_REBOOT_HINT="${RUN_REBOOT_HINT:-1}"
SETUP_PBS_BACKUP="${SETUP_PBS_BACKUP:-auto}"

log() {
	printf '[clock-bootstrap] %s\n' "$*"
}

die() {
	printf '[clock-bootstrap] ERROR: %s\n' "$*" >&2
	exit 1
}

require_root() {
	if [ "$(id -u)" -ne 0 ]; then
		die "run as root: curl -fsSL https://raw.githubusercontent.com/AKEB/clock_alarm/main/bootstrap_clock.sh | sudo bash"
	fi
}

apt_install() {
	log "Installing base packages"
	mount -o remount,rw /boot/firmware >/dev/null 2>&1 || true
	apt-get update
	DEBIAN_FRONTEND=noninteractive apt-get install -y \
		alsa-utils \
		ca-certificates \
		cron \
		curl \
		git \
		locales \
		mc \
		mplayer \
		nginx \
		psmisc \
		rsync \
		sqlite3 \
		sudo \
		tzdata

	if ! DEBIAN_FRONTEND=noninteractive apt-get install -y \
		"php${PHP_VERSION}-cli" \
		"php${PHP_VERSION}-common" \
		"php${PHP_VERSION}-curl" \
		"php${PHP_VERSION}-fpm" \
		"php${PHP_VERSION}-gd" \
		"php${PHP_VERSION}-imagick" \
		"php${PHP_VERSION}-imap" \
		"php${PHP_VERSION}-intl" \
		"php${PHP_VERSION}-mbstring" \
		"php${PHP_VERSION}-mysql" \
		"php${PHP_VERSION}-opcache" \
		"php${PHP_VERSION}-soap" \
		"php${PHP_VERSION}-sqlite3" \
		"php${PHP_VERSION}-xml" \
		"php${PHP_VERSION}-xmlrpc" \
		"php${PHP_VERSION}-zip"; then
		log "Exact PHP ${PHP_VERSION} packages were not available; trying distribution defaults"
		DEBIAN_FRONTEND=noninteractive apt-get install -y \
			php-cli php-common php-curl php-fpm php-gd php-imagick php-imap \
			php-intl php-mbstring php-mysql php-opcache php-soap php-sqlite3 \
			php-xml php-xmlrpc php-zip
	fi
}

detect_php_fpm_socket() {
	local socket
	socket="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' 2>/dev/null | sort -Vr | head -1 || true)"
	if [ -n "$socket" ]; then
		printf '%s\n' "$socket"
		return 0
	fi

	socket="$(find /etc/php -maxdepth 3 -type f -path '*/fpm/pool.d/www.conf' 2>/dev/null | sed -n 's#^/etc/php/\([^/]*\)/.*#/run/php/php\1-fpm.sock#p' | sort -Vr | head -1 || true)"
	if [ -n "$socket" ]; then
		printf '%s\n' "$socket"
		return 0
	fi

	printf '/run/php/php-fpm.sock\n'
}

install_repo() {
	log "Installing app from ${REPO_URL} (${REPO_BRANCH})"
	mkdir -p "$(dirname "$APP_DIR")"
	if [ -d "$APP_DIR/.git" ]; then
		git -C "$APP_DIR" fetch origin "$REPO_BRANCH"
		git -C "$APP_DIR" checkout "$REPO_BRANCH"
		git -C "$APP_DIR" pull --ff-only origin "$REPO_BRANCH"
	else
		rm -rf "$APP_DIR"
		git clone --branch "$REPO_BRANCH" --single-branch "$REPO_URL" "$APP_DIR"
	fi
}

restore_state() {
	case "$RESTORE_MODE" in
		none|fresh)
			log "Skipping PBS restore (RESTORE_MODE=${RESTORE_MODE})"
			;;
		pbs)
			log "Restoring mutable state from PBS"
			APP_DIR="$APP_DIR" bash "$APP_DIR/restore_from_pbs.sh"
			;;
		*)
			die "unknown RESTORE_MODE=${RESTORE_MODE}; expected none, fresh, or pbs"
			;;
	esac
}

setup_pbs_backup() {
	case "$SETUP_PBS_BACKUP" in
		0|false|no)
			log "Skipping PBS backup setup"
			return
			;;
		1|true|yes)
			;;
		auto)
			if [ "$RESTORE_MODE" != "pbs" ]; then
				return
			fi
			;;
		*)
			die "unknown SETUP_PBS_BACKUP=${SETUP_PBS_BACKUP}; expected auto, 1, or 0"
			;;
	esac

	if [ -n "${PBS_REPOSITORY:-}" ] && { [ -n "${PBS_PASSWORD:-}" ] || [ -n "${PBS_PASSWORD_FILE:-}" ]; } && [ -n "${PBS_FINGERPRINT:-}" ]; then
		log "Installing PBS backup job"
		APP_DIR="$APP_DIR" bash "$APP_DIR/setup_pbs_backup.sh"
	else
		log "PBS backup setup requested but PBS env is incomplete; skipping"
	fi
}

ensure_runtime_files() {
	log "Preparing config and database"
	if [ ! -f "$APP_DIR/config.php" ] && [ -f "$APP_DIR/config.example.php" ]; then
		cp "$APP_DIR/config.example.php" "$APP_DIR/config.php"
	fi

	if ! find "$APP_DIR" -maxdepth 1 -name '*.sqlite3' -type f | grep -q .; then
		(cd "$APP_DIR" && php init.php)
	fi

	chown -R "$APP_USER:$APP_GROUP" "$APP_DIR"
	chmod 0755 "$APP_DIR/clock_alarm_loop.sh" "$APP_DIR/install_low_write.sh" 2>/dev/null || true
}

configure_nginx() {
	local php_socket
	php_socket="$(detect_php_fpm_socket)"
	log "Configuring nginx with PHP socket ${php_socket}"
	cat >/etc/nginx/sites-available/default <<EOF
server {
  listen 80 default_server;
  listen [::]:80 default_server;

  access_log off;
  root ${APP_DIR}/;

  index index.html index.php;

  server_name _;

  location ~ /\\. {
    deny all;
  }

  location ~* \\.(sqlite3|sh|log|src)$ {
    deny all;
  }

  location ~* /(logs|lib)/ {
    deny all;
  }

  location ~* \\.example.php$ {
    deny all;
  }

  location ~* /config.php {
    deny all;
  }

  location / {
    try_files \$uri \$uri/ =404;
  }

  location ~ \\.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:${php_socket};
  }
}
EOF
}

enable_services() {
	log "Enabling web/audio services"
	usermod -aG audio "$APP_USER" || true
	systemctl daemon-reload
	systemctl enable --now "php${PHP_VERSION}-fpm.service" >/dev/null 2>&1 || systemctl enable --now php-fpm.service >/dev/null 2>&1 || true
	systemctl enable --now nginx.service
}

run_low_write() {
	if [ "$RUN_LOW_WRITE" = "1" ]; then
		log "Applying low-write profile"
		APP_DIR="$APP_DIR" APP_USER="$APP_USER" APP_GROUP="$APP_GROUP" bash "$APP_DIR/install_low_write.sh"
	fi
}

verify_install() {
	log "Verifying install"
	systemctl is-active --quiet clock-alarm-loop.service || die "clock-alarm-loop.service is not active"
	systemctl is-active --quiet nginx.service || die "nginx.service is not active"
	curl -fsS -o /dev/null http://127.0.0.1/ || die "web UI did not return HTTP 200"
	[ -d /dev/snd ] || die "/dev/snd is missing; audio device is not visible"
	log "Install verification passed"
}

main() {
	require_root
	apt_install
	install_repo
	restore_state
	setup_pbs_backup
	ensure_runtime_files
	configure_nginx
	enable_services
	run_low_write
	verify_install

	log "Done."
	if [ "$RUN_REBOOT_HINT" = "1" ]; then
		log "Reboot once to activate every mount option from /etc/fstab."
	fi
}

main "$@"
