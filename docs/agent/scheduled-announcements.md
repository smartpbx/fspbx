# Scheduled Announcements

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Scheduled Announcements

- Scheduled Announcements are broader than school bells. Keep naming and UI copy generic enough for bells, tones, and periodic announcements.
- The schedule is the main object. It owns one recording, one selected-extension list, optional start/end dates, events, and exclusions. Events are simple day/time rows; their presence means they run. Do not add event names or per-event enabled flags unless the product scope changes.
- Exclusions are simple date/comment rows on the schedule. A matching exclusion skips that schedule for that local date. Do not model alternate schedules or exception types unless explicitly requested.
- Use VueForm for all create/update modal controls and mirror existing Ring Group recording controls for recording select/play/edit/delete/upload behavior.
- Keep Scheduled Announcements controllers thin. Put validation and normalization in FormRequest classes, and keep schedule persistence/execution behavior in services.
- Do not use FreeSWITCH `sched_api` or pre-schedule FreeSWITCH jobs. Laravel finds due events and executes at fire time through `FreeswitchEslService`.
- Real-time behavior is "on time or missed, never late." Respect the configured fire window; events found after the window should be logged as missed rather than played late.
- Redundant-server execution is guarded by authoritative DNS active-node checks before claim and before ESL execution. If active status, DNS, local node IPs, or FreeSWITCH health are uncertain, fail closed and log a skipped/missed run rather than risking duplicates.
- Busy extension behavior is schedule-scoped. `skip` should check active FreeSWITCH channels and leave busy extensions alone; `force` may originate with auto-answer even if a phone is already on a call. `page.lua` is a useful reference for busy-extension detection, but scheduled playback should stay in the Laravel/ESL path.
- Playback should include a short silence lead-in before the recording to avoid clipped audio on auto-answer endpoints.

