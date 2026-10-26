<?php

return [
    'asset_hosts' => [
        'images.unsplash.com',
        'images.pexels.com',
        'upload.wikimedia.org',
    ],
    'asset_directory' => public_path('image/portal'),
    'assets' => [
        [
            'key' => 'hero-building',
            'url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?fit=crop&w=1920&q=75',
            'path' => 'hero-building.jpg',
            'max_bytes' => 409600,
            'source' => 'Unsplash',
            'license' => 'Unsplash License (free use; attribution appreciated but not required): https://unsplash.com/license',
            'attribution' => null,
        ],
        [
            'key' => 'sig-logo',
            'url' => null,
            'path' => 'sig-logo.png',
            'max_bytes' => 204800,
            'source' => null,
            'license' => 'Merek dagang; gunakan berkas resmi dengan izin yang sesuai.',
            'attribution' => null,
        ],
        [
            'key' => 'semen-tonasa-logo',
            'url' => null,
            'path' => 'semen-tonasa.png',
            'max_bytes' => 204800,
            'source' => null,
            'license' => 'Merek dagang; gunakan berkas resmi dengan izin yang sesuai.',
            'attribution' => null,
        ],
    ],
    'authenticated' => [
        'eyebrow' => 'PORTAL HUMAN CAPITAL',
        'title' => 'Pilih Workspace',
        'description' => 'Pilih workspace untuk mengelola organisasi, kinerja, dan pengembangan kompetensi secara terhubung.',
        'workspace_label' => 'WORKSPACE',
    ],
    'workspace_navigation' => [
        'performance-talent-management' => [
            [
                'route' => 'performance-talent.dashboard',
                'label' => 'Dashboard',
                'icon' => 'home',
                'permission' => 'workspace.performance-talent-management.view',
            ],
            [
                'route' => 'performance-talent.critical-positions.index',
                'label' => 'Critical Position',
                'icon' => 'clipboard',
                'permission' => 'workspace.performance-talent-management.view',
            ],
            [
                'route' => 'performance-talent.successors.index',
                'label' => 'Successor View',
                'icon' => 'users',
                'permission' => 'workspace.performance-talent-management.view',
            ],
            [
                'route' => 'performance-talent.talent-pool.index',
                'label' => 'Talent Pool',
                'icon' => 'users',
                'permission' => 'workspace.performance-talent-management.view',
            ],
            [
                'route' => 'performance-talent.development-plans.index',
                'label' => 'Development Plan',
                'icon' => 'chart',
                'permission' => 'workspace.performance-talent-management.view',
            ],
            [
                'route' => 'performance-talent.reports.index',
                'label' => 'Report',
                'icon' => 'document',
                'permission' => 'workspace.performance-talent-management.view',
            ],
        ],
    ],
    'units' => [
        [
            'slug' => 'hc-planning-development',
            'name' => 'HC Planning & Development',
            'workspaces' => [
                [
                    'slug' => 'hc-od-career-management',
                    'name' => 'HC OD & Career Management',
                    'description' => 'Kelola struktur organisasi, perencanaan tenaga kerja, dan pengembangan karier dalam satu ruang kerja.',
                    'icon' => 'org-chart',
                    'tone' => 'blue',
                    'permission' => 'workspace.hc-od-career-management.view',
                ],
                [
                    'slug' => 'performance-talent-management',
                    'name' => 'Performance & Talent Management',
                    'description' => 'Pantau performa, kelola data pegawai, dan dukung keputusan pengembangan talenta.',
                    'icon' => 'target',
                    'tone' => 'green',
                    'permission' => 'workspace.performance-talent-management.view',
                    'home_route' => 'performance-talent.dashboard',
                ],
                [
                    'slug' => 'learning-development',
                    'name' => 'Learning & Development',
                    'description' => 'Akses layanan pembelajaran dan pengembangan kompetensi pegawai secara terarah.',
                    'icon' => 'learning',
                    'tone' => 'amber',
                    'permission' => 'workspace.learning-development.view',
                ],
            ],
        ],
    ],
    'footer' => [
        'organization' => 'Dept. of Human Capital & GRC',
        'description' => 'Portal layanan informasi, alur kerja, dan pengembangan talenta Dept. of Human Capital & GRC.',
        'copyright' => 'SIG – Dept. of Human Capital & GRC. Hak cipta dilindungi.',
    ],
];
