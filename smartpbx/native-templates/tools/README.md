# native-templates/tools: shared read-only render + compare harness (patchbay#4352)

Installed on fspbx01 at `~pbx/native-render/` (copy of this directory; this git directory is the source of truth,
reinstall with `rsync -a smartpbx/native-templates/tools/ pbx@fspbx01:native-render/`). Run on fspbx01 as `pbx`.

```
R=~pbx/native-render
$R/render.sh --domain <domain-name|uuid> --mac <mac> --legacy [--file <tail>] [--ua <UA>] [--prove]
$R/render.sh --domain <d> --mac <m> --native [--template-file <path> | --template-uuid <uuid>] [--file <tail>] [--ua <UA>] [--prove]
$R/compare.sh <a-file> <b-file> [--ignore-empty] [--include-same] [--format=xml|kv] [--map=FILE] [--no-mask]
$R/http_proof.sh --domain <d> --mac <m> [--file <tail>]      # byte-identity proof of --legacy vs a real HTTP response
```

- Config goes to stdout, diagnostics to stderr. **Stdout contains real SIP/admin passwords**: write it to
  `/tmp/p4352-<slice>.*`, compare, delete at the end; never paste it anywhere. `compare.sh` masks secrets as `<redacted>`.
- `--file` is the URL tail the phone asks for: `y000000000108.cfg`, `phone<mac>.cfg`, `VVX450-<mac>.cfg`,
  `cfg<mac>.xml`, `algom<mac>.conf`, `<mac>-contacts.cfg` ... Default: the vendor's main file (`<mac>.cfg`;
  `cfg<mac>.xml` for Grandstream/Htek; `<mac>.xml` for Cisco/Snom). `--legacy` maps it through a copy of the nginx
  rewrites (`rewrites.php`); a tail no rewrite matches exits 3. `--native` goes through the controller's own flavor
  logic, so Polycom `phone<mac>.cfg` / `<Model>-<mac>.cfg` and Yealink `y0000000000NN.cfg` hit the right flavor.
- `--native` with no template flag uses what `/prov/` would pick for the device (`device_template_uuid`, else
  vendor/name default); exit 6 = `/prov/` would answer 404. `--template-file` renders your draft `.tpl` in memory
  (never saved, never inserted); `--template-uuid` renders any stock/custom row without assigning it.
- `--prove` prints, to stderr: the psql and PHP session `transaction_read_only`, a rejected `UPDATE v_devices` attempt,
  and the device's `device_provisioned_*` before and after (must read UNCHANGED).
- Safety: every process runs under `PGOPTIONS='-c default_transaction_read_only=on'`; the native renderer refuses to run
  (exit 10) if the session is not read-only; it never calls `ProvisioningController::serve()`. Nothing is written to the
  DB or to `/var/www/fspbx`. Temp files use `/tmp/p4352-harness.*` and are removed.
- `compare.sh` output: one line per differing key, `key | a | b`; `<absent>` = key missing on that side, `<empty>` = key
  present with an empty value (`--ignore-empty` treats empty as absent). Summary counts go to stderr. XML keys: element
  path, `name[index]` for `index=""` elements (Fanvil `Fkey[1]`), Polycom dotted attributes by their own name
  (`reg.1.address`), Grandstream `P123`, native `<item name="x">` as `x`. Legacy Grandstream P-values and the native
  `<item name>` form only line up with `--map=FILE` (lines `P271 account.1.enable`). Malformed legacy XML (bare `&`) falls back to a tolerant scan with a warning.

## Safety notes (review fixes, fspbx#8)
- **Masking (compare.sh) fails closed.** A row's value is `<redacted>` if (a) the key name matches the secret pattern
  (pass, pswd, pwd, pw, pin, authcode, secret, psk, token, digest, ... covers Fanvil `RegisterPswd`/`ProxyPswd`/`AdminPW`/`UserPIN`,
  Algo `sip.pwd`, Polycom `*.password`, Grandstream password P-values and any leaf whose preceding comment says password) **or** (b)
  the value equals or contains any real secret in the fleet (SIP/extension/device passwords and provision password/secret/PIN
  settings, read live by `known_secrets.py`), whatever the key is called. If the secret list cannot be read, compare.sh refuses
  (`--no-db-secrets` only for files that are not device configs). `test_mask.sh` checks the key patterns with synthetic values;
  `secret_proof.py <domain> <mac> [native args]` proves a real device's compare output holds none of its secrets (counts only).
  Still: treat render output as secret, and look at a compare file before pasting it into a comment.
- **Native render is the body only.** `render_native.php` does not run `DigestProvisionAuth` (device lookup by path, realm
  `Prov-<hash>`, `http_auth_type`/`userAgentForcesBasic`, CIDR check), nginx `/prov/` routing or ETag/304. A clean native render does
  not prove the phone can authenticate on `/prov/`; that needs one real hit on an `ewee` device before the first pilot, which stamps
  `device_provisioned_*` and needs approval.
- **Read-only.** Session default via `PGOPTIONS`; native additionally renders inside an explicit `SET TRANSACTION READ ONLY` transaction
  (verified before and after, then rolled back); legacy proves before running that its connection is read-only and rejects a write
  (`render.sh` refuses to release output otherwise, exit 11). A dedicated read-only DB role would be stronger but needs a prod DB change.
- Temp files live in a `mktemp -d` 700 directory (files 600) removed by a trap on EXIT/INT/TERM; a draft `--template-file` is passed on
  stdin, so no copy exists. Fanvil without `--ua` takes its UA from the device template/model (X210, X4U, V67, W710D, else X303) and
  says so on stderr. `check_rewrites.sh` fails when the live nginx provisioning rewrites drift from `nginx-rewrites.snapshot`.
- `audit_leaks.py <dir>` searches a corpus (git patches, issue/PR comments) for real secret values and prints only locations/labels.
