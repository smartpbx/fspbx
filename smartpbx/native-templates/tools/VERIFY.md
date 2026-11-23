# VERIFY: harness (patchbay#4352)

## 1. Legacy render equals a real legacy HTTP response (4 vendors, 5 files, 0 differences)
`http_proof.sh` serves the unmodified `/app/provision/index.php` over HTTP (`php -S` on 127.0.0.1, nginx rewrites
mirrored, real `curl --digest` against the tenant's http_auth settings, DB session read-only) and compares sha256 with
`render.sh --legacy`. The production nginx path was deliberately not used: a production hit stamps `device_provisioned_*`.

| Vendor | Tenant / MAC | File | HTTP bytes = CLI bytes | sha256 (first 16) | device_provisioned_* |
|---|---|---|---|---|---|
| Fanvil X303 | 105229 / 0c383e696bfe | `<mac>.cfg` | 69590 | b144e1b4e2593f84 | unchanged |
| Polycom VVX | 100202 / 482567b275ec | `<mac>.cfg` | 6910 | c823e14873944366 | unchanged |
| Yealink T46U | 100171 / 44dbd27aaa06 | `<mac>.cfg` | 119387 | af5b1492ddb7f22d | unchanged |
| Yealink T46U | 100171 / 44dbd27aaa06 | `y000000000108.cfg` | 70631 | 9ce3ef0276d5c1dd | unchanged |
| Grandstream GXP16xx | 100203 / 000b82e61499 | `cfg<mac>.xml` | 80444 | 6356b362130f71ed | unchanged |

Caveats: CLI PHP vs php-fpm can differ in ini settings (none seen); the CLI-only `permissions::new()` fatal at the end of
the legacy page is stripped. Algo/Cisco and Polycom `phone<mac>.cfg` return the same 162-byte 404 on both paths (identical,
but not a content proof). A hit on the production nginx for a device on our own `ewee` tenant is the only remaining
step, and it would stamp that device: not done without approval.

## 2. Read-only proof (`render.sh --prove`)
- psql session `transaction_read_only = on`; the PHP session (PDO and Laravel) reports `on`.
- `UPDATE v_devices ...` attempt: `cannot execute UPDATE in a read-only transaction` (SQLSTATE 25006).
- `device_provisioned_*` before == after for a native render (Fanvil 0c383e696bfe, XU Series uuid, in memory) and a legacy render (Yealink 44dbd27aaa06): `UNCHANGED`.
- `render_native.php` run without PGOPTIONS prints `transaction_read_only=off` and `REFUSING: DB session is not read-only`.
- `--template-file` render uses a temp copy removed on exit; no `provisioning_templates` row exists or is created.

## 3. compare.sh checks
- Same file against itself with `--include-same`: Fanvil 1583 keys, 1583 same; Polycom secrets (`device.auth.localAdminPassword`) show `<redacted>`.
- Fanvil 105229 legacy vs stock XU Series: 1583 vs 399 keys, 170 same, 27 differ, 1386 only legacy, 202 only native (identical to the wave-1 numbers). Yealink T46U legacy vs stock T4: 42 same, 18 differ.
- Grandstream legacy (P-values, bare `&` in the file, tolerant scan) vs stock native (`<item name>`): disjoint key sets without `--map`; P2, P34, P1361 (comment "Config HTTP/HTTPS Password") masked.

## Known limits / open items
- Grandstream P-value to item-name map does not exist yet; the Grandstream template worker should supply one for `--map`.
- Algo `algom<mac>.conf` and Cisco files 404 on legacy for the sampled devices; the owners should pick a device that has a legacy file.

## 4. Review fixes (fspbx#8 review)
- **Masking proof, one device per vendor** (`secret_proof.py`, device's own line/device/extension passwords plus its tenant's and the default password settings; compare run default, `--include-same`, `--ignore-empty`; control run with `--no-mask` must see the secrets):

| Vendor / device | Secrets checked | Present in render (control hits) | Leaks in compare output |
|---|---|---|---|
| Fanvil X303 105229 / 0c383e696bfe (XU Series) | 24 | 2 (2) | 0 |
| Fanvil X210 100138 / 0c383e7dff3e (XU Series) | 65 | 2 (2) | 0 |
| Polycom 100202 / 482567b275ec (VVX) | 37 | 2 (2) | 0 |
| Yealink T46U 100171 / 44dbd27aaa06 (T4) | 50 | 3 (3) | 0 |
| Grandstream 100203 / 000b82e61499 (resolved) | 26 | 3 (2) | 0 |
| Algo 105284 / 0022ee19e243 (native vs itself) | 32 | 2 (2) | 0 |
| Yealink T34W 107585 / c4fc2266bb25 (native vs itself) | 15 | 2 (2) | 0 |
| Grandstream HT 100250 / ec74d72e92f2 (HT8xx stock) | 28 | 2 (2) | 0 |

  `test_mask.sh`: 19 synthetic key names (RegisterPswd, ProxyPswd, AdminPW, UserPIN, AuthCode, P2, P34, sip.pwd, ...) all `<redacted>`, harmless key stays visible.
- Render temp files: 700 dir, 600 files, trap cleanup; draft template via stdin. Read-only: native explicit READ ONLY transaction, legacy connection proven read-only and write-rejected before the page runs (`legacy: db transaction_read_only=on write_rejected=yes`).
- Native skips `DigestProvisionAuth`: documented in README and `render.sh --help`. No native auth proof yet (needs approval, stamps `device_provisioned_*`).
- Secret audit (read-only, 2026-11-23): PR branches, PR bodies/reviews/comments of fspbx#8-#13, and patchbay#4352 / #4364 bodies and comments, searched against 1245 real secret values of 6+ characters: 0 leaks; a planted control value was found. Short (<6 char) values are not searched.
