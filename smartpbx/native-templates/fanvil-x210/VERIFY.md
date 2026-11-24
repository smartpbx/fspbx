# Fanvil X210 native template: verification (patchbay#4352, slice x210)

Offline only. Nothing was loaded into `provisioning_templates`, no `device_template_uuid` was set, no phone was
contacted. Evidence comes from the shared harness (`~pbx/native-render/render.sh`, read-only session, native render in
memory with `--template-file`) on fspbx01, 2026-11-23. Passwords are never shown (compare.sh masks them).

## 1. What the template is

`fanvil-x210.tpl` is stock `fanvil / XU Series` v1.1.3 (`3b9563d1-b4bf-41a7-a4ce-ddf51f20b60c`) with the key block
rewritten for the X210 and a few legacy-parity defaults (full list in `fanvil-x210.meta.yaml`). Serves `X210` and
`X210-V2` (`device_template = fanvil/x210`). Load it as a custom template named "Fanvil X210" in the pilot wave.

## 2. Provisioning URL rule and rollback

The phone stores its server URL from `sysConf/ap/FlashServerIP`, delivered inside the config itself, so the config must
keep re-asserting the `/prov/` URL or the phone drifts back. The template renders it from the same setting the legacy
`x210/{$mac}.cfg` reads:

- legacy: `{if isset($fanvil_provision_url)}{$fanvil_provision_url}{else}https://{$domain_name}/app/provision{/if}`
- native template: `{{ $setting('fanvil_provision_url', 'https://' . ($domain_name ?? '') . '/app/provision') }}` (same fallback as legacy; stock used `/prov/`, which would move a phone as soon as a template was assigned)

Native resolves settings default -> domain -> device (`ProvisioningController::getProvisionSettings`, device last wins),
so a device-level `fanvil_provision_url` decides what that one phone is told. Rendered today (no device override, native
template on the 22V X210), the line is identical to legacy, which is the safe state:

```
legacy : <FlashServerIP>https://provision.smartpbx.io/app/provision/</FlashServerIP>
native : <FlashServerIP>https://provision.smartpbx.io/app/provision/</FlashServerIP>
```

- Cutover per device: set device setting `fanvil_provision_url` = `https://provision.smartpbx.io/prov/` AND set
  `device_template_uuid` to this template. The phone adopts the new URL on its next scheduled pull
  (first switch: it fetches the config once from wherever it currently points, then every native render keeps `/prov/`).
  Do not change the default or domain value: 263 X303s share the key and have no native template.
- Rollback per device: set that device's `fanvil_provision_url` back to `https://provision.smartpbx.io/app/provision/`
  (or delete the device row, the default is the legacy URL) while the template is still assigned, wait for one pull so
  the phone is told the legacy URL by the native render, then clear `device_template_uuid` (re-attach the old key
  template or profile). Clearing the template first would leave the phone pointing at `/prov/` with no template.
- Not provable offline (bench probe P4): that the X210 really adopts a changed `FlashServerIP` on its scheduled pull
  without a reboot. DHCP option 66 (RB5009 hands `.../app/provision`) and Fanvil RPS can pull a factory-reset phone back
  to legacy; `DHCPOption=66` and `PNPEnable=1` are rendered the same as legacy.

Order at cutover: assigning the template alone changes nothing about the URL, the phone keeps being told the legacy URL.
Only the device-level `fanvil_provision_url` moves it.

## 3. Keys

Native X210 layout: `dssSide index=1` with 10 `Fkey` (blank slots Type 0, empty Value and Title), then
`internal index=1..3` with 32 `Fkey` each, `FuncKeyPageNum=3`, `SideKeyPageNum=1`. 106 `Fkey` per phone. Main-area
key n goes to page ceil(n/32), slot ((n-1)%32)+1, the same split legacy used.

Native renumbers main-area keys 1..N in key order (`postProcessEffectiveKeys` sets `id = position`), and drops a BLF
that targets the phone's own extension (`drop_self_extension_keys`). Legacy kept the stored index. A key template with
gaps in `key_index`, or a BLF on the phone's own extension, therefore lands on a different slot than on legacy. The 22V
template (indexes 1-4, no self key) is not affected. This is controller behaviour, not fixable in a template; check any
key template with gaps before it is cut over.

BLF subtype: legacy writes `bc` for every key-template BLF (`fspbx_vendor_key_type_code`); stock native writes `ba`.
Template: `bc` for call-flow keys (value `flow<N>`, `flow30@1/bc` is verified lit on a physical X210) and for other BLFs
by default, so a key-template phone renders exactly what legacy rendered. `fanvil_blf_subtype=ba` flips ordinary BLFs
per device or tenant if the probe shows a difference.

## 4. Verification table (legacy vs native-with-this-file)

`compare.sh --ignore-empty`. "same" = leaf settings equal. Legacy-only is the ~2,360 factory-default dump the legacy
file re-asserts (about 4,000 tags per pull), native-only is mostly blank key slots.

| Device | Tenant | Keys source | same | differ | legacy-only | native-only | Key result |
|---|---|---|---|---|---|---|---|
| 0c383e7dff3e (wave 1) | 100138 | key template "22V Reception X210" | 193 | 10 | 2362 | 107 | **identical** (line, Park 1, Park 2, Day/Night `flow30@1/bc`) |
| 0c383e7dffb2 | 100138 | key template | 193 | 10 | 2362 | 107 | **identical** |
| 0c383e7dffa8 (wave 1) | 105421 | profile | 233 | 12 | 2366 | 85 | profile keys differ (section 5) |
| 0c383e7dffb0 | 105421 | profile | 239 | 12 | 2366 | 83 | profile keys differ |
| 0c383e7e048c | 105421 | profile | 233 | 12 | 2366 | 85 | profile keys differ |
| 0c383e7dffa6 | 105421 | profile | 181 | 12 | 2366 | 113 | profile keys differ, raw foreign types |
| 0c383e66fe2d | 105181 | profile | 180 | 12 | 2366 | 113 | profile keys differ |
| 0c383e6b1889 | 105200 | profile | 181 | 12 | 2366 | 113 | profile keys differ |
| 0c383e6b1c25 | 105209 | profile | 180 | 12 | 2366 | 113 | profile keys differ |
| 0c383e66fe21 | 105209 | profile | 180 | 12 | 2366 | 113 | profile keys differ |
| 0c383e7dffae | 105481 | profile | 181 | 12 | 2366 | 113 | profile keys differ |
| 0c383e7dffb8 | 106300 | profile | 181 | 12 | 2366 | 113 | profile keys differ |

All 12 enabled X210s render, the output is well-formed XML, passwords equal on both sides. Seven tenants. Only the two
22V phones are on a key template, so those are the only ones that can verify key-clean today; the other ten need their
profiles converted first (separate slice).

### Remaining diffs on the 22V pair (the whole list), with reason

| Setting | Legacy | Native (this file) | Class |
|---|---|---|---|
| `mm/capability/AudioCodecSets` | `PCMU,PCMA,G726-32,G729,G723,iLBC,AMR,opus,G722` | `OPUS,PCMU,PCMA,G726-16,G726-24,G726-32,G726-40,G729,G723,iLBC,G722` | harmless (capability list; the per-line map below decides the offer) |
| `sip/line[1]/VoiceCodecMap` | `opus,PCMU,PCMA,G726-32,G729,G723,iLBC,AMR,G722,AMR-WB` | `OPUS,PCMU,PCMA,G726-32,G729,G723,iLBC,G722` | harmless (AMR and AMR-WB dropped, FreeSWITCH offers neither; opus still first) |
| `phone/EmergencyCall` | 110 (stock Chinese default) | 911 | intended |
| `net/SecondaryDNS` | 202.96.134.133 | 208.67.222.222 | harmless (`DHCPAutoDNS=1`) |
| `net/VendorClassID`, `Vendor6ClassID`, `wifi/VendorClassID` | Fanvil X210 | Fanvil XU | harmless (`UseVendorClassID=0`) |
| `sip/line[1]/BackupPort` / `BackupTTL` / `BackupTransport` | 5060 / 3600 / 0 | 36000 / 120 / 1 | harmless (BackupAddr empty, native follows the line's own port, expiry and transport) |
| `web/account[2]` guest (Level 5) | present | absent | harmless (phones keep an existing guest account; nothing removes it) |
| native-only `LocalDomain`, `SipName`, `DNSMode`, `DNSSRV`, `SoftkeyMode` | | present | harmless (native adds them) |
| about 2,360 factory-default tags (tr069, vpn, hotspot, cti, mcast, log style, Bluetooth, theme colours...) | re-asserted every pull | not written | harmless unless drift appears (then add specific tags); needs a bench look after cutover |
| blank key slots (`Type 0`) | not written | written | intended (clears stale keys) |

Already equal to legacy in this template (differences in stock, fixed here, not in the table because they now match):
`SIPPort` 36000, `TimeZone` blank, `RTPInitialPort`/`RTPPortQuantity` 16384, `RTPKeepAlive` 0, `SoftKeyExitStyle` 1,
`FuncKeyPageNum` 3, 10 side keys, BLF subtype `bc`.

## 5. Devices still on deprecated profiles (reported, not fixed)

Ten X210s use a device profile. Native maps every profile key to area `main` (the profile `line` category does not
become `dssSide`), so on these phones: legacy `dssSide` 1-2 (Park 1, Park 2) render as blank side keys, and the same
Parks appear on `internal` page 1 slots 1-2 on six of them (105181, 105200, 105209 x2, 105481, 106300; 105421 x3 lose
them entirely). 0c383e7dffa6 carries foreign type codes in its profile (renders `park+*5901@1/automata` and
`park+*5902@1/16`), a data problem in the profile. Do not cut these over until their profiles are converted to key
templates and re-rendered clean with this file.

**Guard (cutover rule):** the ten profile-keyed X210s must have their profile converted to a key template at cutover,
before `device_template_uuid` is set and before any `/prov/` URL is applied (conversion tracked in fspbx#12). Assigning
this template to a profile phone would otherwise blank its side keys and move or drop its Park keys on the next pull.

## 6. X4U on stock "XU Series": no custom template needed

Rendered stock `XU Series` (in memory) against legacy for Clayton's test X4U `0c383e73481c` (ewee, key template) and
`0c383e66729a` (100250, no keys). The other four X4U are disabled (tenant 121307) and their legacy render is nearly empty
(3 leaf settings), so only two tenants can be compared for this model.

| Item | Legacy | Native stock | Class | How to align |
|---|---|---|---|---|
| `SIPPort` | 36000 | 5060 | needs a setting | device setting `sip_port` = 36000 (stock reads it). Matters only for site firewall pinholes. |
| BLF subtype | `bc` | `ba` | **bench probe P1** | stock has no switch; keep `ba` unless the lamp behaves differently |
| `phone/date/TimeZone` | blank | `0` | **bench probe P2** | device setting `fanvil_time_zone` blank, or custom template; the phone is on `0` already |
| `AudioCodecSets` / `VoiceCodecMap` | no OPUS, no G722 first | OPUS first, G722 added | harmless / intended | HD on the X4U is acceptable (the X210 already offers opus first) |
| `qos/EnablePVID` | 1 (254) | 0 | harmless unless a PC is on the X4U PC port | `fanvil_pc_port_vlan` per device |
| `EmergencyCall`, DNS, vendor class, Backup block | as for the X210 | | intended / harmless | none |
| Keys | legacy writes all 13 BLFs of the ewee template into `dssSide` 1-13 (colliding with the line and Park keys on 1-3) | native puts the side area in `dssSide` and main on `internal` pages of 6 | intended (legacy output is the collision bug) | none; the phone already runs this layout |

Conclusion: stock covers the X4U. Add a custom X4U template only if probe P1 or P2 shows a real difference; it would be
this file with the key block reverted to stock (6 per page, 5 side keys). Provisioning URL: set the per-device
`fanvil_provision_url` as in section 2.

## 7. Bench probes (nothing here was probed, no phone was touched)

Run on Clayton's test X4U `0c383e73481c` (tenant ewee, ext 1014) unless noted.

- **P1, BLF subtype `ba` vs `bc`** for ordinary extension BLFs: pickup and lamp behaviour. This template emits `bc`
  (legacy on key-template phones). Probe on the X4U with a few BLFs on each subtype. Call-flow keys stay `bc` regardless
  (verified on an X210).
- **P2, `TimeZone` blank vs `0`**: with `TimeZoneName=UTC-5`, does a literal `0` put the clock on UTC? The X210 template
  sends blank, which is what the X210s run now. Read the phone's current value first.
- **P3, X210 page model** on the 22V pair at cutover: 3 pages of 32, 10 side keys. The rendered keys are identical to
  legacy, so this confirms display only.
- **P4, URL adoption**: a device-level `fanvil_provision_url` change is adopted on the next scheduled pull without a
  reboot, and rollback (section 2) restores the legacy URL. First on the X4U (already native).
- **P5, common config**: `DownloadCommonConf=1` makes the phone request a common cfg file. Does `/prov/` 404 it, and does
  the phone mind? Same value as legacy, but legacy serves it.
- Open, needs Clayton: any customer whose firewall relies on SIP port 36000 or an RTP allow-list (template matches
  legacy for both, so no action unless a profile or setting changes them).

## 8. Reproduce

Copy the file to fspbx01 under `/tmp/p4352-x210.*`, then per device:

```
render.sh --domain <d> --mac <m> --legacy --ua "Fanvil X210-V2 2.12.23.3 <m>" > legacy.cfg
render.sh --domain <d> --mac <m> --native --template-file /tmp/p4352-x210.tpl --ua "Fanvil X210-V2 2.12.23.3 <m>" > native.cfg
compare.sh legacy.cfg native.cfg --ignore-empty
```

## DHCPOption from a setting (patchbay#4352 wave 3)

`sysConf/ap/opt/DHCPOption` now reads `fanvil_dhcp_option` (default 66 when unset or empty, which is the old
hard-coded value). It exists so a phone on an option-66 site can be moved to `/prov/` per device; see
`../fanvil-xu/VERIFY.md`. Render with no setting: unchanged, 66.
