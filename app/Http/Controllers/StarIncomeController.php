<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StarIncomeController extends Controller
{
    // sell.type me jin types ko matching volume me include karna hai:
    private array $includeTypes = ['silver','gold','diamond','repurchase'];

    // Image ke hisaab se fixed slabs (threshold = total matching volume in INR)
    private array $slabs = [
        ['no'=>1,  'name'=>'1 STAR',  'threshold'=> 100000,      'income'=>   2000],
        ['no'=>2,  'name'=>'2 STAR',  'threshold'=> 200000,      'income'=>   4000],
        ['no'=>3,  'name'=>'3 STAR',  'threshold'=> 400000,      'income'=>   8000],
        ['no'=>4,  'name'=>'4 STAR',  'threshold'=> 800000,      'income'=>  16000],
        ['no'=>5,  'name'=>'5 STAR',  'threshold'=>1600000,      'income'=>  32000],
        ['no'=>6,  'name'=>'6 STAR',  'threshold'=>3200000,      'income'=>  64000],
        ['no'=>7,  'name'=>'7 STAR',  'threshold'=>6400000,      'income'=> 128000],   // 1.28 Lac
        ['no'=>8,  'name'=>'8 STAR',  'threshold'=>12800000,     'income'=> 256000],   // 1.28 Cr / 2.56 Lac
        ['no'=>9,  'name'=>'9 STAR',  'threshold'=>25000000,     'income'=> 512000],   // 2.5 Cr / 5.12 Lac
        ['no'=>10, 'name'=>'10 STAR', 'threshold'=>50000000,     'income'=>1024000],   // 5 Cr / 10.24 Lac
        ['no'=>11, 'name'=>'11 STAR', 'threshold'=>100000000,    'income'=>2048000],   // 10 Cr / 20.48 Lac
        ['no'=>12, 'name'=>'12 STAR', 'threshold'=>200000000,    'income'=>4096000],   // 20 Cr / 40.96 Lac
    ];

    /**
     * Table-only page: fixed ranks + user ka total matching volume.
     * Optional filters: ?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function show(Request $request): Response
    {
        $uid = (int) Auth::id();
        abort_unless($uid, 401);

        $from = $request->query('from');
        $to   = $request->query('to');

        // Lifetime (or filtered) L/R volume from `sell` table for this sponsor
        $q = DB::table('sell')
            ->select('leg', DB::raw('SUM(amount) as amt'))
            ->where('status', 'paid')
            ->whereIn('type', $this->includeTypes)
            ->where('sponsor_id', $uid);

        if ($from) $q->whereDate('created_at', '>=', $from);
        if ($to)   $q->whereDate('created_at', '<=', $to);

        $L = 0.0; $R = 0.0;
        foreach ($q->groupBy('leg')->get() as $r) {
            $leg = strtoupper((string)($r->leg ?? ''));
            if ($leg === 'L') $L = (float)$r->amt;
            if ($leg === 'R') $R = (float)$r->amt;
        }
        $matched = min($L, $R);

        // Slabs me status/progress add karo
        $rows = array_map(function ($s) use ($matched) {
            $s['achieved'] = $matched >= $s['threshold'];
            $s['progress'] = max(0, min(100, $s['threshold'] > 0 ? ($matched / $s['threshold']) * 100 : 0));
            $s['remaining'] = max(0, $s['threshold'] - $matched);
            return $s;
        }, $this->slabs);

        // Highest achieved
        $current = null;
        foreach ($rows as $s) {
            if ($s['achieved']) $current = $s;
        }

        return Inertia::render('Income/Star', [
            'asOf'     => now()->toDateTimeString(),
            'filters'  => ['from' => $from, 'to' => $to],
            'left'     => $L,
            'right'    => $R,
            'matched'  => $matched,
            'rows'     => $rows,
            'current'  => $current,   // null or last achieved slab
        ]);
    }
}
