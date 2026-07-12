<?php
/**
 * Jackpot widget config: epoch and provider tiers.
 * Include once before rendering views/partials/jackpot.php
 */
if (!isset($jackpotEpoch)) {
    $jackpotEpoch = date('Y-m-d H:i:s'); // her sayfa yüklemesinde güncel epoch
}
if (!isset($providers)) {
    $providers = [
        [
            'id'    => 'amusnet',
            'name'  => 'AMUSNET',
            'tab'   => 'AMUSNET',
            'tiers' => [
                ['name' => 'PİKES',  'amount' => 598246.56,  'increment' => 12.5, 'main' => true],
                ['name' => 'KUPA',   'amount' => 72980.53,   'increment' => 3.8],
                ['name' => 'KARO',   'amount' => 6425.71,    'increment' => 1.2],
                ['name' => 'SİNEK',  'amount' => 824.50,     'increment' => 0.4],
            ],
        ],
        [
            'id'    => 'apollogames',
            'name'  => 'APOLLOGAMES',
            'tab'   => 'APOLLO GAMES',
            'tiers' => [
                ['name' => 'ALTIN',   'amount' => 159239.84, 'increment' => 8.2, 'main' => true],
                ['name' => 'GÜMÜŞ',   'amount' => 57755.14,  'increment' => 2.5],
                ['name' => 'BRONZE',  'amount' => 8460.37,   'increment' => 0.9],
            ],
        ],
        [
            'id'    => 'fugasoogs',
            'name'  => 'FUGASOOGS',
            'tab'   => 'FUGAS',
            'tiers' => [
                ['name' => 'MAXİ', 'amount' => 180456.70, 'increment' => 9.3, 'main' => true],
                ['name' => 'MİDİ', 'amount' => 44174.55,  'increment' => 3.1],
                ['name' => 'MİNİ', 'amount' => 23429.41,  'increment' => 1.2],
            ],
        ],
        [
            'id'    => 'egtdigital',
            'name'  => 'EGT DİGİTAL',
            'tab'   => 'EGT',
            'tiers' => [
                ['name' => 'GRAND', 'amount' => 716324.63, 'increment' => 15.0, 'main' => true],
                ['name' => 'MAJOR', 'amount' => 88501.22,  'increment' => 4.5],
                ['name' => 'MINOR', 'amount' => 3247.59,   'increment' => 0.8],
                ['name' => 'MINI',  'amount' => 445.62,    'increment' => 0.2],
            ],
        ],
    ];
}
