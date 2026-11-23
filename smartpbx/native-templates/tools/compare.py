#!/usr/bin/env python3
"""Format-aware normalized diff of two provisioning configs (patchbay#4352 harness).

usage: compare.py A B [--format=xml|kv] [--ignore-empty] [--include-same] [--no-mask] [--map=FILE]

Flattens each file to key -> value and prints one line per differing key:   key | a | b
  * XML (Fanvil sysConf, Polycom attribute XML, Grandstream P-values, Cisco/Snom/Htek XML):
      element path (siblings carrying an index="" attribute become name[index]; other repeated siblings name[n]),
      Polycom dotted attributes use the attribute name itself as the key (reg.1.address), other attributes are path@attr,
      Grandstream <P123> leaves use the bare tag P123; native <item name="x"> leaves use x. (Legacy P-values and native
      item names only line up through --map=FILE, lines `P271 account.1.enable`.)
      A leaf whose preceding XML comment mentions a password/secret is masked too (Grandstream "Config HTTP/HTTPS Password").
  * key/value text (Yealink, Algo, generic): `key = value` or `key: value`, [section] prefixes, #/; comments skipped.
Missing side prints <absent>; an empty value prints <empty> (with --ignore-empty, empty == absent).
Secret keys (password/secret/psk/token/digest..., Grandstream password P-values) are masked as <redacted>; a masked key
is listed only when its real values differ. Extra secret key patterns: env P4352_SECRET_RE. Summary goes to stderr.
Exit 0 always when the comparison ran (differences are the output); 2 on usage/parse errors.
"""
import os, re, sys
import xml.etree.ElementTree as ET

GS_SECRET_P = {'P2', 'P3', 'P34', 'P196', 'P406', 'P506', 'P606', 'P706', 'P806', 'P1406', 'P1506', 'P1606', 'P1706', 'P1806', 'P1906', 'P2006', 'P2106', 'P2206', 'P2306', 'P2406', 'P2506', 'P2606', 'P2706', 'P2806', 'P2906', 'P3006', 'P3106', 'P3206', 'P3306', 'P3406', 'P3506', 'P3606'}
SECRET_RE = re.compile(os.environ.get('P4352_SECRET_RE') or r'pass(word|wd)?|pwd|secret|psk|token|digest|credential|auth_?key|private_?key|passphrase', re.I)


def is_secret(key):
    return key in GS_SECRET_P or key in SECRET_KEYS or bool(SECRET_RE.search(key))


def local(tag):
    return tag.rsplit('}', 1)[-1]


SECRET_KEYS = set()   # keys whose preceding XML comment says they are a password/secret (Grandstream P-values)


def flatten_xml(text):
    out = {}
    parser = ET.XMLParser(target=ET.TreeBuilder(insert_comments=True))
    root = ET.fromstring(text.encode('utf-8', 'replace'), parser=parser)

    def elems(el):
        return [k for k in el if isinstance(k.tag, str)]

    def seg(el, siblings):
        name = local(el.tag)
        if name == 'item' and 'name' in el.attrib:
            return None          # <item name="account.1.enable">: the name attribute is the key
        idx = el.attrib.get('index', el.attrib.get('id'))
        if idx is not None:
            return f'{name}[{idx}]'
        same = [x for x in siblings if local(x.tag) == name]
        if len(same) > 1:
            return f'{name}[{same.index(el) + 1}]'
        return name

    def walk(el, path, siblings, comments):
        s = seg(el, siblings)
        if s is None:
            key = el.attrib['name']
            out[key] = (el.text or '').strip()
            if any(SECRET_RE.search(c) for c in comments):
                SECRET_KEYS.add(key)
            return
        here = path + [s]
        kids = elems(el)
        for an, av in el.attrib.items():
            an = local(an)
            if an == 'index' or (an == 'id' and kids) or an.startswith('xmlns'):
                continue
            key = an if '.' in an else '/'.join(here) + '@' + an
            out[key] = av.strip()
        text = (el.text or '').strip()
        if not kids:
            key = local(el.tag) if re.fullmatch(r'P\d+', local(el.tag)) else '/'.join(here)
            if text or not el.attrib:
                out[key] = text
                if any(SECRET_RE.search(c) for c in comments):
                    SECRET_KEYS.add(key)
        elif text:
            out['/'.join(here) + '#text'] = text
        pend = []
        for k in el:
            if not isinstance(k.tag, str):
                pend.append((k.text or '').strip())
                continue
            walk(k, here, kids, pend)
            pend = []

    walk(root, [], [root], [])
    return out


def flatten_kv(text):
    out, section = {}, ''
    for raw in text.splitlines():
        line = raw.strip()
        if not line or line[0] in '#;!' :
            continue
        m = re.fullmatch(r'\[(.+)\]|<<(.+)>>', line)
        if m:
            section = (m.group(1) or m.group(2)).strip() + '/'
            continue
        if '=' in line:
            k, v = line.split('=', 1)
        elif ':' in line:
            k, v = line.split(':', 1)
        else:
            k, v = line, ''
        out[section + k.strip()] = v.strip()
    return out


def load(path, fmt):
    with open(path, 'rb') as f:
        data = f.read().decode('utf-8', 'replace')
    if fmt == 'auto':
        fmt = 'xml' if data.lstrip().startswith('<') else 'kv'
    if fmt == 'kv':
        return flatten_kv(data), fmt
    try:
        return flatten_xml(data), fmt
    except ET.ParseError as e:
        # Legacy templates emit values with bare & < etc. that a strict parser rejects (seen on Grandstream P-values).
        # Fall back to a tolerant leaf scan: <Tag ...>value</Tag> pairs, keyed by tag (P-values) or tag with a running count.
        sys.stderr.write(f'compare.py: warning: {path}: not well-formed XML ({e}); using tolerant leaf scan\n')
        out, seen = {}, {}
        for m in re.finditer(r'((?:<!--.*?-->\s*)*)<([A-Za-z_][\w.:-]*)([^<>]*)>([^<]*)</\2>', data, re.S):
            cm, tag, attrs, val = m.group(1), m.group(2), m.group(3), m.group(4).strip()
            for am in re.finditer(r'([\w.:-]+)="([^"]*)"', attrs):
                out[am.group(1) if '.' in am.group(1) else f'{tag}@{am.group(1)}'] = am.group(2)
            if tag == 'item' and 'name=' in attrs:
                nm = re.search(r'name="([^"]*)"', attrs).group(1)
                out[nm] = val
                if SECRET_RE.search(cm):
                    SECRET_KEYS.add(nm)
            elif re.fullmatch(r'P\d+', tag):
                out[tag] = val
                if SECRET_RE.search(cm):
                    SECRET_KEYS.add(tag)
            else:
                seen[tag] = seen.get(tag, 0) + 1
                out[tag if seen[tag] == 1 else f'{tag}[{seen[tag]}]'] = val
        return out, 'xml-tolerant'


def main(argv):
    args = [a for a in argv if not a.startswith('--')]
    flags = [a for a in argv if a.startswith('--')]
    fmt = 'auto'
    for a in flags:
        if a.startswith('--format='):
            fmt = a.split('=', 1)[1]
    if len(args) != 2 or any(a not in ('--ignore-empty', '--include-same', '--no-mask') and not a.startswith(('--format=', '--map=')) for a in flags):
        sys.stderr.write(__doc__)
        return 2
    ignore_empty, include_same, mask = '--ignore-empty' in flags, '--include-same' in flags, '--no-mask' not in flags
    a, fa = load(args[0], fmt)
    b, fb = load(args[1], fmt)
    for fl in flags:
        if fl.startswith('--map='):   # file of `OLDKEY NEWKEY` lines (e.g. Grandstream `P271 account.1.enable`), applied to both sides
            mp = dict(l.split(None, 1)[:2] for l in open(fl[6:]) if l.strip() and not l.startswith('#'))
            mp = {k: v.strip() for k, v in mp.items()}
            a = {mp.get(k, k): v for k, v in a.items()}
            b = {mp.get(k, k): v for k, v in b.items()}
            SECRET_KEYS.update(mp.get(k, k) for k in list(SECRET_KEYS))
    if fa != fb:
        sys.stderr.write(f'compare.py: warning: formats differ (a={fa}, b={fb})\n')
    if ignore_empty:
        a = {k: v for k, v in a.items() if v != ''}
        b = {k: v for k, v in b.items() if v != ''}
    keys = sorted(set(a) | set(b))
    n_same = n_diff = n_only_a = n_only_b = 0
    for k in keys:
        ia, ib = k in a, k in b
        va, vb = a.get(k), b.get(k)
        if ia and ib and va == vb:
            n_same += 1
            if not include_same:
                continue
        elif ia and ib:
            n_diff += 1
        elif ia:
            n_only_a += 1
        else:
            n_only_b += 1
        def show(present, v):
            if not present:
                return '<absent>'
            if mask and is_secret(k):
                return '<redacted>' if v != '' else '<empty>'
            return '<empty>' if v == '' else v.replace('\n', '\\n')
        print(f'{k} | {show(ia, va)} | {show(ib, vb)}')
    sys.stderr.write(f'compare: {len(a)} keys in a, {len(b)} in b; same {n_same}, differ {n_diff}, only-a {n_only_a}, only-b {n_only_b}\n')
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main(sys.argv[1:]))
    except BrokenPipeError:
        os._exit(0)
