# Device profiles to key templates before native cutover (patchbay#4352, slice `profiles`)

Analysis only. Nothing was written to the database, `/var/www/fspbx` or any phone. All renders used the shared harness
(`~pbx/native-render/render.sh`, `compare.sh`) read-only on fspbx01, 2026-11-23. Per-tenant detail: `profiles-migration.csv`
(79 rows: tenant, vendor, legacy template, profile, devices, target key rows, action).

## 1. Census (enabled devices with `device_profile_uuid`, fspbx01)

558 devices in 58 tenants, not 336. The ~336 in the brief covers Polycom, Yealink and X210 only; **203 Fanvil X303-2 Wire
(190 + 13 `-initial`) also sit on profiles**, plus 2 V67, 1 Grandstream and 1 Fanvil-vendor device on a Yealink template.

| Vendor / legacy template | Devices on a profile | Profiles in use | Notes |
|---|---|---|---|
| Polycom 6.x_old / 6.x / 5.x_old | 227 / 13 / 2 (242) | 6 distinct key sets in 22 tenants | 23 of the 6.x_old devices point at an empty profile (no Polycom rows) |
| Yealink T46U 49, T44U 21, T33G 14, T41S 6, T73U 3, T48U 3, others 3 | 99 | 3 distinct key sets in 14 tenants | |
| Fanvil X303-2 Wire (+`-initial`) | 203 | 170 park-only; 33 with a Page key or a Reception pickup key | largest group; no native template exists yet |
| Fanvil X210 | 10 | 10 per-phone profiles (26-28 keys: 2 Park + 24 BLF) | tenants 105421 (Genesis), 106300, 105200 and others |
| Fanvil V67 2, Grandstream 1, Fanvil-vendor device on a Yealink template 1 | 4 | park-only | total 242 + 99 + 203 + 10 + 4 = 558 |

Top tenants by profile devices: 100171 (38), 105200 (35), 100176 (29), 105500 (28), 100201 (27), 105209 (24), 100157 (23),
100165 (22), 104575 (22). Park-only profiles ("2 Park Keys", "3 Park Keys", "4 Park Keys", "Park Keys and Page") dominate.

## 2. Why native breaks (source: `ProvisioningController::getEffectiveDeviceKeysByArea`, `resolveLegacyKeyPlacement`)

1. **A profile holds rows for every vendor.** `v_device_profile_keys` has `profile_key_vendor`. A "2 Park Keys" profile stores,
   for key id 2, one row each for fanvil (`c`), grandstream (`call park`), polycom (`automata`) and yealink (`16`).
   Legacy `provision.php` selects `profile_key_vendor = <device vendor>`; the native loop **never reads the vendor column**.
2. It does `$maps[$area][$id] = ...` for every row, so rows of different vendors **overwrite each other by id**. The
   winner is the last row the query returns (`orderBy('profile_key_line')`, ties in heap order), so the result is arbitrary.
   Wave 1 saw Yealink `c`; this slice's run on 44dbd27aaa06 showed `16`; Polycom lost its park keys. Same cause, different row order.
3. **No category-to-area logic except a few vendors.** `resolveLegacyKeyPlacement` sends every category to area `main`
   (Grandstream `memory`, Cisco and Yealink `expansion-*` excepted). Fanvil X210 profile `line` rows (Park, legacy `dssSide`) and
   `memory` rows (BLF, legacy `internal`) both land in `main` with the same ids: `memory` 1 overwrites `line` 1. Rendered Genesis
   X210: no Park keys, no line key (confirmed: native effective keys for 0c383e7dffa8 start at `main|1|ba|1001`).
4. Profile type codes are vendor-raw (`c`, `automata`, `16`) and are passed through untranslated; only key templates go through
   `mapNewDeviceKeyToLegacyShape` / `translateKeyTypeForVendor` / `mapNewDeviceKeyToPolycomShape`.

So the profile path is a half-ported compatibility shim. Key templates are the supported path (and what upstream is building on).

## 3. Proof: legacy render after converting to a key template (harness, read-only)

Legacy applies keys in the order profile, then key template, then device keys, so a template only overlays the profile. Identical output with
the profile still attached therefore proves little. This section was redone for the state the cutover actually creates: **profile removed
(device_profile_uuid NULL) and the key template alone**.

Method (all read-only on fspbx01, shared harness `render.sh`/`compare.sh` plus a /tmp copy for the overrides): the `database` class is subclassed
in memory so the profile key and profile setting queries return nothing (profile NULL), and the two key-template helper functions return the converted rows (the
real code path `fspbx_apply_new_keys_override` still runs). Baseline = legacy render of the device as it is now (profile). Conversion rules: only the
vendor's own rows, **blank rows kept** (Yealink `0`, Fanvil `3` become empty key rows), no `line` row, `park` rows for Polycom/Fanvil, `blf` rows valued `park+*59xx` for Yealink,
`blf` for Polycom `normal`, `speed_dial` for Yealink `13`/Fanvil `f`. Control: profile NULL with no template loses the Polycom park keys, so the override is live.
One device per distinct (vendor, legacy template, key set) was rendered: 30 devices, covering every row of `profiles-migration.csv`. Native: the effective keys with profile NULL and the template come only from the template (0 profile-sourced rows) with the expected vendor types.

| Class | Devices rendered | Result with profile NULL + key template |
|---|---|---|
| Polycom 6.x_old, 6.x, 5.x_old: park rows; park + BLF/Page rows (`*724`, 249, 500) | 7 (every Polycom key set) | identical |
| Fanvil X303-2 Wire and `-initial`, V67: park rows; park + Page (`f` speed dial `*724`) | 8 | identical |
| Fanvil X303 "Park Key and Reception Pickup" (`bb` pickup with extension) | 1 | **not identical**: the extension (`**1001`) is lost, key templates cannot carry it |
| Fanvil X210 park-only | 1 | identical |
| Fanvil X210 per-phone profiles with 24 BLFs | 2 | **not identical** on legacy (BLF `ba` becomes `bc`, main keys land in `dssSide`) |
| Yealink T46U, T33G, T44U (park and park + page/speed dial), T31G, T41S, T48U, T73U, T57W, T46S | 13 | identical, with one exception below |
| Yealink T44U "Park and Page t44" (blank key 4, speed dial on key 8) | 1 | identical except `linekey.3.line` on an unassigned slot (0 becomes 1, type stays 0). Harmless. **Dropping the blank rows does shift later keys** (four slots differ), so blank rows must be kept |
| Grandstream GXP16xx park | 1 | identical |

Yealink `park` typed rows render type 10 instead of the profile's 16, so use `blf` rows valued `park+*59xx`.

Conversion statuses are in the CSV column `legacy_identical_after_convert` (proven / proven except / NOT identical / n/a empty profile). All of it is proven with an in-memory
template. **Gate: before any bulk run, repeat on one device per batch with a real stored key template** (tenant scoping `t.domain_uuid = d.domain_uuid`, stored-row path of `getEffectiveDeviceKeysByArea`); that needs a DB write, so it is done in the pilot wave by the coordinator.

## 4. Per-tenant plan

`profiles-migration.csv` has one row per tenant x profile. Pattern for every tenant: one key template per distinct (vendor, key set),
named after the profile ("2 Park Keys", "Park Keys and Page", ...), tenant-scoped (`t.domain_uuid = d.domain_uuid` is required by the renderer), then
`device_key_template_uuid` set on each device. The 79 rows reduce to ~25 distinct key sets. Largest buckets: Yealink 2-park (38 devices in 2 tenants),
Fanvil 2-park (35), Polycom "Park Keys and Page" (29), "Park Keys" (28), Polycom 4-park (22), Fanvil "Park 1 + Page" (22).
X210: 10 per-phone profiles, 3 rows flagged cutover-only (BLF), the rest park-only.

## 5. Recommendation

**Convert profiles to key templates before cutover; do not patch the native renderer locally.**

- A local patch (filter by `profile_key_vendor`, add category-to-area for Fanvil, translate types) is a fork we carry on a box that tracks
  upstream `nemerald-voip/fspbx`; every upstream pull touches `ProvisioningController`, and upstream is deprecating profiles. It would also leave 558 devices on a path
  that nobody tests upstream. Optional: send the one-line vendor filter upstream as a PR (we do not carry it).
- Conversion is data-only, scriptable, reversible and (except X210 BLF) provably identical on legacy.
- At each device's cutover also set `device_profile_uuid = NULL`: native seeds from every profile row first, so rows of other vendors at ids the key template does not cover would survive. Record the old profile uuid for rollback; rollback keeps the key template (legacy-identical).

Order (follows the cutover order from the issue; each step is gated by the stored-row repeat in section 3):
1. Fanvil X303 (park, park + Page) and Polycom (all key sets): create key templates with blank rows kept and **no profile removal until the device's own cutover**; render legacy with profile NULL + template per device before and after (the harness proof above is the expected result).
2. Yealink (99): `blf` rows valued `park+*59xx`, blank rows kept; every model in the CSV was rendered identical, T44U with blanks identical except one harmless field.
3. X210 with 24-BLF profiles (2 in 105421 plus others) and the X303 "Reception Pickup" key: **not identical on legacy**, so convert at cutover with the custom X210 template, or leave on the profile until the template can carry an extension.
4. 23 Polycom devices with empty profiles: no key work; cutover only.

Open items: (a) `-initial` X303 devices carry a fixed Page key `*724` (#4358); (b) Yealink `park` (type 10) is the nicer long-term mapping but changes lamp behaviour, so it needs a bench probe; (c) the stored-row repeat is a gate (section 3), not an open item.

## 6. Escaping census (counts only; for patchbay#4364)

Characters tested: `&`, `<`, `>`, `"`, `'`. Enabled devices on fspbx01, 2026-11-23: 728 in 80 tenants (720 legacy, 8 native).

| Secret | Native | Legacy | Tenants |
|---|---|---|---|
| SIP line password (`v_device_lines.password`) | 0 devices | 21 devices | 2 |
| Device password (`v_devices.device_password`) | 0 | 0 | 0 |
| Provision/phone setting with `pass`/`secret`/`pin` in a domain setting | 0 | 0 | 0 |
| Provision `http_auth_password` (default setting) | 1 default setting contains one of the characters; it applies to every device without a domain override (no domain override has one): all 728 devices, 80 tenants | | |
| Any other default or domain setting named `*pass*`/`*secret*` | none | none | none |

So the escaping risk today is the 21 legacy SIP line passwords (not yet native) and the one default `http_auth_password`, which every template embeds in digest or directory URLs. LDAP/admin secrets were not found under those names in the provision/phone categories.

Format decides the fix: Polycom configs are XML (`&amp;`, `&lt;`, `&quot;` needed), Yealink `.cfg` and Fanvil `.cfg` are plain key=value text (raw, no XML escaping; Fanvil X303/X210 `{$mac}.cfg` are XML-shaped `sysConf/...`, so those do need XML escaping).
