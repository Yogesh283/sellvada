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
    public function index(Request $request)
    {
        $user = Auth::user();

        // preload relations if present
        $user->loadMissing([
            'sponsor:id,name,email',
            'leftChild:id,name,email',
            'rightChild:id,name,email',
        ]);

        // Direct referrals (immediate)
        $directReferrals = DB::table('users')->where('sponsor_id', $user->id)->count();

        // My referral_id (this is the "code" others use in refer_by)
        $myReferralId = DB::table('users')->where('id', $user->id)->value('referral_id');

        // Recent sells of the logged-in user
        $recentSells = DB::table('sell')
            ->where('buyer_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id','buyer_id','product','amount','type','status','created_at']);

        // --- Prepare recursive CTE only if we have a referral root ---
        $hasReferral = ! empty($myReferralId);

        // Team sells (using propagated root_leg). Fallback: if no referral, keep existing behaviour (empty)
        $teamSells = collect();
        $teamLeftSells = collect();
        $teamRightSells = collect();

        if ($hasReferral) {
            // CTE: root children keep their position as root_leg, propagate to descendants
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
            // team sells (all legs) - recent 10
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

            // left sells (root_leg = 'L')
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

            // right sells (root_leg = 'R')
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
        } else {
            // keep empty collections (or you can keep existing logic using sponsor_id)
            $teamSells = collect();
            $teamLeftSells = collect();
            $teamRightSells = collect();
        }

        // Immediate left/right users (if set)
        $leftUser  = $user->leftChild ? $user->leftChild->only(['id','name','email'])   : null;
        $rightUser = $user->rightChild ? $user->rightChild->only(['id','name','email']) : null;

        // Referral link activation check (same as before)
        $Ref = DB::table('sell')->where('buyer_id', $user->id)->where('status', 'paid')->orderByDesc('id')->value('type');

        if ($Ref) {
            $refLink = url('/register?refer_by=' . ($user->referral_id ?? ''));
            $refral  = $user->referral_id;
        } else {
            $refLink = "Please purchase a plan to activate your referral link.";
            $refral  = '-';
        }

        // Safe user objects
        $raw = $user->toArray();
        $mask = function (?string $v, int $keep = 2) {
            if (!$v) return null;
            $len = strlen($v);
            if ($len <= 4) return str_repeat('*', $len);
            return substr($v, 0, $keep) . str_repeat('*', max(0, $len - 2*$keep)) . substr($v, -$keep);
        };

        // Wallets / payouts
        $WalletAmount       = DB::table('wallet')->where('user_id', $user->id)->sum('amount');
        $PayoutAmount       = DB::table('_payout')->where('user_id', $user->id)->sum('amount');
        $PayoutAmountToday  = DB::table('_payout')
                                ->where('user_id', $user->id)
                                ->whereDate('created_at', Carbon::today())
                                ->sum('amount');

        // Total team count (using propagated CTE)
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

        // Timewise Sales (12 AM - 12 PM & 12 PM - 12 AM) - using sponsor_id & leg (kept as before)
        $today = Carbon::today();

        // First Half (12 AM - 12 PM)
        $firstHalfLeft = DB::table('sell')
            ->where('sponsor_id', $user->id)->where('leg', 'L')->where('status', 'paid')
            ->whereBetween('created_at', [$today->copy()->setTime(0,0,0), $today->copy()->setTime(11,59,59)])
            ->sum('amount');

        $firstHalfRight = DB::table('sell')
            ->where('sponsor_id', $user->id)->where('leg', 'R')->where('status', 'paid')
            ->whereBetween('created_at', [$today->copy()->setTime(0,0,0), $today->copy()->setTime(11,59,59)])
            ->sum('amount');

        // Second Half (12 PM - 12 AM)
        $secondHalfLeft = DB::table('sell')
            ->where('sponsor_id', $user->id)->where('leg', 'L')->where('status', 'paid')
            ->whereBetween('created_at', [$today->copy()->setTime(12,0,0), $today->copy()->setTime(23,59,59)])
            ->sum('amount');

        $secondHalfRight = DB::table('sell')
            ->where('sponsor_id', $user->id)->where('leg', 'R')->where('status', 'paid')
            ->whereBetween('created_at', [$today->copy()->setTime(12,0,0), $today->copy()->setTime(23,59,59)])
            ->sum('amount');

        // Full user data (mask sensitive fields)
        $userAll = [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'email_verified_at'=> $user->email_verified_at,
            'password'         => $mask($raw['password'] ?? '', 3),
            'referral_id'      =>  $refral,
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

        // Left & Right business from sell table (for logged-in user) using propagated root_leg
        $businessSummary = ['left' => 0.0, 'right' => 0.0];

        if ($hasReferral) {
            // Sum paid amount where root_leg = 'L'
            $leftRow = DB::selectOne("
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
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'L' AND s.status = 'paid'
            ", [$myReferralId]);

            $rightRow = DB::selectOne("
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
                SELECT COALESCE(SUM(s.amount),0) AS amt
                FROM team t
                JOIN sell s ON s.buyer_id = t.id
                WHERE t.root_leg = 'R' AND s.status = 'paid'
            ", [$myReferralId]);

            $businessSummary['left']  = (float) ($leftRow->amt ?? 0);
            $businessSummary['right'] = (float) ($rightRow->amt ?? 0);
        } else {
            // fallback: keep direct sponsor-based sums (same as original behaviour)
            $businessSummary['left']  = DB::table('sell')->where('sponsor_id', $user->id)->where('leg', 'L')->where('status', 'paid')->sum('amount');
            $businessSummary['right'] = DB::table('sell')->where('sponsor_id', $user->id)->where('leg', 'R')->where('status', 'paid')->sum('amount');
        }

        // Add in props
        return Inertia::render('Dashboard', [
            'user'             => $userSafe,
            'user_all'         => $userAll,
            'sponsor'          => $user->sponsor ? $user->sponsor->only(['id','name','email']) : null,
            'wallet_amount'    => $WalletAmount,
            'payout_wallet'    => $PayoutAmount,
            'today_profit'     => $PayoutAmountToday,
            'total_team'       => $totalTeam,
            'current_plan'     => $plan,
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

            // 👉 New: Left & Right Business (propagated)
            'businessSummary'  => $businessSummary,
            'timewiseSales'    => [
                'first_half'  => ['left' => $firstHalfLeft, 'right' => $firstHalfRight],
                'second_half' => ['left' => $secondHalfLeft, 'right' => $secondHalfRight],
            ],
        ]);
    }
}
