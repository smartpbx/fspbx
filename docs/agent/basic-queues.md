# Basic Queues

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Basic Queues

- Basic Queues are a main-repo feature and must work without optional modules under `Modules/`. Do not route Basic Queue status/detail pages into module controllers.
- Basic Queue pages use FS PBX call center tables and FreeSWITCH `mod_callcenter`. The database is the source of truth, but queue, tier, and agent changes also need the matching event socket commands so FreeSWITCH runtime state changes immediately.
- For Basic Queue Music on Hold selects, mirror the Phone Number update modal pattern: use grouped `getMusicOnHoldCollection(session('domain_uuid'))` options with VueForm `:groups="true"`, searchable non-native selects, `:strict="false"`, and `allow-absent`.
- Keep Music on Hold values compatible with FreeSWITCH. Domain streams should use `local_stream://{domain_name}/{music_on_hold_name}`; global streams can remain `local_stream://{name}`. Do not rebuild full recording paths as relative filenames when saving existing values.
- Queue extension generation must avoid colliding with Ring Groups and other extension-like features. Keep Basic Queue ranges separate from Ring Group ranges.
- Basic Queue agent forms should put Contact first. Selecting a contact should autofill agent name, agent ID, and agent password from the selected extension/contact. Do not reintroduce the deprecated User field.
- Keep Basic Queue agent status choices simple unless the product scope changes: `Logged Out`, `Available`, and `On Break`.
- For queue tier changes, keep the FreeSWITCH command order close to the legacy call center behavior. Removing an agent from a queue should delete the live tier before reloading the queue. Deleting an assigned agent should delete the live tier, reload the queue, then delete the live agent. Avoid extra `reloadxml` calls for pure tier removals.

