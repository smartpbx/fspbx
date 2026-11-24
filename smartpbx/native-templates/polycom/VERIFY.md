# Polycom native templates: verification (patchbay#4352, wave 2)

Templates: `VVX-ewee.tpl` (VVX 150/250/311/350/411/450 and the 16 `polycom/6.x` devices) and `Trio8800-ewee.tpl` (Trio 8800).
Both are clones of the stock native templates (`polycom/VVX` 1.0.9 and `polycom/Trio 8800` 1.0.0; DB content equals the files on disk) and are
generated from them by a fixed set of edits (list below). Nothing was written to the DB or to `/var/www/fspbx`; templates were passed to the shared
harness with `--template-file`.

## Method
Harness `~pbx/native-render/` on fspbx01 (read-only). Legacy: `render.sh --legacy --file <mac>.cfg`. Native: `render.sh --native --template-file <tpl> --file phone<mac>.cfg`
(6.x devices: master `<mac>.cfg` on both sides, see below). `compare.sh legacy native` per device; secrets are masked by the tool.
Legacy renders contain bare `&` in some passwords (invalid XML); for comparison only, bare `&` in the legacy output was escaped to `&amp;` first.
Native escapes `&` as `&amp;` which is correct XML (the parser decodes it), and compare shows no password differences on the devices with `&` in a password.
28 devices, 21 tenants-or-fewer per model as the fleet allows, all rendered without errors (exit 0).

## What was changed from stock (and why)
| Area | Change |
|---|---|
| DND / Forward | removed `feature.doNotDisturb.enable="0"` and `feature.forward.enable="0"` (phones keep their defaults, as legacy) |
| Intercom auto-answer | added `voIpProt.SIP.alertInfo.2.class="autoAnswer"` / `value="answer"`; removed stock `use486forReject` |
| Dial map | `dialplan.digitmap` and `.timeOut` come only from `polycom_digitmap(_timeout)`, empty when the setting is disabled (it is, so empty, as legacy) |
| Registrar | `server.1.address` = tenant domain (`server_address_primary` or `server_address`); `reg.N.outboundProxy.address/port/transport` restored when the line has an outbound proxy (portal host, port 36000) |
| Register expiry | line value, else `line_register_expires` (120), never 3600 |
| Provisioning URL | see next section |
| Firmware | `device.prov.upgradeServer` from `polycom_firmware_url` (6.4.7 URL) with `.set="1"` |
| Legacy parity | `nat.keepalive.interval` 30, `softkey.1.precede` from `polycom_softkey_1_precede` (0), `feature.nonVolatileRingerVolume`, `exchange.server.url`, `feature.callRecording.enabled=0`, `homeScreen.intercom.enable=0`, `dir.local.readonly`, `call.callWaiting.ring`, `diags.pcap.*`, global `sec.srtp.*`, `reg.N.type="private"`, `thirdPartyName`, optional ring types, polling mode/period, video, nat.ip |
| Dropped stock additions legacy never sent | `use486forReject`, `powerSaving.idleTimeout.officeHours`, `se.stutterOnVoiceMail`, `sec.srtp.simplifiedBestEffort`, `msg.bypassInstantMessage`, hard-coded `up.Pagination.enabled` (now from `polycom_pagination_enabled`), hard-coded TLS cert-validation values (now only when the setting exists), `sec.tagSerialNo`, default analog headset option 2 |
| DST | `fixedDayEnable="1"` like legacy (see probes) |
| Keys (key-template devices) | `attendant.resourceList.<key id>` is written exactly as legacy (indexed by the key id from `$main_keys`; rows with an empty value are skipped. Verified only on the 9 key-template devices, whose ids are contiguous from 1 with no blank values; a key list with gaps is untested); `lineKey.reassignment.enabled="0"`, no `lineKey.N.category=BLF index=0` rows. Non-attendant key types render `key.N.function.prim` + `subPoint.prim` as legacy |

## Provisioning-URL rule and rollback
`device.prov.serverName` is rendered from `polycom_provision_url` (default -> domain -> device chain, the same setting legacy reads), with `.set="1"` on it and on every
`device.prov.*` (serverType, tagSerialNo, redun*, abortSWUpgrade*, upgradeServer, syslog; user and password only when `http_auth_*` are set, so empty credentials are never pushed). `serverType` comes from `polycom_server_type` (3).
If the setting is unset or empty, the template falls back to the legacy URL `https://provision.smartpbx.io/app/provision` (the fleet default), **never** to `provision_base_url`, which the controller always sets to `/prov/`.
The default today is the legacy URL, so **assigning the template alone changes nothing on the phone**, even if the default setting is later blanked.

Rendered, same device (107638 / 482567e6fbd3), from the harness:
- legacy: `device.prov.serverName="https://provision.smartpbx.io/app/provision"`, `serverType="3"`, `upgradeServer="http://firmware.smartpbx.io/app/polycom/resources/firmware/6.4.7"`
- native, template as committed (setting not overridden on the device; re-verified after the review fix on 3 devices, plus a run with the setting blanked in memory: still the legacy URL): `device.prov.serverName.set="1"` `device.prov.serverName="https://provision.smartpbx.io/app/provision"`, `serverType="3"`, same `upgradeServer`
- native, same template with the setting forced to the native URL in memory only: `device.prov.serverName.set="1"` `device.prov.serverName="https://provision.smartpbx.io/prov/"`

Cutover: set the device's `polycom_provision_url` (device setting) to `https://provision.smartpbx.io/prov/` and `device_template_uuid`. The phone writes the new URL (`.set="1"`) on its next pull.
Rollback: set the device's `polycom_provision_url` back to `https://provision.smartpbx.io/app/provision` while the template is still assigned. The next pull of `/prov/<mac>.cfg` writes the legacy URL;
after that one pull (confirm in nginx), clear `device_template_uuid`. Do not clear the template first: the phone would be left on `/prov/` with a 404.
Caveat for both directions (wave-1 finding, not changed by the template): `device.dhcp.bootSrvUseOpt` is CustomAndDefault, so DHCP option 66 wins over the stored URL on sites that hand it out;
those sites need option 66 changed too, and a reboot (check-sync does not re-pull `<mac>.cfg`).

## Results (legacy vs native with our file)
Counts are normalized keys. "diff" = same key, different value. The remaining differences are listed after the table. Details of every row are in the harness output on fspbx01 (deleted at the end).

| Model | Tenant | MAC | Source | same | diff | only-legacy | only-native |
|---|---|---|---|---|---|---|---|
| VVX250 | 100202 | 482567b275ec | wave1, profile | 149 | 0 | 20 | 12 |
| VVX250 | 107638 | 482567e6fbd3 | wave1, key template | 162 | 1 | 7 | 12 |
| VVX250 | 023631 | 482567b1d1ad | extra | 151 | 1 | 8 | 12 |
| VVX250 | 100156 | 64167f57bba3 | extra | 150 | 2 | 8 | 12 |
| VVX250 | 100178 | 482567b29890 | extra, profile | 152 | 0 | 18 | 12 |
| VVX250 | 105500 | 482567e68fff | extra, profile | 152 | 0 | 18 | 12 |
| VVX311 | 105416 | 64167f8ebd18 | wave1, key template | 162 | 1 | 7 | 12 |
| VVX311 | 105416 | 64167f8ebd07 | extra, key template | 182 | 1 | 7 | 12 |
| VVX311 | 100044 | 64167f43ff1f | extra, profile | 151 | 1 | 28 | 12 |
| VVX450 | 100202 | 482567bbdefe | wave1, key template (about 40 keys) | 314 | 1 | 9 | 12 |
| VVX450 | 100202 | 482567bc0bb4 | wave1, profile | 149 | 1 | 19 | 16 |
| VVX450 | 100044 | 4825672fc86c | extra, profile | 152 | 1 | 27 | 20 |
| VVX450 | 100164 | 482567b72b84 | extra, profile (about 100 keys) | 263 | 0 | 27 | 20 |
| VVX450 | 100165 | 482567b7b140 | extra, profile | 153 | 0 | 27 | 20 |
| VVX350 | 100157 | 482567b775cb | wave1 | 148 | 1 | 10 | 12 |
| VVX350 | 100157 | 482567b76180 | extra, profile | 148 | 1 | 10 | 12 |
| VVX350 | 100164 | 482567b8eef7 | extra, profile | 152 | 0 | 28 | 12 |
| VVX411 | 100044 | 482567069c83 | wave1, profile | 151 | 1 | 28 | 12 |
| VVX411 | 100158 | 4825672611d8 | extra | 151 | 1 | 8 | 12 |
| VVX150 | 100165 | 48256716aca2 | wave1 | 151 | 1 | 28 | 12 |
| VVX150 | 100156 | 64167f7f6fbb | extra, profile | 150 | 2 | 28 | 12 |
| VVX150 | 100162 | 482567b028a1 | extra | 148 | 1 | 10 | 12 |
| VVX150 | 100165 | 48256716d722 | extra, profile | 151 | 1 | 28 | 12 |
| Trio 8800 | 100201 | 48256712430c | wave1 (Trio template) | 144 | 1 | 15 | 15 |
| Trio 8800 | 105200 | 4825671246c9 | extra (Trio template) | 144 | 1 | 15 | 15 |
| 6.x master | 100180 | 482567b298c3, 482567b29214, 482567b29b4e | wave1 + 2 | 65 | 1 | 6 | 0 |

Fleet limits: VVX 411 has 2 devices (2 tenants), Trio 8800 has 2 devices (2 tenants), VVX 311 and 350 have 2 tenants each. These are all covered; there are no more tenants to add.
For devices with no recorded model (blank UA, 111 on 6.x_old), the model file (`<Model>-<mac>.cfg`) is the only model-dependent output (wallpapers); the template body does not depend on the model.

### Remaining differences (all devices; counts are about the same on every row)
| Difference | Verdict |
|---|---|
| Profile devices (227): all `attendant.resourceList.*` (park/BLF) rows legacy-only | **Not fixed here by instruction**: the native key mapper ignores Device Profile rows. Needs the profile-to-key-template migration slice before any profile device moves. Key-template devices (9) now match |
| `reg.1.server.2.*` blank rows (address empty, port, transport, expires) legacy-only; native has `server.2.subscribe.expires` | harmless (legacy writes an empty second server; native writes none unless a secondary exists) |
| `reg.1.outboundProxy.port/transport` legacy-only on lines with no outbound proxy | harmless (legacy writes them without an address) |
| `reg.1.type` legacy `""` vs native `private` (12 devices) | harmless (phone default is private) |
| `reg.1.label` legacy `421`, native `tbd` (7 devices) | needs decision: native exposes display name only, legacy used the line label; cosmetic. Needs a controller change to expose `label` |
| `reg.1.server.1.register`, `reg.N.callsPerLineKey`, `reg.N.srtp.offer/require`, `softkey.feature.recentCalls`, `ptt.volume`, `device.eulaAccepted` native-only; `call.callsPerLineKey`, `prov.polling.mode`/`timeRandomEnd` empty, `attendant.uri` empty legacy-only | harmless |
| `lineKey.reassignment.enabled="0"` native-only (key-template devices) | intended (this is what makes `resourceList.N` map to key N, as legacy does) |
| Trio: `reg.limit=3`, `.set` on sntp, firmware path from `poly_trio_8800_firmware`; legacy-only volume persist keys and headset keys | harmless for 5.9.6/7.2.8; Trio hardware has no headset keys |
| 6.x master: legacy `APP_FILE_PATH="sip.ld"`, `DECT_FILE_PATH`, `COREFILE_DIRECTORY`, empty `LOG_FILE_DIRECTORY`; native `LOG_FILE_DIRECTORY="logs"` | needs bench probe: native master has no `APP_FILE_PATH` unless `polycom_vvx_firmware_url` is set, so the phone keeps its firmware. That is the safer behaviour; logs go to `/prov/logs` |
| DST: both `fixedDayEnable="1"` with 3/9 and 11/2 | same as legacy. This is wrong in most years (fixed calendar day, not a Sunday); March 2027 switches on 9 Mar (a Tuesday). Bench probe: does `fixedDayEnable=0` with date 8 and dayOfWeek 1 give the second Sunday? Not changed without a bench; tracked in an issue before the pilot (see PR) |

## 6.x devices (16): live registration check (read-only)
Legacy `phone<mac>.cfg` is a PHP fatal for all of them, so they have no working legacy config to match; only the master compares. `fs_cli -x 'show registrations'` (805 rows at the time, 2026-11-23): **none of the 16 is registered**. There are no registrations at all for realm 100180 or `demo`.
Devices: tenant 100180: 482567b29214 (1001), 482567b2974c (1012), 482567b298c3 (1002), 482567b298d6 (1003), 482567b298eb (1004), 482567b298f1 (1011), 482567b29b21 (1005), 482567b29b25 (1006), 482567b29b3c (1008), 482567b29b35 (1007), 482567b29b4e (1009), 482567b29b5e (1010), 482567d623ed (1013); tenant demo (never provisioned): 112233aabbcc, 112233aabbcd, 112233aabbce (ext 101). Matching was by line user id and domain. Recommendation: disable them (patchbay#4366). That should land before this template is assigned to any of them: they have no working legacy config to roll back to, and the master drops legacy `APP_FILE_PATH`, `DECT_FILE_PATH`, `COREFILE_DIRECTORY`.

## Trio 8800: needs its own template?
Yes, a small one. The VVX template would add model-specific blocks the Trio ignores, but the stock Trio template differs in `reg.limit=3`, an explicit `.set` on sntp, a reduced volume and headset set, its firmware setting (`poly_trio_8800_firmware`) and no wallpaper file. `Trio8800-ewee.tpl` carries the same fixes. Not verified on hardware; 2 devices.

## Directory (patchbay#4359, Clayton's open question)
Not built. The master already sets `CONTACTS_DIRECTORY="directories"`, so the phone asks for `/prov/directories/<mac>-directory.xml`. Native serves only a file the phone uploaded (`PolycomProvisioningFileController`).
Adding the generated directory later takes: (1) an fspbx Polycom directory formatter (XML `<directory><item_list><item><ln/><fn/><ct/><sd/>…`) like the existing Yealink and Grandstream formatters, built from the tenant's extensions or the assigned phonebook (`$phonebooks` is already in the template vars); (2) the `PolycomProvisioningFileController` GET falling back to that formatter when no uploaded file exists (a PHP change, no template change); (3) optionally a per-tenant switch setting read by the template for `dir.local.readonly`. The templates need no edit for (1) and (2).

## Open bench probes
1. DST flag/date semantics (`fixedDayEnable`) before March 2027.
2. Master without `APP_FILE_PATH` on 6.x firmware (keeps firmware; confirm no reboot loop) and `<LOG_FILE_DIRECTORY>` upload to `/prov/logs`.
3. **Cutover gate** for any tenant whose line or provisioning password contains `& < > " '` (patchbay#4364): `&amp;` in a SIP or provisioning password on a real phone (legacy sends a bare `&`, invalid XML; native sends `&amp;`). Test with 482567b8eef7-style password on a spare VVX 250.
4. `reg.N.outboundProxy.*` with `server.1.address` = tenant domain on one portal-proxy line (restores legacy behaviour; pilot on 107638 and 105416).
5. Trio 8800 template on a real Trio (5.9.6 and 7.2.8).
6. Intercom auto-answer line (`alertInfo.2`) is copied from legacy; confirm on a phone that an inbound `*8` intercom answers.

## DST from a setting (patchbay#4373, wave 3)

`tcpIpApp.sntp.daylightSavings.fixedDayEnable` now reads `polycom_dst_fixed_day_enable`. Unset or empty renders 1,
the old hard-coded value. Read-only harness, 2026-11-24, VVX 450 `482567b7691d` (ewee): `<mac>.cfg` and
`phone<mac>.cfg` are byte-identical before and after (4269 and 6424 bytes).

Candidate fix: per Polycom UC Software, `fixedDayEnable=0` makes `date` the first day searched for `dayOfWeek`
(1 = Sunday). North America would be start month 3, date 8, then stop month 11, date 1. For the pilot this goes
into device settings only: `polycom_dst_fixed_day_enable=0`, `daylight_savings_start_day=8`,
`daylight_savings_stop_day=1`. It needs the bench read-back on the VVX 450 before any tenant or default change.
