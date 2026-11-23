{{-- version: 1.0.0 | HT881/HT841 (FXO gateway) | smartpbx custom native template, ported from the legacy Smarty file (patchbay#4352). Edit in git: smartpbx/fspbx smartpbx/native-templates/grandstream-algo/ --}}
@switch($flavor)

@case('cfgmac.xml')
@php
    // Normalise lines exactly as legacy provision.php did, so P47/P48 and the register/transport values match the legacy bytes.
    $gl = [];
    foreach ($lines as $n => $l) {
        $t = strtolower((string) ($l['sip_transport'] ?? ''));
        $l['sip_transport'] = match ($t) { 'tcp' => 'tcp', 'tls or tcp', 'tls' => 'tls', 'dnssrv', 'dns srv' => 'dns srv', 'udp' => 'udp', default => 'tcp' };
        $exp = $l['register_expires'] ?? null;
        if (empty($exp)) { $exp = 120; }
        $l['register_expires'] = round($exp / 60);
        if (empty($l['sip_port'])) { $l['sip_port'] = ((string) $n === '' || (string) $n === '1') ? '5060' : '506' . ($n + 1); }
        $l['outbound_proxy'] = $l['outbound_proxy_primary'] ?? null;
        $gl[$n] = $l;
    }
@endphp
<?xml version="1.0" encoding="UTF-8"?>
<gs_provision version="1">
  <config version="1">
    <!-- Config upgrade via HTTPS (same as the HT80x templates). -->
    <P212>2</P212>
    {{-- Provisioning URL: same setting as legacy (grandstream_config_server_path via default > domain > device). Cutover sets it to the /prov/ URL; rollback sets it back to the legacy URL. Unset or empty falls back to the legacy URL (never provision_base_url, which is always /prov/). 'none' renders empty P237 like legacy. --}}
    @if(($settings['grandstream_config_server_path'] ?? null) === 'none')
    <P237></P237>
    @elseif(($settings['grandstream_config_server_path'] ?? '') !== '')
    <P237>{{ $settings['grandstream_config_server_path'] }}</P237>
    @else
    <P237>https://provision.smartpbx.io/app/provision</P237>
    @endif

    <!-- Disable unused FXS profile/port. -->
    <P271>0</P271>
    <P31>0</P31>
    <P4595>0</P4595>

    <!-- FXO Profile 1. -->
    <P401>1</P401>
    <P747>{{ ($gl[1]['server_address'] ?? '') }}:{{ ($gl[1]['sip_port'] ?? '') }}</P747>
    <P748>{{ ($gl[1]['outbound_proxy_primary'] ?? '') }}:{{ ($gl[1]['sip_port'] ?? '') }}</P748>
    <P8618>{{ $domain_name }}</P8618>
    <P730>2</P730>
    <P731>1</P731>
    <P830>@if(($gl[1]['sip_transport'] ?? null) == 'tcp')1{{ '' }}@elseif(($gl[1]['sip_transport'] ?? null) == 'tls')2{{ '' }}@else()0{{ '' }}@endif()</P830>
    <P732>2</P732>
    <P2430>0</P2430>
    <P471>20</P471>
    <P813>1</P813>
    <P743>1</P743>

    <!-- FXO Port 1. -->
    <P4061>{{ ($gl[1]['user_id'] ?? '') }}</P4061>
    <P4091>{{ ($gl[1]['auth_id'] ?? '') }}</P4091>
    <P4121>{{ ($gl[1]['password'] ?? '') }}</P4121>
    <P4181>{{ ($gl[1]['display_name'] ?? '') }}</P4181>
    <P4151>1</P4151>
    <P4301>0</P4301>
    <P4596>1</P4596>

    <!-- FXO Port 2. -->
    <P4062>{{ ($gl[2]['user_id'] ?? '') }}</P4062>
    <P4092>{{ ($gl[2]['auth_id'] ?? '') }}</P4092>
    <P4122>{{ ($gl[2]['password'] ?? '') }}</P4122>
    <P4182>{{ ($gl[2]['display_name'] ?? '') }}</P4182>
    <P4152>1</P4152>
    <P4302>0</P4302>
    <P4597>1</P4597>

    <!-- FXO Port 3. Renders only when device line 3 exists, so a 2-port site still
         gets these blank and disabled exactly as before. Fixed 2026-08-06: these were
         hardcoded empty, leaving ports 3-4 dead on every 4-port HT841. -->
    <P4063>@if(isset($gl[3]['user_id'])){{ ($gl[3]['user_id'] ?? '') }}@endif()</P4063>
    <P4093>@if(isset($gl[3]['auth_id'])){{ ($gl[3]['auth_id'] ?? '') }}@endif()</P4093>
    <P4123>@if(isset($gl[3]['password'])){{ ($gl[3]['password'] ?? '') }}@endif()</P4123>
    <P4183>@if(isset($gl[3]['display_name'])){{ ($gl[3]['display_name'] ?? '') }}@endif()</P4183>
    <P4153>1</P4153>
    <P4303>0</P4303>
    <P4598>@if(isset($gl[3]['user_id']))1{{ '' }}@else()0{{ '' }}@endif()</P4598>

    <!-- FXO Port 4. Renders only when device line 4 exists, so a 2-port site still
         gets these blank and disabled exactly as before. Fixed 2026-08-06: these were
         hardcoded empty, leaving ports 3-4 dead on every 4-port HT841. -->
    <P4064>@if(isset($gl[4]['user_id'])){{ ($gl[4]['user_id'] ?? '') }}@endif()</P4064>
    <P4094>@if(isset($gl[4]['auth_id'])){{ ($gl[4]['auth_id'] ?? '') }}@endif()</P4094>
    <P4124>@if(isset($gl[4]['password'])){{ ($gl[4]['password'] ?? '') }}@endif()</P4124>
    <P4184>@if(isset($gl[4]['display_name'])){{ ($gl[4]['display_name'] ?? '') }}@endif()</P4184>
    <P4154>1</P4154>
    <P4304>0</P4304>
    <P4599>@if(isset($gl[4]['user_id']))1{{ '' }}@else()0{{ '' }}@endif()</P4599>

    <!-- FXO Port 5. P-value numbers extrapolated from ports 1-4 (+1 per port); NOT verified for HT881 (bench probe). Off unless setting grandstream_ht881_ports_5_8 = 1. -->
    @if(($settings['grandstream_ht881_ports_5_8'] ?? '0') == '1' && isset($gl[5]['user_id']))
    <P4065>{{ $gl[5]['user_id'] ?? '' }}</P4065>
    <P4095>{{ $gl[5]['auth_id'] ?? '' }}</P4095>
    <P4125>{{ $gl[5]['password'] ?? '' }}</P4125>
    <P4185>{{ $gl[5]['display_name'] ?? '' }}</P4185>
    <P4155>1</P4155>
    <P4305>0</P4305>
    @endif

    <!-- FXO Port 6. P-value numbers extrapolated from ports 1-4 (+1 per port); NOT verified for HT881 (bench probe). Off unless setting grandstream_ht881_ports_5_8 = 1. -->
    @if(($settings['grandstream_ht881_ports_5_8'] ?? '0') == '1' && isset($gl[6]['user_id']))
    <P4066>{{ $gl[6]['user_id'] ?? '' }}</P4066>
    <P4096>{{ $gl[6]['auth_id'] ?? '' }}</P4096>
    <P4126>{{ $gl[6]['password'] ?? '' }}</P4126>
    <P4186>{{ $gl[6]['display_name'] ?? '' }}</P4186>
    <P4156>1</P4156>
    <P4306>0</P4306>
    @endif

    <!-- FXO Port 7. P-value numbers extrapolated from ports 1-4 (+1 per port); NOT verified for HT881 (bench probe). Off unless setting grandstream_ht881_ports_5_8 = 1. -->
    @if(($settings['grandstream_ht881_ports_5_8'] ?? '0') == '1' && isset($gl[7]['user_id']))
    <P4067>{{ $gl[7]['user_id'] ?? '' }}</P4067>
    <P4097>{{ $gl[7]['auth_id'] ?? '' }}</P4097>
    <P4127>{{ $gl[7]['password'] ?? '' }}</P4127>
    <P4187>{{ $gl[7]['display_name'] ?? '' }}</P4187>
    <P4157>1</P4157>
    <P4307>0</P4307>
    @endif

    <!-- FXO Port 8. P-value numbers extrapolated from ports 1-4 (+1 per port); NOT verified for HT881 (bench probe). Off unless setting grandstream_ht881_ports_5_8 = 1. -->
    @if(($settings['grandstream_ht881_ports_5_8'] ?? '0') == '1' && isset($gl[8]['user_id']))
    <P4068>{{ $gl[8]['user_id'] ?? '' }}</P4068>
    <P4098>{{ $gl[8]['auth_id'] ?? '' }}</P4098>
    <P4128>{{ $gl[8]['password'] ?? '' }}</P4128>
    <P4188>{{ $gl[8]['display_name'] ?? '' }}</P4188>
    <P4158>1</P4158>
    <P4308>0</P4308>
    @endif
  </config>
</gs_provision>
@break

@endswitch
