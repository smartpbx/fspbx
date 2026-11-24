# Fax Jobs And Retention

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Fax Jobs And Retention

- New outbound fax state lives in `outbound_faxes`; do not use legacy `v_fax_queue` for current outbound fax alerts or status decisions.
- The old fax queue controller/page can remain legacy unless explicitly asked to rebuild or remove it. Do not add compatibility fields to `OutboundFax` just to support that page.
- `CheckFaxServiceStatus` should evaluate pending and failed outbound fax alerts from `outbound_faxes`.
- `DeleteOldFaxes` should clean old inbound/sent `v_fax_files`, associated `v_fax_logs`, old terminal `outbound_faxes`, logs/files associated with those outbound rows, and leftover legacy `v_fax_queue` records.
- Fax retention file cleanup scans the `fax` storage disk. Outside temp directories it only removes old `.tif` and `.pdf` files; temp directories have their own shorter orphan cleanup window.
- Physical fax file deletion is based on filesystem modified time, while database retention uses `fax_date` or `created_at`. Be careful when changing either side so old DB rows and physical files do not drift unexpectedly.
- A txfax originate must not outlive the fax. The inline extension is `sched_hangup:+<page budget>,txfax:<file>,hangup:NORMAL_CLEARING`, with `execute_on_fax_success` and `execute_on_fax_failure` set to `sched_hangup +2`, `loopback_bowout_on_execute=true`, and `fax_channel_budget` set to the same budget (90 s/page, 15 min floor, 6 h when the page count is unknown). Do not schedule that hangup with `execute_on_answer`: loopback bowout copies the remaining apps onto the gateway and hangs up the origination uuid, so an answer hook runs on the loopback and the gateway stays in txfax after `fax_success=1`. A file path that contains a comma or a quote stays a single `txfax` app.
- `CheckStuckFaxesJob` matches a live channel by `outbound_fax_uuid`, or by the row `call_uuid` against the channel uuid, `origination_uuid`, `originating_leg_uuid`, or `call_uuid`. Hang up every match and the row uuid. Do not mark the fax failed, and do not send the failure notice, when any matched channel has `fax_success=1`: mark it sent, write a success log even if that attempt already has a failure log, and notify. Do not redispatch while a matched channel is still up.
- `CheckLongFaxChannelsJob` runs every five minutes on each node from `app/Console/Kernel.php`. It stays local. Do not put it through the HA coordinator, because a node can see only its own channels. It mails the Patchbay alerts address when a txfax channel is older than its `fax_channel_budget` plus 15 minutes, or 6 hours when that variable is missing, then hangs the channel up. The same channel is alerted once and cleared when it is gone. A blank address skips the mail and still hangs up. Never send that alert through Home Assistant.

