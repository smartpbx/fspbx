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

## Open: the "disabled" value

The value that turns DHCP option use off is bench-probed on the X4U through the web UI (patchbay#4352 wave 3,
step 2): set it off in the UI, then read the exported config's `DHCPOption`. Record that value here. Until then,
put nothing in `fanvil_dhcp_option`.
