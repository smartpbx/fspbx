#!/usr/bin/env python3
"""Keep the always-loaded FS PBX agent rules under the size cap.

AGENTS.md is read on every agent call. Long procedures live in docs/agent/.
The cap is 6144 bytes (6 KiB), the target from orca-hq#493. Hard-rule
phrases below must stay in AGENTS.md; deleting one fails this check.
"""

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
AGENTS = ROOT / "AGENTS.md"
DOCS = ROOT / "docs" / "agent"
LIMIT = 6144

# Phrases an agent must see even when it does not open a topic file.
NEEDLES = (
    "docs/agent/README.md",
    "routes/web.php",
    "routes/api.php",
    "server-side validation",
    "Spatie QueryBuilder",
    "v_email_templates",
    "version.json",
    "fs-esl-listener-call-webhooks",
    "credential-bearing",
    "ActiveNodeResolver.php",
    "app:update",
    "music root",
    "luarun",
    "fails open",
    "lang:sync",
    "domain_uuid",
    "DatabaseSeeder.php",
    "php -l",
    "public/",
)

# Topic files AGENTS.md must point at, and a heading that proves the
# verbatim section is still in that file.
TOPICS = {
    "laravel-vue-pages.md": "## Laravel And Vue Pages",
    "email-templates.md": "## Email Templates",
    "updates.md": "## Updates",
    "devices-provisioning.md": "## Devices And Provisioning",
    "phonebooks.md": "## Phonebooks",
    "basic-queues.md": "## Basic Queues",
    "optional-modules.md": "## Optional Modules",
    "scheduled-jobs.md": "## Scheduled-Job Coordination",
    "music-on-hold.md": "## Music On Hold",
    "freeswitch-files.md": "## FreeSWITCH Files",
    "call-block.md": "## Call Block Runtime",
    "localization.md": "## Localization",
    "verification.md": "## Verification",
    "settings.md": "## Settings Pages",
    "freeswitch-modules.md": "## FreeSWITCH Modules Page",
    "scheduled-announcements.md": "## Scheduled Announcements",
    "fax.md": "## Fax Jobs And Retention",
    "logs.md": "## Logs Page",
}


def main() -> int:
    errors = []
    if not AGENTS.is_file():
        errors.append("AGENTS.md is missing")
        return report(errors)

    text = AGENTS.read_text()
    size = len(text.encode("utf-8"))
    print(f"AGENTS.md is {size} bytes (limit {LIMIT})")
    if size > LIMIT:
        errors.append(
            f"AGENTS.md is {size} bytes; limit is {LIMIT}. "
            "Move procedure text to docs/agent/ and leave a pointer."
        )

    for needle in NEEDLES:
        if needle not in text:
            errors.append(f"AGENTS.md is missing hard rule phrase: {needle}")

    linked = set(re.findall(r"docs/agent/[A-Za-z0-9._/-]+\.md", text))
    if "docs/agent/README.md" not in linked:
        errors.append("AGENTS.md must point at docs/agent/README.md")

    for name, heading in TOPICS.items():
        path = DOCS / name
        rel = f"docs/agent/{name}"
        if rel not in linked and name != "README.md":
            # settings, fax, logs, and the rest are named from AGENTS.md
            errors.append(f"AGENTS.md does not point at {rel}")
        if not path.is_file():
            errors.append(f"missing topic file {rel}")
            continue
        body = path.read_text()
        if heading not in body:
            errors.append(f"{rel} is missing {heading}")
        if len(body.encode("utf-8")) < 400:
            errors.append(f"{rel} is too small to hold its rules")

    readme = DOCS / "README.md"
    if not readme.is_file() or readme.stat().st_size < 400:
        errors.append("docs/agent/README.md is missing or empty")

    # Lua expectations were moved into the FreeSWITCH file.
    fs = DOCS / "freeswitch-files.md"
    if fs.is_file() and "## Lua Expectations" not in fs.read_text():
        errors.append("docs/agent/freeswitch-files.md is missing ## Lua Expectations")
    updates = DOCS / "updates.md"
    if updates.is_file() and "## Included Legacy Files" not in updates.read_text():
        errors.append("docs/agent/updates.md is missing ## Included Legacy Files")

    return report(errors)


def report(errors: list[str]) -> int:
    if errors:
        print("agent rules check failed:", file=sys.stderr)
        for err in errors:
            print(f"- {err}", file=sys.stderr)
        return 1
    print("agent rules check passed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
