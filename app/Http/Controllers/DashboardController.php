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

        // Team sells (direct legs by sponsor_id = me) + optional leg filter
        $teamSellsQuery = DB::table('sell')
            ->join('users as buyer', 'buyer.id', '=', 'sell.buyer_id')
            ->where('sell.sponsor_id', $user->id)
            ->orderByDesc('sell.id');

        if (in_array($request->get('leg'), ['L','R'], true)) {
            $teamSellsQuery->where('sell.leg', $request->get('leg'));
        }

        $teamSells = $teamSellsQuery
            ->limit(10)
            ->get([
                'sell.id',
                'buyer.id as buyer_id',
                'buyer.name as buyer_name',
                'sell.product',
                'sell.amount',
                'sell.type',
                'sell.status',
                'sell.leg',
                'sell.created_at',
            ]);

        // Immediate left/right users (if set)
        $leftUser  = $user->leftChild ? $user->leftChild->only(['id','name','email'])   : null;
        $rightUser = $user->rightChild ? $user->rightChild->only(['id','name','email']) : null;

        // Left leg recent sells
        $teamLeftSells = DB::table('sell')
            ->join('users as buyer', 'buyer.id', '=', 'sell.buyer_id')
            ->where('sell.sponsor_id', $user->id)
            ->where('sell.leg', 'L')
            ->orderByDesc('sell.id')
            ->limit(10)
            ->get([
                'sell.id',
                'buyer.id as buyer_id',
                'buyer.name as buyer_name',
                'sell.product','sell.amount','sell.type','sell.status','sell.leg','sell.created_at',
            ]);

        // Right leg recent sells
        $teamRightSells = DB::table('sell')
            ->join('users as buyer', 'buyer.id', '=', 'sell.buyer_id')
            ->where('sell.sponsor_id', $user->id)
            ->where('sell.leg', 'R')
            ->orderByDesc('sell.id')
            ->limit(10)
            ->get([
                'sell.id',
                'buyer.id as buyer_id',
                'buyer.name as buyer_name',
                'sell.product','sell.amount','sell.type','sell.status','sell.leg','sell.created_at',
            ]);

        // Referral link
        $refLink = url('/register?refer_by=' . ($user->referral_id ?? ''));

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

        /**
         * ✅ ALL-LEVEL TEAM COUNT (entire downline)
         * Tree rule: child.refer_by = parent.referral_id
         * - Seed: users whose refer_by = myReferralId (level 1)
         * - Recurse: join on u.refer_by = t.referral_id
         * - Count all rows in the CTE (self excluded naturally)
         */
        $totalTeam = 0;
        if (!empty($myReferralId)) {
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

        $userAll = [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'email_verified_at'=> $user->email_verified_at,
            'password'         => $mask($raw['password'] ?? '', 3),
            'referral_id'      => $user->referral_id,
            'refer_by'         => $user->refer_by,
            'parent_id'        => $user->parent_id,
            'position'         => $user->position,
            'left_user_id'     => $user->left_user_id,
            'right_user_id'    => $user->right_user_id,
            'remember_token'   => $mask($raw['remember_token'] ?? '', 3),
            'created_at'       => $user->created_at,
            'updated_at'       => $user->updated_at,
            'Password_plain'   => $mask($raw['Password_plain'] ?? '', 0),
            'sponsor_id'       => $user->sponsor_id,
            'referral_code'    => $user->referral_code,
        ];

        $userSafe = Arr::except($raw, ['password','remember_token','Password_plain']);

        return Inertia::render('Dashboard', [
            'user'             => $userSafe,
            'user_all'         => $userAll,
            'sponsor'          => $user->sponsor ? $user->sponsor->only(['id','name','email']) : null,

            // Top cards
            'wallet_amount'    => $WalletAmount,
            'payout_wallet'    => $PayoutAmount,
            'today_profit'     => $PayoutAmountToday,

            // ✅ All-level team count sent as-is to the page
            'total_team'       => $totalTeam,

            // Children shortcuts
            'children'         => [
                'left'  => $user->leftChild ? $user->leftChild->only(['id','name'])  : null,
                'right' => $user->rightChild ? $user->rightChild->only(['id','name']) : null,
            ],

            // Misc stats
            'stats'            => [
                'direct_referrals' => $directReferrals,
            ],

            // Tables
            'recent_sells'     => $recentSells,     // your own
            'team_sells'       => $teamSells,       // any leg (direct by sponsor)
            'team_left_sells'  => $teamLeftSells,   // only L
            'team_right_sells' => $teamRightSells,  // only R

            // Context
            'left_user'        => $leftUser,
            'right_user'       => $rightUser,
            'ref_link'         => $refLink,
        ]);
    }
}
