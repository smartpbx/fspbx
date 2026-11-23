# Email Templates

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Email Templates

- Native email templates live in the `email_templates` table. Do not restore the legacy `v_email_templates` schema, legacy template-population blocks, install-time cleanup scripts, or `LegacyEmailTemplateCleaner`.
- Default email template sources use `resources/views/emails/{category}/{subcategory}.blade.php` and a required `{subcategory}-text.blade.php` companion.
- The HTML file owns shared `email-template` metadata, including `version`, language, category, subcategory, subject, and description. Its plain-text companion carries only `format: text` and `layout: none`; bump the version once in the HTML file when either body changes.
- Bump the template version whenever seeded subject, HTML, text, or layout content changes. Updates overwrite versioned defaults but never custom overrides.
- The email template create-table migration runs `email:templates:seed` immediately after creating the table, so manually running migrations after `app:update` populates defaults even when the earlier update-time seed skipped a missing table.
- Runtime resolution order is current-account custom, global custom, then shipped default, with the requested language preferred before `en-us`. The database is the source of truth once a matching row exists.
- Application mailables extend `BaseMailable`, prepare trusted data in the constructor, call `useEmailTemplate(category, subcategory)`, and declare an explicit Laravel `content(): Content` method showing the HTML/text view pair. The base class owns shared `Envelope`, headers, and database-override wrapping.
- Editable templates support Blade directives but reject `@php`, raw PHP tags, and scripts. Superadmin access is still the trust boundary because Blade expressions are executable.
- Email template previews must render through `SafeEmailTemplateRenderer` with representative sample variables; never send raw Blade source directly to an iframe. Read-only rows preview stored content, while authorized editable rows may preview unsaved content.
- Email Template visibility is fixed to shipped defaults, global custom overrides, and custom overrides for `session('domain_uuid')`; never add an all-accounts view. `email_templates_manage_global` controls global-custom mutations only and does not broaden record visibility.
- This feature is a breaking change for installations that edited legacy email templates: legacy customizations are not imported or used. Release notes must tell administrators to save those changes before upgrading and recreate them as custom templates afterward.

