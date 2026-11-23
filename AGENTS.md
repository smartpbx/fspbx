# FS PBX Agent Guide

This repo is a Laravel, Vue/Inertia, VueForm, and FreeSWITCH application. Before changing behavior, read the nearby code and follow the existing page, service, route, and update patterns.

Long rules live in `docs/agent/` (index: `docs/agent/README.md`) so they are not loaded on every call. Read the matching file before editing that area. Those bullets are rules. Do not drop a rule; relocate it.

## Guards

- Pages: Inertia in `routes/web.php`; axios/data/actions in `routes/api.php`. Follow Devices and Conference. Rebuild legacy `/public/app/.../*.php` only when asked. Searchable tables use Spatie QueryBuilder when that is the local pattern. Pass `permissions` from the controller; read `props.permissions`; no new page keys on `HandleInertiaRequests` unless a shared layout needs them. Date filters follow CDR: `timezone` is an Inertia prop, not an API `filter`. New or revised VueForms use server-side validation only: no `rules` or client checks that block submit; Laravel errors go in the message bag; clear them before resubmit; disable VueForm validation on new forms. `docs/agent/laravel-vue-pages.md`.
- Email: do not restore `v_email_templates` or `LegacyEmailTemplateCleaner`. Legacy edits are not imported. Release notes must tell admins to save them before upgrade and recreate custom templates. `docs/agent/email-templates.md`.
- Updates: `public/` is not tracked. Ship legacy changes through an update class. Bump `public/version.json` when the included FusionPBX app changes. A shipped update is frozen; new behavior is the next update. Host-service steps are best-effort and must not fail the app update because a FreeSWITCH module will not compile. `getSupervisorProgramsToRestart()` must omit `fs-esl-listener-call-webhooks` (`autostart=false`). Horizon already restarts via `horizon:terminate`. Clear only the affected dialplan cache. Claim a refresh only if it happened. Replacing `public/app/...`: download the canonical GitHub raw URL, create the directory, reject an empty file, register it in `UpdateApp.php`. `docs/agent/updates.md`.
- Provisioning previews are credential-bearing. Use the live render path, but do not touch `/prov`, digest auth, or last-contact. Gate with a dedicated action permission. After `prov:templates:seed`, run `php artisan view:clear`. Do not render provisioning Blade as root. Verify with `curl` against `/prov/...`. Bump template `version:`. `docs/agent/devices-provisioning.md`. Phonebooks: `docs/agent/phonebooks.md`.
- Basic Queues stay in the main repo and must not depend on an optional module. `docs/agent/basic-queues.md`. Do not assume `Modules/` exists; check and fall back. Module notes go in that module's `AGENTS.md`. `docs/agent/optional-modules.md`.
- Scheduled jobs use only `app/Services/Ha/ActiveNodeResolver.php`. Never run coordination migrations through `app:update` or an update class. Default Settings edits are not a handoff. `docs/agent/scheduled-jobs.md`.
- Music on Hold: do not delete the music root or a domain root. Global streams are view-only on the tenant page. `docs/agent/music-on-hold.md`.
- Lua startup belongs in `resources/autoload_configs/lua.conf.xml` and a patch to `/etc/freeswitch/autoload_configs/lua.conf.xml`. Do not `luarun`, reload `mod_lua`, or restart FreeSWITCH from an update; print the restart command. One `DEBUG_MODE` switch, plain-English debug, do not block calls on a malformed rule unless that is explicit, and use atomic SQL for shared counters. `docs/agent/freeswitch-files.md`.
- Call block: the database is the source of truth, Redis is a cache, and Lua fails open if both are down. `docs/agent/call-block.md`.
- Translations: `resources/lang/{locale}.json`, keyed by the English source string. Wrap copy with `__()` / `trans()` / `$t()` and run `php artisan lang:sync`. `docs/agent/localization.md`.
- Verify with `php -l`. After frontend changes, `npm run build` when allowed. A Vite sandbox permission error: rerun outside the sandbox when approved. FreeSWITCH issues need `fs_cli` or logs. `docs/agent/verification.md`.
- Also read before editing: `docs/agent/settings.md`, `docs/agent/freeswitch-modules.md`, `docs/agent/scheduled-announcements.md`, `docs/agent/fax.md`, `docs/agent/logs.md`.

## Working Style

- Prefer small, focused changes that match the current app structure.
- Do not revert unrelated local changes. This repo is often worked on with a dirty tree.
- Use `rg`/`rg --files` for searches.
- Use `apply_patch` for manual edits.
- Keep user-facing copy short and practical.
- For release notes, write for users first. Avoid implementation details unless they explain visible behavior.

## Extensions And Voicemail

- Extension numbers and voicemail IDs are only unique inside a domain. Any lookup, relationship use, eager load, listener, observer, job, or response reload that connects `v_extensions.extension` to `v_voicemails.voicemail_id` must also constrain `domain_uuid`.
- `Extensions::voicemail()` is intentionally broad because Eloquent does not enforce the domain match there. Do not update, delete, sync copies, or read user-facing voicemail data from `$extension->voicemail` unless the relation was loaded with a domain filter or the voicemail was queried explicitly by `domain_uuid + voicemail_id`.
- Extension suspension and unsuspension must only toggle voicemail for the same tenant. DND updates should target the concrete extension row by `extension_uuid` and `domain_uuid`, not by extension number alone.

## Permissions

- Keep action permissions separate from record-scope permissions.
- For record scope, prefer the pattern:
  - `*_view_all_records`
  - `*_view_self_records`
- Do not repurpose legacy permissions unless there is a concrete existing use that proves the meaning.
- Add durable permissions to `database/seeders/DatabaseSeeder.php`; it runs as part of updates.
- Do not manually add permissions in update classes unless the normal update flow is bypassing the seeder for that release.

