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
| Fanvil X303-2 Wire (+`-initial`) | 203 | park-only (2 keys) mostly; "Park 1 + Page" 22 | largest group; no native template exists yet |
| Fanvil X210 | 10 | 10 per-phone profiles (26-28 keys: 2 Park + 24 BLF) | tenants 105421 (Genesis), 106300, 105200 and others |
| Fanvil V67 2, Grandstream 1 | 3 | park-only | |

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

Method: render legacy as-is (profile), then legacy again with the device's key template supplied **in memory**
(`fspbx_get_assigned_device_key_template_rows` / `..._has_...` pre-defined via `auto_prepend_file` in a /tmp copy of the harness; the
key rows are the profile rows converted by rule, per vendor). Compared with `compare.sh`. A negative control (a changed park number) did differ,
so the override is live. Native check: `getEffectiveDeviceKeysByArea` called through reflection with the profile relation replaced by the same in-memory template.

| Device (tenant, MAC) | File | KT rows | Legacy keys vs profile render |
|---|---|---|---|
| Polycom 6.x_old VVX250 (100202, 482567b275ec) | `<mac>.cfg` | `park` x2, no line row | **169/169 identical** |
| same | | + `line` row at index 1 | differs: parks shift to resourceList 2/3, a line entry is added |
| same | | `blf` typed park | differs: `automata` becomes `normal` |
| Yealink T46U (100171, 44dbd27aaa06) | `y000000000108.cfg` | `park` x2 | differs: `linekey.N.type` 16 becomes 10, `pickup_value` goes, `extension` appears |
| same | | `blf` rows valued `park+*5901/2` | **1046/1046 identical** |
| Yealink T33G (105168, 44dbd275aa81) | `y000000000124.cfg` | `blf` rows | **1091/1091 identical** (`park` rows differ 16 to 10 as above) |
| Yealink T44U (102905, 44dbd254d878) | `y000000000066.cfg` | `blf` rows + speed_dial 400 (key 8), blank key 4 dropped | **956/956 identical** |
| Fanvil X303 (100228, 0c383e69668a) | `<mac>.cfg` | `park` x2 | **1583/1583 identical** |
| same | | + `line` row | differs: Fkey1 becomes SIP1 line, parks shift down |
| same | | `blf` typed park | differs: `@1/c` becomes `@1/bc` |
| Fanvil X210 (105421, 0c383e7dffa8, 26 keys) | `<mac>.cfg` | side `park` x2, main `blf` x24 | **differs**: 88 added keys. Legacy maps both areas to category `line`, so the 24 BLFs overwrite/extend `dssSide` instead of `internal` |

Native effective keys with the in-memory template are correct for all four: Polycom `automata` park, Yealink 16/`blf`, X303 `park`, X210
side park + main BLF 1-24 (profile as-is lost the parks).

Rules that follow:
- Convert **only the vendor's own rows**, dropped blank rows (`type 0`/`3`), no `line` row (the line key already comes from device lines; adding it changes legacy).
- **Yealink: use `blf` rows with value `park+*59xx`, not `park`** (type 10 vs the profile's 16; a lamp/behaviour change that needs a bench probe if wanted).
- **Polycom and Fanvil X303: `park` rows** are byte-identical on legacy.
- **X210 BLF profiles: convert at cutover, not earlier**, because the legacy template cannot place `main` area keys. Native places them correctly.
- Identical-on-legacy conversions can therefore be done in bulk before any cutover and rolled back by clearing `device_key_template_uuid`.
- Existing key templates (16, all tenant-scoped) do not match any profile: they carry a `line` row, so they are not identical on legacy for Polycom/X303. Create new ones.

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

Order (follows the cutover order from the issue, bulk-safe first):
1. Fanvil X303 park-only and Polycom park-only (419 devices): bulk-create KTs, assign, verify legacy unchanged with the harness, in tenant batches. Needs no phone contact.
2. Yealink (99): blf-typed park KTs, same procedure; model files differ per model, so verify T46U, T44U, T33G, T41S, T48U/T73U once each.
3. X210 with 24-BLF profiles (3 in 105421 plus the others): create KT (side park + main BLF) and assign **at cutover** with the custom X210 template; compare native vs the old legacy render.
4. 23 Polycom devices with empty profiles: no key work; cutover only.

Open items: (a) `-initial` X303 devices carry a fixed Page key `*724` (#4358); (b) Yealink `park` (type 10) is the nicer long-term mapping but changes lamp behaviour, so it needs a bench probe; (c) the harness proof simulates the template rather than reading a stored one, so repeat it with the real row for the first device in each batch.
