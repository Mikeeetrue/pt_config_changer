## How to use

1. git clone -b pt_1 https://github.com/jokaorgua/pt_config_changer.git
2. cd pt_config_changer
3. cp .env.example .env
4. composer update
5. fulfill .env file with appropriate data
6. add set_by_mikes_index.php to cron once per 15 minutes
7. run script once by hands with `php -f set_by_mikes_index.php` and after that check the PAIRS and DCA files. Ensure that variables were set correctly

## How to add to cron
1. crontab -e
2. at the very end of the screen from new line add (change /path/to/ to the path of the dir with script)
` */15 * * * * (cd /path/to/pt_config_changer/ && php -f set_by_mikes_index.php >> /tmp/pt_config_updater_mikes_index) `

3. make sure that at the end of the file there is blank line
4. save and exit 

## How to use backup script
1. ensure that in your .env file BACKUP_PATH var is set to you backup location and it is writable for user from which cron is run
2. add to cron with crontab -e ` */1 * * * * (cd /path/to/pt_config_changer/ && php -f backup_pt_datafile.php) ` (make sure you've changed /path/to/ to appropriate path)

## How to update the project
1. Execute ` git pull && composer update` in script's dir.

## In case of any issues
1. Try to update. Maybe new version is available which fixes your issues.
2. Try to reinstall from scratch
3. If above sections don't work than calm down and drink coffee. Start hoping that author will fix the issue soon.