# Updates

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Updates

- Whenever changing the included FusionPBX application in the separate `public/` repository, also increment its release version in `public/version.json`. This is the version used by its release workflow and the installer.
- If an update version has shipped, do not keep editing it for new behavior. Create the next update.
- An update should be best-effort when touching host services. App updates should not fail just because a FreeSWITCH module cannot be compiled.
- Update classes can expose `getSupervisorProgramsToRestart()` for `UpdateApp` to restart long-running processes after code, templates, assets, and ownership are updated. Do not include the opt-in `fs-esl-listener-call-webhooks` program in unconditional restarts because its Supervisor config intentionally uses `autostart=false`; Horizon is already recycled with `horizon:terminate`.
- When updates change generated dialplan XML or dialplan details, clear only the affected dialplan cache contexts.
- Keep update console output truthful. Do not claim a module, file, or cache was refreshed unless it actually was.
- When replacing untracked legacy files under `public/app/...`, follow the existing update pattern: download from the canonical GitHub raw URL, ensure the destination directory exists, reject empty downloads, and register the update step in `UpdateApp.php`.

## Included Legacy Files

- Legacy admin files are included in the `/public` directory. This directory is not tracked. All updates must be made via an update file.
