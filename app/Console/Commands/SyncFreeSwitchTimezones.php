<?php

namespace App\Console\Commands;

use App\Services\FreeSwitchTimezoneConfigService;
use Illuminate\Console\Command;
use Throwable;

class SyncFreeSwitchTimezones extends Command
{
    protected $signature = 'freeswitch:sync-timezones
        {--zoneinfo-dir=/usr/share/zoneinfo : Host tzdata directory}
        {--destination=/etc/freeswitch/autoload_configs/timezones.conf.xml : FreeSWITCH timezone table}';

    protected $description = 'Generate the FreeSWITCH timezone table from the host tzdata rules';

    public function handle(FreeSwitchTimezoneConfigService $timezones): int
    {
        $destination = (string) $this->option('destination');
        $zoneinfoDirectory = (string) $this->option('zoneinfo-dir');
        $backup = $destination.'.fspbx-pre-tzdata-sync.bak';

        try {
            $changed = $timezones->sync($destination, $zoneinfoDirectory, $backup);
            $count = $timezones->countZones((string) file_get_contents($destination));

            if ($changed) {
                $this->info("Generated {$destination} from {$count} host tzdata rules.");
                $this->line("Rollback backup (when a previous table existed): {$backup}");
                $this->warn('FreeSWITCH was not restarted or reloaded. After rollout gates pass, run: fs_cli -x reloadxml');
            } else {
                $this->info("{$destination} already matches {$count} host tzdata rules.");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Timezone table sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
