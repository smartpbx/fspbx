# Laravel And Vue Pages

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Laravel And Vue Pages

- Rebuild legacy `/public/app/.../*.php` admin pages as native Laravel/Vue pages when asked.
- Follow existing first-class pages, especially Devices and Conference-style pages, before inventing new patterns.
- Put the Inertia page route in `routes/web.php`.
- Put axios/data/action routes in `routes/api.php`.
- Use Spatie QueryBuilder for searchable/sortable/paginated data tables when that is the local pattern.
- For modern Inertia pages, pass page-specific permissions from the controller as a `permissions` prop and read `props.permissions` in Vue. Do not add new page-specific permission keys to `HandleInertiaRequests` unless a truly shared layout/component needs them globally.
- For date filters, follow the CDR pattern: pass `timezone` as a top-level Inertia prop for the DatePicker/UI, but do not include it in the API `filter` payload. Only send filters the QueryBuilder endpoint explicitly allows.
- Use VueForm for create/update modals.
- All new or revised VueForms must use server-side validation only. Do not add VueForm `rules`, custom client validators, or client validity checks that block submission. Keep validation in Laravel, display returned field errors through VueForm's message bags, and clear previous errors before resubmitting. Disable VueForm validation for newly created forms; field formatting and input widgets may remain.
- Keep forms clear and operational. Avoid marketing-style UI.

