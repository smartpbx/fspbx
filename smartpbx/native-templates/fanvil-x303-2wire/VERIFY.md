# Verify: Fanvil X303-2 Wire native template (patchbay#4352, slice x303)

Template: `x303-2wire.tpl` (Blade, flavor `mac.cfg`). Built by converting the legacy `fanvil/x303-2wire` Smarty file 1:1 (so the ~1,500 firmware-default leaves are pushed exactly as legacy pushes them) onto the XU Series Blade helpers. Nothing was written to the DB or to `/var/www/fspbx`; all renders used the shared harness (`~pbx/native-render/render.sh`, `compare.sh`) with the template supplied by `--template-file`.

## Ported (wave-1 table)
HandsetVol from `fanvil_handset_volume` (default 5; 105229 renders 7); SIPPort; PLC `ModifyPlcNmk` HomePlugAV; guest account (level 5); full DST rule block + `fanvil_location`/`fanvil_dst_minute_offset`; `TimeZone` left empty, `TimeZoneName` from `fanvil_time_zone_name`; `FuncKeyPageNum` 1, `SideKeyPageNum` 1, `MemoryKeytoBXfer` 2; `dssSide` side keys; `dssSoft` for category `programmable` main keys and `multi_purpose` keys; softkey lists (105284 `desktopsoftkey` = `history;contact;dss1;menu;`); SIP line block (4 lines, codecs, DTMF, UDPUpdateTTL, backup 5060/0/3600); log level ERROR + syslog settings; `BLFFailedColor` 0; `SaveProvisionInfo` 0; all `fanvil_*` variables the legacy file reads. Video, wifi and the 5 key pages of XU Series are dropped.
Notes: legacy has no `DssPage1` element; the Page key is an ordinary key (`*724@1/f`) in `dssSide`, so nothing named DssPage is emitted. `SIPPort` is what legacy renders: line 1's `sip_port` (36000 for almost all, 5060 on e.g. 104211), fallback 36000, override `fanvil_sip_port`. `RegisterAddr` uses the line's `server_address` first (legacy), then `server_address_primary` (stock order differs: 096978 would otherwise register at portal.smartpbx.io). `RegisterTTL` defaults to 120 when the line has none (legacy behaviour). `SubExpire` uses `fanvil_subscribe_expires` when that setting is set, and otherwise the same 120 fallback (patchbay#4456). Literal secrets from the legacy file are now variables: guest password (`fanvil_guest_password`), `fanvil_keylock_password`, `fanvil_cpe_password`; defaults are Fanvil factory values.

## Legacy hard-codes vs variables (review of fspbx#10)
Checked line by line against the legacy file `fanvil/x303-2wire/{$mac}.cfg` (legacy line numbers in brackets):
- `HandsetVol` reads `fanvil_handset_volume`, default 5 [1053: `{if isset($fanvil_handset_volume)}{$fanvil_handset_volume}{else}5{/if}`]. The template does the same (`$s('fanvil_handset_volume', '5')`). Only `-initial` hard-codes 5.
- DST: `DSTMinOffset` [1077, `fanvil_dst_minute_offset`, default 60] remains a variable. Version 1.0.2 intentionally differs from legacy by deriving `DSTType` from `fanvil_enable_dst` and rendering `DSTLocation=0` when DST is off (patchbay#4542); when enabled, `fanvil_location` still defaults to 2. `DSTRuleMode` 0 and the rule rows `DSTStartMon` 3 / Week 5 / Wday 0 / Hour 2 and `DSTEndMon` 10 / Week 5 / Wday 0 / Hour 2 [1074-1085] remain the legacy literals.
- `ModifyPlcNmk` is the literal `HomePlugAV` in legacy [1781]; no setting feeds it. Legacy never changes a phone's NMK after the first push, but native pushes `HomePlugAV` on every pull, so native WOULD reset a custom NMK. See gate 2.

## Cutover gates (hard, per tenant or device)
1. **105168, 7 devices (vendor "Fanvil"):** legacy renders `CallWaiting` 0 from the device profile "2 Park Keys" setting `fanvil_call_waiting`=0; native does not read profile settings and renders 1. Set `fanvil_call_waiting`=0 as a domain setting on 105168 BEFORE `device_template_uuid` is assigned to any of them, re-render, and confirm `CallWaiting` 0. Do not cut these devices over until then. (The underlying gap, profile settings ignored by native, belongs to the profile slice.)
2. **PLC sites (Genesis, Stewart, Capitol, Docks & Lifts, any tenant on PN8/PN24 switches):** do not cut over until each phone's PLC NMK has been read and confirmed `HomePlugAV`, or a per-device setting is added to the template (open bench probe 3).
3. **`-initial` devices (13):** do not cut over before bench probe 2 (duplicate Fkey indexes). The "equal" result below is partly an artefact of `compare.sh` keeping the last duplicate.

## Provisioning URL and rollback
`ap/FlashServerIP` is `fanvil_provision_url` (default, then domain, then device setting chain), the same setting legacy uses; when unset it falls back to `https://<domain>/app/provision` exactly as legacy. `FlashInterval` 1, `SaveProvisionInfo` 0, PNP/DHCP 66 unchanged. Fanvil stores `FlashServerIP` from the config it receives, so a config carrying the URL persists it (no Polycom `.set` equivalent needed).
Rendered evidence (105229 and 107562, native with this file): `<FlashServerIP>https://provision.smartpbx.io/app/provision/</FlashServerIP>`, `<FlashInterval>1</FlashInterval>`, `<SaveProvisionInfo>0</SaveProvisionInfo>`.
- Cutover per device: set `device_template_uuid`, then set the device's (or domain's) `fanvil_provision_url` = `https://provision.smartpbx.io/prov/`. Order matters: with only the template assigned, the phone keeps fetching `/app/provision/` (which ignores `device_template_uuid`) and nothing changes.
- Rollback: while the template is still assigned, set `fanvil_provision_url` back to `https://provision.smartpbx.io/app/provision/` (or delete the override), wait for one pull (`FlashInterval` 1), confirm the phone now fetches `/app/provision/`, then clear `device_template_uuid`. Record old URL + old `device_template_uuid` per device first.

## Results: legacy vs native-with-this-file (20 devices, 17 tenants, `compare.sh`)
Wave-1 devices: 105229 `0c383e696bfe`, 107562 `0c383e87c3b2`, 105200 `0c383e70c037`, 104575 `0c383e6963fc` (-initial). Additional: 105421 `…6c69b2`, 100138 `…6c772a`, 107632 `…6c6d26` (key templates); 096978 `…87c37e`; 105168 `…70c0dd`, `…70c0f3` (vendor "Fanvil"); 105284 `…695ffe` (softkey override); 105389 `…70be55`, 121307 `…655493` (-initial); 100250 `…696234`, 105357 `…6960cc` (no profile, no keys); 105209, 105181, 104211, 100592, 105286.
~1,580 keys per device compared. Per-device result: every device differs only in the rows below.

| Diff | Devices | Class | Reason |
|---|---|---|---|
| `net/SecondaryDNS` 202.96.134.133 -> 208.67.222.222 | all 20 | intended | Wave-1 recommendation (legacy value looks like a Fanvil default). One-line revert: default in the template. |
| `dssSide Fkey[3]` absent -> Type 0 (empty) | 13 profile-keyed devices that have keys (2 keys) | intended | Template sets every index it controls (3 minimum when any key exists). Clears a stale third key on the phone. Devices with no keys at all (100250, 105357) emit no side keys, same as legacy. |
| `call/port[1]/CallWaiting` 0 -> 1 | 105168 (7 devices) | needs fix before cutover | Their device profile "2 Park Keys" carries `fanvil_call_waiting`=0. Native does not read device-profile settings. Belongs to the profile slice; workaround: set `fanvil_call_waiting`=0 as a domain/device setting before cutover. |
| `-initial` (104575, 105389, 121307) | 13 devices | harmless / needs bench probe | Legacy emits fixed keys 1-3 and then the profile keys again with duplicate indexes 1 and 2; compare.sh collapses duplicates to the last, which equals native (Park 1 at key 1, Page `*724@1/f` at key 2, key 3 blank). How the phone resolves duplicate indexes was not probed. `CallWaiting` 1 / `HandsetVol` 5 hard-coded in -initial equal the defaults. Served by key template or profile keys until #4358 is answered. |

Key-template devices (43 across 5 tenants, three rendered in the table): identical keys, `SIP1` + park keys, zero key diffs.

## Report only: deprecated device profiles
190 devices use device profiles. Native reads every `v_device_profile_keys` row regardless of vendor (rows exist for Yealink 16, Grandstream `call park`, Snom `automata`, Fanvil `c`/`f`/`bb`) and orders only by `profile_key_line`, so which type wins is not guaranteed. All 20 rendered devices happened to pick the Fanvil row. The profile slice should confirm this is deterministic before cutting over profile devices. Device-profile settings are ignored (see CallWaiting).

## Open bench probes
1. Does the X303-2W read keys from `dssSide` (legacy has always sent it)? Confirms key placement.
2. Duplicate `Fkey` indexes on the -initial devices today (which wins); and whether padding key 3 with an empty key is wanted.
3. Custom PLC NMK on Genesis, Stewart, Capitol, Docks & Lifts sites: template pushes `HomePlugAV` only.
4. `EmergencyCall` default is legacy 110 (override `fanvil_emergency_number`; Canada likely wants 911): decision, not changed silently.

## DHCPOption from a setting (patchbay#4352 wave 3)

`sysConf/ap/opt/DHCPOption` now reads `fanvil_dhcp_option` (default 66 when unset or empty, which is the old
hard-coded value). It exists so a phone on an option-66 site can be moved to `/prov/` per device; see
`../fanvil-xu/VERIFY.md`. Render with no setting: unchanged, 66.
