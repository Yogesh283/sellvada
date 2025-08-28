<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function create(): Response
    {
        // Simple catalog (images must exist in public/images/*)
        $catalog = [
            [
                'id' => 1,
                'name' => 'Superfruit Mix (Silver)',
                'price' => 3000,
                'variant' => '350g Jar',
                'img' => '/images/1.png',
                'type' => 'silver',
            ],
            [
                'id' => 2,
                'name' => 'Immunity Boost (Gold)',
                'price' => 15000,
                'variant' => '200g Pack',
                'img' => '/images/2.png',
                'type' => 'gold',
            ],
            [
                'id' => 3,
                'name' => 'Diamond Wellness Kit',
                'price' => 48000,
                'variant' => 'Combo',
                'img' => '/images/3.png',
                'type' => 'diamond',
            ],
            [
                'id' => 4,
                'name' => 'Wellness Refill (Repurchase)',
                'price' => 3000,
                'variant' => 'Monthly',
                'img' => '/images/4.png',
                'type' => 'repurchase',
            ],
        ];

        $walletAmount = (float) \DB::table('wallet')
            ->where('user_id', Auth::id())
            ->value('amount');

        return Inertia::render('Shop/Buy', [
            'catalog'        => $catalog,
            'defaults'       => ['shipping' => 49],
            'walletBalance'  => $walletAmount,
        ]);
    }
}
