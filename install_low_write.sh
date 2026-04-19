#!/bin/bash

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/clock_alarm}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
BACKUP_DIR="/root/clock_alarm_low_write_backups/$(date +%Y%m%d_%H%M%S)"

if [ "$(id -u)" -ne 0 ]; then
	echo "Run as root: sudo bash $0"
	exit 1
fi

if [ ! -d "$APP_DIR" ]; then
	echo "App directory not found: $APP_DIR"
	exit 1
fi

mkdir -p "$BACKUP_DIR"

backup_file() {
	local path="$1"
	if [ -e "$path" ]; then
		mkdir -p "$BACKUP_DIR$(dirname "$path")"
		cp -a "$path" "$BACKUP_DIR$path"
	fi
}

backup_unit_state() {
	local name="$1"
	systemctl is-enabled "$name" >"$BACKUP_DIR/${name}.enabled" 2>/dev/null || true
	systemctl is-active "$name" >"$BACKUP_DIR/${name}.active" 2>/dev/null || true
}

disable_unit() {
	local name="$1"
	backup_unit_state "$name"
	timeout 20 systemctl disable --now "$name" >/dev/null 2>&1 || true
}

chown "$APP_USER:$APP_GROUP" "$APP_DIR/clock_alarm_loop.sh"
chmod 0755 "$APP_DIR/clock_alarm_loop.sh"

backup_file /etc/systemd/system/clock-alarm-loop.service
cat >/etc/systemd/system/clock-alarm-loop.service <<EOF
[Unit]
Description=Clock Alarm scheduler loop
After=network-online.target sound.target
Wants=network-online.target

[Service]
Type=simple
User=$APP_USER
Group=$APP_GROUP
WorkingDirectory=$APP_DIR
Environment=CLOCK_ALARM_INTERVAL_SECONDS=10
ExecStart=/bin/bash $APP_DIR/clock_alarm_loop.sh
Restart=always
RestartSec=5
StandardOutput=null
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

if crontab -u "$APP_USER" -l >/tmp/clock_alarm_crontab.$$ 2>/dev/null; then
	cp /tmp/clock_alarm_crontab.$$ "$BACKUP_DIR/crontab_${APP_USER}"
	awk '
		/\/var\/www\/clock_alarm\/cron_play\.sh/ && $0 !~ /^#/ {
			print "# disabled by install_low_write.sh: " $0
			next
		}
		{ print }
	' /tmp/clock_alarm_crontab.$$ | crontab -u "$APP_USER" -
	rm -f /tmp/clock_alarm_crontab.$$
fi

backup_file /etc/cron.d/sysstat
if [ -f /etc/cron.d/sysstat ]; then
	awk '
		/^[^#]/ {
			print "# disabled by install_low_write.sh: " $0
			next
		}
		{ print }
	' /etc/cron.d/sysstat >/tmp/clock_alarm_sysstat_cron.$$ && cat /tmp/clock_alarm_sysstat_cron.$$ >/etc/cron.d/sysstat && rm -f /tmp/clock_alarm_sysstat_cron.$$
fi

mkdir -p /etc/systemd/journald.conf.d
backup_file /etc/systemd/journald.conf.d/clock-alarm-low-write.conf
cat >/etc/systemd/journald.conf.d/clock-alarm-low-write.conf <<'EOF'
[Journal]
Storage=volatile
RuntimeMaxUse=16M
RuntimeKeepFree=32M
ForwardToSyslog=no
Compress=yes
RateLimitIntervalSec=30s
RateLimitBurst=200
EOF

backup_file /etc/fstab
if grep -q '^LABEL=writable[[:space:]]\+/[[:space:]]\+ext4' /etc/fstab; then
	awk 'BEGIN { OFS="\t" }
		$1 == "LABEL=writable" && $2 == "/" && $3 == "ext4" {
			$4 = "defaults,noatime,nodiratime,commit=600"
		}
		{ print }
	' /etc/fstab > /tmp/clock_alarm_fstab.$$ && cat /tmp/clock_alarm_fstab.$$ >/etc/fstab && rm -f /tmp/clock_alarm_fstab.$$
fi
if grep -q '^LABEL=system-boot[[:space:]]\+/boot/firmware[[:space:]]\+vfat' /etc/fstab; then
	awk 'BEGIN { OFS="\t" }
		$1 == "LABEL=system-boot" && $2 == "/boot/firmware" && $3 == "vfat" {
			$4 = "defaults,ro"
		}
		{ print }
	' /etc/fstab > /tmp/clock_alarm_fstab.$$ && cat /tmp/clock_alarm_fstab.$$ >/etc/fstab && rm -f /tmp/clock_alarm_fstab.$$
fi
grep -qE '[[:space:]]/var/log[[:space:]]+tmpfs[[:space:]]' /etc/fstab || \
	echo 'tmpfs /var/log tmpfs defaults,noatime,nosuid,nodev,mode=0755,size=64M 0 0' >>/etc/fstab
grep -qE '[[:space:]]/var/tmp[[:space:]]+tmpfs[[:space:]]' /etc/fstab || \
	echo 'tmpfs /var/tmp tmpfs defaults,noatime,nosuid,nodev,mode=1777,size=64M 0 0' >>/etc/fstab

mkdir -p /etc/sysctl.d
backup_file /etc/sysctl.d/99-clock-alarm-low-write.conf
cat >/etc/sysctl.d/99-clock-alarm-low-write.conf <<'EOF'
vm.swappiness=1
vm.dirty_writeback_centisecs=6000
vm.dirty_expire_centisecs=6000
EOF

mkdir -p /etc/tmpfiles.d
backup_file /etc/tmpfiles.d/clock-alarm-low-write.conf
cat >/etc/tmpfiles.d/clock-alarm-low-write.conf <<'EOF'
d /var/log/nginx 0755 root adm -
d /var/log/php 0755 root adm -
d /var/log/apt 0755 root root -
d /var/log/unattended-upgrades 0755 root root -
d /var/log/journal 2755 root systemd-journal -
d /var/log/private 0700 root root -
EOF

for unit in \
	rsyslog.service \
	sysstat.service sysstat-collect.timer sysstat-summary.timer sysstat-rotate.timer \
	phpsessionclean.timer logrotate.timer e2scrub_all.timer \
	apt-daily.timer apt-daily-upgrade.timer unattended-upgrades.service \
	fwupd.service fwupd-refresh.timer \
	motd-news.timer update-notifier-download.timer update-notifier-motd.timer \
	man-db.timer dpkg-db-backup.timer \
	apport-autoreport.timer ua-timer.timer \
	snapd.service snapd.socket snapd.seeded.service snapd.snap-repair.timer \
	ModemManager.service udisks2.service
do
	disable_unit "$unit"
done

systemctl daemon-reload
systemd-tmpfiles --create /etc/tmpfiles.d/clock-alarm-low-write.conf
systemctl enable --now clock-alarm-loop.service
systemctl restart systemd-journald.service
sysctl --system >/dev/null || true
mount -o remount,noatime,nodiratime,commit=600 / >/dev/null 2>&1 || true
mountpoint -q /boot/firmware && mount -o remount,ro /boot/firmware >/dev/null 2>&1 || true

echo "Low-write setup complete."
echo "Backups saved in: $BACKUP_DIR"
echo "Reboot is recommended so /var/log and /boot/firmware mount options take effect."
