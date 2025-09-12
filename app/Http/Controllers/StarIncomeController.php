<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StarIncomeController extends Controller
{
    private array $includeTypes = ['silver','gold','diamond','repurchase'];

   private array $slabs = [
    ['no'=>1,  'name'=>'1 STAR',  'threshold'=> 100000,      'income'=>   5000],
    ['no'=>2,  'name'=>'2 STAR',  'threshold'=> 200000,      'income'=>  10000],
    ['no'=>3,  'name'=>'3 STAR',  'threshold'=> 400000,      'income'=>  20000],
    ['no'=>4,  'name'=>'4 STAR',  'threshold'=> 800000,      'income'=>  40000],
    ['no'=>5,  'name'=>'5 STAR',  'threshold'=>1600000,      'income'=>  80000],
    ['no'=>6,  'name'=>'6 STAR',  'threshold'=>3200000,      'income'=> 160000],
    ['no'=>7,  'name'=>'7 STAR',  'threshold'=>6400000,      'income'=> 320000],
    ['no'=>8,  'name'=>'8 STAR',  'threshold'=>12800000,     'income'=> 640000],
    ['no'=>9,  'name'=>'9 STAR',  'threshold'=>25000000,     'income'=>1250000],
    ['no'=>10, 'name'=>'10 STAR', 'threshold'=>50000000,     'income'=>2500000],
    ['no'=>11, 'name'=>'11 STAR', 'threshold'=>100000000,    'income'=>5000000],
    ['no'=>12, 'name'=>'12 STAR', 'threshold'=>200000000,    'income'=>10000000],
];


    public function show(Request $request): Response
    {
        $uid = (int) Auth::id();
        abort_unless($uid, 401);

        $from = $request->query('from');
        $to   = $request->query('to');

        // Build placement team (full downline of this user)
        $rootRow = DB::table('users')->where('id', $uid)->select('left_user_id','right_user_id')->first();
        $leftRoot  = (int)($rootRow->left_user_id ?? 0);
        $rightRoot = (int)($rootRow->right_user_id ?? 0);

        $teamCte = "
WITH RECURSIVE team AS (
  SELECT id, left_user_id, right_user_id, 'NA' AS root_leg
  FROM users WHERE id = ?

  UNION ALL

  SELECT u.id, u.left_user_id, u.right_user_id,
         CASE WHEN u.id = ? THEN 'L'
              WHEN u.id = ? THEN 'R'
              ELSE t.root_leg END AS root_leg
  FROM users u
  JOIN team t ON u.id IN (t.left_user_id, t.right_user_id)
)
";

        $binds = [$uid, $leftRoot, $rightRoot];
        $dateSql = '';
        if ($from) { $dateSql .= " AND DATE(s.created_at) >= ?"; $binds[] = $from; }
        if ($to)   { $dateSql .= " AND DATE(s.created_at) <= ?"; $binds[] = $to; }

        // Get Left / Right total business of full team
        $sql = $teamCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, SUM(s.amount) as amt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid'
  AND LOWER(s.type) IN ('silver','gold','diamond','repurchase')
  {$dateSql}
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $rows = DB::select($sql, $binds);

        $L = 0.0; $R = 0.0;
        foreach ($rows as $r) {
            if ($r->leg === 'L') $L = (float)$r->amt;
            elseif ($r->leg === 'R') $R = (float)$r->amt;
        }
        $matched = min($L, $R);

        // Slab status/progress
        $rows = array_map(function ($s) use ($matched) {
            $s['achieved']  = $matched >= $s['threshold'];
            $s['progress']  = max(0, min(100, $s['threshold'] > 0 ? ($matched / $s['threshold']) * 100 : 0));
            $s['remaining'] = max(0, $s['threshold'] - $matched);
            return $s;
        }, $this->slabs);

        $current = null;
        foreach ($rows as $s) {
            if ($s['achieved']) $current = $s;
        }

        return Inertia::render('Income/Star', [
            'asOf'     => now()->toDateTimeString(),
            'filters'  => ['from'=>$from, 'to'=>$to],
            'left'     => $L,
            'right'    => $R,
            'matched'  => $matched,
            'rows'     => $rows,
            'current'  => $current,
        ]);

    }



public function showw(Request $request): Response
    {
        $uid = (int) Auth::id();
        abort_unless($uid, 401);

        $from = $request->query('from');
        $to   = $request->query('to');

        // Build placement team (full downline of this user)
        $rootRow = DB::table('users')->where('id', $uid)->select('left_user_id','right_user_id')->first();
        $leftRoot  = (int)($rootRow->left_user_id ?? 0);
        $rightRoot = (int)($rootRow->right_user_id ?? 0);

        $teamCte = "
WITH RECURSIVE team AS (
  SELECT id, left_user_id, right_user_id, 'NA' AS root_leg
  FROM users WHERE id = ?

  UNION ALL

  SELECT u.id, u.left_user_id, u.right_user_id,
         CASE WHEN u.id = ? THEN 'L'
              WHEN u.id = ? THEN 'R'
              ELSE t.root_leg END AS root_leg
  FROM users u
  JOIN team t ON u.id IN (t.left_user_id, t.right_user_id)
)
";

        $binds = [$uid, $leftRoot, $rightRoot];
        $dateSql = '';
        if ($from) { $dateSql .= " AND DATE(s.created_at) >= ?"; $binds[] = $from; }
        if ($to)   { $dateSql .= " AND DATE(s.created_at) <= ?"; $binds[] = $to; }

        // Get Left / Right total business of full team
        $sql = $teamCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, SUM(s.amount) as amt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid'
  AND LOWER(s.type) IN ('silver','gold','diamond','repurchase')
  {$dateSql}
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $rows = DB::select($sql, $binds);

        $L = 0.0; $R = 0.0;
        foreach ($rows as $r) {
            if ($r->leg === 'L') $L = (float)$r->amt;
            elseif ($r->leg === 'R') $R = (float)$r->amt;
        }
        $matched = min($L, $R);

        // Slab status/progress
        $rows = array_map(function ($s) use ($matched) {
            $s['achieved']  = $matched >= $s['threshold'];
            $s['progress']  = max(0, min(100, $s['threshold'] > 0 ? ($matched / $s['threshold']) * 100 : 0));
            $s['remaining'] = max(0, $s['threshold'] - $matched);
            return $s;
        }, $this->slabs);

        $current = null;
        foreach ($rows as $s) {
            if ($s['achieved']) $current = $s;
        }

        return Inertia::render('Dashboard', [
            'asOf'     => now()->toDateTimeString(),
            'filters'  => ['from'=>$from, 'to'=>$to],
            'left'     => $L,
            'right'    => $R,
            'matched'  => $matched,
            'rows'     => $rows,
            'current'  => $current,
        ]);

    }

}
