# Devices And Provisioning

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Devices And Provisioning

- Device Key Templates are the preferred reusable key layout workflow. Show Key Template controls before legacy Device Profile controls, but keep legacy Profile support in place unless explicitly asked to remove it.
- Key Template and Device Profile assignment should be mutually exclusive in device create, update, bulk update, and extension device modals. If one is selected, clear/disable the other.
- Device tables and extension assigned-device mini tables should show a combined Profile / Key Template column so users can see which assignment is active.
- Device Key Templates are domain-scoped. Use `device_key_template_assign` for assignment UI/actions and pass permissions from the controller props rather than global Inertia middleware when working on the Devices page.
- Provisioning merge order for effective keys is: old assigned Device Profile keys, new assigned Device Key Template keys, old per-device legacy `v_device_keys`, then new per-device `device_keys`.
- Blank or `N/A` Key Template keys intentionally occupy their slot and can clear older profile keys.
- Keep vendor translation and normalization in the existing provisioning path. Do not add new broad vendor translations unless the stored data shape truly requires it.
- The modern provisioning path is `app/Http/Controllers/ProvisioningController.php`; the old provisioning URL still uses `public/app/provision/resources/classes/provision.php`.
- Native provisioning renders `provisioning_templates.content` from the database, not the resource file directly. Changes under `resources/provisioning/.../template.blade.php` require reseeding default templates with `php artisan prov:templates:seed` or manually updating custom template content.
- After reseeding provisioning templates, run `php artisan view:clear`. Changed template content forces Blade to recompile the stored string, and a stale/wrongly-owned compiled view causes a 500 on the phone's config request. Do not render provisioning Blade as root (via `tinker`/`artisan`) — it writes root-owned files in `storage/framework/views` that the `www-data` web server cannot overwrite. Verify template rendering with `curl` against `/prov/...`, not a root tinker `Blade::render`.
- Custom provisioning templates do not inherit default template changes automatically.
- When changing default provisioning templates, bump the Blade front-matter `version:` so the seeder can update the database row clearly.
- The provisioning template seeder keys default rows by `vendor + folder name + type`. When renaming a default template folder, add an update step before `prov:templates:seed` runs that renames the existing `provisioning_templates.name` row and any custom `base_template` references, otherwise the seeder will create a second default row under the new folder name.
- Provisioning preview should render through the same code path as live provisioning but must not touch `/prov`, digest auth, or device last-contact metadata. Treat generated previews as credential-bearing output and gate them with a dedicated action permission.
- The old provisioning URL delegates modern `device_keys` overlay behavior to `fspbx_apply_new_keys_override()` in `app/helpers.php`. Key Template support for that old path should stay in helpers where possible.
- `device_key_templates.enabled` is stored as the string `'true'`, not a boolean. Legacy SQL helpers should compare it as `t.enabled = 'true'`.
- If the old `provision.php` path must participate in a new behavior, remember `public/` is not tracked. Make the runtime file change for the working server, then add or update an update class to download/replace the file for deployed systems.
- When expanding a Grandstream template from an official configuration file, use active `P... = ...` assignments as the vendor baseline and retain commented P-value entries as documentation only. Apply FS PBX system, account, credential, and key values as an explicit overlay after parsing the baseline.
- Comments inside Blade/PHP setup blocks are not part of the provisioned XML. If provisioning previews should explain each Grandstream P-value, render the captured descriptions as XML comments next to the resulting elements and render prominent XML-comment banners for section headings.
- Grandstream key mode numbers are key-family specific. On GXP17xx, fixed/line VPKs 1-6 use values such as `11` for BLF and `26` for Monitored Call Park, while dynamic VPKs 7-32 use `1` for BLF and `16` for Monitored Call Park. Do not reuse one mode map for both families.
- Grandstream account selectors in P-values are zero-based even when the UI labels them as Account 1, Account 2, and so on. Normalize FS PBX line assignments accordingly.
- For normal GXP17xx BLF operation, leave the account BLF server blank so subscriptions use the configured SIP server. Monitored park values use the form `park+*5901`.
- When a vendor baseline contains settings for unsupported accounts or model variants as commented entries, do not activate them merely to make the generated template more complete. Only emit supported active settings and intentional FS PBX overrides.
- Yealink templates should use `$main_keys` for `linekey.*` output and `$expansion_keys` for `expansion_module.1.key.*` output.
- Yealink phone line keys should clear unused slots through 30; Yealink expansion module keys should clear unused slots through 60 per module.
- Yealink devices can use the Expansion Keys tab in the device edit modal.
- Fanvil templates must derive every DST enable/type field from `fanvil_enable_dst`; when it is off, render `DSTLocation=0`. Yealink no-DST behavior remains controlled by `yealink_summer_time`, and Polycom/Poly by `daylight_savings_enabled`.
