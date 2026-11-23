<?php
// Read-only in-memory render of what /prov/<file> would serve, using the real ProvisioningController render path.
// usage: php render_native.php DOMAIN_UUID MAC FILE UA [template-file=PATH | template-uuid=UUID]
// Never calls serve() (which writes device_provisioned_*). Template content is never saved. Refuses to run unless
// the DB session reports transaction_read_only=on.
use App\Http\Controllers\ProvisioningController;
use App\Models\Devices;
use App\Models\ProvisioningTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

[, $domainUuid, $mac, $file, $ua] = $argv;
$opt = [];
foreach (array_slice($argv, 5) as $a) { [$k, $v] = explode('=', $a, 2); $opt[$k] = $v; }

require '/var/www/fspbx/vendor/autoload.php';
$app = require '/var/www/fspbx/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ro = DB::selectOne('show transaction_read_only')->transaction_read_only ?? '?';
fwrite(STDERR, "native: db transaction_read_only=$ro\n");
if ($ro !== 'on') { fwrite(STDERR, "REFUSING: DB session is not read-only (set PGOPTIONS)\n"); exit(10); }

// Belt and braces beyond the session default: do the whole render inside an explicit READ ONLY transaction and verify it.
DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
$txro = DB::selectOne('show transaction_read_only')->transaction_read_only ?? '?';
fwrite(STDERR, "native: explicit transaction READ ONLY, transaction_read_only=$txro\n");
if ($txro !== 'on') { DB::rollBack(); fwrite(STDERR, "REFUSING: transaction is not read-only\n"); exit(10); }

$device = Devices::where('domain_uuid', $domainUuid)->whereRaw('lower(device_address) = ?', [strtolower($mac)])->first();
if (!$device) { fwrite(STDERR, "native: device not found\n"); exit(4); }

$ctl = $app->make(ProvisioningController::class);
$call = function (string $name, ...$args) use ($ctl) {
    $m = new ReflectionMethod($ctl, $name);
    return $m->invoke($ctl, ...$args);
};

if (!empty($opt['template-file'])) {
    $content = file_get_contents($opt['template-file']);
    if ($content === false) { fwrite(STDERR, "native: cannot read template file\n"); exit(5); }
    $tpl = (new ProvisioningTemplate())->forceFill([
        'template_uuid' => '00000000-0000-0000-0000-000000000000', 'vendor' => strtolower((string) $device->device_vendor),
        'name' => 'p4352-in-memory', 'type' => 'custom', 'version' => 'in-memory', 'content' => $content,
    ]);
    fwrite(STDERR, 'native: template=file ' . basename($opt['template-file']) . ' sha256=' . substr(hash('sha256', $content), 0, 12) . "\n");
} elseif (!empty($opt['template-uuid'])) {
    $tpl = ProvisioningTemplate::where('template_uuid', $opt['template-uuid'])->first();
    if (!$tpl) { fwrite(STDERR, "native: template uuid not found\n"); exit(5); }
    fwrite(STDERR, "native: template=uuid {$tpl->template_uuid} {$tpl->vendor}/{$tpl->name} v{$tpl->version}\n");
} else {
    $tpl = $call('resolveTemplateForDevice', $device);
    if (!$tpl) { fwrite(STDERR, "native: no template resolves for this device (/prov/ would answer 404)\n"); exit(6); }
    fwrite(STDERR, "native: template=resolved {$tpl->template_uuid} {$tpl->vendor}/{$tpl->name} v{$tpl->version}\n");
}

$request = Request::create('/prov/' . ltrim($file, '/'), 'GET', [], [], [], ['HTTP_USER_AGENT' => $ua, 'HTTPS' => 'on', 'REMOTE_ADDR' => '127.0.0.1']);
[$id, $ext] = $call('extractIdAndExt', $request, $file);
$rendered = $call('renderProvisioningTemplate', $request, $device, $tpl, $id, $ext);
fwrite(STDERR, "native: flavor={$rendered['flavor']} mime={$rendered['mime']} bytes=" . strlen($rendered['body']) . "\n");
$still = DB::selectOne('show transaction_read_only')->transaction_read_only ?? '?';
DB::rollBack();   // nothing to commit, ever
if ($still !== 'on') { fwrite(STDERR, "native: transaction left read-only mode during render!\n"); exit(12); }
echo $rendered['body'];
