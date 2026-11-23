# Music On Hold

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Music On Hold

- The native Music on Hold page replaces the old `/app/music_on_hold/music_on_hold.php` surface. Keep create/update/upload forms in VueForm.
- Treat a visible stream as a family of FreeSWITCH `mod_local_stream` rows, normally one row for each rate: `8000`, `16000`, `32000`, and `48000`. Do not expose a rate dropdown for normal stream creation; create, edit, upload, and delete the family together.
- Uploaded audio should be converted with `ffmpeg` to mono 16-bit PCM WAV for all supported rates to avoid FreeSWITCH transcoding surprises.
- Stream paths are generated, not user editable. Use `$${sounds_dir}/music/{domain-or-global}/{stream-name}/{rate}` for stored rows. The UI can display the family root without the trailing rate.
- Global Music on Hold streams use `global` in the generated filesystem path and `NULL` `domain_uuid` in the database. If a VueForm select needs a non-empty value for Global, use a UI-only sentinel and normalize it back to `NULL` before validation.
- Treat global Music on Hold streams as view-only from the tenant page. Normal add/edit/delete/upload permissions must only modify streams assigned to `session('domain_uuid')`; do not let action permissions alone modify `NULL` `domain_uuid` rows.
- Users without `music_on_hold_domain` should not see the domain selector. Creating a stream should assign their session domain. Editing an existing stream must preserve its stored `domain_uuid`.
- When Music on Hold rows or files change, clear the FS PBX local stream XML cache with `FusionCache::clear('configuration:local_stream.conf')` and reload `mod_local_stream` when requested. The generated cache file lives at `/var/cache/fusionpbx/configuration.local_stream.conf`.
- The FS PBX Lua XML generator for `local_stream.conf` may need patches in two places: `public/app/switch/resources/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua` for future installs and `/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/configuration/local_stream.conf.lua` for existing servers.
- The Lua XML generator should build directory names as `{domain_name}/{music_on_hold_name}` without appending the rate, while the row path still points to the rate-specific directory. It should default blank or null rates to `48000` and honor row-level `channels` and `interval` values.
- Deleting a stream should delete the whole generated stream folder under `/usr/share/freeswitch/sounds/music/{domain-or-global}/{stream-name}`, not just individual DB rows. Keep deletion guarded so it cannot remove the music root or a domain root.
- Avoid icons that look like playback unless the action actually starts playback. For file selection rows, prefer an audio/file icon; reserve play icons for buttons that open or start the player.

