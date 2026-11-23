<?php
// Mirror of the provisioning rewrites in /etc/nginx/sites-enabled/fspbx.conf (lines 181-239), first match wins.
// Returns the query array nginx would hand to /app/provision/index.php, or null.
function p4352_rewrite(string $tail): ?array {
    $tail = ltrim($tail, '/');
    $m = [];
    $rules = [
        ['#^algom([A-Fa-f0-9]{12})\.conf$#', fn($m) => ['mac' => $m[1], 'file' => 'algom{$mac}.conf']],
        ['#^MN_([A-Fa-f0-9]{12})\.cfg$#', fn($m) => ['mac' => $m[1], 'file' => 'MN_{$mac}.cfg']],
        ['#^MN_Generic\.cfg$#', fn($m) => ['mac' => '08000f000000', 'file' => 'MN_Generic.cfg']],
        ['#^cfg([A-Fa-f0-9]{12})(\.(xml|cfg))?$#', fn($m) => ['mac' => $m[1]]],
        ['#^([A-Fa-f0-9]{12})/phonebook\.xml$#', fn($m) => ['mac' => $m[1], 'file' => 'phonebook.xml']],
        ['#^pb/([A-Fa-f0-9]{12})/phonebook\.xml$#', fn($m) => ['mac' => $m[1], 'file' => 'phonebook.xml']],
        ['#^([0-9]{5})/cfg([A-Fa-f0-9]{12})\.xml$#', fn($m) => ['ext' => $m[1]]],
        ['#^aastra\.cfg$#', fn($m) => ['file' => 'aastra.cfg']],
        ['#^(y[0-9]{12})(\.cfg|\.boot)?$#', fn($m) => ['file' => $m[1] . ($m[2] ?? '')]],
        ['#^([A-Fa-f0-9]{12})(\.(xml|cfg))?$#', fn($m) => ['mac' => $m[1]]],
        ['#^phone([A-Fa-f0-9]{12})\.cfg$#', fn($m) => ['mac' => $m[1], 'file' => 'phoneMAC.cfg']],
        ['#^(SPIP|VVX|SSIP|Trio|EdgeE)(\d{3,4})-([A-Fa-f0-9]{12})\.cfg$#', fn($m) => ['mac' => $m[3], 'file' => 'custom.cfg']],
        ['#^000000000000\.cfg$#', fn($m) => ['file' => '{$mac}.cfg']],
        ['#^features\.cfg$#', fn($m) => ['file' => 'features.cfg']],
        ['#^([A-Fa-f0-9]{12})-sip\.cfg$#', fn($m) => ['mac' => $m[1], 'file' => 'sip.cfg']],
        ['#^([A-Fa-f0-9]{12})-phone\.cfg$#', fn($m) => ['mac' => $m[1]]],
        ['#^([A-Fa-f0-9]{12})-registration\.cfg$#', fn($m) => ['mac' => $m[1], 'file' => '{$mac}-registration.cfg']],
        ['#^file/(.*\.(xml|cfg))$#', fn($m) => ['file' => $m[1]]],
        ['#^([0-9]{1,11})_Extern\.xml$#', fn($m) => ['ext' => $m[1], 'file' => '{$mac}_extern.xml']],
        ['#^([0-9]{1,11})_Phonebook\.xml$#', fn($m) => ['ext' => $m[1], 'file' => '{$mac}_phonebook.xml']],
        ['#^VCS754_([A-Fa-f0-9]{12})\.cfg$#', fn($m) => ['mac' => $m[1]]],
        ['#^pb([A-Fa-f0-9-]{12,17})/directory\.xml$#', fn($m) => ['mac' => $m[1], 'file' => 'directory.xml']],
        ['#^([A-Fa-f0-9]{12})-contacts\.cfg$#', fn($m) => ['mac' => $m[1], 'file' => '{$mac}-contacts.cfg']],
        ['#^([A-Fa-f0-9]{12})-smartblf\.cfg$#', fn($m) => ['mac' => $m[1], 'file' => '{$mac}-smartblf.cfg']],
    ];
    foreach ($rules as [$re, $fn]) {
        if (preg_match($re, $tail, $m)) { return $fn($m); }
    }
    return null;
}
