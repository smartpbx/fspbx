# Settings Pages

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Settings Pages

- Default Settings and Domain Settings are native Laravel/Vue pages. Keep page routes in `routes/web.php` and data/action routes in `routes/api.php`.
- Default Settings is the global catalog. Domain Settings is the working surface for overrides and custom domain rows.
- Domain Settings should open focused on overrides/custom rows by default; users can switch the source filter to see inherited defaults.
- Domain Settings status toggles should update the row in place and refresh silently. Do not blank the whole list for a single on/off click.
- Use VueForm for settings edit and copy modals, including copy-to-domain workflows. Keep VueForm labels non-floating on these forms.
- In settings edit forms, Category should be a searchable/createable select populated from existing categories, and Subcategory should be labeled as `Setting Name`.
- Do not invent an order value for existing or newly-created settings. Preserve `NULL`/blank order unless the user enters one or a default-backed override is intentionally copying the default row.
- Domain option labels should prefer `domain_description` and only fall back to `domain_name` when no human label exists.
- The native Domain Settings route requires a domain UUID (`/domains/{domain}/settings`). Generic navigation should go to `/domains`, where each domain row links to its settings.
- Preserve legacy settings semantics: non-array domain overrides match defaults by category/subcategory/type, while `array` settings remain distinct rows.

