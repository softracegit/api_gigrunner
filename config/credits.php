<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Credit types
    |--------------------------------------------------------------------------
    */
    'types' => [
        'ai' => 'Créditos AI',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purchasable packs (simulation until Stripe)
    |--------------------------------------------------------------------------
    */
    'packs' => [
        'license_standard' => [
            'name' => 'Licença Standard',
            'description' => 'Licença da app + 50 créditos AI',
            'price_cents' => 1000, // 10,00 €
            'currency' => 'EUR',
            'grants_license' => [
                'plan' => 'standard',
                'days' => null, // sem expiração
            ],
            'credits' => [
                'ai' => 50,
            ],
        ],
        'ai_50' => [
            'name' => 'Pack AI 50',
            'description' => '50 utilizações AI extra',
            'price_cents' => 500,
            'currency' => 'EUR',
            'grants_license' => null,
            'credits' => [
                'ai' => 50,
            ],
        ],
        'ai_200' => [
            'name' => 'Pack AI 200',
            'description' => '200 utilizações AI extra',
            'price_cents' => 1500,
            'currency' => 'EUR',
            'grants_license' => null,
            'credits' => [
                'ai' => 200,
            ],
        ],
    ],
];
