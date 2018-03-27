## How to use

1. git clone https://github.com/jokaorgua/pt_config_changer.git
2. cd pt_config_changer
3. cp .env.example .env
4. composer update
5. fulfill .env file with appropriate data
6. add set_by_mikes_index.php to cron once per 15 minutes
 6.1 crontab -e
 6.2 at the very end from new line add (change /path/to/ to the path of the dir with script)
 
 ` */15 * * * * (cd /path/to/pt_config_changer/ && php -f set_by_mikes_index.php >> /tmp/pt_config_updater_mikes_index) `
 6.3 make sure that at the end of the file there is blank line
 6.4 save and exit 