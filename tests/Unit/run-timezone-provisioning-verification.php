<?php

require_once __DIR__.'/../../app/Services/FreeSwitchTimezoneConfigService.php';

use App\Services\FreeSwitchTimezoneConfigService;

function verify(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function zoneValues(string $xml): array
{
    $document = simplexml_load_string($xml);
    verify($document !== false, 'generated timezone XML parses');

    $values = [];
    foreach ($document->timezones->zone as $zone) {
        $values[(string) $zone['name']] = (string) $zone['value'];
    }

    return $values;
}

function filesUnder(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** @return array<string, string> */
function renderFanvilDstSettings(string $template, array $settings): array
{
    $contents = file_get_contents($template);
    verify(
        preg_match('/@php\s*(.*?)\s*@endphp/s', $contents, $prelude) === 1,
        basename($template).' has a PHP prelude'
    );

    $lines = [];
    $domain_name = 'example.test';
    eval($prelude[1]);

    $rendered = [];
    foreach (['Enable_DST', 'DSTType', 'DSTLocation'] as $tag) {
        if (preg_match('~<'.preg_quote($tag, '~').'>\s*{{\s*(.*?)\s*}}\s*</'.preg_quote($tag, '~').'>~s', $contents, $expression) !== 1) {
            continue;
        }

        $rendered[$tag] = (string) eval('return '.$expression[1].';');
    }

    return $rendered;
}

$service = new FreeSwitchTimezoneConfigService();
$fixtureRoot = sys_get_temp_dir().'/fspbx-timezones-'.bin2hex(random_bytes(6));
mkdir($fixtureRoot.'/America', 0755, true);
file_put_contents($fixtureRoot.'/America/Test', "TZif3fixture\nTST3\n");
file_put_contents($fixtureRoot.'/America/Edmonton', "TZif3fixture\nMST7MDT,M3.2.0,M11.1.0\n");
file_put_contents($fixtureRoot.'/America/Winnipeg', "TZif3fixture\nCST6CDT,M3.2.0,M11.1.0\n");

try {
    $fixtureValues = zoneValues($service->build($fixtureRoot));
    verify(($fixtureValues['America/Test'] ?? null) === 'TST3', 'reads the last POSIX TZ string from a TZif file');
    verify(($fixtureValues['America/Edmonton'] ?? null) === 'STD6', 'supplies a FreeSWITCH-compatible Edmonton fallback');
    verify(($fixtureValues['America/Winnipeg'] ?? null) === 'STD5', 'supplies a FreeSWITCH-compatible Winnipeg fallback');
    verify($service->normalizeForFreeSwitch('<-05>5', 'America/Test') === 'STD5', 'normalizes a quoted fixed offset');
    verify($service->normalizeForFreeSwitch('<+0530>-5:30', 'Asia/Test') === 'STD-5:30', 'normalizes a quoted half-hour offset');
    verify(
        $service->normalizeForFreeSwitch('<-02>2<-01>,M3.5.0/-1,M10.5.0/0', 'America/Test')
            === 'STD2DST,M3.5.0/-1,M10.5.0/0',
        'normalizes quoted standard and daylight abbreviations without losing transition rules'
    );
    verify($service->normalizeForFreeSwitch('<-05>5', 'Etc/GMT+5') === 'GMT5', 'uses GMT for GMT aliases');

    $destination = $fixtureRoot.'/timezones.conf.xml';
    $backup = $fixtureRoot.'/timezones.conf.xml.bak';
    file_put_contents($destination, 'old table');
    verify($service->sync($destination, $fixtureRoot, $backup), 'replaces an outdated table');
    verify(file_get_contents($backup) === 'old table', 'preserves the rollback table');
    verify(! $service->sync($destination, $fixtureRoot, $backup), 'second sync is idempotent');

    $symlinkTarget = $fixtureRoot.'/linked-target.xml';
    $symlinkDestination = $fixtureRoot.'/linked-timezones.conf.xml';
    $symlinkBackup = $fixtureRoot.'/linked-timezones.conf.xml.bak';
    file_put_contents($symlinkTarget, 'old linked table');
    symlink(basename($symlinkTarget), $symlinkDestination);
    verify($service->sync($symlinkDestination, $fixtureRoot, $symlinkBackup), 'updates a symlinked table');
    verify(is_link($symlinkDestination), 'preserves the live table symlink');
    verify(str_starts_with(file_get_contents($symlinkTarget), '<configuration'), 'atomically replaces the symlink target');
    verify(file_get_contents($symlinkBackup) === 'old linked table', 'backs up the previous symlink target contents');
} finally {
    @unlink($fixtureRoot.'/linked-timezones.conf.xml.bak');
    @unlink($fixtureRoot.'/linked-timezones.conf.xml');
    @unlink($fixtureRoot.'/linked-target.xml');
    @unlink($fixtureRoot.'/timezones.conf.xml.bak');
    @unlink($fixtureRoot.'/timezones.conf.xml');
    unlink($fixtureRoot.'/America/Test');
    unlink($fixtureRoot.'/America/Edmonton');
    unlink($fixtureRoot.'/America/Winnipeg');
    rmdir($fixtureRoot.'/America');
    rmdir($fixtureRoot);
}

$trackedValues = zoneValues(file_get_contents(__DIR__.'/../../resources/autoload_configs/timezones.conf.xml'));
verify(isset($trackedValues['America/Edmonton']), 'tracked table contains America/Edmonton');
verify(isset($trackedValues['America/Winnipeg']), 'tracked table contains America/Winnipeg');
verify(! str_contains($trackedValues['America/Edmonton'], 'MDT'), 'tracked Edmonton rule has no DST');
verify(! str_contains($trackedValues['America/Winnipeg'], 'CDT'), 'tracked Winnipeg rule has no DST');
foreach ($trackedValues as $zone => $value) {
    verify(! str_contains($value, '<') && ! str_contains($value, '>'), "tracked {$zone} rule is compatible with FreeSWITCH");
}

$fanvilTemplates = [
    __DIR__.'/../../smartpbx/native-templates/fanvil-x303-2wire/x303-2wire.tpl',
    __DIR__.'/../../smartpbx/native-templates/grandstream-algo/fanvil-v67-smartpbx.tpl',
];

foreach ($fanvilTemplates as $template) {
    $contents = file_get_contents($template);
    verify(! str_contains($contents, '<DSTType>1</DSTType>'), basename($template).' does not force DSTType=1');
    verify(str_contains($contents, 'fanvil_enable_dst'), basename($template).' reads fanvil_enable_dst');
    verify(str_contains($contents, '<DSTLocation>{{'), basename($template).' derives DSTLocation');

    foreach (['0', 'false', 'off'] as $disabled) {
        $rendered = renderFanvilDstSettings($template, [
            'fanvil_enable_dst' => $disabled,
            'fanvil_location' => '9',
        ]);
        verify(($rendered['DSTType'] ?? null) === '0', basename($template)." disables DSTType for {$disabled}");
        verify(($rendered['DSTLocation'] ?? null) === '0', basename($template)." clears DSTLocation for {$disabled}");
        if (isset($rendered['Enable_DST'])) {
            verify($rendered['Enable_DST'] === '0', basename($template)." disables Enable_DST for {$disabled}");
        }
    }

    foreach (['1', 'true', 'on'] as $enabled) {
        $rendered = renderFanvilDstSettings($template, [
            'fanvil_enable_dst' => $enabled,
            'fanvil_location' => '9',
        ]);
        verify(($rendered['DSTType'] ?? null) === '1', basename($template)." enables DSTType for {$enabled}");
        verify(($rendered['DSTLocation'] ?? null) === '9', basename($template)." renders the configured DSTLocation for {$enabled}");
        if (isset($rendered['Enable_DST'])) {
            verify($rendered['Enable_DST'] === '1', basename($template)." enables Enable_DST for {$enabled}");
        }
    }
}

foreach (filesUnder(__DIR__.'/../../resources/provisioning/fanvil') as $template) {
    $contents = file_get_contents($template);
    if (str_contains($contents, '<DSTType>') || str_contains($contents, '<Enable_DST>')) {
        verify(str_contains($contents, 'fanvil_enable_dst'), basename($template).' honors fanvil_enable_dst');
    }
}

foreach (glob(__DIR__.'/../../smartpbx/native-templates/yealink/*.tpl') as $template) {
    verify(str_contains(file_get_contents($template), 'yealink_summer_time'), basename($template).' honors yealink_summer_time');
}

foreach (filesUnder(__DIR__.'/../../resources/provisioning/yealink') as $template) {
    $contents = file_get_contents($template);
    if (str_contains($contents, 'local_time.summer_time')) {
        verify(str_contains($contents, 'yealink_summer_time'), basename($template).' honors yealink_summer_time');
    }
}

foreach (glob(__DIR__.'/../../smartpbx/native-templates/polycom/*.tpl') as $template) {
    verify(str_contains(file_get_contents($template), 'daylight_savings_enabled'), basename($template).' honors daylight_savings_enabled');
}

foreach ([__DIR__.'/../../resources/provisioning/polycom', __DIR__.'/../../resources/provisioning/poly'] as $directory) {
    foreach (filesUnder($directory) as $template) {
        $contents = file_get_contents($template);
        if (str_contains($contents, 'daylightSavings.enable')) {
            verify(str_contains($contents, 'daylight_savings_enabled'), basename($template).' honors daylight_savings_enabled');
        }
    }
}

echo "Timezone and no-DST provisioning verification passed.\n";
