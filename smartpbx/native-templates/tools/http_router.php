<?php
// Router for `php -S` used only by http_proof.sh: serves the real legacy /app/provision/index.php over HTTP with the
// nginx rewrites mirrored, on 127.0.0.1, under a read-only DB session. Not a production path.
require __DIR__ . '/rewrites.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!preg_match('#^/app/provision/(.*)$#', $path, $m) || ($q = p4352_rewrite($m[1])) === null) { http_response_code(404); echo "no rewrite\n"; return true; }
$h = function_exists('getallheaders') ? getallheaders() : [];
$auth = $h['Authorization'] ?? $h['authorization'] ?? '';
if (stripos($auth, 'Digest ') === 0) { $_SERVER['PHP_AUTH_DIGEST'] = substr($auth, 7); }
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/app/provision/index.php?' . http_build_query($q);
$_SERVER['SCRIPT_NAME'] = '/app/provision/index.php'; $_SERVER['PHP_SELF'] = '/app/provision/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/var/www/fspbx/public'; $_SERVER['SCRIPT_FILENAME'] = '/var/www/fspbx/public/app/provision/index.php';
$_GET = $q; $_REQUEST = $q;
chdir('/var/www/fspbx/public/app/provision');
include 'index.php';
return true;
