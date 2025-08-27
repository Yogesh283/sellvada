<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StarRankSeeder extends Seeder {
    public function run(): void {
        $slabs = [
            [1, "1 STAR",  100000,     2000],
            [2, "2 STAR",  200000,     4000],
            [3, "3 STAR",  400000,     8000],
            [4, "4 STAR",  800000,    16000],
            [5, "5 STAR", 1600000,    32000],
            [6, "6 STAR", 3200000,    64000],
            [7, "7 STAR", 6400000,   128000],   // 1.28 Lac
            [8, "8 STAR",12800000,   256000],   // 2.56 Lac
            [9, "9 STAR",25000000,   512000],   // 5.12  Lac
            [10,"10 STAR",50000000, 1024000],   // 10.24 Lac
            [11,"11 STAR",100000000,2048000],   // 20.48 Lac
            [12,"12 STAR",200000000,4096000],   // 40.96 Lac
        ];

        DB::table('star_rank_slabs')->truncate();
        foreach ($slabs as [$no,$title,$thr,$rew]) {
            DB::table('star_rank_slabs')->insert([
                'rank_no' => $no,
                'title'   => $title,
                'threshold_volume' => $thr,
                'reward_amount'    => $rew,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }
}
