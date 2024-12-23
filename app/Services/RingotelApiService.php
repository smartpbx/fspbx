<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\DTO\RingotelOrganizationDTO;
use Illuminate\Support\Facades\Http;

class RingotelApiService
{
    // protected $apiUrl;
    protected $timeout = 30;

    public function __construct()
    {
        // $this->apiUrl = config('services.third_party_api.url');
    }

    public function createOrganization($params)
    {
        // Prepare the payload
        $data = [
            'method' => 'createOrganization',
            'params' => [
                'name' => $params['organization_name'],
                'region' => $params['region'],
                'domain' => $params['organization_domain'],
                'packageid' => (int) $params['package'],
                'params' => [
                    'hidePassInEmail' => $params['dont_send_user_credentials'],
                ],
            ],
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to activate organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function getOrganizations()
    {
        $data = array(
            'method' => 'getOrganizations',
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to retrieve organizations");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return collect($response['result'])->map(function ($item) {
            return RingotelOrganizationDTO::fromArray($item);
        });
    }

    public function createConnection($params)
    {

        // Build codecs array based on enabled flags
        $codecs = [];

        if ($params['g711u_enabled']) {
            $codecs[] = [
                'codec' => 'G.711 Ulaw',
                'frame' => 20,
            ];
        }

        if ($params['g711a_enabled']) {
            $codecs[] = [
                'codec' => 'G.711 Alaw',
                'frame' => 20,
            ];
        }

        if ($params['g729_enabled']) {
            $codecs[] = [
                'codec' => 'G.729',
                'frame' => 20,
            ];
        }

        if ($params['opus_enabled']) {
            $codecs[] = [
                'codec' => 'OPUS',
                'frame' => 20,
            ];
        }

        // Build data array
        $data = array(
            'method' => 'createBranch',
            'params' => array(
                "orgid" => $params['org_id'],
                'name' => $params['connection_name'],
                'address' => $params['domain'] . ":" . $params['port'],
                'provision' => array(
                    'protocol' => $params['protocol'],
                    'noverify' => $params['dont_verify_server_certificate'],
                    'nosrtp' => $params['disable_srtp'],
                    'multitenant' => $params['multitenant'],
                    'norec' => !$params['allow_call_recording'],
                    // 'internal' => false,
                    // 'sms' => false,
                    'maxregs' => (int) $params['max_registrations'],
                    // 'private' => false,
                    'dtmfmode' => 'rfc2833',
                    'regexpires' => (int) $params['registration_ttl'],
                    'proxy' => array(
                        'paddr' => $params['proxy'],
                        'pauth' => '',
                        'ppass' => '',
                    ),
                    'httpsproxy' => array(
                        'address' => '',
                    ),
                    'certificate' => '',
                    'tones' => array(
                        'Ringback2' => 'Ringback 1',
                        'Progress' => 'Progress 1',
                        'Ringback' => 'United States',
                    ),
                    '1push' => $params['one_push'],
                    'noptions' => !$params['show_call_settings'],
                    'features' => 'pbx',
                    // "speeddial" => array(
                    //     [
                    //         'number' => '*97',
                    //         'title' => 'Voicemail'
                    //     ]
                    // ),
                    // 'vmail' => [
                    //     'ext' => '*97',
                    //     'name' => 'Voicemail',
                    //     'mess' => 'You have a new message',
                    //     'off' => '',
                    //     'on' => ''
                    // ],
                    // 'dnd' => [
                    //     'off' => '*79',
                    //     'on' => '*78'
                    // ],
                    // 'forwarding' => [
                    //     'cfuon' => '',
                    //     'cfboff' => '',
                    //     'cfon' => '*72',
                    //     'cfbon' => '',
                    //     'cfuoff' => '',
                    //     'cfoff' => '*73'
                    // ],

                    'codecs' => $codecs,
                    'app' => array(
                        'g711' => !$params['app_opus_codec'],

                    ),

                )
            )
        );

        // logger($data);

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to activate organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function getUsersByOrgId($orgId)
    {
        $data = [
            'method' => 'getUsers',
            'orgid' => $orgId,
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) use ($orgId) {
                throw new \Exception("Unable to retrieve users for organization ID: $orgId");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function matchLocalDomains($organizations)
    {

        $orgs = DB::table('v_domain_settings')
            ->join('v_domains', 'v_domains.domain_uuid', '=', 'v_domain_settings.domain_uuid')
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->get([
                'v_domain_settings.domain_uuid',
                'domain_setting_value AS org_id',
                'domain_name',
                'domain_description',
            ]);

        $orgArray = $organizations->map(function ($organization) use ($orgs) {
            foreach ($orgs as $org) {
                if ($organization->id == $org->org_id) {
                    $organization->domain_uuid = $org->domain_uuid;
                    $organization->domain_name = $org->domain_name;
                    $organization->domain_description = $org->domain_description;
                }
            }
            return $organization;
        });

        return $orgArray;
    }
}
