# No-DST provisioning audit (patchbay#4541 / #4542)

Audit date: 2026-11-27. Scope: the stock and SmartPBX-native Polycom, Yealink, and Fanvil templates in this repository.

| Vendor | Control used by templates | No-DST result | Finding |
| --- | --- | --- | --- |
| Fanvil | `fanvil_enable_dst` | `0` now renders `DSTType=0` and `DSTLocation=0` in X303-2Wire and V67; X210/XU already render `Enable_DST=0` | Fixed the two templates that forced `DSTType=1`. Keep the offset/name settings aligned with the tenant. |
| Yealink | `yealink_summer_time` | `0` is passed through by every stock and SmartPBX-native template | No forced-DST defect found. |
| Polycom / Poly | `daylight_savings_enabled` | false renders `tcpIpApp.sntp.daylightSavings.enable=0` | No forced-DST defect found. The separate recurrence-date and call-list work in patchbay#4533 owns Polycom template edits. |

For Alberta and Manitoba tenants, set the vendor-specific DST control off as well as the correct fixed offset/name. A correct server timezone does not change a phone that has DST forced locally.
