# Fanvil XU (smartpbx): verification (patchbay#4352 wave 3)

## Why this template exists

On a site whose DHCP hands out option 66, a Fanvil with `DHCPOption=66` pulls the option-66 URL after every
reboot. It does so even when its config says `FlashServerIP=/prov/`. Stock "XU Series" hard-codes
`<DHCPOption>66</DHCPOption>`, so a device-level `fanvil_provision_url` alone cannot move a phone there. This copy of
stock reads `DHCPOption` from the `fanvil_dhcp_option` setting. Set it per device, never on DEFAULT.

The same one-line change is in `fanvil-x210.tpl`, `x303-2wire.tpl` and `fanvil-v67-smartpbx.tpl`.

## Offline proof (fspbx01 harness, read-only, 2026-11-24)

- Rendered for X4U `0c383e73481c` (ewee) with `--template-file`. The only difference from the resolved stock row
  (v1.1.3 in the DB) is the `<background><OtherPopup>0</OtherPopup></background>` block from fspbx#16, which the DB
  copy does not have yet. `DHCPOption` = 66 with no setting.
- x210, x303-2wire and v67 templates render (exit 0) with `DHCPOption` = 66 with no setting.
- Setting path: a probe copy reading `fanvil_provision_url` through the same expression rendered the device's
  `/prov/` value. So a device setting keyed by its subcategory reaches the expression.

## Bench result: the "disabled" value is `0` (X4U-V2 2.12.20, 2026-11-25, patchbay#4352 wave 3 step 2)

- Web UI `provision.htm`, select `SYS_OptionCustom_R` (`#dhcpOptionSelect`): `66` Option 66, `43` Option 43, `128`
  Custom Option, `0` Disabled. The page's `display()` treats `0` like 66 and 43, so the custom field stays disabled.
- In the UI: DHCP Option Disabled, Server Address `https://provision.smartpbx.io/prov/`, then Apply (no Autoprovision
  Now). The text export changed exactly two lines: `Flash Server IP` (`/app/provision/` to `/prov/`) and
  `DHCP Option :66` to `:0`.
- Test X4U `0c383e73481c` (ewee 1014) has this file loaded as custom row `2365b988-ffac-44b1-9260-4198ffde401f`
  (sha256 `c91f217a…`, byte-identical) and the device setting `fanvil_dhcp_option=0`. The harness render gives
  `<DHCPOption>0</DHCPOption>` and `FlashServerIP` `/prov/`.
- Two check-syncs of that phone alone, each a reboot: both pulled `/prov/0c383e73481c.cfg` (digest 401, then 200,
  15,640 bytes). There has been no `/app/provision/` request since. The export after the second reboot still reads
  `DHCP Option :0` and `/prov/`.

Cutover rule this implies: legacy does not read `fanvil_dhcp_option` and keeps pushing 66. A phone on an option-66 site
therefore has to reach `/prov/` once by another route before the setting does anything: its web UI (as above) or a
change to the site's option 66. Set `0` per device only, never on DEFAULT or a domain.

Web UI gotchas (fw 2.12.20): `GET /default_user_config.xml` hung the web server and the phone rebooted, so use
`/default_user_config.txt`. Playwright `selectOption`/`fill` time out on `provision.htm` because the inputs report not
visible. Set the values through the page JS, then click `DefaultSubmit`.
