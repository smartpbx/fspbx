#!/usr/bin/env python3
"""Leak audit: which documents in a corpus directory contain real secret values from the fspbx01 DB (patchbay#4352).
usage: audit_leaks.py CORPUS_DIR     (run as pbx on fspbx01; corpus files are split into sections at lines starting '=== ')
Prints only locations and labels (category|tenant|device|setting), never a value. Matches values of >=8 chars as substrings and
6-7 chars on token boundaries (plain and HTML-escaped); shorter values are not searched (too many false positives)."""
import os, re, sys
here = os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0, here)
import known_secrets

secs = {}
for lab, v in known_secrets.load():
    if len(v) >= 6:
        for form in {v, v.replace('&', '&amp;')}:
            secs.setdefault(form, set()).add(lab)
long_ = [s for s in secs if len(s) >= 8]
short = {s: re.compile(r'(?<![A-Za-z0-9])' + re.escape(s) + r'(?![A-Za-z0-9])') for s in secs if len(s) < 8}
print(f'secrets searched: {len(secs)} values', file=sys.stderr)
total = 0
for name in sorted(os.listdir(sys.argv[1])):
    text = open(os.path.join(sys.argv[1], name), errors='replace').read()
    hits = [s for s in long_ if s in text] + [s for s, rx in short.items() if rx.search(text)]
    if not hits:
        print(f'{name}: clean'); continue
    # locate: section header + diff file
    sect, dfile, found = name, '', {}
    for line in text.split('\n'):
        if line.startswith('=== '):
            sect, dfile = line[:120], ''
        elif line.startswith('+++ b/'):
            dfile = line[6:]
        for s in hits:
            if s in line:
                found.setdefault((sect, dfile), set()).update(secs[s])
    for (sect, dfile), labs in found.items():
        total += 1
        print(f'LEAK {name} | {sect} | {dfile or "-"} | {len(labs)} secret(s): ' + '; '.join(sorted(labs)[:6]))
print(f'locations with leaks: {total}', file=sys.stderr)
