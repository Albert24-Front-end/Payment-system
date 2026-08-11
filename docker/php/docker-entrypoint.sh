#!/bin/sh
set -e

PUID=${PUID:-1000}
PGID=${PGID:-1000}
WWW_HOME=${WWW_HOME:-/home/www-data}

current_gid="$(getent group www-data | cut -d: -f3)"
if [ "$current_gid" != "$PGID" ]; then
    groupmod -o -g "$PGID" www-data
fi

current_uid="$(id -u www-data)"
if [ "$current_uid" != "$PUID" ]; then
    usermod -o -u "$PUID" -g www-data www-data
fi

usermod -d "$WWW_HOME" www-data

mkdir -p \
    "$WWW_HOME/.composer" \
    "$WWW_HOME/.config/composer" \
    "$WWW_HOME/.cache/composer"

chown -R www-data:www-data "$WWW_HOME"

exec "$@"
