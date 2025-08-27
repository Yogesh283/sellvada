<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $uid = optional($request->user())->id;

        // Single-balance approach: read amount from wallet row; if none, use 0
        $walletBalance = 0.0;
        if ($uid) {
            $walletBalance = (float) (DB::table('wallet')
                ->where('user_id', $uid)
                ->value('amount') ?? 0);
        }

        return array_merge(parent::share($request), [
            'auth'          => ['user' => $request->user()],
            'walletBalance' => $walletBalance,
        ]);
    }
}
