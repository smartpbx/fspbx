# FreeSWITCH Files

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## FreeSWITCH Files

- FreeSWITCH BLF daemons, including voicemail, call flow, follow-me, and agent presence, belong under `resources/freeswitch_scripts/lua`.
- New event-driven BLF daemons should follow the established `resources/freeswitch_scripts/lua/vm_blf.lua` structure: a simple local logger, explicit event handlers, raw `freeswitch.EventConsumer` instances, nonblocking queue drains, and a short idle sleep. Do not copy unused state or known parsing defects merely for visual parity.
- Compact agent BLF actions use the purpose-built `resources/freeswitch_scripts/lua/agent_toggle.lua`; do not route `agent<id>` or `break<id>` through the legacy `app/agent_status` script used by `*22`, `*23`, and `*24`.
- `resources/autoload_configs/lua.conf.xml` is the canonical future-install startup configuration. New Lua startup services must be added there and patched into `/etc/freeswitch/autoload_configs/lua.conf.xml` for existing installs.
- Enabling a new Lua startup service requires a complete, administrator-controlled FreeSWITCH restart. Updates may patch `lua.conf.xml`, but must not start the script with `luarun`, reload `mod_lua`, or restart FreeSWITCH automatically; print the manual restart command instead.
- Other future-install FreeSWITCH autoload config belongs under `resources/autoload_configs`; other FreeSWITCH config remains under `public/app/switch/resources/conf/...`.
- Existing-install FreeSWITCH config usually needs to be written or patched under `/etc/freeswitch/...` from an update class.
- `timezones.conf.xml` is generated from the last POSIX rule in each host TZif file by `freeswitch:sync-timezones`; `app:update` re-runs the sync after Update201. Preserve the pre-sync backup, do not restart or reload FreeSWITCH from the update, and print `fs_cli -x reloadxml` for the administrator to run after rollout gates pass.
- Dialplan templates live under `public/app/dialplans/resources/switch/conf/dialplan`.
- For Lua called by dialplan XML, use paths that match the FreeSWITCH runtime script directory, such as `lua/call_block.lua`.


## Lua Expectations

- Keep Lua comments useful and close to non-obvious logic.
- Use a single `DEBUG_MODE` switch when adding debug logs.
- Debug logs should be plain English and explain the important runtime decision.
- Preserve call routing safety: avoid blocking calls from unsupported or malformed rules unless behavior is explicit.
- Use atomic SQL for counters where concurrent calls can update the same row.
