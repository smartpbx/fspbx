<?php

namespace App\Jobs;

use App\Mail\FaxInvalidDestination;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Notifications\SendSlackFaxNotification;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class SendFaxInvalidDestinationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    private $request;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request->all();
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('fax'))];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('fax')->allow(2)->every(1)->then(function () {

            $this->request['slack_message'] = 'Someone with the email ' . $this->request['From'] . ' tried sending a fax to ' .  $this->request['invalid_number'] .  ' which is not a valid US number.';
            
            Notification::route('slack', env('SLACK_FAX_HOOK'))
                ->notify(new SendSlackFaxNotification($this->request));

            Mail::to($this->request['From'])->send(new FaxInvalidDestination($this->request));


        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });

    }
}
