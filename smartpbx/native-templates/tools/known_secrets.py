#!/usr/bin/env python3
"""Known secret values on fspbx01, for masking (compare.py) and leak audits (audit_leaks.py). Read-only.

Collects every value that could legitimately appear in a phone config and must never be pasted: SIP line passwords,
extension passwords, device passwords, and provision/domain/device settings whose subcategory looks like a password,
secret, PIN, PSK or token. Returns [(label, value)]; the label says where the value belongs (never contains the value).
Run as pbx on fspbx01 (uses `sudo -u postgres psql` under a read-only session).
"""
import os, subprocess, sys

SEP = '\x1f'
KEY_PAT = r'(pass|pwd|pswd|secret|psk|token|digest|(^|_)pin($|_)|auth_?code)'
SQL = f"""
select 'line password|'||d.domain_name||'|'||v.device_address||'|line '||l.line_number, l.password
  from v_device_lines l join v_devices v using(device_uuid) join v_domains d on d.domain_uuid=v.domain_uuid where coalesce(l.password,'')<>''
union all select 'device password|'||d.domain_name||'|'||v.device_address, v.device_password
  from v_devices v join v_domains d using(domain_uuid) where coalesce(v.device_password,'')<>''
union all select 'extension password|'||d.domain_name||'|ext '||e.extension, e.password
  from v_extensions e join v_domains d using(domain_uuid) where coalesce(e.password,'')<>''
union all select 'default setting|'||default_setting_category||'/'||default_setting_subcategory, default_setting_value
  from v_default_settings where default_setting_subcategory ~* '{KEY_PAT}' and coalesce(default_setting_value,'')<>''
union all select 'domain setting|'||d.domain_name||'|'||s.domain_setting_category||'/'||s.domain_setting_subcategory, s.domain_setting_value
  from v_domain_settings s join v_domains d using(domain_uuid) where s.domain_setting_subcategory ~* '{KEY_PAT}' and coalesce(s.domain_setting_value,'')<>''
union all select 'device setting|'||d.domain_name||'|'||v.device_address||'|'||s.device_setting_category||'/'||s.device_setting_subcategory, s.device_setting_value
  from v_device_settings s join v_devices v using(device_uuid) join v_domains d on d.domain_uuid=v.domain_uuid
  where s.device_setting_subcategory ~* '{KEY_PAT}' and coalesce(s.device_setting_value,'')<>''
"""


def load():
    env = dict(os.environ, PGOPTIONS='-c default_transaction_read_only=on')
    r = subprocess.run(['sudo', '-n', '-u', 'postgres', 'env', 'PGOPTIONS=' + env['PGOPTIONS'], 'psql', '-d', 'fusionpbx', '-At', '-F', SEP, '-c', SQL],
                       capture_output=True, text=True, env=env)
    if r.returncode != 0:
        raise RuntimeError('known_secrets: psql failed (' + r.stderr.strip().splitlines()[-1][:120] + ')' if r.stderr.strip() else 'known_secrets: psql failed')
    out = []
    for line in r.stdout.split('\n'):
        if SEP in line:
            label, value = line.split(SEP, 1)
            out.append((label, value))
    return out


if __name__ == '__main__':
    s = load()
    print(f'{len(s)} known secret values loaded', file=sys.stderr)
