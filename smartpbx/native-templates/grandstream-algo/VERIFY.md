# native templates: misc slice (patchbay#4352, wave 2)

Seven custom native (Blade) templates, loaded into `provisioning_templates` later by the coordinator (pilot wave). Nothing was written to the database or to /var/www/fspbx. All evidence comes from the shared harness (`~pbx/native-render`, read-only): `render.sh --legacy` vs `render.sh --native --template-file`, then `compare.sh`. Secrets are masked by compare.sh; none are in this repo.

## How the templates were built
Each is a mechanical port of the legacy Smarty file (Smarty tags converted to Blade, same P-values/keys), not a clone of a stock native template, because stock native covers 69 of 345-458 HT80x P-values. Exceptions: `algo-8301` is the stock native 8301 patched to the legacy values. Each file starts with a prelude that rebuilds legacy `$account.N.*` semantics from the native `$lines`: default `register_expires` 120 (minutes for Grandstream), default transport `tcp`, default `sip_port` 5060/506N, `outbound_proxy` alias. XML templates use `{{ }}` (valid XML; `&` becomes `&amp;` and the phone decodes it); the Algo text template uses `{!! !!}` so secrets render raw.

## Provisioning URL and rollback rule
| Models | URL comes from | Rendered line (legacy = native today) |
|---|---|---|
| HT801/HT802/HT818/HT841/HT881/GXV3350 | `grandstream_config_server_path` (default > domain > device), `P237`; `none` renders empty; unset falls back to `provision_base_url` | `<P237>https://provision.smartpbx.io/app/provision` |
| Fanvil V67 | `fanvil_provision_url`, `FlashServerIP` | `<FlashServerIP>https://provision.smartpbx.io/app/provision/` |
| Algo 8180/8301 | legacy has NO url setting (tenant domain + `app/provision` over http). New optional setting `algo_provision_url`; unset = legacy bytes | `prov.server.static = <tenant>`, `prov.download.cfgpath = app/provision`, `prov.download.method = http`, `prov.server.method = static` |

Cutover (Grandstream/Fanvil): set the device's URL setting to the `/prov/` URL and set `device_template_uuid`; the phone picks the new URL from the first config it pulls (Grandstream `P237`, Fanvil `FlashServerIP` are persisted by the phone). Cutover (Algo): set device setting `algo_provision_url=https://provision.smartpbx.io/prov` (template then renders host, path `prov`, method `https`, and pushes `prov.auth.*`; the default renders them blank like legacy). Rollback: set the URL setting back to the legacy URL (Algo: delete `algo_provision_url`) while the template is still assigned, wait for one pull, then clear `device_template_uuid`. The /prov/ request for Grandstream needs `cfg<mac>.xml`, for Algo `algom<mac>.conf`, for Fanvil `<mac>.cfg`: all resolve in the native flavor switch. Note: a Grandstream phone only reaches native after its URL changes; the first native render must be pulled through legacy or the URL changed on the phone (web UI / option 66). Not proven live (needs a pilot phone): that the phone persists the new URL and re-pulls from it. The cutover values themselves (`algo_provision_url` branch) were not rendered because a setting cannot be created offline.

## Proxy port (HT80x)
Legacy semantics kept byte for byte: `P47 = <server_address>:<sip_port>`, `P48 = outbound_proxy_primary` verbatim. Stock native appended `:<sip_port>` to P48 (doubling `portal.smartpbx.io:36000:36000` when the line stores the port, and moving a bare-proxy phone to another profile). Evidence: ec74d76026e8 (105488, proxy stored with port) P48 `portal.smartpbx.io:36000` legacy = native; ec74d72e92f2 (100250, bare proxy) P48 `portal.smartpbx.io` legacy = native; both P47 `<tenant>:36000`.

## Verification table (legacy vs native-with-our-file, harness)
Fleet reality: there are only 3 HT80x, 1 HT818, 1 GXV3350, 2 Algo 8180, 7 Algo 8301, 2 V67 devices, so "3 more across tenants" is capped by population; every device in the fleet was rendered.
| Template | Devices (tenant mac) | Result |
|---|---|---|
| ht80x | 105488 ec74d76026e8, 102905 ec74d757e3db, 100250 ec74d72e92f2 | 348/348 and 461/461 keys identical; 102905: 0 value diffs |
| ht818 | 105291 ec74d7620c32 | 414/414 identical |
| gxv3350 | 100203 c074ad1aad48 | 1759/1760 identical (+wrapper tags, see D2) |
| algo-8180 | 105220 0022ee22c6a6, 100165 0022ee196485 | 210/210 identical |
| algo-8301 | 105229 c3d3, 100228 a85f, 100234 9f49, 104278 7079, 104670 9f80, 107562 ee64 | 688/689 identical, 1 intended diff (D3) + 26 harmless native-only keys (D4) |
| fanvil-v67 | 104211 0c383e60ab89, 105389 0c383e616eb7 | 1518/1518 identical |
| ht881 (also HT841) | HT841 023631 ec74d721e4ac, 107638 ec74d7bf352e, 096978 ec74d7bf39c0 vs legacy ht841; HT881 105421, 107562, 107518 native only | HT841: all keys identical except 2 added URL keys (D5); 096978 differs (D6) |
Algo 8301 devices carry legacy template name `algo/8103`, whose file is misnamed `algom{}.conf`, so real legacy requests 404. Their legacy baseline was rendered with a /tmp copy of the harness whose rewrite maps `algom<mac>.conf` to that file (same method as wave 1).

## Remaining differences
- D1 HT80x 102905: legacy output is not well-formed XML (its provision HTTP password contains `&`); native renders `&amp;` (valid; proven by count: legacy P1361 raw `&` 1, escaped 0; native raw 0, escaped 1). Intended. The phone's XML parser decodes `&amp;`; if a bench phone shows auth failure, switch P1360/P1361 to `{!! !!}` (probe).
- D2 GXV3350/HT80x: compare shows `gs_provision@version`, `config@version`, `mac` only because the legacy file is not well-formed XML for those tenants (compare falls back to a tolerant scan). Harmless.
- D3 Algo 8301 `admin.timezone`: legacy file glues `admin.timezone = America/New_York` to the next line (missing newline); native has a separate `admin.voice = en`. Intended fix.
- D4 Algo 8301: 26 native-only keys (`audio.ring.tone6-9`, `mcast.polycom.tone30-50`): newer-firmware defaults from the stock template. Harmless.
- D5 HT841/HT881: `P212=2` and `P237` added (legacy ht841 file has no URL, so the phone could not follow a URL change). Value equals the current default URL. Intended.
- D6 HT841 096978 ec74d7bf39c0: legacy renders port-4 values (P4064, P4094, P4124, P4184) empty and P4599=0 while native renders them (line 4 exists). The legacy file (edited 2026-08-06) should render them: likely a stale Smarty compiled cache for the legacy file. Native is the intended result. Needs a probe: confirm the phone has the port-4 SIP account after cutover.
- D7 Fanvil V67 `<line index="">`: legacy emits an empty index (`device_key_id` does not exist on device lines); ported as-is so output is identical. Needs bench probe whether the phone accepts it (it does today).

## Needs bench probe (open)
1. HT881 ports 5-8: P-value numbering is extrapolated (+1 per port from ports 1-4). Ports 5-8 are OFF unless device setting `grandstream_ht881_ports_5_8=1`. Devices 105421 (7 lines) and 107562 (5 lines) need this; ports 1-4 render (4 account values each). The HT881 has 8 FXO + 1 FXS ports (Grandstream manual); confirm with Grandstream's HT881 P-value template.
2. GXV3350 key loops (line keys, expansion modules) are ported but untested: the only device has no keys. Probe with a device that has keys.
3. Cutover phone behaviour: P237/FlashServerIP/Algo `prov.*` persistence, digest auth against /prov/.
4. Native transport: native passes only a normalised transport; every line in the fleet is `tcp`, so the prelude maps an unknown value to `tcp` (legacy default); a line with empty transport would render `tcp`.

## Devices already 404 on legacy (read-only)
`device_provisioned_date` on many of these was stamped on 2026-11-23 20:2x-20:4x UTC by wave-1 curls, so those dates are not real pulls (marked *).
| Tenant | MAC | Template name | Last provisioned |
|---|---|---|---|
| 105421 | ec74d766203e | grandstream/ht881 (dir missing) | stamped 2026-11-23* |
| 107562 | ec74d77a8ca4 | HT881 | stamped 2026-11-23* |
| 107518 | ec74d7043eba | HT841 (no such legacy dir) | stamped 2026-11-23* |
| 100228 | 0022ee19a85f | algo/8103 | stamped 2026-11-23* |
| 100234 | 0022ee199f49 | algo/8103 | 2025-02-24 |
| 104278 | 0022ee197079 | algo/8103 | 2025-05-09 |
| 104670 | 0022ee199f80 | algo/8103 | 2025-02-15 |
| 105229 | 0022ee19c3d3 | algo/8103 | stamped 2026-11-23* |
| 105229 | 0022ee19c3d2 | algo/8103 (0 lines) | 2025-07-31 |
| 107562 | 0022ee19ee64 | algo/8103 | 2026-05-06 |
Of the 7 Algo 8103-named devices, 6 have lines (c3d2 has none); all are 8301s. Algo 8301 devices 100234/104278/104670 have not pulled since 2025: check they are still deployed before cutting over.
Deprecated device profile: both V67 (104211, 105389) use profile "2 Park Keys"; the legacy V67 file ignores profiles (static keys) and so does this port, so legacy = native. Nothing to fix here.

## Recommendations for the rest of the family
- Cisco SPA112/122/191 (3 devices: 100044, 100023, 100151): no native template exists and none is planned upstream; recommend STAY LEGACY, retire when legacy goes (replace with HT801/HT802 where analog is still needed).
- Polycom 5.x_old (3 devices: 100137 64167f3b55fb, 100151 64167f432d72 and 4825670697cf): firmware-5 file format; native polycom/VVX is 6.x+. Recommend RETIRE/UPGRADE the phones (64167f432d72 last pulled 2025-05-23); do not template.
