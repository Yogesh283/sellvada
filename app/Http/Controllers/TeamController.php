<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    /**
     * /team — Show counts + lists for login user's downline
     * Tree rule: child.refer_by = parent.referral_id
     */
    public function index(Request $request)
    {
        $user        = $request->user();
        $uid         = $user->id;
        $myReferral  = (string) DB::table('users')->where('id', $uid)->value('referral_id'); // login ka code

        // --- Me (header ke liye basic info) ---
        $me = DB::table('users')
            ->where('id', $uid)
            ->select([
                'id','name','email','referral_id','refer_by','position',
                'left_user_id','right_user_id','created_at'
            ])->first();

        // --- Direct team (sirf first level) ---
        $direct_list = DB::table('users')
            ->where('refer_by', $myReferral)
            ->orderByDesc('id')
            ->get(['id','name','email','referral_id','refer_by','position','created_at']);

        $direct_count = $direct_list->count();

        // --- LEFT subtree (multi-level) ---
        // root level: mere code se jo directly 'L' pe aaye
        $left = DB::select(/** @lang SQL */ "
            WITH RECURSIVE team AS (
                SELECT id, name, email, referral_id, refer_by, position, 1 AS lvl, created_at
                FROM users
                WHERE refer_by = ? AND position = 'L'
                UNION ALL
                SELECT u.id, u.name, u.email, u.referral_id, u.refer_by, u.position, t.lvl + 1, u.created_at
                FROM users u
                JOIN team t ON u.refer_by = t.referral_id
            )
            SELECT id, name, email, referral_id, refer_by, position, lvl, created_at
            FROM team
            ORDER BY lvl, id DESC
        ", [$myReferral]);

        // --- RIGHT subtree (multi-level) ---
        $right = DB::select(/** @lang SQL */ "
            WITH RECURSIVE team AS (
                SELECT id, name, email, referral_id, refer_by, position, 1 AS lvl, created_at
                FROM users
                WHERE refer_by = ? AND position = 'R'
                UNION ALL
                SELECT u.id, u.name, u.email, u.referral_id, u.refer_by, u.position, t.lvl + 1, u.created_at
                FROM users u
                JOIN team t ON u.refer_by = t.referral_id
            )
            SELECT id, name, email, referral_id, refer_by, position, lvl, created_at
            FROM team
            ORDER BY lvl, id DESC
        ", [$myReferral]);

        // --- FULL team (left + right, multi-level) ---
        $team_all = DB::select(/** @lang SQL */ "
            WITH RECURSIVE team AS (
                -- level 1: mere code se directly aaye (L ya R)
                SELECT id, name, email, referral_id, refer_by, position, 1 AS lvl, created_at
                FROM users
                WHERE refer_by = ?
                UNION ALL
                -- next levels: child.refer_by = parent.referral_id
                SELECT u.id, u.name, u.email, u.referral_id, u.refer_by, u.position, t.lvl + 1, u.created_at
                FROM users u
                JOIN team t ON u.refer_by = t.referral_id
            )
            SELECT id, name, email, referral_id, refer_by, position, lvl, created_at
            FROM team
            ORDER BY lvl, id DESC
        ", [$myReferral]);

        // --- Counts summary ---
        $counts = [
            'direct' => $direct_count,
            'left'   => count($left),
            'right'  => count($right),
            'total'  => count($team_all), // self exclude already (humne self ko seed me add nahi kiya)
        ];

        return Inertia::render('Team', [
            'auth'        => ['user' => $user],
            'me'          => $me,
            'counts'      => $counts,
            'direct_list' => $direct_list,
            'left'        => $left,
            'right'       => $right,
            'team_all'    => $team_all,
            'my_referral' => $myReferral,
        ]);
    }
}
