# Fax Jobs And Retention

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Fax Jobs And Retention

- New outbound fax state lives in `outbound_faxes`; do not use legacy `v_fax_queue` for current outbound fax alerts or status decisions.
- The old fax queue controller/page can remain legacy unless explicitly asked to rebuild or remove it. Do not add compatibility fields to `OutboundFax` just to support that page.
- `CheckFaxServiceStatus` should evaluate pending and failed outbound fax alerts from `outbound_faxes`.
- `DeleteOldFaxes` should clean old inbound/sent `v_fax_files`, associated `v_fax_logs`, old terminal `outbound_faxes`, logs/files associated with those outbound rows, and leftover legacy `v_fax_queue` records.
- Fax retention file cleanup scans the `fax` storage disk. Outside temp directories it only removes old `.tif` and `.pdf` files; temp directories have their own shorter orphan cleanup window.
- Physical fax file deletion is based on filesystem modified time, while database retention uses `fax_date` or `created_at`. Be careful when changing either side so old DB rows and physical files do not drift unexpectedly.

