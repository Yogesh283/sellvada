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
    /**
     * Slab definitions (copied from StarIncomeController so Dashboard is self-contained)
     */
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

        // preload relations if present (optional)
        $user->loadMissing([
            'sponsor:id,name,email',
            'leftChild:id,name,email',
            'rightChild:id,name,email',
        ]);

        // Basic details
        $raw = $user->toArray();
        $mask = function (?string $v, int $keep = 2) {
            if (!$v) return null;
            $len = strlen($v);
            if ($len <= 4) return str_repeat('*', $len);
            return substr($v, 0, $keep) . str_repeat('*', max(0, $len - 2*$keep)) . substr($v, -$keep);
        };

        // Immediate counts / simple queries
        $directReferrals = DB::table('users')->where('sponsor_id', $user->id)->count();
        $myReferralId = DB::table('users')->where('id', $user->id)->value('referral_id');

        $recentSells = DB::table('sell')
            ->where('buyer_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id','buyer_id','product','amount','type','status','created_at']);

        // Prepare recursive CTE only if we have a referral root
        $hasReferral = ! empty($myReferralId);

        $teamSells = collect();
        $teamLeftSells = collect();
        $teamRightSells = collect();

        $teamCte = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position,
         UPPER(COALESCE(position,'NA')) AS root_leg
  FROM users
  WHERE refer_by = ?

  UNION ALL

  SELECT u.id, u.referral_id, u.refer_by, u.position, t.root_leg
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
";

        if ($hasReferral) {
            // recent team sells (all legs)
            $teamSellsSql = $teamCte . "
SELECT
  s.id,
  s.buyer_id,
  buyer.name AS buyer_name,
  s.product,
  s.amount,
  s.type,
  s.status,
  t.root_leg AS leg,
  s.created_at
FROM team t
JOIN sell s ON s.buyer_id = t.id
LEFT JOIN users buyer ON buyer.id = s.buyer_id
ORDER BY s.id DESC
LIMIT 10
";
            $teamSells = collect(DB::select($teamSellsSql, [$myReferralId]));

            // left sells
            $teamLeftSql = $teamCte . "
SELECT
  s.id,
  s.buyer_id,
  buyer.name AS buyer_name,
  s.product,
  s.amount,
  s.type,
  s.status,
  t.root_leg AS leg,
  s.created_at
FROM team t
JOIN sell s ON s.buyer_id = t.id
LEFT JOIN users buyer ON buyer.id = s.buyer_id
WHERE t.root_leg = 'L'
ORDER BY s.id DESC
LIMIT 10
";
            $teamLeftSells = collect(DB::select($teamLeftSql, [$myReferralId]));

            // right sells
            $teamRightSql = $teamCte . "
SELECT
  s.id,
  s.buyer_id,
  buyer.name AS buyer_name,
  s.product,
  s.amount,
  s.type,
  s.status,
  t.root_leg AS leg,
  s.created_at
FROM team t
JOIN sell s ON s.buyer_id = t.id
LEFT JOIN users buyer ON buyer.id = s.buyer_id
WHERE t.root_leg = 'R'
ORDER BY s.id DESC
LIMIT 10
";
            $teamRightSells = collect(DB::select($teamRightSql, [$myReferralId]));
        }

        // Immediate left/right users
        $leftUser  = $user->leftChild ? $user->leftChild->only(['id','name','email'])   : null;
        $rightUser = $user->rightChild ? $user->rightChild->only(['id','name','email']) : null;

        // Referral link activation check
        $Ref = DB::table('sell')->where('buyer_id', $user->id)->where('status', 'paid')->orderByDesc('id')->value('type');

        if ($Ref) {
            $refLink = url('/register?refer_by=' . ($user->referral_id ?? ''));
            $refral  = $user->referral_id;
        } else {
            $refLink = "Please purchase a plan to activate your referral link.";
            $refral  = '-';
        }

        // Wallets / payouts
        $WalletAmount       = (float) DB::table('wallet')->where('user_id', $user->id)->sum('amount');
        $PayoutAmount       = (float) DB::table('_payout')->where('user_id', $user->id)->sum('amount');
        $PayoutAmountToday  = (float) DB::table('_payout')
                                ->where('user_id', $user->id)
                                ->whereDate('created_at', Carbon::today())
                                ->sum('amount');

        // Total team count (propagated CTE)
        $totalTeam = 0;
        if ($hasReferral) {
            $row = DB::selectOne("
                WITH RECURSIVE team AS (
                    SELECT id, referral_id, refer_by, position
                    FROM users
                    WHERE refer_by = ?

                    UNION ALL

                    SELECT u.id, u.referral_id, u.refer_by, u.position
                    FROM users u
                    INNER JOIN team t ON u.refer_by = t.referral_id
                )
                SELECT COUNT(*) AS cnt FROM team
            ", [$myReferralId]);

            $totalTeam = (int) ($row->cnt ?? 0);
        }

        // ---------------- Business (Lifetime + Today First/Second Half) ----------------
        $businessSummary = ['left' => 0.0, 'right' => 0.0];
        $timewiseSales   = [
            'first_half'  => ['left' => 0.0, 'right' => 0.0],
            'second_half' => ['left' => 0.0, 'right' => 0.0],
        ];

        if ($hasReferral) {
            // Lifetime (team-based)
            $leftRow = DB::selectOne($teamCte . "
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'L' AND s.status = 'paid'
            ", [$myReferralId]);

            $rightRow = DB::selectOne($teamCte . "
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'R' AND s.status = 'paid'
            ", [$myReferralId]);

            $businessSummary['left']  = (float) ($leftRow->amt ?? 0);
            $businessSummary['right'] = (float) ($rightRow->amt ?? 0);

            // Today windows
            $today = Carbon::today();
            $firstStart  = $today->copy()->setTime(0,0,0)->format('Y-m-d H:i:s');
            $firstEnd    = $today->copy()->setTime(11,59,59)->format('Y-m-d H:i:s');
            $secondStart = $today->copy()->setTime(12,0,0)->format('Y-m-d H:i:s');
            $secondEnd   = $today->copy()->setTime(23,59,59)->format('Y-m-d H:i:s');

            // First half (team-based)
            $fhLeft = DB::selectOne($teamCte . "
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'L' AND s.status = 'paid'
                  AND s.created_at BETWEEN ? AND ?
            ", [$myReferralId, $firstStart, $firstEnd]);

            $fhRight = DB::selectOne($teamCte . "
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'R' AND s.status = 'paid'
                  AND s.created_at BETWEEN ? AND ?
            ", [$myReferralId, $firstStart, $firstEnd]);

            // Second half (team-based)
            $shLeft = DB::selectOne($teamCte . "
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'L' AND s.status = 'paid'
                  AND s.created_at BETWEEN ? AND ?
            ", [$myReferralId, $secondStart, $secondEnd]);

            $shRight = DB::selectOne($teamCte . "
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'R' AND s.status = 'paid'
                  AND s.created_at BETWEEN ? AND ?
            ", [$myReferralId, $secondStart, $secondEnd]);

            $timewiseSales['first_half']['left']   = (float) ($fhLeft->amt ?? 0);
            $timewiseSales['first_half']['right']  = (float) ($fhRight->amt ?? 0);
            $timewiseSales['second_half']['left']  = (float) ($shLeft->amt ?? 0);
            $timewiseSales['second_half']['right'] = (float) ($shRight->amt ?? 0);

        } else {
            // Fallback to direct sponsor sums if no referral tree
            $businessSummary['left']  = (float) DB::table('sell')->where('sponsor_id', $user->id)->where('leg', 'L')->where('status', 'paid')->sum('amount');
            $businessSummary['right'] = (float) DB::table('sell')->where('sponsor_id', $user->id)->where('leg', 'R')->where('status', 'paid')->sum('amount');

            $today = Carbon::today();
            $timewiseSales['first_half']['left'] = (float) DB::table('sell')
                ->where('sponsor_id', $user->id)->where('leg','L')->where('status','paid')
                ->whereBetween('created_at', [$today->copy()->setTime(0,0,0), $today->copy()->setTime(11,59,59)])
                ->sum('amount');

            $timewiseSales['first_half']['right'] = (float) DB::table('sell')
                ->where('sponsor_id', $user->id)->where('leg','R')->where('status','paid')
                ->whereBetween('created_at', [$today->copy()->setTime(0,0,0), $today->copy()->setTime(11,59,59)])
                ->sum('amount');

            $timewiseSales['second_half']['left'] = (float) DB::table('sell')
                ->where('sponsor_id', $user->id)->where('leg','L')->where('status','paid')
                ->whereBetween('created_at', [$today->copy()->setTime(12,0,0), $today->copy()->setTime(23,59,59)])
                ->sum('amount');

            $timewiseSales['second_half']['right'] = (float) DB::table('sell')
                ->where('sponsor_id', $user->id)->where('leg','R')->where('status','paid')
                ->whereBetween('created_at', [$today->copy()->setTime(12,0,0), $today->copy()->setTime(23,59,59)])
                ->sum('amount');
        }

        // Carry Forward (lifetime unmatched)
        $carryTotals = [
            'cf_left'  => max($businessSummary['left']  - $businessSummary['right'], 0),
            'cf_right' => max($businessSummary['right'] - $businessSummary['left'], 0),
        ];

        // Build star summary (placement downline totals / slabs) — use same CTE approach
        $uid = (int) $user->id;
        $rootRow = DB::table('users')->where('id', $uid)->select('left_user_id','right_user_id')->first();
        $leftRoot  = (int)($rootRow->left_user_id ?? 0);
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

        $binds = [$uid, $leftRoot, $rightRoot];
        $dateSql = '';
        if ($request->query('from')) { $dateSql .= " AND DATE(s.created_at) >= ?"; $binds[] = $request->query('from'); }
        if ($request->query('to'))   { $dateSql .= " AND DATE(s.created_at) <= ?"; $binds[] = $request->query('to'); }

        $sql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg, SUM(s.amount) as amt
FROM team t
JOIN sell s ON s.buyer_id = t.id
WHERE s.status='paid'
  AND LOWER(s.type) IN ('silver','gold','diamond','repurchase')
  {$dateSql}
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
        $placementRows = DB::select($sql, $binds);

        $L2 = 0.0; $R2 = 0.0;
        foreach ($placementRows as $r) {
            if ($r->leg === 'L') $L2 = (float)$r->amt;
            elseif ($r->leg === 'R') $R2 = (float)$r->amt;
        }
        $matched = min($L2, $R2);

        // compute slabs progress using $this->slabs
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

        // Build safe user objects
        $userAll = [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'email_verified_at'=> $user->email_verified_at,
            'password'         => $mask($raw['password'] ?? '', 3),
            'referral_id'      => $refral,
            'refer_by'         => $user->refer_by,
            'parent_id'        => $user->parent_id,
            'position'         => $user->position,
            'left_user_id'     => $user->left_user_id,
            'right_user_id'    => $user->right_user_id,
            'remember_token'   => $mask($raw['remember_token'] ?? '', 3),
            'created_at'       => $user->created_at,
            'updated_at'       => $user->updated_at,
            'sponsor_id'       => $user->sponsor_id,
            'referral_code'    => $user->referral_code,
        ];

        $userSafe = Arr::except($raw, ['password','remember_token','Password_plain']);

        $plan = DB::table('sell')->where('buyer_id', $user->id)->where('status', 'paid')->orderByDesc('id')->value('type');

        // Final props
        $props = [
            'user'             => $userSafe,
            'user_all'         => $userAll,
            'sponsor'          => $user->sponsor ? $user->sponsor->only(['id','name','email']) : null,
            'wallet_amount'    => $WalletAmount,
            'payout_wallet'    => $PayoutAmount,
            'today_profit'     => $PayoutAmountToday,
            'total_team'       => $totalTeam,
            'current_plan'     => $plan,
            'left'             => $L2,
            'right'            => $R2,
            'matched'          => $matched,
            'children'         => [
                'left'  => $user->leftChild ? $user->leftChild->only(['id','name'])  : null,
                'right' => $user->rightChild ? $user->rightChild->only(['id','name']) : null,
            ],
            'stats'            => [
                'direct_referrals' => $directReferrals,
            ],
            'recent_sells'     => $recentSells,
            'team_sells'       => $teamSells,
            'team_left_sells'  => $teamLeftSells,
            'team_right_sells' => $teamRightSells,
            'left_user'        => $leftUser,
            'right_user'       => $rightUser,
            'ref_link'         => $refLink,
            'businessSummary'  => $businessSummary,
            'timewiseSales'    => $timewiseSales,
            'carryTotals'      => $carryTotals,
            // star summary
            'rows'             => $rows,
            'current'          => $current,
            'asOf'             => now()->toDateTimeString(),
            'filters'          => ['from' => $request->query('from'), 'to' => $request->query('to')],
        ];

        // Return single Inertia response for dashboard
        return Inertia::render('Dashboard', $props);
    }
}
