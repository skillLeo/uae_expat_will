#!/bin/bash
#
# Drains the notification queue.
#
# Shared hosting kills long-lived daemons, so a supervised worker is not
# available. --stop-when-empty with a bounded --max-time keeps each run short,
# and cron keeps them coming every minute. The health panel watches the age of
# the oldest waiting job, which is what actually reveals a stalled worker.
#
APP=~/domains/will.skillleo.com/public_html
cd "$APP" || exit 1
pgrep -f "artisan queue:work" >/dev/null 2>&1 && exit 0
/opt/alt/php84/usr/bin/php artisan queue:work \
  --stop-when-empty --max-time=280 --tries=3 >> storage/logs/queue.log 2>&1
