<?php

namespace App\Http\Controllers;

use App\Models\Sell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BinarySummaryController extends Controller
{
    /**
     * Show Binary business (Left/Right) grouped by rank (type).
     * Uses Sell table:
     *  - sponsor_id = auth()->id()
     *  - status = 'paid'
     * Optional filters: ?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Renders Inertia page: Income/Binary
     */
    public function show(Request $request): Response
    {
        $userId = (int) Auth::id();
        abort_unless($userId, 401);

        $from = $request->query('from'); // YYYY-MM-DD
        $to   = $request->query('to');   // YYYY-MM-DD

        $base = Sell::query()
            ->where('sponsor_id', $userId)
            ->where('status', 'paid');

        if ($from) {
            $base->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $base->whereDate('created_at', '<=', $to);
        }

        // rank/type + leg wise grouped summary
        $rows = (clone $base)
            ->selectRaw("
                LOWER(COALESCE(NULLIF(type,''),'other')) as type,
                UPPER(COALESCE(leg,'NA'))               as leg,
                SUM(amount)                              as amount,
                COUNT(*)                                 as orders
            ")
            ->groupBy('type','leg')
            ->get();

        // Prepare matrix for ranks
        $ranks  = ['silver','gold','diamond','other'];
        $matrix = [];
        foreach ($ranks as $r) {
            $matrix[$r] = [
                'left' => 0.0,
                'right' => 0.0,
                'orders_left' => 0,
                'orders_right' => 0,
            ];
        }

        foreach ($rows as $r) {
            $type = $r->type ?? 'other';
            if (!isset($matrix[$type])) {
                $matrix[$type] = ['left'=>0,'right'=>0,'orders_left'=>0,'orders_right'=>0];
            }
            if ($r->leg === 'L') {
                $matrix[$type]['left']        += (float) $r->amount;
                $matrix[$type]['orders_left'] += (int) $r->orders;
            } elseif ($r->leg === 'R') {
                $matrix[$type]['right']        += (float) $r->amount;
                $matrix[$type]['orders_right'] += (int) $r->orders;
            }
        }

        // Totals
        $leftTotal  = array_sum(array_map(fn($x) => $x['left'],  $matrix));
        $rightTotal = array_sum(array_map(fn($x) => $x['right'], $matrix));

        // (Optional) last 20 paid orders of this sponsor for quick glance
        $recent = (clone $base)
            ->select(['buyer_id','product','type','leg','amount','created_at'])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function ($r) {
                return [
                    'buyer_id'   => $r->buyer_id,
                    'product'    => $r->product,
                    'type'       => strtolower($r->type ?? 'other'),
                    'leg'        => strtoupper($r->leg ?? 'NA'),
                    'amount'     => (float) $r->amount,
                    'created_at' => $r->created_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('Income/Binary', [
            'asOf'    => now()->toDateTimeString(),
            'filters' => ['from'=>$from, 'to'=>$to],
            'totals'  => ['left'=>$leftTotal, 'right'=>$rightTotal],
            'matrix'  => $matrix,  // rank-wise Left/Right amounts + order counts
            'recent'  => $recent,  // last 20 lines (for context table)
        ]);
    }
}
