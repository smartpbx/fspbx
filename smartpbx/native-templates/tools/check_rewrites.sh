#!/bin/bash
# Fails (exit 1) if the live nginx provisioning rewrites no longer match the snapshot rewrites.php was copied from.
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
live=$(sudo -n grep -E '^\s*rewrite "?\^\.\*/provision/' /etc/nginx/sites-enabled/fspbx.conf | sed 's/^\s*//; s/\s*#.*$//' | sort)
if [ "$live" = "$(sort "$HERE/nginx-rewrites.snapshot")" ]; then echo "nginx provision rewrites match snapshot ($(echo "$live" | wc -l) rules)"; else
  echo "DRIFT: live nginx provision rewrites differ from nginx-rewrites.snapshot; update rewrites.php and the snapshot:" >&2
  diff <(echo "$live") <(sort "$HERE/nginx-rewrites.snapshot") >&2; exit 1; fi
