# clock_alarm

## Install

```bash
sudo apt-get update && sudo apt-get upgrade -y

sudo apt-get install -y mplayer mc curl alsa-utils git sqlite3
sudo apt-get install -y shairport-sync

sudo apt-get install -y nginx php8.3-fpm

sudo apt-get install php8.3-common php8.3-mysql php8.3-xml php8.3-xmlrpc php8.3-curl php8.3-gd php8.3-imagick php8.3-cli php8.3-dev php8.3-imap php8.3-mbstring php8.3-opcache php8.3-soap php8.3-zip php8.3-intl php8.3-sqlite php8.3-mcrypt -y
```

## Cron

```bash
* * * * * /bin/bash /home/akeb/clock_alarm/cron_play.sh
* * * * * (sleep 10 ; /bin/bash /home/akeb/clock_alarm/cron_play.sh)
* * * * * (sleep 20 ; /bin/bash /home/akeb/clock_alarm/cron_play.sh)
* * * * * (sleep 30 ; /bin/bash /home/akeb/clock_alarm/cron_play.sh)
* * * * * (sleep 40 ; /bin/bash /home/akeb/clock_alarm/cron_play.sh)
* * * * * (sleep 50 ; /bin/bash /home/akeb/clock_alarm/cron_play.sh)
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
  root /home/akeb/clock_alarm/;

  index index.html index.php;

  server_name _;

  location ~ /\. {
    deny all;
  }

  location ~* \.(sqlite3|sh|log)$ {
    deny all;
  }

  location ~* /(logs|lib)/ {
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

## Help

Increase volume by 5%

```amixer -q -M set PCM 5%+```

Decrease volume by 5%

```amixer -q -M set PCM 5%-```

Set volume to 50%

```amixer -q -M set PCM 50%```
