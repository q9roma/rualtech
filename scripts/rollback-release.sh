#!/usr/bin/env bash
# PHP snippets deliberately keep dollar variables literal for PHP.
# shellcheck disable=SC2016
set -Eeuo pipefail
environment=${1:?Usage: rollback-release.sh ENVIRONMENT}
root="/srv/altech/$environment"
# shellcheck source=scripts/release-common.sh
source "$(dirname "$0")/release-common.sh"
require_root
exec 9>"$root/deploy.lock"
flock -n 9 || fail 'Another deployment or rollback is running'
[[ ! -e $root/shared/storage/framework/down ]] || fail 'Already in maintenance: inspect before rollback'
target=$(cat "$root/previous-release")
[[ $target == "$root/releases/"* && -d $target && ! -L $target ]] || fail 'Previous release unavailable'
[[ $(dirname "$(realpath -e "$target")") == "$root/releases" ]] || fail 'Previous release outside release directory'
current=$(readlink -f "$root/current")
[[ $current == "$root/releases/"* && $current != "$target" ]] || fail 'Invalid current release'
cd "$target"
php artisan deploy:check --after-migrate
sha=$(php -r '$r=require "config/release.php"; echo $r["commit"];')
url=$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("app.url");')
trap 'echo "Rollback failed; inspect current symlink and maintenance state before resuming traffic" >&2' ERR
php artisan down --retry=60
stop_workers
backup_shared "rollback-$(date -u +%Y%m%d%H%M%S)"
switch_release "$target"
start_workers
php artisan up
health_check "$url" "$sha"
printf '%s\n' "$current" > "$root/previous-release"
echo 'Code rolled back. Database schema and data were not reversed.'
