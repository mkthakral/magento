
#To avoid Inode full issue, cleaning sessions that are 14 days old

find /var/www/magento/var/session -name 'sess_*' -type f -mtime +14 -exec rm {} \;