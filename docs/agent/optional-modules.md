# Optional Modules

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Optional Modules

- Not every server has optional modules under `Modules/`. Main-repo features should not assume an optional module exists unless the code has an explicit availability check and a safe fallback.
- Module-specific implementation notes belong in an `AGENTS.md` inside that module instead of the root guide.

