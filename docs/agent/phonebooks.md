# Phonebooks

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Phonebooks

- A phonebook is a device directory made of optional internal extensions (an `include_extensions` toggle) plus the phonebook's own contacts. This supports extensions-only, contacts-only, or both. Keep the editor simple enough that an end user grasps that composition at a glance.
- Contacts are per-phonebook in `phonebook_contacts`, not the shared CRM `contacts`/Messages store. Each phonebook owns its list; do not reuse account-wide contacts for phonebooks or every phonebook shows the same people. Contacts save together with the phonebook via `PhonebookService` (delete + recreate), not through separate contact endpoints.
- Data model: `phonebooks` (`enabled`, `is_default`, `include_extensions`), `phonebook_contacts` (per phonebook), and `device_phonebook` (per-device assignment with `slot`).
- Directory entries are built in `app/Services/Provisioning/Phonebook/PhonebookBuilder.php` and rendered by vendor formatters (Grandstream `AddressBook`, Yealink `YealinkIPPhoneDirectory`). The final list is de-duplicated and sorted by last name, then first name, then display name, matching Grandstream's on-phone `phonebook.sortBy = LastName`.
- Phones fetch directories from `/prov/directory/{book}/{path}` (`PhonebookController`), reusing the `provision.digest` middleware. `{book}` is a phonebook UUID or `all`. The device is resolved from the MAC/serial path segment, because some phones append a fixed filename (Grandstream requests `<server>/phonebook.xml`) so the basename is not the device token — `DigestProvisionAuth` scans path segments when the basename yields no token.
- Grandstream downloads a single `phonebook.xml`, so its config points at the device-merged `all` directory (`/prov/directory/all/{mac}`), which combines every assigned phonebook. Yealink supports multiple remote phonebooks, so it gets one credentialed URL per phonebook via `remote_phonebook.data.X`.
- Grandstream `phonebook.download.server` must be host/path WITHOUT a scheme; the separate `phonebook.download.mode`/P330 selects HTTP vs HTTPS. Item-style templates strip the scheme inline; GXP17xx strips it for P331 and derives P330 from the scheme.
- Grandstream config is injected through settings in `ProvisioningController::buildTemplateVars` (`grandstream_phonebook_server`/`_username`/`_password`/`_download_interval`), so every Grandstream model picks it up without per-template edits. When no phonebook resolves, leave any manually-configured phonebook settings intact.
- Device assignment lives on the device edit modal's Phonebook tab: "Use account default" (all `is_default` phonebooks) or "Custom" (rows in `device_phonebook`, order = slot). `DeviceService` syncs `device_phonebook` from `phonebook_mode` + `phonebook_uuids`.
- Provisioning preview mirrors the phone: Grandstream shows one merged `phonebook.xml`; Yealink shows one file per phonebook.
- User-facing phonebook copy uses "Account", never "domain" (the internal multi-tenant term). Show a per-vendor hint on the device Phonebook tab so the Grandstream-merges-into-one vs Yealink-separate-directories behavior is not surprising.

