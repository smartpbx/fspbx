# Call Block Runtime

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Call Block Runtime

- The database is the source of truth.
- Redis is a runtime cache for FreeSWITCH call matching, not the source of truth.
- Keep dialplan XML cache separate from call-block rule cache.
- Call-block rule cache keys are versioned per domain:
  - `call_block:version:{domain_uuid}`
  - `call_block:rules:v{version}:{domain_uuid}:{direction}:{scope}`
- Laravel should bump the per-domain version after create/update/toggle/delete.
- Lua should fail open if both Redis and the database are unavailable.
- Use `hiredis_raw default ...` for `mod_hiredis` commands from FreeSWITCH.

