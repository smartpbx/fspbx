#!/bin/bash
# Shared read-only render harness for patchbay#4352 (runs on fspbx01, installed at ~pbx/native-render/).
#
#   render.sh --domain <name|uuid> --mac <mac> --legacy [--file <url-tail>] [--ua <UA>]
#   render.sh --domain <name|uuid> --mac <mac> --native [--template-file <path> | --template-uuid <uuid>] [--file <url-tail>] [--ua <UA>]
#   add --prove to print read-only evidence (session read-only, a rejected write attempt, device_provisioned_* before/after).
#
# Config goes to stdout, diagnostics to stderr. Nothing is written to the database or to /var/www/fspbx: every
# PHP/psql process runs under PGOPTIONS='-c default_transaction_read_only=on' and native refuses to run otherwise.
# NOTE: --native renders the body only. It does NOT run DigestProvisionAuth (device lookup by path, realm Prov-<hash>, http_auth_type /
# userAgentForcesBasic policy, CIDR check, nginx /prov/ routing, ETag/304). A clean native render does not prove the phone can authenticate on /prov/.
# --file is the URL tail after /app/provision/ (legacy) or /prov/ (native), e.g. y000000000108.cfg, phone<mac>.cfg,
# VVX450-<mac>.cfg, cfg<mac>.xml. Default: the vendor's main file.
set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export PGOPTIONS='-c default_transaction_read_only=on'
domain= mac= mode= file= tfile= tuuid= ua= prove=0
while [ $# -gt 0 ]; do case "$1" in
  --domain) domain=$2; shift 2;; --mac) mac=$2; shift 2;; --legacy) mode=legacy; shift;; --native) mode=native; shift;;
  --file) file=$2; shift 2;; --template-file) tfile=$2; shift 2;; --template-uuid) tuuid=$2; shift 2;;
  --ua) ua=$2; shift 2;; --prove) prove=1; shift;;
  -h|--help) sed -n 2,13p "$0"; exit 0;; *) echo "unknown arg: $1" >&2; exit 2;; esac; done
[ -n "$domain" ] && [ -n "$mac" ] && [ -n "$mode" ] || { echo "need --domain, --mac and --legacy|--native (see --help)" >&2; exit 2; }
mac=$(printf '%s' "$mac" | tr -d ':.-' | tr 'A-F' 'a-f')
[[ "$mac" =~ ^[0-9a-f]{12}$ ]] || { echo "bad mac" >&2; exit 2; }
[[ "$domain" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "bad domain" >&2; exit 2; }
[ -z "$tfile" ] || [ -r "$tfile" ] || { echo "cannot read --template-file" >&2; exit 2; }
[ -z "$tuuid" ] || [[ "$tuuid" =~ ^[0-9a-f-]{36}$ ]] || { echo "bad --template-uuid" >&2; exit 2; }
[ -z "$tfile$tuuid" ] || [ "$mode" = native ] || { echo "--template-* only with --native" >&2; exit 2; }

tmpd=$(mktemp -d /tmp/p4352-harness.XXXXXX) || exit 1   # mode 700; holds only files with real passwords while rendering
trap 'rm -rf "$tmpd"' EXIT
trap 'exit 130' INT TERM
q() { sudo -n -u postgres env PGOPTIONS="$PGOPTIONS" psql -d fusionpbx -At -F'|' -c "$1"; }
row=$(q "select d.domain_uuid, d.domain_name, lower(coalesce(v.device_vendor,'')), v.device_uuid, coalesce(v.device_template,''), coalesce(v.device_model,'') from v_devices v join v_domains d using(domain_uuid) where (d.domain_name='$domain' or d.domain_uuid::text='$domain') and lower(v.device_address)='$mac'")
[ -n "$row" ] || { echo "device $mac not found in domain $domain" >&2; exit 4; }
IFS='|' read -r duuid dname vendor devuuid tmplname model <<<"$row"

if [ -z "$file" ]; then case "$vendor" in
  grandstream|htek) file="cfg$mac.xml";; cisco|snom) file="$mac.xml";; *) file="$mac.cfg";; esac; fi
[[ "$file" =~ ^[A-Za-z0-9._/{}\$-]+$ ]] || { echo "bad --file" >&2; exit 2; }
if [ -z "$ua" ]; then case "$vendor" in
  polycom) ua="PolycomVVX-VVX_250-UA/6.4.7.4513";; yealink) ua="Yealink SIP-T46U 108.86.0.50 $mac";;
  fanvil) case "$(echo "$tmplname $model" | tr 'A-Z' 'a-z')" in *x210*) ua="Fanvil X210 2.4.8";; *x4u*) ua="Fanvil X4U 2.4.8";; *v67*) ua="Fanvil V67 2.4.8";; *w710*) ua="Fanvil W710D 2.4.8";; *) ua="Fanvil X303 2.4.8";; esac
          echo "note: no --ua; defaulting to '$ua' (from device template/model). UA-dependent template logic follows it: pass --ua to be exact." >&2;; grandstream) ua="Grandstream GXP1615 1.0.11.6 $mac";;
  cisco) ua="Cisco/SPA112-1.4.1";; algo) ua="Algo 8301";; *) ua="p4352-harness";; esac; fi

snap() { q "select device_provisioned_date, device_provisioned_method, device_provisioned_ip, device_provisioned_agent from v_devices where device_uuid='$devuuid'"; }
if [ $prove = 1 ]; then
  before=$(snap)
  { echo "== read-only proof for $dname $mac"; echo "psql session: $(q 'show transaction_read_only')";
    sudo -n -u www-data env PGOPTIONS="$PGOPTIONS" php -d display_errors=0 "$HERE/ro_check.php" attempt-write "$mac"; echo "device_provisioned_* before: $before"; } >&2
fi

case "$mode" in
legacy)
  # Digest header computed from the tenant's provision http_auth settings (default -> domain chain); the secret never leaves this shell.
  get() { q "select coalesce((select domain_setting_value from v_domain_settings where domain_uuid='$duuid' and domain_setting_category='provision' and domain_setting_subcategory='$1' and domain_setting_enabled='true' limit 1),(select default_setting_value from v_default_settings where default_setting_category='provision' and default_setting_subcategory='$1' and default_setting_enabled='true' limit 1),'')"; }
  U=$(get http_auth_username); PW=$(get http_auth_password)
  URI="/app/provision/$file"
  A1=$(printf '%s' "$U::$PW" | md5sum | cut -d' ' -f1); A2=$(printf '%s' "GET:$URI" | md5sum | cut -d' ' -f1)
  R=$(printf '%s' "$A1:n:00000001:c:auth:$A2" | md5sum | cut -d' ' -f1); unset PW A1
  DIG="username=\"$U\", realm=\"\", nonce=\"n\", uri=\"$URI\", qop=auth, nc=00000001, cnonce=\"c\", response=\"$R\""
  out="$tmpd/legacy.out"; : > "$out"; chmod 600 "$out"   # redirects are opened by this shell (pbx), so 600 is enough
  sudo -n -u www-data env PGOPTIONS="$PGOPTIONS" DIGEST="$DIG" php -d display_errors=0 -d log_errors=1 -d error_log=/dev/stderr -d error_reporting=E_ALL \
     "$HERE/render_legacy.php" "$dname" "$ua" "$mac" "$file" > "$out" 2> "$out.err"; rc=$?
  # the legacy side must prove its own DB connection was read-only (checked inside render_legacy.php) before any output is released
  grep -q '^legacy: db transaction_read_only=on write_rejected=yes' "$out.err" || { echo "REFUSING: legacy DB connection not proven read-only" >&2; exit 11; }
  # CLI-only artifact: the legacy page ends by touching permissions::new() after the body; strip it (an HTTP response never has it).
  perl -0777 -pe 's/\nFatal error: Uncaught Error: Call to undefined method permissions::new\(\).*\z//s' "$out"
  grep -v 'permissions::new\|^Stack trace\|^#[0-9]\|^  thrown in\|^PHP Fatal error\|^$' "$out.err" >&2 || true
  # the permissions::new() fatal is the CLI artifact above, not a render failure
  [ "$rc" = 255 ] && grep -q 'permissions::new' "$out.err" && rc=0;;
native)
  # a draft template goes to PHP on stdin: no temp copy of it exists anywhere
  args=(); [ -z "$tuuid" ] || args+=("template-uuid=$tuuid"); in=/dev/null
  if [ -n "$tfile" ]; then args+=("template-file=php://stdin"); in="$tfile"; fi
  sudo -n -u www-data env PGOPTIONS="$PGOPTIONS" php -d display_errors=0 -d log_errors=1 -d error_log=/dev/stderr \
     "$HERE/render_native.php" "$duuid" "$mac" "$file" "$ua" "${args[@]}" < "$in"; rc=$?;;
esac
if [ $prove = 1 ]; then after=$(snap); { echo "device_provisioned_* after:  $after"; [ "$before" = "$after" ] && echo "device_provisioned_*: UNCHANGED" || echo "device_provisioned_*: CHANGED !!"; } >&2; fi
exit ${rc:-0}
