<?php
// Read-only CLI render of the legacy /app/provision/ handler (no nginx, no fpm).
// usage: php render_legacy.php HOST UA MAC FILE   (env DIGEST = PHP_AUTH_DIGEST value; PGOPTIONS must make the session read-only)
// FILE is the URL tail after /app/provision/ as the phone would request it; the nginx rewrite table is mirrored in rewrites.php.
[, $host, $ua, $mac, $tail] = $argv;
require __DIR__ . '/rewrites.php';
$q = p4352_rewrite($tail);
if ($q === null) { fwrite(STDERR, "no nginx rewrite matches '$tail'\n"); exit(3); }
$_SERVER['HTTP_HOST'] = $host; $_SERVER['SERVER_NAME'] = $host; $_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_USER_AGENT'] = $ua; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/app/provision/index.php?' . http_build_query($q);
$_SERVER['SCRIPT_NAME'] = '/app/provision/index.php'; $_SERVER['PHP_SELF'] = '/app/provision/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/var/www/fspbx/public'; $_SERVER['SCRIPT_FILENAME'] = '/var/www/fspbx/public/app/provision/index.php';
$_SERVER['PHP_AUTH_DIGEST'] = getenv('DIGEST');
$_GET = $q; $_REQUEST = $q;
chdir('/var/www/fspbx/public/app/provision');
include 'index.php';
