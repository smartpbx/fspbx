#!/bin/bash
# Key-name mask test with synthetic values (no DB, no real secrets): every name below must print <redacted>, never the value.
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; d=$(mktemp -d /tmp/p4352-maskt.XXXXXX); trap 'rm -rf "$d"' EXIT
names="RegisterPswd ProxyPswd AdminPW UserPIN AuthCode AuthPass WebPassword sip.pwd account.1.password reg.1.auth.password device.prov.password P2 P34 P406 SharedKey ApiKey Token Secret psk"
{ echo '<sysConf>'; for n in $names; do echo "<$n>SENTINEL-A-$n</$n>"; done; echo '<Harmless>visible-a</Harmless></sysConf>'; } > $d/a
{ echo '<sysConf>'; for n in $names; do echo "<$n>SENTINEL-B-$n</$n>"; done; echo '<Harmless>visible-b</Harmless></sysConf>'; } > $d/b
out=$("$HERE/compare.sh" $d/a $d/b --no-db-secrets 2>/dev/null)
bad=$(echo "$out" | grep -c SENTINEL); vis=$(echo "$out" | grep -c "visible-a | visible-b"); red=$(echo "$out" | grep -c "<redacted> | <redacted>")
echo "names=$(echo $names | wc -w) redacted_rows=$red leaked_rows=$bad harmless_visible=$vis"
[ "$bad" = 0 ] && [ "$vis" = 1 ] && [ "$red" -ge "$(echo $names | wc -w)" ]
