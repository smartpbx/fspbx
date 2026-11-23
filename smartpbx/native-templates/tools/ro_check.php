<?php
// Read-only proof, independent of Laravel/legacy code: connects with the same PGOPTIONS and fusionpbx's config.conf.
// usage: php ro_check.php [attempt-write MAC]   exit 0 only if the session reports transaction_read_only=on.
$cfg = [];
foreach (file('/etc/fusionpbx/config.conf', FILE_IGNORE_NEW_LINES) as $l) {
    if (preg_match('/^\s*(database\.0\.\w+)\s*=\s*(.*)$/', $l, $m)) { $cfg[$m[1]] = trim($m[2]); }
}
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $cfg['database.0.host'] ?? '127.0.0.1', $cfg['database.0.port'] ?? '5432', $cfg['database.0.name'] ?? 'fusionpbx');
$pdo = new PDO($dsn, $cfg['database.0.username'], $cfg['database.0.password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$ro = $pdo->query('show transaction_read_only')->fetchColumn();
echo "session transaction_read_only=$ro\n";
if (($argv[1] ?? '') === 'attempt-write') {
    try {
        $st = $pdo->prepare('update v_devices set device_provisioned_agent = device_provisioned_agent where device_address = ?');
        $st->execute([strtolower($argv[2])]);
        echo "write attempt: SUCCEEDED (not read-only!)\n"; exit(2);
    } catch (PDOException $e) {
        echo 'write attempt rejected: ' . preg_replace('/\s+/', ' ', explode("\n", $e->getMessage())[0]) . "\n";
    }
}
exit($ro === 'on' ? 0 : 1);
