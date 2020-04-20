# vcs-automat-api

#### Install necessary packages
```bash
sudo apt-get install php-mysqli python3 python3-matplotlib python3-mysql.connector python3-numpy curl php-curl
```



#### Install default settings and logs
```bash
sudo git clone https://github.com/agimpel/vcs-automat-misc.git /opt/vcs-automat-misc

sudo chown -R www-data:www-data /opt/vcs-automat-misc
```



#### Copy and edit config files to set usernames and passwords
```bash
sudo cp /opt/vcs-automat-api/server/database_creation.sql_default /opt/vcs-automat-api/server/database_creation.sql

sudo nano /opt/vcs-automat-api/server/database_creation.sql

sudo cp /opt/vcs-automat-api/server/settings.ini_default /opt/vcs-automat-api/server/settings.ini

sudo nano /opt/vcs-automat-api/server/settings.ini
```



#### Set up SQL database
```bash
mysql -u <user> -p <password> < /opt/vcs-automat-api/server/database_creation.sql
```




#### Set up cron jobs
```bash
(sudo crontab -l ; echo "0 13 * * * cd /opt/vcs-automat-misc/server/ && /usr/bin/python3 /opt/vcs-automat-misc/server/create_plots.py >> /opt/vcs-automat-misc/server/logs/cron.log") | uniq - | sudo crontab -

(sudo crontab -l ; echo "5 * * * * cd /opt/vcs-automat-misc/server/ && /usr/bin/python3 /opt/vcs-automat-misc/server/update_credits.py >> /opt/vcs-automat-misc/server/logs/cron.log") | uniq - | sudo crontab -
```



#### Install Wordpress plugin
```bash
sudo git clone https://github.com/agimpel/vcs-automat-api.git <wordpress_base_path>/wp-content/plugins/vcs-automat-api

sudo chown -R www-data:www-data <wordpress_base_path>/wp-content/plugins/vcs-automat-api
```
