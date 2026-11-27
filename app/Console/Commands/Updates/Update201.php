<?php

namespace App\Console\Commands\Updates;

use App\Services\FreeSwitchTimezoneConfigService;
use Throwable;

class Update201
{
    private const VERSION = '2.0.1';

    public function apply(): bool
    {
        try {
            $timezones = app(FreeSwitchTimezoneConfigService::class);
            $destination = FreeSwitchTimezoneConfigService::DEFAULT_DESTINATION;
            $backup = $destination.'.fspbx-pre-tzdata-sync.bak';
            $changed = $timezones->sync(
                $destination,
                FreeSwitchTimezoneConfigService::DEFAULT_ZONEINFO_DIRECTORY,
                $backup
            );

            echo $changed
                ? "Generated {$destination} from the host tzdata rules.\n"
                : "{$destination} already matches the host tzdata rules.\n";
            echo "FreeSWITCH was not restarted or reloaded. After rollout gates pass, run: fs_cli -x reloadxml\n";
            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.': '.$exception->getMessage()."\n";

            return false;
        }
    }
}
