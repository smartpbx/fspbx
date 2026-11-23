# Logs Page

On-demand FS PBX agent rules. Not loaded on every call. `AGENTS.md` is the always-loaded file and points here. Read this before changing this area. The bullets are rules, moved verbatim from `AGENTS.md` (orca-hq#493). Do not drop a rule. When the behavior changes, edit it here.

## Logs Page

- `/logs` is the shared logs surface. `LogsController` provides the Inertia page props and routes; individual log components usually fetch data through existing API controllers.
- Email and fax logs are domain-scoped. Default to the current `session('domain_uuid')`, and use `session('domains')` for multi-domain selectors or "all domains" views.
- Do not let a requested domain UUID bypass access. Validate requested domains against the accessible session domain list, and treat "all" as all accessible domains only.
- When a QueryBuilder endpoint receives `filter[domain_uuid]` but applies domain scoping manually, still add `domain_uuid` as an allowed no-op filter so Spatie does not reject the request.
- Keep destructive or retry actions bounded to accessible domains too, not only list queries.

