# Clock Alarm

Smart alarm clock based on raspberry pi 3 and a speaker connected to it

## Install

For a clean Raspberry Pi OS / Ubuntu install, use the bootstrap script:

```bash
curl -fsSL https://raw.githubusercontent.com/AKEB/clock_alarm/main/bootstrap_clock.sh | sudo bash
```

To restore mutable state from Proxmox Backup Server, provide PBS credentials as
environment variables. The secrets are intentionally not stored in Git:

```bash
export RESTORE_MODE=pbs
export PBS_REPOSITORY='user@pbs@host:datastore'
export PBS_PASSWORD_FILE=/root/pbs-password
export PBS_FINGERPRINT='aa:bb:...'
export PBS_BACKUP_GROUP='host/clock'
export PBS_CLIENT_TARBALL_URL='https://github.com/AKEB/clock_alarm/releases/download/pbs-client-v4.0.18-arm64/proxmox-backup-client-v4.0.18-1-arm64.tgz'
export PBS_CLIENT_SHA256='bb6ab63a10358cee93d0fc6a275979bcc2edb9813880e9efe212043023eace94'
curl -fsSL https://raw.githubusercontent.com/AKEB/clock_alarm/main/bootstrap_clock.sh | sudo -E bash
```

If the fresh OS already has `proxmox-backup-client`, you can omit
`PBS_CLIENT_TARBALL_URL` and set `PBS_CLIENT_BIN` to the installed client.

The bootstrap restores `clock-state.pxar` when it exists. Older backups that
only contain `root.pxar` still work as a fallback, but they are slower to scan.
After the first successful bootstrap with PBS credentials, the installed daily
backup job writes both `root.pxar` and a small `clock-state.pxar`.

Manual legacy install:

```bash
sudo apt-get update && sudo apt-get upgrade -y

sudo apt-get install -y mplayer mc curl alsa-utils git sqlite3
sudo apt-get install -y shairport-sync

sudo apt-get install -y nginx php8.3-fpm

sudo apt-get install php8.3-common php8.3-mysql php8.3-xml php8.3-xmlrpc php8.3-curl php8.3-gd php8.3-imagick php8.3-cli php8.3-dev php8.3-imap php8.3-mbstring php8.3-opcache php8.3-soap php8.3-zip php8.3-intl php8.3-sqlite php8.3-mcrypt -y
```

## Cron

For low-write Raspberry Pi installs, prefer the systemd loop service from
`install_low_write.sh` instead of cron:

```bash
sudo bash /var/www/clock_alarm/install_low_write.sh
```

The script keeps the project in `/var/www/clock_alarm`, disables the old
`www-data` cron entries, enables `clock-alarm-loop.service`, moves journald to
volatile storage, and disables noisy OS timers/services.

Legacy cron setup:

```bash
crontab -e
```

```bash
* * * * * /bin/bash /var/www/clock_alarm/cron_play.sh
* * * * * (sleep 10 ; /bin/bash /var/www/clock_alarm/cron_play.sh)
* * * * * (sleep 20 ; /bin/bash /var/www/clock_alarm/cron_play.sh)
* * * * * (sleep 30 ; /bin/bash /var/www/clock_alarm/cron_play.sh)
* * * * * (sleep 40 ; /bin/bash /var/www/clock_alarm/cron_play.sh)
* * * * * (sleep 50 ; /bin/bash /var/www/clock_alarm/cron_play.sh)
```

### Config Nginx

```bash
sudo nano /etc/nginx/sites-available/default
```

```bash
server {
  listen 80 default_server;
  listen [::]:80 default_server;

  access_log  off;
  root /var/www/clock_alarm/;

  index index.html index.php;

  server_name _;

  location ~ /\. {
    deny all;
  }

  location ~* \.(sqlite3|sh|log|src)$ {
    deny all;
  }

  location ~* /(logs|lib)/ {
    deny all;
  }

  location ~* \.example.php$ {
    deny all;
  }

  location ~* /config.php {
    deny all;
  }

  location / {
    try_files $uri $uri/ =404;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
  }
}
```

```bash
sudo nano /etc/nginx/nginx.conf
```

```bash
user akeb;
```

```bash
sudo systemctl restart nginx
```

## Config php-fpm

```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

Hit F6 for search inside the editor and update the following values for better performance.

```bash
upload_max_filesize = 32M 
post_max_size = 48M 
memory_limit = 256M 
max_execution_time = 600 
max_input_vars = 3000 
max_input_time = 1000
```

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

```bash
user = akeb
group = akeb

listen.owner = akeb
listen.group = akeb
```

```bash
sudo reboot
```

## Initialize Database

```bash
php init.php
```

## Help

Increase volume by 5%

```amixer -q -M set PCM 5%+```

Decrease volume by 5%

```amixer -q -M set PCM 5%-```

Set volume to 50%

```amixer -q -M set PCM 50%```
