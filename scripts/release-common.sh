#!/usr/bin/env bash
# PHP snippets deliberately keep dollar variables literal for PHP.
# shellcheck disable=SC2016
# Sourced by release commands on the Linux host.
set -Eeuo pipefail
umask 077
root=${root:-}

fail() { printf '%s\n' "$*" >&2; exit 1; }
require_root() {
    [[ ${environment:-} == staging || ${environment:-} == production ]] || fail 'Invalid environment'
    [[ $root == "/srv/altech/$environment" ]] || fail 'Unexpected deployment root'
    [[ -d $root/releases && -d $root/shared/storage && -d $root/incoming && -d $root/backups ]] || fail 'Host layout not provisioned'
    [[ $(realpath -e "$root") == "$root" && ! -L $root/releases && ! -L $root/shared && ! -L $root/incoming && ! -L $root/backups ]] || fail 'Deployment directories must not be symlinks'
    [[ -f $root/shared/.env && -f $root/shared/mysql.cnf ]] || fail 'Shared configuration missing'
    [[ $(stat -c '%a' "$root/shared/mysql.cnf") == 600 ]] || fail 'mysql.cnf must have mode 600'
    for command in php composer node curl python3 mysql mysqldump gzip tar flock; do command -v "$command" >/dev/null || fail "Missing $command"; done
    [[ $(node -p 'process.versions.node.split(".")[0]') == 24 ]] || fail 'Node.js 24 required'
}
switch_release() {
    local destination=$1
    [[ -d $destination && ! -L $destination ]] || fail 'Invalid release target'
    destination=$(realpath -e "$destination")
    [[ $(dirname "$destination") == "$root/releases" ]] || fail 'Release must be a direct child of releases'
    ln -s "$destination" "$root/current.next"
    mv -Tf "$root/current.next" "$root/current"
}
stop_workers() {
    sudo -n systemctl stop "altech-$environment-queue.service" "altech-$environment-ssr.service"
}
start_workers() {
    sudo -n systemctl restart "altech-$environment-queue.service" "altech-$environment-ssr.service"
    sudo -n systemctl reload php-fpm84.service
    systemctl is-active --quiet "altech-$environment-queue.service"
    systemctl is-active --quiet "altech-$environment-ssr.service"
}
health_check() {
    local url=$1 expected=$2
    for _attempt in {1..12}; do
        if curl --fail --silent --show-error --max-time 10 "$url/ready" \
            | python3 -c 'import json,sys; d=json.load(sys.stdin); sys.exit(not(d.get("status")=="ok" and d.get("release")==sys.argv[1]))' "$expected"; then
            return 0
        fi
        sleep 2
    done
    return 1
}
backup_shared() {
    local label=$1
    local database
    database=$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("database.connections.mysql.database");')
    [[ $database =~ ^[a-zA-Z0-9_]+$ ]] || fail 'Unexpected database name'
    mysqldump --defaults-extra-file="$root/shared/mysql.cnf" --single-transaction --routines --triggers --events --no-tablespaces \
        "$database" \
        | gzip > "$root/backups/$label.sql.gz"
    gzip -t "$root/backups/$label.sql.gz"
    tar -czf "$root/backups/$label-storage.tar.gz" -C "$root/shared" storage
    tar -tzf "$root/backups/$label-storage.tar.gz" >/dev/null
    (cd "$root/backups" && sha256sum "$label.sql.gz" "$label-storage.tar.gz" > "$label.sha256")
}
