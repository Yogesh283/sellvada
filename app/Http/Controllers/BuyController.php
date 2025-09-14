<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class BuyController extends Controller
{
    public function index()
    {
        // Minimal catalog (you can load from DB if you prefer; this is static for now)
        $catalog = [
            [
                'id'      => 1,
                'name'    => 'Superfruit Mix (Silver)',
                'price'   => 3000,
                'variant' => '350g Jar',
                'img'     => '/image/2.png',
                'type'    => 'silver',
            ],
            [
                'id'      => 2,
                'name'    => 'Immunity Boost (Gold)',
                'price'   => 15000,
                'variant' => '200g Pack',
                'img'     => '/image/10.png',
                'type'    => 'gold',
            ],
            [
                'id'      => 3,
                'name'    => 'Diamond Wellness Kit',
                'price'   => 48000,
                'variant' => 'Combo',
                'img'     => '/image/20.png',
                'type'    => 'diamond',
            ],
            [
                'id'      => 4,
                'name'    => 'Wellness Refill (Repurchase)',
                'price'   => 3000,
                'variant' => 'Monthly',
                'img'     => '/image/2.png',
                'type'    => 'repurchase',
            ],
            // NEW: Starter plan (red colour in UI as requested)
            [
                'id'      => 5,
                'name'    => 'Starter Pack',
                'price'   => 1500,               // price you asked for
                'variant' => 'Starter Bundle',
                'img'     => '/image/starter.png', // change to your starter image path
                'type'    => 'starter',
            ],
        ];

        return Inertia::render('Shop/Buy', [
            'catalog' => $catalog,
            'defaults' => [
                'shipping' => 49,   // UI default; server-side final calc is up to you
            ],
        ]);
    }
}
