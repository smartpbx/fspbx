#!/bin/bash
# compare.sh <legacy-config> <native-config> [--ignore-empty] [--include-same] [--format=xml|kv] [--no-mask]
# One line per differing key: key | a | b   (secrets masked as <redacted>). See compare.py for the normalization rules.
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec python3 -I "$HERE/compare.py" "$@"
