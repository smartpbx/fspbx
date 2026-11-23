#!/bin/bash
# http_proof.sh --domain <d> --mac <m> [--file <tail>] [--ua <UA>]
# Byte-identity proof: fetch the legacy config over real HTTP (curl --digest) from the unmodified legacy index.php
# served by `php -S` on 127.0.0.1 under a READ-ONLY DB session, and compare with render.sh --legacy. The production
# nginx path is not used, because a production hit stamps device_provisioned_* (the one write we must not make).
set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export PGOPTIONS='-c default_transaction_read_only=on'
domain= mac= file= ua=
while [ $# -gt 0 ]; do case "$1" in --domain) domain=$2; shift 2;; --mac) mac=$2; shift 2;; --file) file=$2; shift 2;; --ua) ua=$2; shift 2;; *) echo "unknown arg $1" >&2; exit 2;; esac; done
mac=$(printf '%s' "$mac" | tr -d ':.-' | tr 'A-F' 'a-f')
[[ "$mac" =~ ^[0-9a-f]{12}$ && "$domain" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "bad --domain/--mac" >&2; exit 2; }
q() { sudo -n -u postgres env PGOPTIONS="$PGOPTIONS" psql -d fusionpbx -At -F'|' -c "$1"; }
IFS='|' read -r duuid vendor devuuid <<<"$(q "select d.domain_uuid, lower(coalesce(v.device_vendor,'')), v.device_uuid from v_devices v join v_domains d using(domain_uuid) where d.domain_name='$domain' and lower(v.device_address)='$mac'")"
[ -n "${devuuid:-}" ] || { echo "device not found" >&2; exit 4; }
if [ -z "$file" ]; then case "$vendor" in grandstream|htek) file="cfg$mac.xml";; cisco|snom) file="$mac.xml";; *) file="$mac.cfg";; esac; fi
if [ -z "$ua" ]; then case "$vendor" in polycom) ua="PolycomVVX-VVX_250-UA/6.4.7.4513";; yealink) ua="Yealink SIP-T46U 108.86.0.50 $mac";; fanvil) ua="Fanvil X303 2.4.8";; grandstream) ua="Grandstream GXP1615 1.0.11.6 $mac";; cisco) ua="Cisco/SPA112-1.4.1";; algo) ua="Algo 8301";; *) ua="p4352-harness";; esac; fi
get() { q "select coalesce((select domain_setting_value from v_domain_settings where domain_uuid='$duuid' and domain_setting_category='provision' and domain_setting_subcategory='$1' and domain_setting_enabled='true' limit 1),(select default_setting_value from v_default_settings where default_setting_category='provision' and default_setting_subcategory='$1' and default_setting_enabled='true' limit 1),'')"; }
U=$(get http_auth_username); PW=$(get http_auth_password)
before=$(q "select device_provisioned_date||'|'||device_provisioned_agent from v_devices where device_uuid='$devuuid'")
port=$((20000 + RANDOM % 20000)); tmp=$(mktemp -d /tmp/p4352-http.XXXXXX); chmod 700 "$tmp"
sudo -n -u www-data env PGOPTIONS="$PGOPTIONS" php -d display_errors=0 -d log_errors=1 -d error_log=/dev/stderr -S 127.0.0.1:$port -t /var/www/fspbx/public "$HERE/http_router.php" >"$tmp/srv.log" 2>&1 &
srv=$!
trap 'sudo -n pkill -f "php.*-S 127.0.0.1:$port" 2>/dev/null; rm -rf "$tmp"' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$port/x" && break; sleep 0.2; done
printf 'user = "%s:%s"\n' "$U" "$PW" | curl -s --digest -K - -H "Host: $domain" -H "User-Agent: $ua" -o "$tmp/http.out" -w 'http_status=%{http_code} http_bytes=%{size_download}\n' "http://127.0.0.1:$port/app/provision/$file"
unset PW
"$HERE/render.sh" --domain "$domain" --mac "$mac" --legacy --file "$file" --ua "$ua" > "$tmp/cli.out" 2>/dev/null
h=$(sha256sum < "$tmp/http.out" | cut -c1-16); c=$(sha256sum < "$tmp/cli.out" | cut -c1-16)
after=$(q "select device_provisioned_date||'|'||device_provisioned_agent from v_devices where device_uuid='$devuuid'")
echo "vendor=$vendor mac=$mac file=$file http_sha256=$h cli_sha256=$c bytes(http/cli)=$(wc -c <"$tmp/http.out")/$(wc -c <"$tmp/cli.out")"
[ "$before" = "$after" ] && echo "device_provisioned_*: unchanged" || echo "device_provisioned_*: CHANGED !!"
if [ "$h" = "$c" ] && [ -s "$tmp/cli.out" ]; then echo "RESULT: BYTE-IDENTICAL"; else echo "RESULT: DIFFERENT"; cmp "$tmp/http.out" "$tmp/cli.out" | head -2; exit 1; fi
