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

        $directReferrals = DB::table('users')->where('sponsor_id', $user->id)->count();
        $myReferralId = DB::table('users')->where('id', $user->id)->value('referral_id');


        $recentSells = DB::table('sell')
            ->where('buyer_id', $user->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id','buyer_id','product','amount','type','status','created_at']);

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

        $leftUser  = $user->leftChild ? $user->leftChild->only(['id','name','email'])   : null;
        $rightUser = $user->rightChild ? $user->rightChild->only(['id','name','email']) : null;

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

$WalletAmount=DB::table('wallet')->where('user_id',$user->id)->sum('amount');

$PayoutAmount=DB::table('_payout')->where('user_id',$user->id)->sum('amount');
$PayoutAmountToday = DB::table('_payout')->where('user_id', $user->id)->whereDate('created_at', Carbon::today())->sum('amount');
$totalTeam = DB::selectOne("
WITH RECURSIVE team AS (
        SELECT id, referral_id, position
        FROM users
        WHERE referral_id = ?

        UNION ALL

        SELECT u.id, u.referral_id, u.position
        FROM users u
        INNER JOIN team t ON u.referral_id = t.id
    )
    SELECT COUNT(*) AS cnt FROM team
", [$myReferralId])->cnt;



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
            'wallet_amount'    => $WalletAmount,
            'payout_wallet'    => $PayoutAmount,
            'today_profit'     => $PayoutAmountToday,
            'total_team'       => $totalTeam,
            'children'         => [
                'left'  => $user->leftChild ? $user->leftChild->only(['id','name'])  : null,
                'right' => $user->rightChild ? $user->rightChild->only(['id','name']) : null,
            ],
            'stats'            => [
                'direct_referrals' => $directReferrals,
            ],
            'recent_sells'     => $recentSells,     // your own
            'team_sells'       => $teamSells,       // any leg
            'team_left_sells'  => $teamLeftSells,   // only L
            'team_right_sells' => $teamRightSells,  // only R
            'left_user'        => $leftUser,        // immediate L user
            'right_user'       => $rightUser,       // immediate R user
            'ref_link'         => $refLink,
        ]);
    }
}
