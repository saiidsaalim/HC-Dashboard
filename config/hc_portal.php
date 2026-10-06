<?php

/*
 * Sumber data halaman portal (landing).
 * Menambah unit / workspace baru cukup menambah data di sini.
 */
return [
    'name'       => 'HC.Portal',
    'department' => 'Dept. of Human Capital & GRC',
    'company'    => 'PT. Semen Tonasa',

    'hero' => [
        'title' => 'Platform terpadu',
        'lines' => [
            'untuk mengintegrasikan alur kerja, layanan informasi,',
            'serta tata kelola dan pengembangan talenta berkelanjutan',
        ],
    ],

    // Unit dalam departemen. 'groups' kosong = workspace belum tersedia.
    'units' => [
        [
            'key'    => 'hs',
            'name'   => 'Health Services & Industrial Hygiene',
            'groups' => [],
        ],
        [
            'key'    => 'hcpd',
            'name'   => 'HC Planning & Development',
            'groups' => [
                [
                    'title' => 'Unit HC Planning & Development',
                    'items' => [
                        ['name' => 'HC OD & Career Management',      'icon' => 'i-od',  'color' => '#1d6fa5'],
                        ['name' => 'Performance & Talent Management', 'icon' => 'i-ptm', 'color' => '#0e8c8c'],
                        ['name' => 'Learning & Development',          'icon' => 'i-ld',  'color' => '#e08a00'],
                    ],
                ],
                [
                    'title' => 'Executive',
                    'items' => [
                        ['name' => 'GM Human Capital & GRC', 'icon' => 'i-gm', 'color' => '#002d4b'],
                    ],
                ],
            ],
        ],
        ['key' => 'hcop', 'name' => 'HC Operational',                   'groups' => []],
        ['key' => 'bsd',  'name' => 'Business & System Development',    'groups' => []],
        ['key' => 'grc',  'name' => 'GRC Management',                   'groups' => []],
    ],
];
