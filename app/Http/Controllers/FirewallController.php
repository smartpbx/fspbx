<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\EventGuardLogs;
use App\Models\MessageSetting;
use App\Models\SmsDestinations;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Services\SinchMessageProvider;
use Symfony\Component\Process\Process;
use App\Services\CommioMessageProvider;
use Illuminate\Support\Facades\Session;
use App\Jobs\SendSmsNotificationToSlack;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FirewallController extends Controller
{

    // public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Firewall';
    protected $searchable = ['hostname', 'ip', 'filter', 'extension', 'user_agent'];

    public function __construct()
    {
        // $this->model = new Messages();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("firewall_list_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('firewall.index'),
                    'unblock' => route('firewall.unblock'),
                    // 'select_all' => route('messages.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    // 'retry' => route('messages.retry'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData($paginate = 50)
    {

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'ip'); // Default to 'created_at'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

        $data = $this->builder($this->filters);

        // Apply pagination manually
        if ($paginate) {
            $data = $this->paginateCollection($data, $paginate);
        }

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {

        // get a list of blocked IPs from iptables
        $data =  $this->getBlockedIps();

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($this->sortOrder === 'asc') {
            $data = $data->sortBy($this->sortField);
        } else {
            $data = $data->sortByDesc($this->sortField);
        }

        // Get a list of IPs blocked by Event Guard
        $eventGuardLogs = $this->getEventGuardLogs();

        $data = $this->combineEventGuardLogs($data, $eventGuardLogs);

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    // Pass the collection by reference to modify it directly
                    $data = $this->$method($data, $value);
                }
            }
        }

        // logger($data);

        return $data->values(); // Ensure re-indexing of the collection
    }

    /**
     * @param $collection
     * @param $value
     * @return void
     */
    protected function filterSearch($collection, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $collection = $collection->filter(function ($item) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (stripos($item[$field], $value) !== false) {
                    return true;
                }
            }
            return false;
        });

        return $collection;
    }


    public function getBlockedIps()
    {
        $result = $this->getIptablesRules();

        $blockedIps = [];
        $currentChain = '';
        $lines = explode("\n", $result);

        $hostname = gethostname();

        foreach ($lines as $line) {
            // Detect the start of a new chain
            if (preg_match('/^Chain\s+(\S+)/', $line, $matches)) {
                $currentChain = $matches[1];
                continue;
            }

            // Check if the line contains a DROP or REJECT action
            if (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false) {
                // Extract the source IP address 
                $parts = preg_split('/\s+/', $line);
                if (isset($parts[4]) && filter_var($parts[4], FILTER_VALIDATE_IP)) {
                    $blockedIps[] = [
                        'uuid' => Str::uuid()->toString(),
                        'hostname' => $hostname,
                        'ip' => $parts[4],
                        'extension' => null,
                        'user_agent' => null,
                        'filter' => $currentChain,
                        'status' => 'blocked',
                    ];
                }
            }
        }

        // Return the list of blocked IPs, ensuring uniqueness
        // return array_unique($blockedIps, SORT_REGULAR);

        // Convert the array to a Laravel collection and return it
        return collect($blockedIps)->unique();
    }

    public function getIptablesRules()
    {
        // Get the full iptables output including all chains
        $process = new Process(['sudo', 'iptables', '-L', '-n', '--line-numbers']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        return $output;
    }

    public function getEventGuardLogs()
    {
        $logs = EventGuardLogs::select(
            'event_guard_log_uuid',
            'hostname',
            'log_date',
            'filter',
            'ip_address',
            'extension',
            'user_agent',
            'log_status'
        )
            ->get();

        return $logs;
    }


    /**
     * Paginate a given collection.
     *
     * @param \Illuminate\Support\Collection $items
     * @param int $perPage
     * @param int|null $page
     * @param array $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateCollection($items, $perPage = 50, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            $options
        );
    }


    /**
     * Combine event guard logs with blocked IPs data
     *
     * @param  Collection  $data
     * @param  Collection  $eventGuardLogs
     * @return Collection
     */
    protected function combineEventGuardLogs($data, $eventGuardLogs)
    {
        // Group event guard logs by IP address for easy lookup
        $groupedLogs = $eventGuardLogs->groupBy('ip_address');

        // Add additional fields from event guard logs to the data array
        return $data->map(function ($item) use ($groupedLogs) {
            $ip = $item['ip'];
            if (isset($groupedLogs[$ip])) {
                $log = $groupedLogs[$ip]->first();
                $item['uuid'] = $log->event_guard_log_uuid;
                $item['extension'] = $log->extension;
                $item['user_agent'] = $log->user_agent;
                $item['date'] = $log->log_date_formatted;
                // Add any other fields you need here
            }
            return $item;
        });
    }


    public function destroy()
    {
        try {
            // Unblock the IPs in fail2ban
            foreach (request('items') as $ip) {
                $fail2banProcess = new Process(['sudo', 'fail2ban-client', 'unban', 'ip', $ip]);
                $fail2banProcess->run();

                if (!$fail2banProcess->isSuccessful()) {
                    // logger()->error("Failed to unban IP $ip in fail2ban: " . $fail2banProcess->getErrorOutput());
                    throw new ProcessFailedException($fail2banProcess);
                } else {
                    logger("IP $ip is succesfully unbanned in fail2ban");
                }
            }

            $result = $this->getIptablesRules();

            $lines = explode("\n", $result);
            $rulesToDelete = [];

            $currentChain = null;

            foreach ($lines as $line) {
                // Detect the start of a new chain
                if (preg_match('/^Chain\s+(\S+)/', $line, $matches)) {
                    $currentChain = $matches[1];
                    continue;
                }

                // Check each IP in the provided list
                foreach (request('items') as $ip) {
                    // Check if the line contains the IP address and a DROP/REJECT action
                    if (strpos($line, $ip) !== false && (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false)) {
                        // Extract the line number (first column)
                        $parts = preg_split('/\s+/', $line);
                        if (isset($parts[0]) && is_numeric($parts[0]) && $currentChain) {
                            $rulesToDelete[] = ['chain' => $currentChain, 'line' => $parts[0]];
                        }
                    }
                }
            }

            if (!empty($rulesToDelete)) {
                foreach ($rulesToDelete as $rule) {
                    // Delete the rule from the specified chain
                    $deleteProcess = new Process(['sudo', 'iptables', '-D', $rule['chain'], $rule['line']]);
                    $deleteProcess->run();

                    if (!$deleteProcess->isSuccessful()) {
                        throw new ProcessFailedException($deleteProcess);
                    } else {
                        logger("IP $ip is succesfully unbanned in iptables");
                    }
                }
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request to unblock IP addresses was successful']]
            ], 200);


            return;
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))
                ->get();

            foreach ($items as $item) {
                // get originating extension
                $extension = Extensions::find($item->extension_uuid);

                // check if there is an email destination
                $messageSettings = MessageSetting::where('domain_uuid', $item->domain_uuid)
                    ->where('destination', $item->destination)
                    ->first();

                if (!$extension && !$messageSettings && !$messageSettings->email) {
                    throw new Exception('No assigned destination found.');
                }


                if ($item->direction == "out") {

                    //Get message config
                    $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($extension->extension, $item->domain_uuid);
                    $carrier =  $phoneNumberSmsConfig->carrier;
                    // logger($carrier);

                    //Determine message provider
                    $messageProvider = $this->getMessageProvider($carrier);

                    //Store message in the log database
                    $item->status = "Queued";
                    $item->save();

                    // Send message
                    $messageProvider->send($item->message_uuid);
                }

                if ($item->direction == "in") {
                    $org_id = DomainSettings::where('domain_uuid', $item->domain_uuid)
                        ->where('domain_setting_category', 'app shell')
                        ->where('domain_setting_subcategory', 'org_id')
                        ->value('domain_setting_value');

                    if (is_null($org_id)) {
                        throw new \Exception("From: " . $item->source . " To: " . $item->destination . " \n Org ID not found");
                    }

                    if ($extension) {
                        // Logic to deliver the SMS message using a third-party Ringotel API,
                        try {
                            $response = Http::ringotel_api()
                                ->withBody(json_encode([
                                    'method' => 'message',
                                    'params' => [
                                        'orgid' => $org_id,
                                        'from' => $item->source,
                                        'to' => $extension->extension,
                                        'content' => $item->message
                                    ]
                                ]), 'application/json')
                                ->post('/')
                                ->throw()
                                ->json();

                            $this->updateMessageStatus($item, $response);
                        } catch (\Throwable $e) {
                            logger("Error delivering SMS to Ringotel: {$e->getMessage()}");
                            SendSmsNotificationToSlack::dispatch("*Inbound SMS Failed*. From: " . $item->source . " To: " . $item->extension . "\nError delivering SMS to Ringotel")->onQueue('messages');
                            return false;
                        }
                    }

                    if ($messageSettings && $messageSettings->email) {
                        $attributes['orgid'] = $org_id;
                        $attributes['from'] = $item->source;
                        $attributes['email_to'] = $messageSettings->email;
                        $attributes['message'] = $item->message;
                        $attributes['email_subject'] = 'SMS Notification: New Message from ' . $item->source;
                        // $attributes['smtp_from'] = config('mail.from.address');

                        // Logic to deliver the SMS message using email
                        // This method should return a boolean indicating whether the message was sent successfully.
                        Mail::to($messageSettings->email)->send(new SmsToEmail($attributes));

                        if ($item->status = "queued") {
                            $item->status = 'emailed';
                        }
                        $item->save();
                    }
                }
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Selected message(s) scheduled for sending']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . PHP_EOL);
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
