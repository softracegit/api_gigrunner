#!/bin/sh
# GigRunner API — keep queue + Pusher job polling alive via Laravel scheduler.
# Cron (every minute):
#   /bin/sh /home/u291405135/domains/api-gigrunner.softrace.pt/public_html/cron-schedule.sh
#
# Adjust APP_DIR if the project path on the server is different.
# artisan must live in APP_DIR (Laravel root, not /public).

APP_DIR="/home/u291405135/domains/api-gigrunner.softrace.pt/public_html"
PHP_BIN="/opt/alt/php82/usr/bin/php"
LOG_FILE="$APP_DIR/cron.log"

cd "$APP_DIR" || exit 1
"$PHP_BIN" artisan schedule:run >> "$LOG_FILE" 2>&1
