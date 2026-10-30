#!/bin/sh
set -eu
if [ "${APP_MAIL_ENABLED:-0}" = "1" ]; then
    su -s /bin/sh www-data -c 'exec php /var/www/app/scripts/mail-worker.php --loop' &
fi
exec apache2-foreground
