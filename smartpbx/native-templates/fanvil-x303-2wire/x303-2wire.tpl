{{-- version: 1.0.0 --}}
{{-- Custom template: Fanvil X303-2 Wire. Ported from legacy fanvil/x303-2wire; cloned from stock XU Series 1.1.3. --}}
{{-- See smartpbx/native-templates/fanvil-x303-2wire/VERIFY.md --}}

@switch($flavor)

@case('mac.cfg')
@php
    // Legacy {if isset($v)}{$v}{else}D{/if}: a missing/null setting falls back to the default.
    $s = static fn (string $key, mixed $default = '') => $settings[$key] ?? $default;

    // Legacy $account.N.field. Lines are keyed by line number.
    $L = static fn (int $n, string $f) => $lines[$n][$f] ?? '';
    $T = static fn (int $n) => strtolower(trim((string) ($lines[$n]['sip_transport'] ?? '')));
    $ttl = static fn (int $n) => isset($lines[$n]) ? (($lines[$n]['register_expires'] ?? null) ?: 120) : '';
    $addr = static fn (int $n) => isset($lines[$n])
        ? (($lines[$n]['server_address'] ?? null) ?: (($lines[$n]['server_address_primary'] ?? null) ?: ($domain_name ?? '')))
        : '';

    // Key type map: SIP line = 2 (value SIPn), unassigned = 0, everything else = 1 (value "<value>@<line>/<code>").
    // Legacy rows carry Fanvil codes already (c park, f speed dial, ba/bb BLF); native key templates carry logical types.
    $fanvilKey = static function (array $key) use ($lines): array {
        $type = strtolower(trim((string) ($key['type'] ?? '')));
        $line = (int) ($key['line'] ?? 1);
        $value = (string) ($key['value'] ?? '');

        if ($type === 'line' || $type === '1') {
            if ($type === 'line') {
                foreach ($lines as $lineNumber => $lineData) {
                    if ($value !== '' && $value === (string) ($lineData['auth_id'] ?? '')) {
                        $line = (int) $lineNumber;
                        break;
                    }
                }
            }

            return [2, 'SIP' . max(1, $line)];
        }

        if (in_array($type, ['', '3', 'unassigned', 'n/a'], true)) {
            return [0, ''];
        }

        if ($type === 'dtmf') {
            return [5, $value];
        }

        $code = match ($type) {
            'speed_dial', 'speeddial' => 'f',
            'park' => 'c',
            'blf' => 'ba',
            'check_voicemail' => 'bc',
            default => $type,
        };

        return [1, $value === '' ? '' : "{$value}@{$line}/{$code}"];
    };
@endphp
<?xml version="1.0" encoding="UTF-8"?>
<sysConf>
    <Version>2.0000000000</Version>
    <net>
        <WANTYPE>0</WANTYPE>
        <WANIP>192.168.1.179</WANIP>
        <WANSubnetMask>255.255.255.0</WANSubnetMask>
        <WANGateway>192.168.1.1</WANGateway>
        <DomainName></DomainName>
        <PrimaryDNS>{{ $s('dns_server_primary', '8.8.8.8') }}</PrimaryDNS>
        <SecondaryDNS>{{ $s('dns_server_secondary', '208.67.222.222') }}</SecondaryDNS>
        <EnableDHCP>1</EnableDHCP>
        <DHCPAutoDNS>1</DHCPAutoDNS>
        <DHCPAutoTime>1</DHCPAutoTime>
        <DHCPOption100-101>1</DHCPOption100-101>
        <UseVendorClassID>0</UseVendorClassID>
        <VendorClassID>Fanvil X303-2 Wire</VendorClassID>
        <EnablePPPoE>0</EnablePPPoE>
        <PPPoEUser>user123</PPPoEUser>
        <PPPoEPassword></PPPoEPassword>
        <ARPCacheLife>2</ARPCacheLife>
        <MTU>1500</MTU>
        <DHCPHostname>X303-2 Wire</DHCPHostname>
        <WAN6IP></WAN6IP>
        <WAN6IPPREFIX></WAN6IPPREFIX>
        <WAN6Gateway></WAN6Gateway>
        <Domain6Name></Domain6Name>
        <PrimaryDNS6></PrimaryDNS6>
        <SecondaryDNS6></SecondaryDNS6>
        <EnableDHCP6>1</EnableDHCP6>
        <DHCP6AutoDNS>1</DHCP6AutoDNS>
        <DHCP6AutoTime>0</DHCP6AutoTime>
        <UseVendor6ClassID>0</UseVendor6ClassID>
        <Vendor6ClassID></Vendor6ClassID>
        <DHCP6Hostname></DHCP6Hostname>
        <NetGlobal>
            <NetPriority>01</NetPriority>
        </NetGlobal>
    </net>
    <mm>
        <G723BitRate>1</G723BitRate>
        <HandsetAudioQuality>0</HandsetAudioQuality>
        <ILBCPayloadType>97</ILBCPayloadType>
        <ILBCPayloadLen>20</ILBCPayloadLen>
        <AMRPayloadType>108</AMRPayloadType>
        <AMRWBPayloadType>109</AMRWBPayloadType>
        <G726-16PayloadType>103</G726-16PayloadType>
        <G726-24PayloadType>104</G726-24PayloadType>
        <G726-32PayloadType>102</G726-32PayloadType>
        <G726-40PayloadType>105</G726-40PayloadType>
        <DtmfPayloadType>101</DtmfPayloadType>
        <OpusPayloadType>107</OpusPayloadType>
        <OpusSampleRate>0</OpusSampleRate>
        <VAD>0</VAD>
        <H264PayloadType>117</H264PayloadType>
        <H264PacketMode>0</H264PacketMode>
        <H264Profile>0</H264Profile>
        <ResvAudioBand>0</ResvAudioBand>
        <H265PayloadType>98</H265PayloadType>
        <Mpa-robustPayloadType>121</Mpa-robustPayloadType>
        <PSPayloadType>96</PSPayloadType>
        <RTPInitialPort>10000</RTPInitialPort>
        <RTPPortQuantity>1000</RTPPortQuantity>
        <RTPKeepAlive>1</RTPKeepAlive>
        <RTPRelay>0</RTPRelay>
        <RTCPCNAMEUser></RTCPCNAMEUser>
        <RTCPCNAMEHost></RTCPCNAMEHost>
        <SelectYourTone>{{ $s('fanvil_country_toneset', '11') }}</SelectYourTone>
        <SidetoneGAIN>1</SidetoneGAIN>
        <PlayEgressDTMF>0</PlayEgressDTMF>
        <AudioProfile></AudioProfile>
        <DialTone>350+440/0</DialTone>
        <RingbackTone>440+480/2000,0/4000</RingbackTone>
        <BusyTone>480+620/500,0/500</BusyTone>
        <CongestionTone></CongestionTone>
        <CallwaitingTone>425/100,0/100,425/100,0/9700,425/100,0/100,425/100,0/30000</CallwaitingTone>
        <HoldingTone></HoldingTone>
        <ErrorTone></ErrorTone>
        <StutterTone></StutterTone>
        <InformationTone></InformationTone>
        <DialRecallTone>350+440/100,0/100,350+440/100,0/100,350+440/100,0/100,350+440/0</DialRecallTone>
        <MessageTone></MessageTone>
        <HowlerTone></HowlerTone>
        <NumberUnobtainable>400/500,0/6000</NumberUnobtainable>
        <WarningTone>1400/500,0/0</WarningTone>
        <RecordTone>440/500,0/5000</RecordTone>
        <AutoAnswerTone></AutoAnswerTone>
        <capability>
            <AudioCodecSets>G722,PCMU,PCMA,G729,opus,iLBC</AudioCodecSets>
        </capability>
    </mm>
    <sip>
        <SIPPort>{{ $s('fanvil_sip_port', ($lines[1]['sip_port'] ?? null) ?: 36000) }}</SIPPort>
        <STUNServer>{{ $s('fanvil_stun_server') }}</STUNServer>
        <STUNPort>{{ $s('fanvil_stun_port') }}</STUNPort>
        <STUNRefreshTime>50</STUNRefreshTime>
        <SIPWaitStunTime>800</SIPWaitStunTime>
        <ExternNATAddrs></ExternNATAddrs>
        <RegFailInterval>32</RegFailInterval>
        <StrictBranchPrefix>0</StrictBranchPrefix>
        <VideoMuteAttr>0</VideoMuteAttr>
        <EnableGroupBackup>0</EnableGroupBackup>
        <EnableRFC4475>1</EnableRFC4475>
        <StrictUAMatch>1</StrictUAMatch>
        <CSTAEnable>0</CSTAEnable>
        <NotifyReboot>1</NotifyReboot>
        <SMSdirectEnabled>0</SMSdirectEnabled>
        <SMSSaveEnabled>1</SMSSaveEnabled>
        <SMSRingEnabled>1</SMSRingEnabled>
        <EnableKarelContact>0</EnableKarelContact>
        <line index="1">
            <PhoneNumber>{{ $L(1,'user_id') }}</PhoneNumber>
            <DisplayName>{{ $L(1,'user_id') }}</DisplayName>
            <SipName></SipName>
            <RegisterAddr>{{ $addr(1) }}</RegisterAddr>
            <RegisterPort>{{ $L(1,'sip_port') }}</RegisterPort>
            <RegisterUser>{{ $L(1,'auth_id') }}</RegisterUser>
            <RegisterPswd>{{ $L(1,'password') }}</RegisterPswd>
            <RegisterTTL>{{ $ttl(1) }}</RegisterTTL>
            <NeedRegOn>0</NeedRegOn>
            <BackupAddr></BackupAddr>
            <BackupPort>5060</BackupPort>
            <BackupTransport>0</BackupTransport>
            <BackupTTL>3600</BackupTTL>
            <BackupNeedRegOn>0</BackupNeedRegOn>
            <EnableReg>{{ trim((string) $L(1,'password')) !== '' ? 1 : 0 }}</EnableReg>
            <BackupMode>0</BackupMode>
            <ProxyAddr>{{ $L(1,'outbound_proxy_primary') }}</ProxyAddr>
            <ProxyPort>{{ $L(1,'sip_port') }}</ProxyPort>
            <ProxyUser>{{ $L(1,'auth_id') }}</ProxyUser>
            <ProxyPswd>{{ $L(1,'password') }}</ProxyPswd>
            <ProxyNeedRegOn>0</ProxyNeedRegOn>
            <BakProxyAddr>{{ $L(1,'outbound_proxy_secondary') }}</BakProxyAddr>
            <BakProxyPort>{{ $L(1,'sip_port') }}</BakProxyPort>
            <BakProxyNeedRegOn>0</BakProxyNeedRegOn>
            <EnableFailback>{{ trim((string) $L(1,'outbound_proxy_secondary')) !== '' ? 1 : 0 }}</EnableFailback>
            <FailbackInterval>1800</FailbackInterval>
            <SignalFailback>0</SignalFailback>
            <SignalRetryCounts>3</SignalRetryCounts>
            <SigCryptoKey></SigCryptoKey>
            <EnableOSRTP>0</EnableOSRTP>
            <MediaCrypto>0</MediaCrypto>
            <MedCryptoKey></MedCryptoKey>
            <SRTPAuth-Tag>0</SRTPAuth-Tag>
            <EnableRFC5939>0</EnableRFC5939>
            <LocalDomain></LocalDomain>
            <AlwaysFWD>0</AlwaysFWD>
            <BusyFWD>0</BusyFWD>
            <NoAnswerFWD>0</NoAnswerFWD>
            <AlwaysFWDNum></AlwaysFWDNum>
            <BusyFWDNum></BusyFWDNum>
            <NoAnswerFWDNum></NoAnswerFWDNum>
            <FWDTimer>5</FWDTimer>
            <HotlineNum></HotlineNum>
            <EnableHotline>0</EnableHotline>
            <WarmLineTime>0</WarmLineTime>
            <PickupNum></PickupNum>
            <JoinNum></JoinNum>
            <IntercomNum></IntercomNum>
            <RingType>{{ $s('fanvil_ringtone_line1', 'default') }}</RingType>
            <NATUDPUpdate>2</NATUDPUpdate>
            <UDPUpdateTTL>15</UDPUpdateTTL>
            <UDPUpdateTryTimes>3</UDPUpdateTryTimes>
            <ServerType>0</ServerType>
            <UserAgent></UserAgent>
            <PRACK>0</PRACK>
            <KeepAUTH>0</KeepAUTH>
            <SessionTimer>0</SessionTimer>
            <STimerExpires>1800</STimerExpires>
            <EnableGRUU>0</EnableGRUU>
            <DTMFMode>3</DTMFMode>
            <DTMFInfoMode>0</DTMFInfoMode>
            <NATType>0</NATType>
            <EnableRport>1</EnableRport>
            <Subscribe>1</Subscribe>
            <SubExpire>{{ $ttl(1) }}</SubExpire>
            <SingleCodec>0</SingleCodec>
            <CLIR>0</CLIR>
            <StrictProxy>1</StrictProxy>
            <DirectContact>0</DirectContact>
            <HistoryInfo>0</HistoryInfo>
            <DNSSRV>{{ $T(1) === 'dns srv' ? 1 : 0 }}</DNSSRV>
            <DNSMode>{{ $T(1) === 'dns srv' ? 1 : 0 }}</DNSMode>
            <XFERExpire>0</XFERExpire>
            <BanAnonymous>0</BanAnonymous>
            <DialOffLine>0</DialOffLine>
            <QuotaName>0</QuotaName>
            <PresenceMode>0</PresenceMode>
            <RFCVer>1</RFCVer>
            <PhonePort>0</PhonePort>
            <SignalPort>5060</SignalPort>
            <Transport>{{ $T(1) === 'udp' ? 0 : 1 }}</Transport>
            <UseSRVMixer>0</UseSRVMixer>
            <SRVMixerUri></SRVMixerUri>
            <LongContact>0</LongContact>
            <AutoTCP>0</AutoTCP>
            <UriEscaped>1</UriEscaped>
            <ClicktoTalk>0</ClicktoTalk>
            <MwiNo></MwiNo>
            <MWINum>*97</MWINum>
            <ParkNo></ParkNo>
            <CallParkNum></CallParkNum>
            <RetrieveNum></RetrieveNum>
            <RetrieveType>0</RetrieveType>
            <HelpNo></HelpNo>
            <MSRPHelpNum></MSRPHelpNum>
            <UserIsPhone>0</UserIsPhone>
            <AutoAnswer>0</AutoAnswer>
            <NoAnswerTime>5</NoAnswerTime>
            <MissedCallLog>1</MissedCallLog>
            <ParkMode></ParkMode>
            <SvcCodeMode>1</SvcCodeMode>
            <DNDOnSvcCode>*78</DNDOnSvcCode>
            <DNDOffSvcCode>*79</DNDOffSvcCode>
            <CFUOnSvcCode>*72</CFUOnSvcCode>
            <CFUOffSvcCode>*73</CFUOffSvcCode>
            <CFBOnSvcCode></CFBOnSvcCode>
            <CFBOffSvcCode></CFBOffSvcCode>
            <CFNOnSvcCode></CFNOnSvcCode>
            <CFNOffSvcCode></CFNOffSvcCode>
            <ANCOnSvcCode></ANCOnSvcCode>
            <ANCOffSvcCode></ANCOffSvcCode>
            <SendANOnCode></SendANOnCode>
            <SendANOffCode></SendANOffCode>
            <CWOnCode></CWOnCode>
            <CWOffCode></CWOffCode>
            <VoiceCodecMap>PCMU,PCMA,G726-32,G729,iLBC,G722</VoiceCodecMap>
            <VideoCodecMap>{{ $s('fanvil_video_codec', '') }}</VideoCodecMap>
            <BLFListUri></BLFListUri>
            <BLFServer></BLFServer>
            <Respond182>0</Respond182>
            <EnableBLFList>0</EnableBLFList>
            <CallerIdType>4</CallerIdType>
            <KeepHigherCallerID>0</KeepHigherCallerID>
            <SynClockTime>0</SynClockTime>
            <MohServer></MohServer>
            <UseVPN>1</UseVPN>
            <EnableDND>0</EnableDND>
            <InactiveHold>0</InactiveHold>
            <ReqWithPort>1</ReqWithPort>
            <UpdateRegExpire>1</UpdateRegExpire>
            <EnableSCA>0</EnableSCA>
            <SubCallPark>0</SubCallPark>
            <SubCCStatus>0</SubCCStatus>
            <FeatureSync>0</FeatureSync>
            <EnableXferBack>0</EnableXferBack>
            <XferBackTime>35</XferBackTime>
            <UseTelCall>0</UseTelCall>
            <EnablePreview>0</EnablePreview>
            <PreviewMode>1</PreviewMode>
            <TLSVersion>2</TLSVersion>
            <CSTANumber></CSTANumber>
            <EnableChgPort>0</EnableChgPort>
            <VQName></VQName>
            <VQServer></VQServer>
            <VQServerPort>5060</VQServerPort>
            <VQHTTPServer></VQHTTPServer>
            <FlashMode>0</FlashMode>
            <ContentType></ContentType>
            <ContentBody></ContentBody>
            <UnregisterOnBoot>0</UnregisterOnBoot>
            <EnableMACHeader>0</EnableMACHeader>
            <EnableRegisterMAC>0</EnableRegisterMAC>
            <RecordStart>Record:on</RecordStart>
            <RecordStop>Record:off</RecordStop>
            <BLFDialogMatch>1</BLFDialogMatch>
            <Ptime>0</Ptime>
            <EnableDeal180>1</EnableDeal180>
            <KeepSingleContact>0</KeepSingleContact>
            <SessionTimerT1>500</SessionTimerT1>
            <SessionTimerT2>4000</SessionTimerT2>
            <SessionTimerT4>5000</SessionTimerT4>
            <UnavailableMode>0</UnavailableMode>
            <TCPUseRetryTimer>0</TCPUseRetryTimer>
            <Call-IDFormat>$id@$ip</Call-IDFormat>
            <GB28181Mode>0</GB28181Mode>
            <ProxyRequire></ProxyRequire>
            <BlockRTPWhenAlerting>0</BlockRTPWhenAlerting>
        </line>
        <line index="2">
            <PhoneNumber>{{ $L(2,'user_id') }}</PhoneNumber>
            <DisplayName>{{ $L(2,'display_name') }}</DisplayName>
            <SipName></SipName>
            <RegisterAddr>{{ $addr(2) }}</RegisterAddr>
            <RegisterPort>{{ $L(2,'sip_port') }}</RegisterPort>
            <RegisterUser>{{ $L(2,'auth_id') }}</RegisterUser>
            <RegisterPswd>{{ $L(2,'password') }}</RegisterPswd>
            <RegisterTTL>{{ $ttl(2) }}</RegisterTTL>
            <NeedRegOn>0</NeedRegOn>
            <BackupAddr></BackupAddr>
            <BackupPort>5060</BackupPort>
            <BackupTransport>0</BackupTransport>
            <BackupTTL>3600</BackupTTL>
            <BackupNeedRegOn>0</BackupNeedRegOn>
            <EnableReg>{{ trim((string) $L(2,'password')) !== '' ? 1 : 0 }}</EnableReg>
            <BackupMode>0</BackupMode>
            <ProxyAddr>{{ $L(2,'outbound_proxy_primary') }}</ProxyAddr>
            <ProxyPort>{{ $L(2,'sip_port') }}</ProxyPort>
            <ProxyUser>{{ $L(2,'auth_id') }}</ProxyUser>
            <ProxyPswd>{{ $L(2,'password') }}</ProxyPswd>
            <ProxyNeedRegOn>0</ProxyNeedRegOn>
            <BakProxyAddr>{{ $L(2,'outbound_proxy_secondary') }}</BakProxyAddr>
            <BakProxyPort>{{ $L(2,'sip_port') }}</BakProxyPort>
            <BakProxyNeedRegOn>0</BakProxyNeedRegOn>
            <EnableFailback>{{ trim((string) $L(2,'outbound_proxy_secondary')) !== '' ? 1 : 0 }}</EnableFailback>
            <FailbackInterval>1800</FailbackInterval>
            <SignalFailback>0</SignalFailback>
            <SignalRetryCounts>3</SignalRetryCounts>
            <SigCryptoKey></SigCryptoKey>
            <EnableOSRTP>0</EnableOSRTP>
            <MediaCrypto>0</MediaCrypto>
            <MedCryptoKey></MedCryptoKey>
            <SRTPAuth-Tag>0</SRTPAuth-Tag>
            <EnableRFC5939>0</EnableRFC5939>
            <LocalDomain></LocalDomain>
            <AlwaysFWD>0</AlwaysFWD>
            <BusyFWD>0</BusyFWD>
            <NoAnswerFWD>0</NoAnswerFWD>
            <AlwaysFWDNum></AlwaysFWDNum>
            <BusyFWDNum></BusyFWDNum>
            <NoAnswerFWDNum></NoAnswerFWDNum>
            <FWDTimer>5</FWDTimer>
            <HotlineNum></HotlineNum>
            <EnableHotline>0</EnableHotline>
            <WarmLineTime>0</WarmLineTime>
            <PickupNum></PickupNum>
            <JoinNum></JoinNum>
            <IntercomNum></IntercomNum>
            <RingType>{{ $s('fanvil_ringtone_line2', 'default') }}</RingType>
            <NATUDPUpdate>2</NATUDPUpdate>
            <UDPUpdateTTL>15</UDPUpdateTTL>
            <UDPUpdateTryTimes>3</UDPUpdateTryTimes>
            <ServerType>0</ServerType>
            <UserAgent></UserAgent>
            <PRACK>0</PRACK>
            <KeepAUTH>0</KeepAUTH>
            <SessionTimer>0</SessionTimer>
            <STimerExpires>0</STimerExpires>
            <EnableGRUU>0</EnableGRUU>
            <DTMFMode>3</DTMFMode>
            <DTMFInfoMode>0</DTMFInfoMode>
            <NATType>0</NATType>
            <EnableRport>1</EnableRport>
            <Subscribe>0</Subscribe>
            <SubExpire>{{ $ttl(2) }}</SubExpire>
            <SingleCodec>0</SingleCodec>
            <CLIR>0</CLIR>
            <StrictProxy>1</StrictProxy>
            <DirectContact>0</DirectContact>
            <HistoryInfo>0</HistoryInfo>
            <DNSSRV>{{ $T(2) === 'dns srv' ? 1 : 0 }}</DNSSRV>
            <DNSMode>{{ $T(2) === 'dns srv' ? 1 : 0 }}</DNSMode>
            <XFERExpire>0</XFERExpire>
            <BanAnonymous>0</BanAnonymous>
            <DialOffLine>0</DialOffLine>
            <QuotaName>0</QuotaName>
            <PresenceMode>0</PresenceMode>
            <RFCVer>1</RFCVer>
            <PhonePort>0</PhonePort>
            <SignalPort>5060</SignalPort>
            <Transport>{{ $T(2) === 'udp' ? 0 : 1 }}</Transport>
            <UseSRVMixer>0</UseSRVMixer>
            <SRVMixerUri></SRVMixerUri>
            <LongContact>0</LongContact>
            <AutoTCP>0</AutoTCP>
            <UriEscaped>1</UriEscaped>
            <ClicktoTalk>0</ClicktoTalk>
            <MwiNo></MwiNo>
            <MWINum></MWINum>
            <ParkNo></ParkNo>
            <CallParkNum></CallParkNum>
            <RetrieveNum></RetrieveNum>
            <RetrieveType>0</RetrieveType>
            <HelpNo></HelpNo>
            <MSRPHelpNum></MSRPHelpNum>
            <UserIsPhone>0</UserIsPhone>
            <AutoAnswer>0</AutoAnswer>
            <NoAnswerTime>5</NoAnswerTime>
            <MissedCallLog>1</MissedCallLog>
            <ParkMode></ParkMode>
            <SvcCodeMode>1</SvcCodeMode>
            <DNDOnSvcCode>*78</DNDOnSvcCode>
            <DNDOffSvcCode>*79</DNDOffSvcCode>
            <CFUOnSvcCode>*72</CFUOnSvcCode>
            <CFUOffSvcCode>*73</CFUOffSvcCode>
            <CFBOnSvcCode></CFBOnSvcCode>
            <CFBOffSvcCode></CFBOffSvcCode>
            <CFNOnSvcCode></CFNOnSvcCode>
            <CFNOffSvcCode></CFNOffSvcCode>
            <ANCOnSvcCode></ANCOnSvcCode>
            <ANCOffSvcCode></ANCOffSvcCode>
            <SendANOnCode></SendANOnCode>
            <SendANOffCode></SendANOffCode>
            <CWOnCode></CWOnCode>
            <CWOffCode></CWOffCode>
            <VoiceCodecMap>PCMU,PCMA,G726-32,G729,iLBC,G722</VoiceCodecMap>
            <VideoCodecMap>{{ $s('fanvil_video_codec', '') }}</VideoCodecMap>
            <BLFListUri></BLFListUri>
            <BLFServer></BLFServer>
            <Respond182>0</Respond182>
            <EnableBLFList>0</EnableBLFList>
            <CallerIdType>4</CallerIdType>
            <KeepHigherCallerID>0</KeepHigherCallerID>
            <SynClockTime>0</SynClockTime>
            <MohServer></MohServer>
            <UseVPN>1</UseVPN>
            <EnableDND>0</EnableDND>
            <InactiveHold>0</InactiveHold>
            <ReqWithPort>1</ReqWithPort>
            <UpdateRegExpire>1</UpdateRegExpire>
            <EnableSCA>0</EnableSCA>
            <SubCallPark>0</SubCallPark>
            <SubCCStatus>0</SubCCStatus>
            <FeatureSync>0</FeatureSync>
            <EnableXferBack>0</EnableXferBack>
            <XferBackTime>35</XferBackTime>
            <UseTelCall>0</UseTelCall>
            <EnablePreview>0</EnablePreview>
            <PreviewMode>1</PreviewMode>
            <TLSVersion>2</TLSVersion>
            <CSTANumber></CSTANumber>
            <EnableChgPort>0</EnableChgPort>
            <VQName></VQName>
            <VQServer></VQServer>
            <VQServerPort>5060</VQServerPort>
            <VQHTTPServer></VQHTTPServer>
            <FlashMode>0</FlashMode>
            <ContentType></ContentType>
            <ContentBody></ContentBody>
            <UnregisterOnBoot>0</UnregisterOnBoot>
            <EnableMACHeader>0</EnableMACHeader>
            <EnableRegisterMAC>0</EnableRegisterMAC>
            <RecordStart>Record:on</RecordStart>
            <RecordStop>Record:off</RecordStop>
            <BLFDialogMatch>1</BLFDialogMatch>
            <Ptime>0</Ptime>
            <EnableDeal180>1</EnableDeal180>
            <KeepSingleContact>0</KeepSingleContact>
            <SessionTimerT1>500</SessionTimerT1>
            <SessionTimerT2>4000</SessionTimerT2>
            <SessionTimerT4>5000</SessionTimerT4>
            <UnavailableMode>0</UnavailableMode>
            <TCPUseRetryTimer>0</TCPUseRetryTimer>
            <Call-IDFormat>$id@$ip</Call-IDFormat>
            <GB28181Mode>0</GB28181Mode>
            <ProxyRequire></ProxyRequire>
            <BlockRTPWhenAlerting>0</BlockRTPWhenAlerting>
        </line>
        <line index="3">
            <PhoneNumber>{{ $L(3,'user_id') }}</PhoneNumber>
            <DisplayName>{{ $L(3,'display_name') }}</DisplayName>
            <SipName></SipName>
            <RegisterAddr>{{ $addr(3) }}</RegisterAddr>
            <RegisterPort>{{ $L(3,'sip_port') }}</RegisterPort>
            <RegisterUser>{{ $L(3,'auth_id') }}</RegisterUser>
            <RegisterPswd>{{ $L(3,'password') }}</RegisterPswd>
            <RegisterTTL>{{ $ttl(3) }}</RegisterTTL>
            <NeedRegOn>0</NeedRegOn>
            <BackupAddr></BackupAddr>
            <BackupPort>5060</BackupPort>
            <BackupTransport>0</BackupTransport>
            <BackupTTL>3600</BackupTTL>
            <BackupNeedRegOn>0</BackupNeedRegOn>
            <EnableReg>{{ trim((string) $L(3,'password')) !== '' ? 1 : 0 }}</EnableReg>
            <BackupMode>0</BackupMode>
            <ProxyAddr>{{ $L(3,'outbound_proxy_primary') }}</ProxyAddr>
            <ProxyPort>{{ $L(3,'sip_port') }}</ProxyPort>
            <ProxyUser>{{ $L(3,'auth_id') }}</ProxyUser>
            <ProxyPswd>{{ $L(3,'password') }}</ProxyPswd>
            <ProxyNeedRegOn>0</ProxyNeedRegOn>
            <BakProxyAddr>{{ $L(3,'outbound_proxy_secondary') }}</BakProxyAddr>
            <BakProxyPort>{{ $L(3,'sip_port') }}</BakProxyPort>
            <BakProxyNeedRegOn>0</BakProxyNeedRegOn>
            <EnableFailback>{{ trim((string) $L(3,'outbound_proxy_secondary')) !== '' ? 1 : 0 }}</EnableFailback>
            <FailbackInterval>1800</FailbackInterval>
            <SignalFailback>0</SignalFailback>
            <SignalRetryCounts>3</SignalRetryCounts>
            <SigCryptoKey></SigCryptoKey>
            <EnableOSRTP>0</EnableOSRTP>
            <MediaCrypto>0</MediaCrypto>
            <MedCryptoKey></MedCryptoKey>
            <SRTPAuth-Tag>0</SRTPAuth-Tag>
            <EnableRFC5939>0</EnableRFC5939>
            <LocalDomain></LocalDomain>
            <AlwaysFWD>0</AlwaysFWD>
            <BusyFWD>0</BusyFWD>
            <NoAnswerFWD>0</NoAnswerFWD>
            <AlwaysFWDNum></AlwaysFWDNum>
            <BusyFWDNum></BusyFWDNum>
            <NoAnswerFWDNum></NoAnswerFWDNum>
            <FWDTimer>5</FWDTimer>
            <HotlineNum></HotlineNum>
            <EnableHotline>0</EnableHotline>
            <WarmLineTime>0</WarmLineTime>
            <PickupNum></PickupNum>
            <JoinNum></JoinNum>
            <IntercomNum></IntercomNum>
            <RingType>{{ $s('fanvil_ringtone_line3', 'default') }}</RingType>
            <NATUDPUpdate>2</NATUDPUpdate>
            <UDPUpdateTTL>15</UDPUpdateTTL>
            <UDPUpdateTryTimes>3</UDPUpdateTryTimes>
            <ServerType>0</ServerType>
            <UserAgent></UserAgent>
            <PRACK>0</PRACK>
            <KeepAUTH>0</KeepAUTH>
            <SessionTimer>0</SessionTimer>
            <STimerExpires>0</STimerExpires>
            <EnableGRUU>0</EnableGRUU>
            <DTMFMode>3</DTMFMode>
            <DTMFInfoMode>0</DTMFInfoMode>
            <NATType>0</NATType>
            <EnableRport>1</EnableRport>
            <Subscribe>0</Subscribe>
            <SubExpire>{{ $ttl(3) }}</SubExpire>
            <SingleCodec>0</SingleCodec>
            <CLIR>0</CLIR>
            <StrictProxy>1</StrictProxy>
            <DirectContact>0</DirectContact>
            <HistoryInfo>0</HistoryInfo>
            <DNSSRV>{{ $T(3) === 'dns srv' ? 1 : 0 }}</DNSSRV>
            <DNSMode>{{ $T(3) === 'dns srv' ? 1 : 0 }}</DNSMode>
            <XFERExpire>0</XFERExpire>
            <BanAnonymous>0</BanAnonymous>
            <DialOffLine>0</DialOffLine>
            <QuotaName>0</QuotaName>
            <PresenceMode>0</PresenceMode>
            <RFCVer>1</RFCVer>
            <PhonePort>0</PhonePort>
            <SignalPort>5060</SignalPort>
            <Transport>{{ $T(3) === 'udp' ? 0 : 1 }}</Transport>
            <UseSRVMixer>0</UseSRVMixer>
            <SRVMixerUri></SRVMixerUri>
            <LongContact>0</LongContact>
            <AutoTCP>1</AutoTCP>
            <UriEscaped>1</UriEscaped>
            <ClicktoTalk>0</ClicktoTalk>
            <MwiNo></MwiNo>
            <MWINum></MWINum>
            <ParkNo></ParkNo>
            <CallParkNum></CallParkNum>
            <RetrieveNum></RetrieveNum>
            <RetrieveType>0</RetrieveType>
            <HelpNo></HelpNo>
            <MSRPHelpNum></MSRPHelpNum>
            <UserIsPhone>0</UserIsPhone>
            <AutoAnswer>0</AutoAnswer>
            <NoAnswerTime>5</NoAnswerTime>
            <MissedCallLog>1</MissedCallLog>
            <ParkMode></ParkMode>
            <SvcCodeMode>1</SvcCodeMode>
            <DNDOnSvcCode>*78</DNDOnSvcCode>
            <DNDOffSvcCode>*79</DNDOffSvcCode>
            <CFUOnSvcCode>*72</CFUOnSvcCode>
            <CFUOffSvcCode>*73</CFUOffSvcCode>
            <CFBOnSvcCode></CFBOnSvcCode>
            <CFBOffSvcCode></CFBOffSvcCode>
            <CFNOnSvcCode></CFNOnSvcCode>
            <CFNOffSvcCode></CFNOffSvcCode>
            <ANCOnSvcCode></ANCOnSvcCode>
            <ANCOffSvcCode></ANCOffSvcCode>
            <SendANOnCode></SendANOnCode>
            <SendANOffCode></SendANOffCode>
            <CWOnCode></CWOnCode>
            <CWOffCode></CWOffCode>
            <VoiceCodecMap>PCMU,PCMA,G726-32,G729,iLBC,G722</VoiceCodecMap>
            <VideoCodecMap>{{ $s('fanvil_video_codec', '') }}</VideoCodecMap>
            <BLFListUri></BLFListUri>
            <BLFServer></BLFServer>
            <Respond182>0</Respond182>
            <EnableBLFList>0</EnableBLFList>
            <CallerIdType>4</CallerIdType>
            <KeepHigherCallerID>0</KeepHigherCallerID>
            <SynClockTime>0</SynClockTime>
            <MohServer></MohServer>
            <UseVPN>1</UseVPN>
            <EnableDND>0</EnableDND>
            <InactiveHold>0</InactiveHold>
            <ReqWithPort>1</ReqWithPort>
            <UpdateRegExpire>1</UpdateRegExpire>
            <EnableSCA>0</EnableSCA>
            <SubCallPark>0</SubCallPark>
            <SubCCStatus>0</SubCCStatus>
            <FeatureSync>0</FeatureSync>
            <EnableXferBack>0</EnableXferBack>
            <XferBackTime>35</XferBackTime>
            <UseTelCall>0</UseTelCall>
            <EnablePreview>0</EnablePreview>
            <PreviewMode>1</PreviewMode>
            <TLSVersion>2</TLSVersion>
            <CSTANumber></CSTANumber>
            <EnableChgPort>0</EnableChgPort>
            <VQName></VQName>
            <VQServer></VQServer>
            <VQServerPort>5060</VQServerPort>
            <VQHTTPServer></VQHTTPServer>
            <FlashMode>0</FlashMode>
            <ContentType></ContentType>
            <ContentBody></ContentBody>
            <UnregisterOnBoot>0</UnregisterOnBoot>
            <EnableMACHeader>0</EnableMACHeader>
            <EnableRegisterMAC>0</EnableRegisterMAC>
            <RecordStart>Record:on</RecordStart>
            <RecordStop>Record:off</RecordStop>
            <BLFDialogMatch>1</BLFDialogMatch>
            <Ptime>0</Ptime>
            <EnableDeal180>1</EnableDeal180>
            <KeepSingleContact>0</KeepSingleContact>
            <SessionTimerT1>500</SessionTimerT1>
            <SessionTimerT2>4000</SessionTimerT2>
            <SessionTimerT4>5000</SessionTimerT4>
            <UnavailableMode>0</UnavailableMode>
            <TCPUseRetryTimer>0</TCPUseRetryTimer>
            <Call-IDFormat>$id@$ip</Call-IDFormat>
            <GB28181Mode>0</GB28181Mode>
            <ProxyRequire></ProxyRequire>
            <BlockRTPWhenAlerting>0</BlockRTPWhenAlerting>
        </line>
        <line index="4">
            <PhoneNumber>{{ $L(4,'user_id') }}</PhoneNumber>
            <DisplayName>{{ $L(4,'display_name') }}</DisplayName>
            <SipName></SipName>
            <RegisterAddr>{{ $addr(4) }}</RegisterAddr>
            <RegisterPort>{{ $L(4,'sip_port') }}</RegisterPort>
            <RegisterUser>{{ $L(4,'auth_id') }}</RegisterUser>
            <RegisterPswd>{{ $L(4,'password') }}</RegisterPswd>
            <RegisterTTL>{{ $ttl(4) }}</RegisterTTL>
            <NeedRegOn>0</NeedRegOn>
            <BackupAddr></BackupAddr>
            <BackupPort>5060</BackupPort>
            <BackupTransport>0</BackupTransport>
            <BackupTTL>3600</BackupTTL>
            <BackupNeedRegOn>0</BackupNeedRegOn>
            <EnableReg>{{ trim((string) $L(4,'password')) !== '' ? 1 : 0 }}</EnableReg>
            <BackupMode>0</BackupMode>
            <ProxyAddr>{{ $L(4,'outbound_proxy_primary') }}</ProxyAddr>
            <ProxyPort>{{ $L(4,'sip_port') }}</ProxyPort>
            <ProxyUser>{{ $L(4,'auth_id') }}</ProxyUser>
            <ProxyPswd>{{ $L(4,'password') }}</ProxyPswd>
            <ProxyNeedRegOn>0</ProxyNeedRegOn>
            <BakProxyAddr>{{ $L(4,'outbound_proxy_secondary') }}</BakProxyAddr>
            <BakProxyPort>{{ $L(4,'sip_port') }}</BakProxyPort>
            <BakProxyNeedRegOn>0</BakProxyNeedRegOn>
            <EnableFailback>{{ trim((string) $L(4,'outbound_proxy_secondary')) !== '' ? 1 : 0 }}</EnableFailback>
            <FailbackInterval>1800</FailbackInterval>
            <SignalFailback>0</SignalFailback>
            <SignalRetryCounts>3</SignalRetryCounts>
            <SigCryptoKey></SigCryptoKey>
            <EnableOSRTP>0</EnableOSRTP>
            <MediaCrypto>0</MediaCrypto>
            <MedCryptoKey></MedCryptoKey>
            <SRTPAuth-Tag>0</SRTPAuth-Tag>
            <EnableRFC5939>0</EnableRFC5939>
            <LocalDomain></LocalDomain>
            <AlwaysFWD>0</AlwaysFWD>
            <BusyFWD>0</BusyFWD>
            <NoAnswerFWD>0</NoAnswerFWD>
            <AlwaysFWDNum></AlwaysFWDNum>
            <BusyFWDNum></BusyFWDNum>
            <NoAnswerFWDNum></NoAnswerFWDNum>
            <FWDTimer>5</FWDTimer>
            <HotlineNum></HotlineNum>
            <EnableHotline>0</EnableHotline>
            <WarmLineTime>0</WarmLineTime>
            <PickupNum></PickupNum>
            <JoinNum></JoinNum>
            <IntercomNum></IntercomNum>
            <RingType>{{ $s('fanvil_ringtone_line4', 'default') }}</RingType>
            <NATUDPUpdate>2</NATUDPUpdate>
            <UDPUpdateTTL>15</UDPUpdateTTL>
            <UDPUpdateTryTimes>3</UDPUpdateTryTimes>
            <ServerType>0</ServerType>
            <UserAgent></UserAgent>
            <PRACK>0</PRACK>
            <KeepAUTH>0</KeepAUTH>
            <SessionTimer>0</SessionTimer>
            <STimerExpires>0</STimerExpires>
            <EnableGRUU>0</EnableGRUU>
            <DTMFMode>3</DTMFMode>
            <DTMFInfoMode>0</DTMFInfoMode>
            <NATType>0</NATType>
            <EnableRport>1</EnableRport>
            <Subscribe>0</Subscribe>
            <SubExpire>{{ $ttl(4) }}</SubExpire>
            <SingleCodec>0</SingleCodec>
            <CLIR>0</CLIR>
            <StrictProxy>1</StrictProxy>
            <DirectContact>0</DirectContact>
            <HistoryInfo>0</HistoryInfo>
            <DNSSRV>{{ $T(4) === 'dns srv' ? 1 : 0 }}</DNSSRV>
            <DNSMode>{{ $T(4) === 'dns srv' ? 1 : 0 }}</DNSMode>
            <XFERExpire>0</XFERExpire>
            <BanAnonymous>0</BanAnonymous>
            <DialOffLine>0</DialOffLine>
            <QuotaName>0</QuotaName>
            <PresenceMode>0</PresenceMode>
            <RFCVer>1</RFCVer>
            <PhonePort>0</PhonePort>
            <SignalPort>5060</SignalPort>
            <Transport>{{ $T(4) === 'udp' ? 0 : 1 }}</Transport>
            <UseSRVMixer>0</UseSRVMixer>
            <SRVMixerUri></SRVMixerUri>
            <LongContact>0</LongContact>
            <AutoTCP>1</AutoTCP>
            <UriEscaped>1</UriEscaped>
            <ClicktoTalk>0</ClicktoTalk>
            <MwiNo></MwiNo>
            <MWINum></MWINum>
            <ParkNo></ParkNo>
            <CallParkNum></CallParkNum>
            <RetrieveNum></RetrieveNum>
            <RetrieveType>0</RetrieveType>
            <HelpNo></HelpNo>
            <MSRPHelpNum></MSRPHelpNum>
            <UserIsPhone>0</UserIsPhone>
            <AutoAnswer>0</AutoAnswer>
            <NoAnswerTime>5</NoAnswerTime>
            <MissedCallLog>1</MissedCallLog>
            <ParkMode></ParkMode>
            <SvcCodeMode>1</SvcCodeMode>
            <DNDOnSvcCode>*78</DNDOnSvcCode>
            <DNDOffSvcCode>*79</DNDOffSvcCode>
            <CFUOnSvcCode>*72</CFUOnSvcCode>
            <CFUOffSvcCode>*73</CFUOffSvcCode>
            <CFBOnSvcCode></CFBOnSvcCode>
            <CFBOffSvcCode></CFBOffSvcCode>
            <CFNOnSvcCode></CFNOnSvcCode>
            <CFNOffSvcCode></CFNOffSvcCode>
            <ANCOnSvcCode></ANCOnSvcCode>
            <ANCOffSvcCode></ANCOffSvcCode>
            <SendANOnCode></SendANOnCode>
            <SendANOffCode></SendANOffCode>
            <CWOnCode></CWOnCode>
            <CWOffCode></CWOffCode>
            <VoiceCodecMap>PCMU,PCMA,G726-32,G729,iLBC,G722</VoiceCodecMap>
            <VideoCodecMap>{{ $s('fanvil_video_codec', '') }}</VideoCodecMap>
            <BLFListUri></BLFListUri>
            <BLFServer></BLFServer>
            <Respond182>0</Respond182>
            <EnableBLFList>0</EnableBLFList>
            <CallerIdType>4</CallerIdType>
            <KeepHigherCallerID>0</KeepHigherCallerID>
            <SynClockTime>0</SynClockTime>
            <MohServer></MohServer>
            <UseVPN>1</UseVPN>
            <EnableDND>0</EnableDND>
            <InactiveHold>0</InactiveHold>
            <ReqWithPort>1</ReqWithPort>
            <UpdateRegExpire>1</UpdateRegExpire>
            <EnableSCA>0</EnableSCA>
            <SubCallPark>0</SubCallPark>
            <SubCCStatus>0</SubCCStatus>
            <FeatureSync>0</FeatureSync>
            <EnableXferBack>0</EnableXferBack>
            <XferBackTime>35</XferBackTime>
            <UseTelCall>0</UseTelCall>
            <EnablePreview>0</EnablePreview>
            <PreviewMode>1</PreviewMode>
            <TLSVersion>2</TLSVersion>
            <CSTANumber></CSTANumber>
            <EnableChgPort>0</EnableChgPort>
            <VQName></VQName>
            <VQServer></VQServer>
            <VQServerPort>5060</VQServerPort>
            <VQHTTPServer></VQHTTPServer>
            <FlashMode>0</FlashMode>
            <ContentType></ContentType>
            <ContentBody></ContentBody>
            <UnregisterOnBoot>0</UnregisterOnBoot>
            <EnableMACHeader>0</EnableMACHeader>
            <EnableRegisterMAC>0</EnableRegisterMAC>
            <RecordStart>Record:on</RecordStart>
            <RecordStop>Record:off</RecordStop>
            <BLFDialogMatch>1</BLFDialogMatch>
            <Ptime>0</Ptime>
            <EnableDeal180>1</EnableDeal180>
            <KeepSingleContact>0</KeepSingleContact>
            <SessionTimerT1>500</SessionTimerT1>
            <SessionTimerT2>4000</SessionTimerT2>
            <SessionTimerT4>5000</SessionTimerT4>
            <UnavailableMode>0</UnavailableMode>
            <TCPUseRetryTimer>0</TCPUseRetryTimer>
            <Call-IDFormat>$id@$ip</Call-IDFormat>
            <GB28181Mode>0</GB28181Mode>
            <ProxyRequire></ProxyRequire>
            <BlockRTPWhenAlerting>0</BlockRTPWhenAlerting>
        </line>
        <p2p>
            <SIPP2PEnableAutoAnswer>0</SIPP2PEnableAutoAnswer>
            <SIPP2PAutoAnswerDelay>30</SIPP2PAutoAnswerDelay>
            <SIPP2PDtmfMode>1</SIPP2PDtmfMode>
            <SIPP2PSipInfoDtmfMode>0</SIPP2PSipInfoDtmfMode>
            <SIPP2PEnablePreview>0</SIPP2PEnablePreview>
            <SIPP2PPreviewMode>0</SIPP2PPreviewMode>
            <SIPP2PUseVPN>1</SIPP2PUseVPN>
            <SIPP2PCall-IDFormat>$id@$ip</SIPP2PCall-IDFormat>
            <SIPP2PSessionTimer>0</SIPP2PSessionTimer>
            <SIPP2PSTimerExpires>1800</SIPP2PSTimerExpires>
        </p2p>
    </sip>
    <call>
        <port index="1">
            <EnableXferDPlan>1</EnableXferDPlan>
            <EnableFwdDPlan>1</EnableFwdDPlan>
            <EnablePreDPlan>0</EnablePreDPlan>
            <IPDialPrefix>.</IPDialPrefix>
            <EnableDND>1</EnableDND>
            <DNDMode>0</DNDMode>
            <EnableSpaceDND>0</EnableSpaceDND>
            <DNDStartTime>1500</DNDStartTime>
            <DNDEndTime>1730</DNDEndTime>
            <DNDAcceptMcast>0</DNDAcceptMcast>
            <EnableWhiteList>1</EnableWhiteList>
            <EnableBlackList>1</EnableBlackList>
            <EnableCallBar>1</EnableCallBar>
            <MuteRinging>0</MuteRinging>
            <BanDialOut>0</BanDialOut>
            <BanEmptyCID>0</BanEmptyCID>
            <EnableCLIP>1</EnableCLIP>
            <CallWaiting>{{ $s('fanvil_call_waiting', '1') }}</CallWaiting>
            <CallTransfer>1</CallTransfer>
            <CallSemiXfer>1</CallSemiXfer>
            <CallConference>1</CallConference>
            <AutoPickupNext>0</AutoPickupNext>
            <BusyNoLine>1</BusyNoLine>
            <AutoOnhook>1</AutoOnhook>
            <AutoOnhookTime>3</AutoOnhookTime>
            <EnableIntercom>1</EnableIntercom>
            <IntercomMute>0</IntercomMute>
            <IntercomTone>1</IntercomTone>
            <IntercomBarge>0</IntercomBarge>
            <UseAutoRedial>0</UseAutoRedial>
            <RedialEnterCallLog>0</RedialEnterCallLog>
            <AutoRedialDelay>30</AutoRedialDelay>
            <AutoRedialTimes>5</AutoRedialTimes>
            <CallComplete>0</CallComplete>
            <CHoldingTone>1</CHoldingTone>
            <CWaitingTone>1</CWaitingTone>
            <HideDTMFType>0</HideDTMFType>
            <TalkDTMFTone>1</TalkDTMFTone>
            <DialDTMFTone>1</DialDTMFTone>
            <PswDialMode>0</PswDialMode>
            <PswDialLength>0</PswDialLength>
            <PswDialPrefix></PswDialPrefix>
            <EnableMultiLine>1</EnableMultiLine>
            <AllowIPCall>1</AllowIPCall>
            <CallerNameType>0</CallerNameType>
            <MuteForRing>0</MuteForRing>
            <AutoHandleVideo>0</AutoHandleVideo>
            <DefaultAnsMode>{{ $s('fanvil_default_answer_mode') }}</DefaultAnsMode>
            <DefaultDialMode>{{ $s('fanvil_default_dial_mode') }}</DefaultDialMode>
            <HoldToTransfer>0</HoldToTransfer>
            <EnablePreDial>1</EnablePreDial>
            <DefaultExtLine>1</DefaultExtLine>
            <EnableDefLine>1</EnableDefLine>
            <EnableSelLine>1</EnableSelLine>
            <RinginHeadset>0</RinginHeadset>
            <AutoHeadset>0</AutoHeadset>
            <DNDReturnCode>480</DNDReturnCode>
            <BusyReturnCode>486</BusyReturnCode>
            <RejectReturnCode>603</RejectReturnCode>
            <ContactType>0</ContactType>
            <EnableCountryCode>0</EnableCountryCode>
            <CountryCode></CountryCode>
            <CallAreaCode></CallAreaCode>
            <NumberPrivacy>0</NumberPrivacy>
            <PrivacyRule>.</PrivacyRule>
            <TransfDTMFCode></TransfDTMFCode>
            <HoldDTMFCode></HoldDTMFCode>
            <ConfDTMFCode></ConfDTMFCode>
            <DisableDialSearch>0</DisableDialSearch>
            <CallNumberFilter></CallNumberFilter>
            <AutoResumeCurrent>0</AutoResumeCurrent>
            <CallTimeout>120</CallTimeout>
            <RingTimeout>120</RingTimeout>
            <RingPriority>0</RingPriority>
            <AutoAnswerTone>1</AutoAnswerTone>
            <AlertingTone>1</AlertingTone>
            <BusyTone>1</BusyTone>
        </port>
        <basic>
            <DialbyPound>1</DialbyPound>
            <BTransferbyPound>0</BTransferbyPound>
            <OnhooktoBXfer>0</OnhooktoBXfer>
            <OnhooktoAXfer>0</OnhooktoAXfer>
            <ConfOnhooktoXfer>0</ConfOnhooktoXfer>
            <DialFixedLength>0</DialFixedLength>
            <FixedLengthNums>11</FixedLengthNums>
            <DialbyTimeout>1</DialbyTimeout>
            <DialTimeoutvalue>10</DialTimeoutvalue>
            <EnableEOneSixFour>0</EnableEOneSixFour>
        </basic>
        <alertInfo index="1">
            <Text>1</Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
        <alertInfo index="2">
            <Text>2</Text>
            <Line>-1</Line>
            <RingType>Type 2</RingType>
        </alertInfo>
        <alertInfo index="3">
            <Text>3</Text>
            <Line>-1</Line>
            <RingType>Type 3</RingType>
        </alertInfo>
        <alertInfo index="4">
            <Text></Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
        <alertInfo index="5">
            <Text></Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
        <alertInfo index="6">
            <Text></Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
        <alertInfo index="7">
            <Text></Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
        <alertInfo index="8">
            <Text></Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
        <alertInfo index="9">
            <Text></Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
        <alertInfo index="10">
            <Text></Text>
            <Line>-1</Line>
            <RingType>Type 1</RingType>
        </alertInfo>
    </call>
    <phone>
        <MenuPassword>{{ $s('fanvil_menu_password', '123') }}</MenuPassword>
        <KeyLockPassword>{{ $s('fanvil_keylock_password', 123) }}</KeyLockPassword>
        <FastKeylockCode></FastKeylockCode>
        <EnableKeyLock>0</EnableKeyLock>
        <KeyLockTimeout>0</KeyLockTimeout>
        <KeyLockStatus>0</KeyLockStatus>
        <EmergencyCall>{{ $s('fanvil_emergency_number', 110) }}</EmergencyCall>
        <PushXMLIP></PushXMLIP>
        <SIPNumberPlan>0</SIPNumberPlan>
        <LDAPSearch>0</LDAPSearch>
        <SearchPath>0</SearchPath>
        <CallerDisplayType>5</CallerDisplayType>
        <CallLogDisplayType>0</CallLogDisplayType>
        <EnableRecvSMS>1</EnableRecvSMS>
        <EnableCallHistory>1</EnableCallHistory>
        <LineDisplayFormat>$name</LineDisplayFormat>
        <EnableMWITone>0</EnableMWITone>
        <SIPNotifyXML>1</SIPNotifyXML>
        <BlockXMLWhenCall>1</BlockXMLWhenCall>
        <XMLUpdateInterval>30</XMLUpdateInterval>
        <VqmDisplayOrder></VqmDisplayOrder>
        <EnablePushXMLAuth>0</EnablePushXMLAuth>
        <PickupVisualAlert>0</PickupVisualAlert>
        <PickupAudioAlert>0</PickupAudioAlert>
        <PickupRingType>Splash</PickupRingType>
        <CallparkVisualAlert>0</CallparkVisualAlert>
        <CallparkAudioAlert>0</CallparkAudioAlert>
        <CallparkRingType></CallparkRingType>
        <PbookDisplayAttr></PbookDisplayAttr>
        <DisplayBusinessCardTimeout>0</DisplayBusinessCardTimeout>
        <DisplayCallDuration>1</DisplayCallDuration>
        <DSSNameSearch>1</DSSNameSearch>
        <display>
            <LCDTitle>{{ $s('fanvil_greeting') }}</LCDTitle>
            <LCDConstrast>5</LCDConstrast>
            <EnableEnergysaving>{{ $s('fanvil_display_brightness_inactive', '4') }}</EnableEnergysaving>
            <LCDLuminanceLevel>{{ $s('fanvil_display_brightness_active', '12') }}</LCDLuminanceLevel>
            <BacklightOffTime>{{ $s('fanvil_display_inactivity_time', '45') }}</BacklightOffTime>
            <DisableCHNIME>0</DisableCHNIME>
            <PhoneModel></PhoneModel>
            <HostName>dvf97</HostName>
            <DefaultLanguage>en</DefaultLanguage>
            <EnableGreetings>0</EnableGreetings>
        </display>
        <powerLed>
            <Power>1</Power>
            <MWIOrSMS>3</MWIOrSMS>
            <InUsing>0</InUsing>
            <Ring>2</Ring>
            <Hold>0</Hold>
            <Mute>0</Mute>
            <MissedCall>3</MissedCall>
            <PowerSaving>1</PowerSaving>
            <IdleMute>3</IdleMute>
            <RegisterFailed>3</RegisterFailed>
            <DND>0</DND>
            <PowerColor>1</PowerColor>
            <MWIOrSMSColor>1</MWIOrSMSColor>
            <InUsingColor>1</InUsingColor>
            <RingColor>1</RingColor>
            <HoldColor>1</HoldColor>
            <MuteColor>1</MuteColor>
            <MissedCallColor>1</MissedCallColor>
            <IdleMuteColor>1</IdleMuteColor>
            <RegisterFailedColor>1</RegisterFailedColor>
            <PowerSavingColor>1</PowerSavingColor>
            <DNDColor>1</DNDColor>
            <EventNotificationLED>0</EventNotificationLED>
            <StandbyLampEffect>1</StandbyLampEffect>
            <LampEffectPlayTime>1</LampEffectPlayTime>
            <CustomLampEffectTime>1200</CustomLampEffectTime>
            <StandbyLampEffectType>0</StandbyLampEffectType>
            <OffhookLampEffect>1</OffhookLampEffect>
            <OffhookLampEffectColor>0</OffhookLampEffectColor>
            <RingingLampEffect>0</RingingLampEffect>
            <RingingLampEffectType>0</RingingLampEffectType>
            <TalkingLampEffect>0</TalkingLampEffect>
            <TalkingLampEffectType>0</TalkingLampEffectType>
            <CallingLampEffect>0</CallingLampEffect>
            <CallingLampEffectType>0</CallingLampEffectType>
        </powerLed>
        <lineLed>
            <LineIdleColor>0</LineIdleColor>
            <LineIdleCtl>1</LineIdleCtl>
        </lineLed>
        <blfLed>
            <BLFIdleColor>0</BLFIdleColor>
            <BLFIdleCtl>1</BLFIdleCtl>
            <BLFIdleText>terminated</BLFIdleText>
            <BLFRingColor>1</BLFRingColor>
            <BLFRingCtl>2</BLFRingCtl>
            <BLFRingText>early</BLFRingText>
            <BLFDialingColor>1</BLFDialingColor>
            <BLFDialingCtl>0</BLFDialingCtl>
            <BLFDialingText></BLFDialingText>
            <BLFTalkingColor>1</BLFTalkingColor>
            <BLFTalkingCtl>1</BLFTalkingCtl>
            <BLFTalkingText>confirmed</BLFTalkingText>
            <BLFHoldColor>1</BLFHoldColor>
            <BLFHoldCtl>0</BLFHoldCtl>
            <BLFHoldText></BLFHoldText>
            <BLFFailedColor>0</BLFFailedColor>
            <BLFFailedCtl>0</BLFFailedCtl>
            <BLFFailedText>failed</BLFFailedText>
            <BLFParkedColor>0</BLFParkedColor>
            <BLFParkedCtl>3</BLFParkedCtl>
            <BLFParkedText>parked</BLFParkedText>
        </blfLed>
        <volume>
            <HandsetVol>{{ $s('fanvil_handset_volume', '5') }}</HandsetVol>
            <HandsetMicVol>3</HandsetMicVol>
            <HandsetToneVol>3</HandsetToneVol>
            <HeadsetVol>5</HeadsetVol>
            <HeadsetMicVol>3</HeadsetMicVol>
            <HeadsetRingVol>5</HeadsetRingVol>
            <HeadsetToneVol>3</HeadsetToneVol>
            <HandFreeVol>5</HandFreeVol>
            <HandFreeMicVol>3</HandFreeMicVol>
            <HandFreeRingVol>5</HandFreeRingVol>
            <HandFreeToneVol>3</HandFreeToneVol>
            <RingType>{{ $s('fanvil_default_ringtone', 'Type 1') }}</RingType>
        </volume>
        <date>
            <EnableSNTP>{{ $s('fanvil_enable_sntp', '1') }}</EnableSNTP>
            <SNTPServer>{{ $s('ntp_server_primary') }}</SNTPServer>
            <SecondSNTPServer>{{ $s('ntp_server_secondary') }}</SecondSNTPServer>
            <TimeZone>{{ $s('fanvil_time_zone') }}</TimeZone>
            <TimeZoneName>{{ $s('fanvil_time_zone_name') }}</TimeZoneName>
            <SNTPTimeout>9600</SNTPTimeout>
            <SNTPFailRetryInterval>3600</SNTPFailRetryInterval>
            <DSTType>1</DSTType>
            <DSTLocation>{{ $s('fanvil_location', '2') }}</DSTLocation>
            <DSTRuleMode>0</DSTRuleMode>
            <DSTMinOffset>{{ $s('fanvil_dst_minute_offset', '60') }}</DSTMinOffset>
            <DSTStartMon>3</DSTStartMon>
            <DSTStartWeek>5</DSTStartWeek>
            <DSTStartWday>0</DSTStartWday>
            <DSTStartHour>2</DSTStartHour>
            <DSTEndMon>10</DSTEndMon>
            <DSTEndWeek>5</DSTEndWeek>
            <DSTEndWday>0</DSTEndWday>
            <DSTEndHour>2</DSTEndHour>
            <EnableDynamicallyDisplayTime>0</EnableDynamicallyDisplayTime>
        </date>
        <timeDisplay>
            <EnableTimeDisplay>0</EnableTimeDisplay>
            <TimeDisplayStyle>{{ $s('fanvil_time_display', '0') }}</TimeDisplayStyle>
            <DateDisplayStyle>{{ $s('fanvil_date_display', '6') }}</DateDisplayStyle>
            <DateSeparator>{{ $s('fanvil_date_separator', '0') }}</DateSeparator>
        </timeDisplay>
        <softkey>
            <SoftkeyMode>0</SoftkeyMode>
            <SoftKeyExitStyle>{{ $s('fanvil_softkey_exit', '2') }}</SoftKeyExitStyle>
            <DesktopSoftkey>{{ $s('fanvil_softkey_desktopsoftkey', 'history;contact;dnd;menu;') }}</DesktopSoftkey>
            <TalkingSoftkey>{{ $s('fanvil_softkey_talkingsoftkey', 'hold;xfer;conf;end;') }}</TalkingSoftkey>
            <RingingSoftkey>{{ $s('fanvil_softkey_ringingsoftkey', 'accept;none;forward;reject;') }}</RingingSoftkey>
            <AlertingSoftkey>end;none;none;none;</AlertingSoftkey>
            <XAlertingSoftkey>end;none;none;xfer;</XAlertingSoftkey>
            <ConferenceSoftkey>hold;none;split;end;</ConferenceSoftkey>
            <WaitingSoftkey>xfer;accept;reject;end;</WaitingSoftkey>
            <EndingSoftkey>repeat;none;none;end;</EndingSoftkey>
            <DialerPreSoftkey>send;2aB;delete;exit;</DialerPreSoftkey>
            <DialerCallSoftkey>send;2aB;delete;exit;</DialerCallSoftkey>
            <DialerXferSoftkey>delete;xfer;send;exit;</DialerXferSoftkey>
            <DialerCfwdSoftkey>send;2aB;delete;exit;</DialerCfwdSoftkey>
            <DesktopClick>{{ $s('fanvil_softkey_desktopclick', 'history;status;none;none;none;') }}</DesktopClick>
            <DailerClick>pline;nline;none;none;none;</DailerClick>
            <RingingClick>none;none;none;none;none;</RingingClick>
            <CallClick>none;none;none;none;none;</CallClick>
            <DesktopLongPress>status;none;none;none;reset;</DesktopLongPress>
            <DialerConfSoftkey>contact;clogs;redial;video;cancel;</DialerConfSoftkey>
        </softkey>
        <agent>
            <AgentUsername></AgentUsername>
            <AgentPassword></AgentPassword>
            <AgentNumber></AgentNumber>
            <AgentSipline>0</AgentSipline>
            <AgentStatus>0</AgentStatus>
            <AgentStatusReason></AgentStatusReason>
            <AgentClearCallLog>0</AgentClearCallLog>
        </agent>
        <bwDir index="1">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwDir>
        <bwDir index="2">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwDir>
        <bwDir index="3">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwDir>
        <bwDir index="4">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwDir>
        <bwDir index="5">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwDir>
        <bwDir index="6">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwDir>
        <bwCallLog index="1">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwCallLog>
        <bwCallLog index="2">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwCallLog>
        <bwCallLog index="3">
            <Title></Title>
            <URL></URL>
            <Username></Username>
            <Password></Password>
            <SipLine>0</SipLine>
        </bwCallLog>
        <ldap index="1">
            <Title></Title>
            <Server></Server>
            <port>389</port>
            <Base></Base>
            <UseSSL>0</UseSSL>
            <Version>3</Version>
            <CallingLine>-1</CallingLine>
            <BindLine>-1</BindLine>
            <InCallSearch>0</InCallSearch>
            <OutCallSearch>0</OutCallSearch>
            <Authenticate>3</Authenticate>
            <Username></Username>
            <Password></Password>
            <TelAttr>telephoneNumber</TelAttr>
            <MobileAttr>mobile</MobileAttr>
            <OtherAttr>other</OtherAttr>
            <NameAttr>cn sn ou</NameAttr>
            <SortAttr>cn</SortAttr>
            <Displayname>cn</Displayname>
            <NumberFilter>(|(telephoneNumber=%)(mobile=%)(other=%))</NumberFilter>
            <NameFilter>(|(cn=%)(sn=%))</NameFilter>
            <MaxHits>50</MaxHits>
            <DisplayType>0</DisplayType>
            <SearchType>0</SearchType>
        </ldap>
        <ldap index="2">
            <Title></Title>
            <Server></Server>
            <port>389</port>
            <Base></Base>
            <UseSSL>0</UseSSL>
            <Version>3</Version>
            <CallingLine>-1</CallingLine>
            <BindLine>-1</BindLine>
            <InCallSearch>0</InCallSearch>
            <OutCallSearch>0</OutCallSearch>
            <Authenticate>3</Authenticate>
            <Username></Username>
            <Password></Password>
            <TelAttr>telephoneNumber</TelAttr>
            <MobileAttr>mobile</MobileAttr>
            <OtherAttr>other</OtherAttr>
            <NameAttr>cn sn ou</NameAttr>
            <SortAttr>cn</SortAttr>
            <Displayname>cn</Displayname>
            <NumberFilter>(|(telephoneNumber=%)(mobile=%)(other=%))</NumberFilter>
            <NameFilter>(|(cn=%)(sn=%))</NameFilter>
            <MaxHits>50</MaxHits>
            <DisplayType>0</DisplayType>
            <SearchType>0</SearchType>
        </ldap>
        <ldap index="3">
            <Title></Title>
            <Server></Server>
            <port>389</port>
            <Base></Base>
            <UseSSL>0</UseSSL>
            <Version>3</Version>
            <CallingLine>-1</CallingLine>
            <BindLine>-1</BindLine>
            <InCallSearch>0</InCallSearch>
            <OutCallSearch>0</OutCallSearch>
            <Authenticate>3</Authenticate>
            <Username></Username>
            <Password></Password>
            <TelAttr>telephoneNumber</TelAttr>
            <MobileAttr>mobile</MobileAttr>
            <OtherAttr>other</OtherAttr>
            <NameAttr>cn sn ou</NameAttr>
            <SortAttr>cn</SortAttr>
            <Displayname>cn</Displayname>
            <NumberFilter>(|(telephoneNumber=%)(mobile=%)(other=%))</NumberFilter>
            <NameFilter>(|(cn=%)(sn=%))</NameFilter>
            <MaxHits>50</MaxHits>
            <DisplayType>0</DisplayType>
            <SearchType>0</SearchType>
        </ldap>
        <ldap index="4">
            <Title></Title>
            <Server></Server>
            <port>389</port>
            <Base></Base>
            <UseSSL>0</UseSSL>
            <Version>3</Version>
            <CallingLine>-1</CallingLine>
            <BindLine>-1</BindLine>
            <InCallSearch>0</InCallSearch>
            <OutCallSearch>0</OutCallSearch>
            <Authenticate>3</Authenticate>
            <Username></Username>
            <Password></Password>
            <TelAttr>telephoneNumber</TelAttr>
            <MobileAttr>mobile</MobileAttr>
            <OtherAttr>other</OtherAttr>
            <NameAttr>cn sn ou</NameAttr>
            <SortAttr>cn</SortAttr>
            <Displayname>cn</Displayname>
            <NumberFilter>(|(telephoneNumber=%)(mobile=%)(other=%))</NumberFilter>
            <NameFilter>(|(cn=%)(sn=%))</NameFilter>
            <MaxHits>50</MaxHits>
            <DisplayType>0</DisplayType>
            <SearchType>0</SearchType>
        </ldap>
        <ldap index="5">
            <Title></Title>
            <Server></Server>
            <port>389</port>
            <Base></Base>
            <UseSSL>0</UseSSL>
            <Version>3</Version>
            <CallingLine>-1</CallingLine>
            <BindLine>-1</BindLine>
            <InCallSearch>0</InCallSearch>
            <OutCallSearch>0</OutCallSearch>
            <Authenticate>3</Authenticate>
            <Username></Username>
            <Password></Password>
            <TelAttr>telephoneNumber</TelAttr>
            <MobileAttr>mobile</MobileAttr>
            <OtherAttr>other</OtherAttr>
            <NameAttr>cn sn ou</NameAttr>
            <SortAttr>cn</SortAttr>
            <Displayname>cn</Displayname>
            <NumberFilter>(|(telephoneNumber=%)(mobile=%)(other=%))</NumberFilter>
            <NameFilter>(|(cn=%)(sn=%))</NameFilter>
            <MaxHits>50</MaxHits>
            <DisplayType>0</DisplayType>
            <SearchType>0</SearchType>
        </ldap>
        <xmlContact index="1">
            <Name></Name>
            <Addr></Addr>
            <UserName></UserName>
            <PassWd></PassWd>
            <Sipline>-1</Sipline>
            <BindLine>-1</BindLine>
            <PhonebookType>0</PhonebookType>
        </xmlContact>
        <xmlContact index="2">
            <Name></Name>
            <Addr></Addr>
            <UserName></UserName>
            <PassWd></PassWd>
            <Sipline>-1</Sipline>
            <BindLine>-1</BindLine>
            <PhonebookType>0</PhonebookType>
        </xmlContact>
        <xmlContact index="3">
            <Name></Name>
            <Addr></Addr>
            <UserName></UserName>
            <PassWd></PassWd>
            <Sipline>-1</Sipline>
            <BindLine>-1</BindLine>
            <PhonebookType>0</PhonebookType>
        </xmlContact>
        <xmlContact index="4">
            <Name></Name>
            <Addr></Addr>
            <UserName></UserName>
            <PassWd></PassWd>
            <Sipline>-1</Sipline>
            <BindLine>-1</BindLine>
            <PhonebookType>0</PhonebookType>
        </xmlContact>
        <xmlContact index="5">
            <Name></Name>
            <Addr></Addr>
            <UserName></UserName>
            <PassWd></PassWd>
            <Sipline>-1</Sipline>
            <BindLine>-1</BindLine>
            <PhonebookType>0</PhonebookType>
        </xmlContact>
    </phone>
    <dm>
        <OnhookTime>120</OnhookTime>
        <EnableHookflash>0</EnableHookflash>
        <KeyLongPressTime>2</KeyLongPressTime>
        <KeyLongLongPressTime>6</KeyLongLongPressTime>
    </dm>
    <vqm>
        <SessionReport>1</SessionReport>
        <IntervalReport>1</IntervalReport>
        <IntervalPeriod>60</IntervalPeriod>
        <MOS-LQWarning>40</MOS-LQWarning>
        <MOS-LQCritical>25</MOS-LQCritical>
        <DelayWarning>150</DelayWarning>
        <DelayCritical>200</DelayCritical>
        <PhoneReport>1</PhoneReport>
        <WEBReport>1</WEBReport>
    </vqm>
    <cti>
        <EnabledActiveUri>1</EnabledActiveUri>
        <EnabledActionUrl>1</EnabledActionUrl>
        <ActionUrlType>0</ActionUrlType>
        <ActiveUriIP></ActiveUriIP>
        <StartRebootUrl></StartRebootUrl>
        <BootCompletedUrl></BootCompletedUrl>
        <IPChangeUrl></IPChangeUrl>
        <RegOnUrl></RegOnUrl>
        <RegOffUrl></RegOffUrl>
        <RegFailedUrl></RegFailedUrl>
        <PhoneStateIdleUrl></PhoneStateIdleUrl>
        <PhoneStateTalking></PhoneStateTalking>
        <PhoneStateRinging></PhoneStateRinging>
        <OnhookUri></OnhookUri>
        <OffhookUrl></OffhookUrl>
        <DNDOnUrl></DNDOnUrl>
        <DNDOffUrl></DNDOffUrl>
        <AlwaysFWDOnUrl></AlwaysFWDOnUrl>
        <AlwaysFWDOffUrl></AlwaysFWDOffUrl>
        <BusyFWDOnUrl></BusyFWDOnUrl>
        <BusyFWDOffUrl></BusyFWDOffUrl>
        <NoAnsFWDOnUrl></NoAnsFWDOnUrl>
        <NoAnsFWDOffUrl></NoAnsFWDOffUrl>
        <MuteOnUrl></MuteOnUrl>
        <MuteOffUrl></MuteOffUrl>
        <IncomingCallUrl></IncomingCallUrl>
        <OutgoingCallUrl></OutgoingCallUrl>
        <CallActiveUrl></CallActiveUrl>
        <CallStopUrl></CallStopUrl>
        <TransferUrl></TransferUrl>
        <HoldOnUrl></HoldOnUrl>
        <HoldOffUrl></HoldOffUrl>
        <HeldOnUrl></HeldOnUrl>
        <HeldOffUrl></HeldOffUrl>
        <MuteOnCallUrl></MuteOnCallUrl>
        <MuteOffCallUrl></MuteOffCallUrl>
        <NewMissedcallUrl></NewMissedcallUrl>
        <NewMWIUrl></NewMWIUrl>
        <NewSMSUrl></NewSMSUrl>
        <WebAuthChangedUrl></WebAuthChangedUrl>
        <RecvSipMsgUrl></RecvSipMsgUrl>
        <ResetPhoneUrl></ResetPhoneUrl>
        <InsufficientRomUrl></InsufficientRomUrl>
        <EquipmentCapabilityUrl></EquipmentCapabilityUrl>
        <at>
            <AtEnabled>0</AtEnabled>
            <AtServer></AtServer>
        </at>
    </cti>
    <ateTest>
        <ATEId>0000000000000000</ATEId>
    </ateTest>
    <mcast>
        <Priority>0</Priority>
        <EnablePriority>0</EnablePriority>
        <IntercomPriority>0</IntercomPriority>
        <EnablePrioChan>0</EnablePrioChan>
        <EnableEmerChan>0</EnableEmerChan>
        <MulticastTone>1</MulticastTone>
        <McastListeningRenewTime>0</McastListeningRenewTime>
        <McastSendDTMFMode>0</McastSendDTMFMode>
        <addr index="1">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="2">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="3">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="4">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="5">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="6">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="7">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="8">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="9">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <addr index="10">
            <Name></Name>
            <Host></Host>
            <Port>0</Port>
            <Channel>0</Channel>
        </addr>
        <dynamic>
            <AutoExitExpires>60</AutoExitExpires>
        </dynamic>
    </mcast>
    <dsskey>
        <SelectDsskeyAction>0</SelectDsskeyAction>
        <MemoryKeytoBXfer>2</MemoryKeytoBXfer>
        <FuncKeyPageNum>1</FuncKeyPageNum>
        <SideKeyPageNum>1</SideKeyPageNum>
        <DSSHomePage>0</DSSHomePage>
        <DSSTimeoutToHome>90</DSSTimeoutToHome>
        <DisplayParkedInfo>0</DisplayParkedInfo>
        <DSSDIALSwitchMode>0</DSSDIALSwitchMode>
        <FirstCallWaitTime>16</FirstCallWaitTime>
        <FirstNumStartTime>360</FirstNumStartTime>
        <FirstNumEndTime>1080</FirstNumEndTime>
        <DSSLongPressAction>2</DSSLongPressAction>
        <AutoBLFList>1</AutoBLFList>
        <Extern1PageBelong>0</Extern1PageBelong>
        <Extern2PageBelong>0</Extern2PageBelong>
        <Extern3PageBelong>0</Extern3PageBelong>
        <Extern4PageBelong>0</Extern4PageBelong>
        <Extern5PageBelong>0</Extern5PageBelong>
        <DSSExtend1MAC></DSSExtend1MAC>
        <DSSExtend1IP></DSSExtend1IP>
        <DSSExtend2MAC></DSSExtend2MAC>
        <DSSExtend2IP></DSSExtend2IP>
        <DSSExtend3MAC></DSSExtend3MAC>
        <DSSExtend3IP></DSSExtend3IP>
        <DSSExtend4MAC></DSSExtend4MAC>
        <DSSExtend4IP></DSSExtend4IP>
        <DSSExtend5MAC></DSSExtend5MAC>
        <DSSExtend5IP></DSSExtend5IP>
@php
    $isSoft = static fn (array $k): bool => strtolower((string) ($k['category'] ?? '')) === 'programmable';
    $sideSrc = array_values(array_filter($main_keys ?? [], fn ($k) => ! $isSoft($k)));
    $softSrc = array_values(array_merge(
        array_filter($main_keys ?? [], $isSoft),
        $multi_purpose_keys ?? []
    ));
    $sideSlots = $sideSrc === [] ? 0 : max(3, count($sideSrc));
@endphp
        <dssSide index="1">
@for ($i = 1; $i <= $sideSlots; $i++)
@php
    $key = $sideSrc[$i - 1] ?? null;
    [$keyType, $keyValue] = $key ? $fanvilKey($key) : [0, ''];
@endphp
         <Fkey index="{{ $i }}">
             <Type>{{ $keyType }}</Type>
             <Value>{{ $keyValue }}</Value>
             <Title>{{ $key ? ($key['label'] ?? '') : '' }}</Title>
             <ICON></ICON>
         </Fkey>
@endfor
        </dssSide>
@foreach ($softSrc as $i => $key)
@php [$keyType, $keyValue] = $fanvilKey($key); @endphp
         <dssSoft index="{{ $i + 1 }}">
             <Type>{{ $keyType }}</Type>
             <Value>{{ $keyValue }}</Value>
             <Title>{{ $key['label'] ?? '' }}</Title>
         </dssSoft>
@endforeach
    </dsskey>
    <web>
        <WebServerType>0</WebServerType>
        <WebPort>80</WebPort>
        <HttpsWebPort>443</HttpsWebPort>
        <RemoteControl>1</RemoteControl>
        <EnableMMIFilter>0</EnableMMIFilter>
        <WebAuthentication>0</WebAuthentication>
        <TelnetPort>23</TelnetPort>
        <TelnetPrompt></TelnetPrompt>
        <LogonTimeout>15</LogonTimeout>
        <account index="1">
            <Name>{{ $s('admin_name', 'admin') }}</Name>
            <Password>{{ $s('admin_password', 'admin') }}</Password>
            <Level>10</Level>
        </account>
        <account index="2">
            <Name>guest</Name>
            <Password>{{ $s('fanvil_guest_password', 'guest') }}</Password>
            <Level>5</Level>
        </account>
    </web>
    <log>
        <Level>ERROR</Level>
        <Style>level,tag</Style>
        <OutputDevice>{{ $s('fanvil_syslog_enable') == '1' ? ',syslog' : 'stdout' }}</OutputDevice>
        <FileName>platform.log</FileName>
        <FileSize>512KB</FileSize>
        <SyslogTag>platform</SyslogTag>
        <SyslogServer>{{ $s('fanvil_syslog_server', '0.0.0.0') }}</SyslogServer>
        <SyslogServerPort>{{ $s('fanvil_syslog_server_port', '514') }}</SyslogServerPort>
    </log>
    <tr069>
        <TR069Tone>1</TR069Tone>
        <ACSServerType>1</ACSServerType>
        <EnableTR069>0</EnableTR069>
        <ACSURL>0.0.0.0</ACSURL>
        <ACSUserName>admin</ACSUserName>
        <ACSPassword></ACSPassword>
        <CPEUserName>dps</CPEUserName>
        <CPEPassword>{{ $s('fanvil_cpe_password', 'dps') }}</CPEPassword>
        <PeriodixInterval>3600</PeriodixInterval>
        <TLSVersion>2</TLSVersion>
        <AreaCode>020</AreaCode>
        <STUNEnable>0</STUNEnable>
        <STUNServerAddr>{{ $s('fanvil_stun_server') }}</STUNServerAddr>
        <STUNServerPort>{{ $s('fanvil_stun_port') }}</STUNServerPort>
        <STUNLocalPort>30000</STUNLocalPort>
        <STUNMaxPeriod>30</STUNMaxPeriod>
        <STUNMinPeriod>30</STUNMinPeriod>
    </tr069>
    <hotspot>
        <EnableHotspot>0</EnableHotspot>
        <Mode>1</Mode>
        <Version>1</Version>
        <ListenType>0</ListenType>
        <ListenIP>224.0.2.0</ListenIP>
        <ListenPort>16360</ListenPort>
        <OwnName>SIP Hotspot</OwnName>
        <RingMode>0</RingMode>
        <GroupCallMode>0</GroupCallMode>
        <EnableManageMode>0</EnableManageMode>
        <EnableConfigMode>0</EnableConfigMode>
        <hs index="1">
            <Enable>1</Enable>
            <ExtPrefix></ExtPrefix>
        </hs>
        <hs index="2">
            <Enable>1</Enable>
            <ExtPrefix></ExtPrefix>
        </hs>
        <hs index="3">
            <Enable>1</Enable>
            <ExtPrefix></ExtPrefix>
        </hs>
        <hs index="4">
            <Enable>1</Enable>
            <ExtPrefix></ExtPrefix>
        </hs>
    </hotspot>
    <vpn>
        <VPNmode>-1</VPNmode>
        <EnableVPN>0</EnableVPN>
        <EnableNat>0</EnableNat>
        <Openvpnmode>0</Openvpnmode>
        <VPNConnectTimeout>60</VPNConnectTimeout>
        <OpenvpnEnableUserAuth>0</OpenvpnEnableUserAuth>
        <OpenvpnUserName></OpenvpnUserName>
        <OpenvpnPassword></OpenvpnPassword>
        <L2TPServerAddress>0.0.0.0</L2TPServerAddress>
        <L2TPUserName></L2TPUserName>
        <L2TPPassword></L2TPPassword>
        <L2TPNegotiateDNS>1</L2TPNegotiateDNS>
        <PPTPServerAddress>0.0.0.0</PPTPServerAddress>
        <PPTPUserName></PPTPUserName>
        <PPTPPassword></PPTPPassword>
        <SSTPServerAddress>0.0.0.0</SSTPServerAddress>
        <SSTPUserName></SSTPUserName>
        <SSTPPassword></SSTPPassword>
    </vpn>
    <mt>
        <ContactUpdateMode>0</ContactUpdateMode>
        <AutoServerDigest>0</AutoServerDigest>
    </mt>
    <ap>
        <DefaultUsername>{{ $s('http_auth_username') }}</DefaultUsername>
        <DefaultPassword>{{ $s('http_auth_password') }}</DefaultPassword>
        <InputCfgFileName></InputCfgFileName>
        <DeviceCfgFileKey></DeviceCfgFileKey>
        <CommonCfgFileKey></CommonCfgFileKey>
        <DownloadCommonConf>1</DownloadCommonConf>
        <SaveProvisionInfo>0</SaveProvisionInfo>
        <CheckFailTimes>5</CheckFailTimes>
        <FlashServerIP>{{ $s('fanvil_provision_url', 'https://' . ($domain_name ?? '') . '/app/provision') }}</FlashServerIP>
        <FlashFileName>{{ $s('fanvil_firmware_config') }}</FlashFileName>
        <FlashProtocol>5</FlashProtocol>
        <FlashMode>1</FlashMode>
        <FlashInterval>1</FlashInterval>
        <updatePBInterval>720</updatePBInterval>
        <APConfigPriority>0</APConfigPriority>
        <pnp>
            <PNPEnable>1</PNPEnable>
            <PNPIP>224.0.1.75</PNPIP>
            <PNPPort>5060</PNPPort>
            <PNPTransport>0</PNPTransport>
            <PNPInterval>1</PNPInterval>
        </pnp>
        <opt>
            <DHCPOption>66</DHCPOption>
            <DHCPv6Option>0</DHCPv6Option>
            <DhcpOption120>0</DhcpOption120>
            <SaveDHCPOpion>0</SaveDHCPOpion>
            <DhcpRenewUpgrade>1</DhcpRenewUpgrade>
            <DHCPOptionACS>0</DHCPOptionACS>
        </opt>
    </ap>
    <fwCheck>
        <EnableAutoUpgrade>{{ $s('fanvil_enable_auto_upgrade', '0') }}</EnableAutoUpgrade>
        <UpgradeServer1>{{ $s('fanvil_firmware_upgrade_server_1', '') }}</UpgradeServer1>
        <UpgradeServer2>{{ $s('fanvil_firmware_upgrade_server_2', '') }}</UpgradeServer2>
        <AutoUpgradeInterval>{{ $s('fanvil_firmware_upgrade_interval', '24') }}</AutoUpgradeInterval>
    </fwCheck>
    <qos>
        <EnableVLAN>{{ $s('fanvil_enable_vlan', '0') }}</EnableVLAN>
        <VLANID>{{ $s('fanvil_lan_port_vlan', '256') }}</VLANID>
        <EnablePVID>{{ isset($settings['fanvil_pc_port_vlan']) ? 2 : 0 }}</EnablePVID>
        <PVIDValue>{{ $s('fanvil_pc_port_vlan', '254') }}</PVIDValue>
        <SignallingPriority>{{ $s('fanvil_qos_sip', '0') }}</SignallingPriority>
        <VoicePriority>{{ $s('fanvil_qos_rtp_voice', '0') }}</VoicePriority>
        <VideoPriority>{{ $s('fanvil_qos_rtp_video', '0') }}</VideoPriority>
        <LANPortPriority>0</LANPortPriority>
        <EnablediffServ>{{ $s('fanvil_enable_diffserv', '0') }}</EnablediffServ>
        <SingallingDSCP>{{ $s('fanvil_dscp_sip', '46') }}</SingallingDSCP>
        <VoiceDSCP>{{ $s('fanvil_dscp_rtp_voice', '46') }}</VoiceDSCP>
        <VideoDSCP>{{ $s('fanvil_dscp_rtp_video', '34') }}</VideoDSCP>
        <LLDPTransmit>{{ $s('fanvil_lldp_tx_enable', '0') }}</LLDPTransmit>
        <LLDPRefreshTime>{{ $s('fanvil_lldp_refresh', '60') }}</LLDPRefreshTime>
        <LLDPLearnPolicy>{{ $s('fanvil_lldp_learn', '0') }}</LLDPLearnPolicy>
        <LLDPSaveLearnData>0</LLDPSaveLearnData>
        <CDPEnable>0</CDPEnable>
        <CDPRefreshTime>60</CDPRefreshTime>
        <DHCPOptionVlan>132</DHCPOptionVlan>
        <DHCPVlanDataType>0</DHCPVlanDataType>
    </qos>
    <dot1x>
        <XsupMode></XsupMode>
        <XsupUser></XsupUser>
        <XsupPassword></XsupPassword>
        <sslMode>
            <PermissionCTF>0</PermissionCTF>
            <CommonName>0</CommonName>
            <CTFmode>0</CTFmode>
            <DeviceCertMode>0</DeviceCertMode>
        </sslMode>
    </dot1x>
    <pubApp>
        <WatchDogEnabled>1</WatchDogEnabled>
        <EnableInAccess>0</EnableInAccess>
        <EnableOutAccess>0</EnableOutAccess>
    </pubApp>
    <record>
        <Enabled>1</Enabled>
        <VoiceCodec>G729</VoiceCodec>
        <RecordType>0</RecordType>
        <FileSizeLimit>8</FileSizeLimit>
        <ServerAddr>0.0.0.0</ServerAddr>
        <ServerPort>10000</ServerPort>
    </record>
    <UIconfig>
        <DisplayLanguage>en;fr;</DisplayLanguage>
        <Currentmaterialversion>0x10000000</Currentmaterialversion>
    </UIconfig>
    <background>
        <TimeoutToScreensaver>0</TimeoutToScreensaver>
        <UserChangeBackground>0</UserChangeBackground>
        <UserChangeSubBackground></UserChangeSubBackground>
        <EHSHeadsettype>0</EHSHeadsettype>
        <MissedCallPopup>1</MissedCallPopup>
        <MWIPopup>0</MWIPopup>
        <DeviceConnectPopup>1</DeviceConnectPopup>
        <SMSPopup>0</SMSPopup>
        <OtherPopup>0</OtherPopup>
        <EnablePortMirror>0</EnablePortMirror>
         <EnableDisplayToInfo>0</EnableDisplayToInfo>
        <AutoCloseLed>0</AutoCloseLed>
        <IdleTimeFontColor>0xffffff</IdleTimeFontColor>
        <CommonTitleFontColor>0xffffff</CommonTitleFontColor>
        <SoftkeyFontColor>0xffffff</SoftkeyFontColor>
        <MenuListFontColor>0x262626</MenuListFontColor>
        <ScrollBarColor>0x5a9dba</ScrollBarColor>
        <WarnThemeColor>0x007bb0</WarnThemeColor>
        <InformThemeColor>0x007bb0</InformThemeColor>
        <FunkeyListFontColor>0xffffff</FunkeyListFontColor>
        <TalkingFontColor>0x262626</TalkingFontColor>
        <DisplayLogoonScreensaver>1</DisplayLogoonScreensaver>
        <DisplayTimeonScreensaver>1</DisplayTimeonScreensaver>
        <DisplayDateonScreensaver>1</DisplayDateonScreensaver>
        <DisplaySIPonScreensaver>1</DisplaySIPonScreensaver>
        <DisplayProvisionprompt>0</DisplayProvisionprompt>
        <PowerSaving>1</PowerSaving>
        <TimeoutToPowerSaving>14400</TimeoutToPowerSaving>
        <SidekeyLabelLength>0</SidekeyLabelLength>
        <AppIconDisplay>1,0,1,1</AppIconDisplay>
        <DesktopTimeDisplay>1</DesktopTimeDisplay>
        <HideDSSIcon>0</HideDSSIcon>
        <RightDSSFormat>0</RightDSSFormat>
    </background>
    <plc>
        <ModifyPlcNmk>HomePlugAV</ModifyPlcNmk>
    </plc>
</sysConf>

@break

@endswitch
