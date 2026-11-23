# Verification

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Verification

- Run `php -l` on changed PHP files when practical.
- Run `npm run build` after frontend changes when possible.
- In the sandbox, Vite may fail with permission errors while writing temporary config files. If that happens, rerun the same build outside the sandbox when approved.
- If a runtime issue depends on FreeSWITCH, verify with real `fs_cli` output or logs when available.

