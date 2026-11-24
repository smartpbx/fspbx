# On-demand FS PBX agent rules

This directory is not loaded on every agent call. `AGENTS.md` points here. Read the matching file before changing that area. The bullets are rules. Each section was moved verbatim from `AGENTS.md` (orca-hq#493), the same split as `rules/reference.md` in claymanner/omarchy_desktop_dotfiles pull request 108.

`AGENTS.md` keeps the short always-on rules: working style, the extension/voicemail domain match, permissions, and a guard line for each topic below (deploy, production, secrets, release). The guard is not a shorter replacement. The file in this directory is the full rule.

Do not put a new rule only here if ignoring it on an unrelated task can ship a bad update, leak a provisioning secret, or damage production. Put that rule in `AGENTS.md` and leave the procedure here.

| Original section | File | Bytes |
| --- | --- | ---: |
| Basic Queues | `docs/agent/basic-queues.md` | 2101 |
| Call Block Runtime | `docs/agent/call-block.md` | 899 |
| Devices And Provisioning | `docs/agent/devices-provisioning.md` | 5806 |
| Email Templates | `docs/agent/email-templates.md` | 2862 |
| Fax Jobs And Retention | `docs/agent/fax.md` | 1380 |
| FreeSWITCH Files, Lua Expectations | `docs/agent/freeswitch-files.md` | 2455 |
| FreeSWITCH Modules Page | `docs/agent/freeswitch-modules.md` | 1604 |
| Laravel And Vue Pages | `docs/agent/laravel-vue-pages.md` | 1752 |
| Localization | `docs/agent/localization.md` | 6742 |
| Logs Page | `docs/agent/logs.md` | 1109 |
| Music On Hold | `docs/agent/music-on-hold.md` | 3100 |
| Optional Modules | `docs/agent/optional-modules.md` | 625 |
| Phonebooks | `docs/agent/phonebooks.md` | 3407 |
| Scheduled Announcements | `docs/agent/scheduled-announcements.md` | 2329 |
| Scheduled-Job Coordination | `docs/agent/scheduled-jobs.md` | 8516 |
| Settings Pages | `docs/agent/settings.md` | 1817 |
| Updates, Included Legacy Files | `docs/agent/updates.md` | 1808 |
| Verification | `docs/agent/verification.md` | 693 |
