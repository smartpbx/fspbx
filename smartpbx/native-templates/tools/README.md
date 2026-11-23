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
