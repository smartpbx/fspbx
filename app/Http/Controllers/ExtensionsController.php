<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Devices;
use App\Models\FollowMe;
use App\Models\IvrMenus;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Jobs\DeleteAppUser;
use App\Models\DeviceLines;
use App\Models\FusionCache;
use App\Models\MusicOnHold;
use App\Models\Destinations;
use App\Models\DeviceVendor;
use Illuminate\Http\Request;
use App\Jobs\SendEventNotify;
use App\Models\DeviceProfile;
use App\Models\ExtensionUser;
use App\Models\MobileAppUsers;
use App\Jobs\UpdateAppSettings;
use Illuminate\Validation\Rule;
use App\Imports\ExtensionsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use libphonenumber\PhoneNumberUtil;
use App\Models\FollowMeDestinations;
use App\Models\VoicemailDestinations;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\AssignDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use Spatie\Activitylog\Contracts\Activity;
use Propaganistas\LaravelPhone\PhoneNumber;
use App\Http\Requests\OldStoreDeviceRequest;
use App\Http\Requests\OldUpdateDeviceRequest;
use Spatie\Activitylog\Facades\CauserResolver;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException;
use Inertia\Inertia;
use App\Jobs\RemoveAppUser;


class ExtensionsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth')->except(['callerId', 'updateCallerID']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!userCheckPermission('extension_view')) {
            return redirect('/');
        }

        $domain_uuid = session('domain_uuid');
        $extensions = Extensions::where('domain_uuid', $domain_uuid)
            ->orderBy('extension')
            ->paginate(50);

        // Transform the data for the frontend
        $extensions->getCollection()->transform(function ($extension) {
            return [
                'extension_uuid' => $extension->extension_uuid,
                'extension' => $extension->extension,
                'number_alias' => $extension->number_alias,
                'description' => $extension->description,
                'enabled' => $extension->enabled,
                'created_at' => $extension->created_at,
                'updated_at' => $extension->updated_at,
            ];
        });

        if ($request->wantsJson()) {
            return response()->json($extensions);
        }

        return Inertia::render('Extensions', [
            'data' => $extensions,
            'filters' => $request->only(['search']),
            'auth' => [
                'can' => [
                    'extensions_create' => userCheckPermission('extension_add'),
                    'extensions_update' => userCheckPermission('extension_edit'),
                    'extensions_delete' => userCheckPermission('extension_delete'),
                    'extensions_import' => userCheckPermission('extension_import'),
                ]
            ],
            'routes' => [
                'current_page' => route('extensions.index'),
                'create' => route('extensions.create'),
                'store' => route('extensions.store'),
                'edit' => route('extensions.edit'),
                'update' => route('extensions.update'),
                'bulk_delete' => route('extensions.bulk-delete'),
                'bulk_update' => route('extensions.bulk-update'),
                'select_all' => route('extensions.select-all'),
            ]
        ]);
    }

    /**
     * Display page with Caller ID options.
     *
     * @return \Illuminate\Http\Response
     */
    public function callerId(Request $request)
    {
        // Find user trying to access the page
        $appUser = MobileAppUsers::where('user_id', $request->user)->first();

        // If user not found throw an error
        if (!isset($appUser)) {
            abort(403, 'Unauthorized user. Contact your administrator');
        }

        // Get all active phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
            ->where('domain_uuid', $appUser->domain_uuid)
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_enabled',
                'destination_description',
                DB::Raw("coalesce(destination_description , 'n/a') as destination_description"),
            ])
            ->sortBy('destination_description');

        // If destinaions not found throw an error
        if (!isset($destinations)) {
            abort(403, 'Unauthorized action. Contact your administrator1');
        }

        // Get extension for user accessing the page
        $extension = Extensions::find($appUser->extension_uuid);

        // If extension not found throw an error
        if (!isset($extension)) {
            abort(403, 'Unauthorized extension. Contact your administrator');
        }

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        //check if this extension already have caller IDs assigend to it
        // if yes, add TRUE column to the new array $phone_numbers
        $phone_numbers = array();
        foreach ($destinations as $destination) {
            if (isset($extension->outbound_caller_id_number) && $extension->outbound_caller_id_number <> "") {
                try {
                    $phoneNumberObject = $phoneNumberUtil->parse($destination->destination_number, 'US');
                    if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                        $destination->destination_number = $phoneNumberUtil
                            ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                    }
                } catch (NumberParseException $e) {
                    // Do nothing and leave the numner as is
                }

                if ($phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164) == (new PhoneNumber($extension->outbound_caller_id_number, "US"))->formatE164()) {
                    $destination->isCallerID = true;
                } else {
                    $destination->isCallerID = false;
                }
            } else {
                $destination->isCallerID = false;
            }
        }

        // $format = PhoneNumberFormat::NATIONAL;
        // $phone_number = phone("6467052267","US",$format);
        // dd($phone_numbers);

        return view('layouts.extensions.callerid')
            ->with('destinations', $destinations)
            ->with('national_phone_number_format', PhoneNumberFormat::NATIONAL)
            ->with('extension', $extension);
    }

    /**
     * Update caller ID for user.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateCallerID($extension_uuid)
    {
        $extension = Extensions::find($extension_uuid);
        if (!$extension) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid extension. Please, contact administrator'
                ]
            ]);
        }

        $destination = Destinations::find(request('destination_uuid'));
        if (!$destination) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid phone number ID submitted. Please, contact your administrator'
                ]
            ]);
        }

        // set causer for activity log
        CauserResolver::setCauser($extension);

        // Update the caller ID field for user's extension
        // If successful delete cache
        if (request('set') == "true") {
            try {
                $extension->outbound_caller_id_number = (new PhoneNumber($destination->destination_number, "US"))->formatE164();
            } catch (NumberParseException $e) {
                $extension->outbound_caller_id_number = $destination->destination_number;
            }
        } else {
            $extension->outbound_caller_id_number = null;
        }
        $extension->save();

        //clear fusionpbx cache
        FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);


        // If successful return success status
        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'The caller ID was successfully updated'
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    public function create()
    {
        // Check permissions
        if (!userCheckPermission('extension_add')) {
            return redirect('/');
        }

        $types = [
            ['value' => 'default', 'label' => 'Default'],
            ['value' => 'internal', 'label' => 'Internal'],
            ['value' => 'external', 'label' => 'External'],
            ['value' => 'public', 'label' => 'Public'],
        ];

        return response()->json([
            'types' => $types,
            'permissions' => [
                'extensions_create' => userCheckPermission('extension_add'),
                'extensions_update' => userCheckPermission('extension_edit'),
                'extensions_destroy' => userCheckPermission('extension_delete'),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Check permissions
        if (!userCheckPermission('extension_add')) {
            return redirect('/');
        }

        $validator = Validator::make($request->all(), [
            'extension' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:default,internal,external,public',
            'password' => 'required|string|min:6',
            'enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $extension = new Extensions();
            $extension->domain_uuid = Session::get('domain_uuid');
            $extension->extension = $request->extension;
            $extension->effective_caller_id_name = $request->name;
            $extension->user_context = $request->type;
            $extension->password = $request->password;
            $extension->enabled = $request->enabled ? 'true' : 'false';
            $extension->save();

            DB::commit();

            return response()->json([
                'message' => 'Extension created successfully',
                'extension' => [
                    'extension_uuid' => $extension->extension_uuid,
                    'extension' => $extension->extension,
                    'name' => $extension->effective_caller_id_name,
                    'type' => $extension->user_context,
                    'enabled' => $extension->enabled === 'true',
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create extension',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\Extentions $extentions
     * @return \Illuminate\Http\Response
     */
    public function show(Extensions $extensions)
    {
        //
    }


    /**
     * Display SIP Credentials for specified resource.
     *
     * @param \App\Models\Extentions $extention
     * @return \Illuminate\Http\Response
     */
    public function sipShow(Request $request, Extensions $extension)
    {

        return response()->json([
            'username' => $extension->extension,
            'password' => $extension->password,
            'domain' => $extension->domain->domain_name,
            // 'user' => $response,
            'status' => 'success',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Extension $extention
     * @return \Illuminate\Http\Response
     */
    public function edit(Extensions $extension)
    {
        // Check permissions
        if (!userCheckPermission('extension_edit')) {
            return redirect('/');
        }

        $types = [
            ['value' => 'default', 'label' => 'Default'],
            ['value' => 'internal', 'label' => 'Internal'],
            ['value' => 'external', 'label' => 'External'],
            ['value' => 'public', 'label' => 'Public'],
        ];

        return response()->json([
            'extension' => [
                'extension_uuid' => $extension->extension_uuid,
                'extension' => $extension->extension,
                'name' => $extension->effective_caller_id_name,
                'type' => $extension->user_context,
                'enabled' => $extension->enabled === 'true',
                'password' => '', // Don't send the actual password
            ],
            'types' => $types,
            'permissions' => [
                'extensions_create' => userCheckPermission('extension_add'),
                'extensions_update' => userCheckPermission('extension_edit'),
                'extensions_destroy' => userCheckPermission('extension_delete'),
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Extentions $extentions
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Extensions $extension)
    {
        // Check permissions
        if (!userCheckPermission('extension_edit')) {
            return redirect('/');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:default,internal,external,public',
            'password' => 'nullable|string|min:6',
            'enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $extension->effective_caller_id_name = $request->name;
            $extension->user_context = $request->type;
            if ($request->filled('password')) {
                $extension->password = $request->password;
            }
            $extension->enabled = $request->enabled ? 'true' : 'false';
            $extension->save();

            DB::commit();

            return response()->json([
                'message' => 'Extension updated successfully',
                'extension' => [
                    'extension_uuid' => $extension->extension_uuid,
                    'extension' => $extension->extension,
                    'name' => $extension->effective_caller_id_name,
                    'type' => $extension->user_context,
                    'enabled' => $extension->enabled === 'true',
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update extension',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Import the specified resource
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        try {

            $headings = (new HeadingRowImport)->toArray(request()->file('file'));

            // Excel::import(new ExtensionsImport, request()->file('file'));

            $import = new ExtensionsImport;
            $import->import(request()->file('file'));

            // Get array of failures and combine into html
            if ($import->failures()->isNotEmpty()) {
                $errormessage = 'Some errors were detected. Please, check the details: <ul>';
                foreach ($import->failures() as $failure) {
                    foreach ($failure->errors() as $error) {
                        $value = (isset($failure->values()[$failure->attribute()]) ? $failure->values()[$failure->attribute()] : "NULL");
                        $errormessage .= "<li>Skipping row <strong>" . $failure->row() . "</strong>. Invalid value <strong>'" . $value . "'</strong> for field <strong>'" . $failure->attribute() . "'</strong>. " . $error . "</li>";
                    }
                }
                $errormessage .= '</ul>';

                // Send response in format that Dropzone understands
                return response()->json([
                    'error' => $errormessage,
                ], 400);
            }
        } catch (Throwable $e) {
            // Log::alert($e);
            // Send response in format that Dropzone understands
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }


        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Extensions were successfully uploaded'
            ]
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Extentions $extentions
     * @return \Illuminate\Http\Response
     */
    public function destroy(Extensions $extension)
    {
        // Check permissions
        if (!userCheckPermission('extension_delete')) {
            return redirect('/');
        }

        try {
            DB::beginTransaction();

            // Delete the extension
            $extension->delete();

            // Dispatch job to remove app user if exists
            if ($extension->mobile_app) {
                RemoveAppUser::dispatch($extension->mobile_app->attributesToArray())->onQueue('default');
            }

            DB::commit();

            return response()->json([
                'message' => 'Extension deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete extension',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Restart devices for selected extensions.
     *
     * @param \App\Models\Extentions $extention
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEventNotify(Request $request, Extensions $extension)
    {

        // Get all registered devices for this domain
        $registrations = get_registrations();

        //check against registrations and add them to array
        $all_regs = [];
        foreach ($registrations as $registration) {
            if ($registration['sip-auth-user'] == $extension['extension']) {
                array_push($all_regs, $registration);
            }
        }

        foreach ($all_regs as $reg) {
            // Get the agent name
            if (preg_match('/Bria|Push|Ringotel/i', $reg['agent']) > 0) {
                $agent = "";
            } elseif (preg_match('/polycom|polyedge/i', $reg['agent']) > 0) {
                $agent = "polycom";
            } elseif (preg_match("/yealink/i", $reg['agent'])) {
                $agent = "yealink";
            } elseif (preg_match("/grandstream/i", $reg['agent'])) {
                $agent = "grandstream";
            }

            if ($agent != "") {
                $command = "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " reboot " . $reg['user'] . " " . $agent . "'";

                // Queue a job to restart the phone
                SendEventNotify::dispatch($command)->onQueue('default');
            }
        }


        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Successfully submitted restart request'
            ]
        ]);
    }

    /**
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEventNotifyAll(Request $request)
    {
        $selectedExtensionIds = $request->get('extensionIds') ?? [];
        $selectedScope = $request->get('scope') ?? 'local';
        if ($selectedScope == 'global') {
            $registrations = get_registrations('all');
        } else {
            $registrations = get_registrations();
        }
        $all_regs = [];
        if (!empty($selectedExtensionIds)) {
            foreach ($selectedExtensionIds as $extensionId) {
                $extension = Extensions::find($extensionId);
                if ($extension) {
                    foreach ($registrations as $registration) {
                        if ($registration['sip-auth-user'] == $extension['extension']) {
                            array_push($all_regs, $registration);
                        }
                    }
                }
            }
        } else {
            $all_regs = $registrations;
        }

        // logger($all_regs);

        foreach ($all_regs as $reg) {
            // Get the agent name
            if (preg_match('/Bria|Push|Ringotel/i', $reg['agent']) > 0) {
                $agent = "";
            } elseif (preg_match('/polycom|polyedge/i', $reg['agent']) > 0) {
                $agent = "polycom";
            } elseif (preg_match("/yealink/i", $reg['agent'])) {
                $agent = "yealink";
            } elseif (preg_match("/grandstream/i", $reg['agent'])) {
                $agent = "grandstream";
            } else {
                /**
                 * Sometimes it throws an exception
                 * "message": "Undefined variable $agent",
                 * "exception": "ErrorException",
                 * "file": "/var/www/freeswitchpbx/app/Http/Controllers/ExtensionsController.php",
                 *
                 * So this line prevents it
                 */
                $agent = "";
            }

            if (!empty($agent)) {
                $command = "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " reboot " . $reg['user'] . " " . $agent . "'";
                // Queue a job to restart the phone
                logger($command);
                SendEventNotify::dispatch($command)->onQueue('default');
            }
        }

        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Successfully submitted bulk restart request'
            ]
        ]);
    }

    public function assignDevice(AssignDeviceRequest $request, Extensions $extension)
    {
        $inputs = $request->validated();

        $deviceExist = DeviceLines::query()->where(['device_uuid' => $inputs['device_uuid']])->first();

        if ($deviceExist) {
            $deviceExist->delete();
            /*return response()->json([
                'status' => 'alert',
                'message' => 'Device is already assigned.'
            ]);*/
        }

        $extension->deviceLines()->create([
            'device_uuid' => $inputs['device_uuid'],
            'line_number' => $inputs['line_number'] ?? '1',
            'server_address' => Session::get('domain_name'),
            'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
            'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
            'server_address_primary' => get_domain_setting('server_address_primary'),
            'server_address_secondary' => get_domain_setting('server_address_secondary'),
            'display_name' => $extension->extension,
            'user_id' => $extension->extension,
            'auth_id' => $extension->extension,
            'label' => $extension->extension,
            'password' => $extension->password,
            'sip_port' => get_domain_setting('line_sip_port'),
            'sip_transport' => get_domain_setting('line_sip_transport'),
            'register_expires' => get_domain_setting('line_register_expires'),
            'enabled' => 'true',
        ]);

        $device = Devices::where('device_uuid', $inputs['device_uuid'])->firstOrFail();
        $device->device_label = $extension->extension;
        $device->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Device has been assigned successfully.'
        ]);
    }

    public function unAssignDevice(Extensions $extension, DeviceLines $deviceLine)
    {
        if ($deviceLine->device->device_label == $extension->extension) {
            $deviceLine->device->device_label = "";
            $deviceLine->device->save();
        }

        $deviceLine->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Device has been unassigned successfully.'
        ]);
    }

    public function clearCallforwardDestination(Extensions $extension, Request $request)
    {
        $type = $request->route('type');
        switch ($type) {
            case 'all':
                $extension->forward_all_destination = '';
                $extension->forward_all_enabled = 'false';
                break;
            case 'user_not_registered':
                $extension->forward_user_not_registered_destination = '';
                $extension->forward_user_not_registered_enabled = 'false';
                break;
            case 'no_answer':
                $extension->forward_no_answer_destination = '';
                $extension->forward_no_answer_enabled = 'false';
                break;
            case 'busy':
                $extension->forward_busy_destination = '';
                $extension->forward_busy_enabled = 'false';
                break;
            default:
                return response()->json([
                    'status' => 'alert',
                    'message' => 'Unknown type.'
                ]);
        }

        $extension->save();

        return response()->json([
            'status' => 'success',
            'message' => 'CallForward destination has been disabled successfully.'
        ]);
    }

    private function getDestinationExtensions()
    {
        $extensions = Extensions::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('extension')
            ->get();
        $ivrMenus = IvrMenus::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('ivr_menu_extension')
            ->get();
        $ringGroups = RingGroups::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('ring_group_extension')
            ->get();

        /* NOTE: disabling voicemails as a call forward destination
         * $voicemails = Voicemails::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('voicemail_id')
            ->get();*/
        return [
            'Extensions' => $extensions,
            'Ivr Menus' => $ivrMenus,
            'Ring Groups' => $ringGroups,
            //'Voicemails' => $voicemails
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDeviceRequest  $request
     * @return JsonResponse
     */
    public function oldStoreDevice(OldStoreDeviceRequest $request, Extensions $extension): JsonResponse
    {
        $inputs = $request->validated();

        if ($inputs['extension_uuid']) {
            $extension = Extensions::find($inputs['extension_uuid']);
        } else {
            $extension = null;
        }

        $device = new Devices();
        $device->fill([
            'device_address' => tokenizeMacAddress($inputs['device_address']),
            'device_label' => $extension->extension ?? null,
            'device_vendor' => explode("/", $inputs['device_template'])[0],
            'device_enabled' => 'true',
            'device_enabled_date' => date('Y-m-d H:i:s'),
            'device_template' => $inputs['device_template'],
            'device_profile_uuid' => $inputs['device_profile_uuid'],
            'device_description' => '',
        ]);
        $device->save();

        if ($extension) {
            // Create device lines
            $device->lines = new DeviceLines();
            $device->lines->fill([
                'device_uuid' => $device->device_uuid,
                'line_number' => '1',
                'server_address' => Session::get('domain_name'),
                'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
                'server_address_primary' => get_domain_setting('server_address_primary'),
                'server_address_secondary' => get_domain_setting('server_address_secondary'),
                'display_name' => $extension->extension,
                'user_id' => $extension->extension,
                'auth_id' => $extension->extension,
                'label' => $extension->extension,
                'password' => $extension->password,
                'sip_port' => get_domain_setting('line_sip_port'),
                'sip_transport' => get_domain_setting('line_sip_transport'),
                'register_expires' => get_domain_setting('line_register_expires'),
                'enabled' => 'true',
            ]);
            $device->lines->save();
        }


        return response()->json([
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been created and assigned.'
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Request  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function oldEditDevice(Request $request, Extensions $extension, Devices $device): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([
                'message' => 'XHR request expected'
            ], 405);
        }

        if ($device->extension()) {
            $device->extension_uuid = $device->extension()->extension_uuid;
        }

        $device->device_address = formatMacAddress($device->device_address);
        $device->update_path = route('devices.update', $device);
        $device->options = [
            'templates' => getVendorTemplateCollection(),
            'profiles' => getProfileCollection($device->domain_uuid),
            'extensions' => getExtensionCollection($device->domain_uuid)
        ];

        return response()->json([
            'status' => 'success',
            'device' => $device
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateDeviceRequest  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function oldUpdateDevice(OldUpdateDeviceRequest $request, Extensions $extension, Devices $device): JsonResponse
    {
        $inputs = $request->validated();
        $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0];
        $device->update($inputs);

        if ($request['extension_uuid']) {
            $extension = Extensions::find($request['extension_uuid']);
            if (($device->extension() && $device->extension()->extension_uuid != $request['extension_uuid']) or !$device->extension()) {
                $deviceLinesExist = DeviceLines::query()->where(['device_uuid' => $device->device_uuid])->first();
                if ($deviceLinesExist) {
                    $deviceLinesExist->delete();
                }

                // Create device lines
                $deviceLines = new DeviceLines();
                $deviceLines->fill([
                    'device_uuid' => $device->device_uuid,
                    'line_number' => '1',
                    'server_address' => Session::get('domain_name'),
                    'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                    'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
                    'server_address_primary' => get_domain_setting('server_address_primary'),
                    'server_address_secondary' => get_domain_setting('server_address_secondary'),
                    'display_name' => $extension->extension,
                    'user_id' => $extension->extension,
                    'auth_id' => $extension->extension,
                    'label' => $extension->extension,
                    'password' => $extension->password,
                    'sip_port' => get_domain_setting('line_sip_port'),
                    'sip_transport' => get_domain_setting('line_sip_transport'),
                    'register_expires' => get_domain_setting('line_register_expires'),
                    'enabled' => 'true',
                    'domain_uuid' => $device->domain_uuid
                ]);
                $deviceLines->save();
                $device->device_label = $extension->extension;
                $device->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been updated.'
        ]);
    }
}
