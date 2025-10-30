<?php declare(strict_types=1);

return [
    'default_theme' => 'classic',
    'default_accent' => '#2563EB',
    'default_text_color' => '#0F172A',
    'themes' => [
        'classic' => [
            'label' => 'Clásico',
            'accent' => '#2563EB',
            'text' => '#0F172A',
            'preview' => 'linear-gradient(135deg, rgba(37, 99, 235, 0.18) 0%, rgba(37, 99, 235, 0.06) 100%)',
        ],
        'sunset' => [
            'label' => 'Atardecer',
            'accent' => '#F97316',
            'text' => '#7C2D12',
            'preview' => 'linear-gradient(135deg, rgba(249, 115, 22, 0.22) 0%, rgba(253, 186, 116, 0.16) 100%)',
        ],
        'aurora' => [
            'label' => 'Aurora',
            'accent' => '#6366F1',
            'text' => '#111827',
            'preview' => 'linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(129, 140, 248, 0.12) 100%)',
        ],
        'forest' => [
            'label' => 'Bosque',
            'accent' => '#16A34A',
            'text' => '#064E3B',
            'preview' => 'linear-gradient(135deg, rgba(22, 163, 74, 0.22) 0%, rgba(134, 239, 172, 0.14) 100%)',
        ],
        'midnight' => [
            'label' => 'Medianoche',
            'accent' => '#0EA5E9',
            'text' => '#F8FAFC',
            'preview' => 'linear-gradient(135deg, rgba(14, 165, 233, 0.2) 0%, rgba(30, 64, 175, 0.24) 100%)',
        ],
    ],
    'accent_palette' => [
        '#2563EB' => 'Azul eléctrico',
        '#F97316' => 'Mandarina',
        '#DB2777' => 'Magenta',
        '#16A34A' => 'Verde hoja',
        '#0EA5E9' => 'Cian brillante',
        '#9333EA' => 'Violeta profundo',
    ],
    'text_palette' => [
        '#0F172A' => 'Azul noche',
        '#111827' => 'Grafito',
        '#1F2937' => 'Pizarra',
        '#312E81' => 'Índigo',
        '#F8FAFC' => 'Blanco humo',
        '#F97316' => 'Mandarina',
    ],
];
