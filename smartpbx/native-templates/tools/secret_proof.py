#!/usr/bin/env python3
"""Proof that compare.sh output never contains a device's real secret values (patchbay#4352).
usage: secret_proof.py DOMAIN MAC [native-args...]   e.g. secret_proof.py 105229.pbx.smartpbx.io 0c383e696bfe --template-uuid <uuid>
Renders legacy + native into a 700 temp dir, runs compare.sh (default and --include-same), and greps the output for every
secret value that belongs to that device (its line/device/extension passwords, its domain's and the default password/secret/PIN
settings; plain and HTML-escaped). Prints only counts, never a value. A control run with --no-mask must find hits, otherwise the
check proves nothing. Exit 1 on any leak or a dead control. Run as pbx on fspbx01."""
import os, subprocess, sys, tempfile, shutil
here = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, here)
import known_secrets

domain, mac, *nargs = sys.argv[1:]
mac = mac.lower()
secs = {v for lab, v in known_secrets.load() if len(v) >= 4 and (lab.startswith('default setting') or f'|{domain}|' in lab)}
secs |= {s.replace('&', '&amp;') for s in list(secs) if '&' in s}
tmp = tempfile.mkdtemp(prefix='p4352-sproof.'); os.chmod(tmp, 0o700)
try:
    def run(args, out):
        with open(out, 'wb') as f:
            os.chmod(out, 0o600)
            return subprocess.run(args, stdout=f, stderr=subprocess.DEVNULL).returncode
    rl = run([f'{here}/render.sh', '--domain', domain, '--mac', mac, '--legacy'] + [a for a in nargs if a.startswith('--file') or a.startswith('--ua')], f'{tmp}/a')
    rn = run([f'{here}/render.sh', '--domain', domain, '--mac', mac, '--native'] + nargs, f'{tmp}/b')
    if rn != 0 or os.path.getsize(f'{tmp}/b') == 0:
        print(f'{domain} {mac}: native render rc={rn}, cannot prove'); sys.exit(1)
    if os.path.getsize(f'{tmp}/a') == 0:   # legacy 404 (e.g. native-only devices): compare native with itself, still exercises masking
        shutil.copy(f'{tmp}/b', f'{tmp}/a')
    def cmp(*flags):
        return subprocess.run([f'{here}/compare.sh', f'{tmp}/a', f'{tmp}/b', *flags], capture_output=True, text=True).stdout
    outs = {'default': cmp(), 'include-same': cmp('--include-same'), 'ignore-empty': cmp('--ignore-empty', '--include-same')}
    raw = subprocess.run([f'{here}/compare.sh', f'{tmp}/a', f'{tmp}/b', '--include-same', '--no-mask'], capture_output=True, text=True).stdout
    rendered = open(f'{tmp}/a', errors='replace').read() + open(f'{tmp}/b', errors='replace').read()
    present = sum(1 for s in secs if s in rendered)          # secrets that really occur in the renders
    control = sum(1 for s in secs if s in raw)               # ...and are visible with masking off
    leaks = {k: sum(1 for s in secs if s in o) for k, o in outs.items()}
    print(f'{domain} {mac}: device secrets={len(secs)} present_in_render={present} control_unmasked_hits={control} '
          f'leaks default={leaks["default"]} include-same={leaks["include-same"]} ignore-empty={leaks["ignore-empty"]} lines={outs["include-same"].count(chr(10))}')
    sys.exit(0 if (sum(leaks.values()) == 0 and control > 0) else 1)
finally:
    shutil.rmtree(tmp, ignore_errors=True)
