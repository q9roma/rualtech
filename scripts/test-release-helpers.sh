#!/usr/bin/env bash
# Linux-only integration checks; no production directories or services are used.
set -Eeuo pipefail
# shellcheck source=scripts/release-common.sh
source "$(dirname "$0")/release-common.sh"
root=$(mktemp -d)
[[ $root == /* && -d $root && ! -L $root && $(basename "$root") == tmp.* ]] || fail 'Unsafe test directory'
trap 'rm -rf -- "$root"' EXIT
mkdir -p "$root/releases/one" "$root/releases/two"
switch_release "$root/releases/one"
[[ $(readlink -f "$root/current") == "$root/releases/one" ]]
switch_release "$root/releases/two"
[[ $(readlink -f "$root/current") == "$root/releases/two" ]]
if (switch_release /etc); then fail 'Accepted release outside release directory'; fi
if (switch_release "$root/releases/../"); then fail 'Accepted traversal outside release directory'; fi
[[ $(readlink -f "$root/current") == "$root/releases/two" ]]
environment=production
if (require_root); then fail 'Accepted arbitrary deployment root'; fi
curl() { printf '{"status":"ok","release":"abc"}'; }
health_check https://example.invalid abc
sleep() { :; }
if health_check https://example.invalid wrong; then fail 'Accepted stale release health response'; fi
echo 'Release switch and health regression checks passed.'
