<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private array $slabs = [
        ['no'=>1,  'name'=>'1 STAR',  'threshold'=> 100000,      'income'=>   2000],
        ['no'=>2,  'name'=>'2 STAR',  'threshold'=> 200000,      'income'=>   4000],
        ['no'=>3,  'name'=>'3 STAR',  'threshold'=> 400000,      'income'=>   8000],
        ['no'=>4,  'name'=>'4 STAR',  'threshold'=> 800000,      'income'=>  16000],
        ['no'=>5,  'name'=>'5 STAR',  'threshold'=>1600000,      'income'=>  32000],
        ['no'=>6,  'name'=>'6 STAR',  'threshold'=>3200000,      'income'=>  64000],
        ['no'=>7,  'name'=>'7 STAR',  'threshold'=>6400000,      'income'=> 128000],
        ['no'=>8,  'name'=>'8 STAR',  'threshold'=>12800000,     'income'=> 256000],
        ['no'=>9,  'name'=>'9 STAR',  'threshold'=>25000000,     'income'=> 512000],
        ['no'=>10, 'name'=>'10 STAR', 'threshold'=>50000000,     'income'=>1024000],
        ['no'=>11, 'name'=>'11 STAR', 'threshold'=>100000000,    'income'=>2048000],
        ['no'=>12, 'name'=>'12 STAR', 'threshold'=>200000000,    'income'=>4096000],
    ];

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $raw = $user->toArray();
        $mask = function (?string $v, int $keep = 2) {
            if (!$v) return null;
            $len = strlen($v);
            if ($len <= 4) return str_repeat('*', $len);
            return substr($v, 0, $keep) . str_repeat('*', max(0, $len - 2*$keep)) . substr($v, -$keep);
        };

        // basic ids
        $userId = (int)$user->id;
        $myReferralId = DB::table('users')->where('id', $userId)->value('referral_id');
        $hasReferral = ! empty($myReferralId);

        // recent personal sells (for UI)
        $recentSells = DB::table('sell')
            ->where('buyer_id', $userId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id','buyer_id','product','amount','type','status','created_at']);

        // wallet / payouts
        $walletAmount = (float) DB::table('wallet')->where('user_id', $userId)->sum('amount');
        $payoutAmount = (float) DB::table('_payout')->where('user_id', $userId)->sum('amount');
        $payoutToday = (float) DB::table('_payout')
            ->where('user_id', $userId)
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        // first sell (fristsell expected by frontend)
        $firstsell = DB::table('sell')
            ->where('buyer_id', $userId)
            ->where('status','paid')
            ->orderBy('created_at','asc')
            ->first();

        // -------------------- TEAM (referral) CTE totals (sell & repurchase) --------------------
        $teamCte = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position
  FROM users
  WHERE refer_by = ?

  UNION ALL

  SELECT u.id, u.referral_id, u.refer_by, u.position
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
";

        $teamSell = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0,'rows'=>[]];
        $teamRep = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0,'rows'=>[]];

        if ($hasReferral) {
            // team sell totals (no date filter — lifetime). You can add date filters if you want monthly view.
            $tsSql = $teamCte . "
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt, COUNT(s.id) AS cnt
FROM team u
JOIN sell s ON s.buyer_id = u.id
WHERE s.status='paid'
GROUP BY UPPER(COALESCE(u.position,'NA'))
";
            $tsRows = DB::select($tsSql, [$myReferralId]);
            foreach ($tsRows as $r) {
                if ($r->leg === 'L') { $teamSell['left'] = (float)$r->amt; $teamSell['cnt_left'] = (int)$r->cnt; }
                if ($r->leg === 'R') { $teamSell['right'] = (float)$r->amt; $teamSell['cnt_right'] = (int)$r->cnt; }
            }
            $teamSell['rows'] = $tsRows;

            // team repurchase totals
            $trSql = $teamCte . "
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt, COUNT(r.id) AS cnt
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid'
GROUP BY UPPER(COALESCE(u.position,'NA'))
";
            $trRows = DB::select($trSql, [$myReferralId]);
            foreach ($trRows as $r) {
                if ($r->leg === 'L') { $teamRep['left'] = (float)$r->amt; $teamRep['cnt_left'] = (int)$r->cnt; }
                if ($r->leg === 'R') { $teamRep['right'] = (float)$r->amt; $teamRep['cnt_right'] = (int)$r->cnt; }
            }
            $teamRep['rows'] = $trRows;
        } else {
            // fallback to sponsor-based sums if no referral root
            $teamSell['left'] = (float) DB::table('sell')->where('sponsor_id',$userId)->where('status','paid')->where('leg','L')->sum('amount');
            $teamSell['right'] = (float) DB::table('sell')->where('sponsor_id',$userId)->where('status','paid')->where('leg','R')->sum('amount');

            $teamRep['left'] = (float) DB::table('repurchase')->where('refer_by',$user->referral_id ?? '')->where('status','paid')->sum('amount'); // best-effort fallback (may require schema adjust)
            $teamRep['right'] = 0.0;
        }

        $teamCombined = [
            'left' => $teamSell['left'] + $teamRep['left'],
            'right' => $teamSell['right'] + $teamRep['right'],
        ];
        $teamCombined['matched'] = min($teamCombined['left'], $teamCombined['right']);

        // -------------------- PLACEMENT CTE (left_user_id / right_user_id) --------------------
        $uid = $userId;
        $rootRow = DB::table('users')->where('id', $uid)->select('left_user_id','right_user_id')->first();
        $leftRoot = (int)($rootRow->left_user_id ?? 0);
        $rightRoot = (int)($rootRow->right_user_id ?? 0);

        $placementCte = "
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

        // placement sells (lifetime)
        $placementSell = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $psSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(s.amount),0) AS amt, COUNT(s.id) AS cnt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid'
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $psRows = DB::select($psSql, [$uid, $leftRoot, $rightRoot]);
        foreach ($psRows as $r) {
            if ($r->leg === 'L') { $placementSell['left'] = (float)$r->amt; $placementSell['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementSell['right'] = (float)$r->amt; $placementSell['cnt_right'] = (int)$r->cnt; }
        }

        // placement repurchase
        $placementRep = ['left'=>0.0,'right'=>0.0,'cnt_left'=>0,'cnt_right'=>0];
        $prSql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amt, COUNT(r.id) AS cnt
FROM team t
JOIN repurchase r ON r.buyer_id = t.id
WHERE r.status='paid'
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $prRows = DB::select($prSql, [$uid, $leftRoot, $rightRoot]);
        foreach ($prRows as $r) {
            if ($r->leg === 'L') { $placementRep['left'] = (float)$r->amt; $placementRep['cnt_left'] = (int)$r->cnt; }
            if ($r->leg === 'R') { $placementRep['right'] = (float)$r->amt; $placementRep['cnt_right'] = (int)$r->cnt; }
        }

        $placementCombinedLeft = $placementSell['left'] + $placementRep['left'];
        $placementCombinedRight = $placementSell['right'] + $placementRep['right'];
        $placementCombinedMatched = min($placementCombinedLeft, $placementCombinedRight);

        // -------------------- STAR (placement-based) calculation --------------------
        $matchedPlacement = $placementCombinedMatched;
        $rows = array_map(function ($s) use ($matchedPlacement) {
            $s['achieved']  = $matchedPlacement >= $s['threshold'];
            $s['progress']  = max(0, min(100, $s['threshold'] > 0 ? ($matchedPlacement / $s['threshold']) * 100 : 0));
            $s['remaining'] = max(0, $s['threshold'] - $matchedPlacement);
            return $s;
        }, $this->slabs);

        $current = null;
        foreach ($rows as $s) {
            if ($s['achieved']) $current = $s;
        }
        $starIncome = $current['income'] ?? 0;

        // package totals (team sells bucketed)
        $packageTotals = ['starter'=>0.0,'silver'=>0.0,'gold'=>0.0,'diamond'=>0.0,'other'=>0.0];
        if ($hasReferral) {
            $caseSql = "CASE WHEN LOWER(NULLIF(s.type,'')) IN ('starter','silver','gold','diamond') THEN LOWER(s.type) ELSE 'other' END";
            $pkgSql = $teamCte . "
SELECT ttype, COALESCE(SUM(amt),0) AS amt FROM (
  SELECT {$caseSql} AS ttype, s.amount AS amt
  FROM team t
  JOIN sell s ON s.buyer_id = t.id
  WHERE s.status = 'paid'
) x
GROUP BY ttype
";
            $pkgRows = DB::select($pkgSql, [$myReferralId]);
            foreach ($pkgRows as $pr) {
                $k = strtolower((string)$pr->ttype ?: 'other');
                $packageTotals[$k] = (float)$pr->amt;
            }
        }

        // businessSummary: pass combined team totals (sell+repurchase) — used by RewardPlan progress
        $businessSummary = ['left' => $teamCombined['left'], 'right' => $teamCombined['right']];

        // prepare props (ensure names match Dashboard.jsx)
        $props = [
            'user' => Arr::except($raw, ['password','remember_token','Password_plain']),
            'user_all' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => $mask($raw['password'] ?? '', 3),
                'referral_id' => $user->referral_id,
            ],
            'wallet_amount' => $walletAmount,
            'payout_wallet' => $payoutAmount,
            'today_profit' => $payoutToday,
            'total_team' => ($hasReferral ? (int) DB::selectOne("
                WITH RECURSIVE team AS (
                    SELECT id, referral_id, refer_by, position FROM users WHERE refer_by = ?
                    UNION ALL
                    SELECT u.id, u.referral_id, u.refer_by, u.position FROM users u JOIN team t ON u.refer_by = t.referral_id
                )
                SELECT COUNT(*) AS cnt FROM team
            ", [$myReferralId])->cnt : 0),
            'current_plan' => DB::table('sell')->where('buyer_id', $userId)->where('status','paid')->orderByDesc('id')->value('type'),
            'fristsell' => $firstsell,
            // placement left/right shown in UI
            'left' => $placementCombinedLeft,
            'right' => $placementCombinedRight,
            // matched used by RewardPlan (set to team combined matched: sell+repurchase)
            'matched' => $teamCombined['matched'],
            // businessSummary passed to RewardPlan
            'businessSummary' => $businessSummary,
            // star/placement props
            'rows' => $rows,
            'current' => $current,
            'star_income' => $starIncome,
            // team & placement breakdowns for additional UI if needed
            'team_sells' => $teamSell,
            'team_repurchases' => $teamRep,
            'team_combined' => $teamCombined,
            'placement_sells' => $placementSell,
            'placement_repurchases' => $placementRep,
            'placement_combined' => [
                'left' => $placementCombinedLeft,
                'right' => $placementCombinedRight,
                'matched' => $placementCombinedMatched,
            ],
            'packageTotals' => $packageTotals,
            'recent_sells' => $recentSells,
            'stats' => ['direct_referrals' => DB::table('users')->where('sponsor_id', $userId)->count()],
            'ref_link' => (DB::table('sell')->where('buyer_id', $userId)->where('status','paid')->exists() ? url('/register?refer_by=' . ($user->referral_id ?? '')) : "Please purchase a plan to activate your referral link."),
            'asOf' => now()->toDateTimeString(),
        ];

        return Inertia::render('Dashboard', $props);
    }
}
