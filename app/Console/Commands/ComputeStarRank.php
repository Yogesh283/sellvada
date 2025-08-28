<?php
// app/Console/Commands/ComputeStarRank.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ComputeStarRank extends Command
{
    protected $signature = 'star:compute {--date=} {--dry}';
    protected $description = 'Award Star Ranks based on cumulative matching volume (includes new IDs + repurchases)';

    // apne sell.type ke real values yahan maintain karo
    private array $includeTypes = ['silver','gold','diamond','repurchase'];

    public function handle(): int
    {
        $asOf = $this->option('date')
            ? Carbon::parse($this->option('date'))->endOfDay()
            : now();

        // slabs ascending by rank_no
        $slabs = DB::table('star_rank_slabs')->orderBy('rank_no')->get();

        // jinke paas asOf tak paid sales hain
        $sponsorIds = DB::table('sell')
            ->where('status','paid')
            ->whereIn('type', $this->includeTypes)
            ->where('created_at','<=',$asOf)
            ->whereNotNull('sponsor_id')
            ->distinct()
            ->pluck('sponsor_id');

        foreach ($sponsorIds as $sid) {
            // lifetime (<= asOf) L/R volume
            $rows = DB::table('sell')
                ->select('leg','amount')
                ->where('status','paid')
                ->whereIn('type', $this->includeTypes)
                ->where('sponsor_id', $sid)
                ->where('created_at','<=',$asOf)
                ->get();

            // guard for leg case
            $L = 0.0; $R = 0.0;
            foreach ($rows as $r) {
                $leg = strtoupper((string)($r->leg ?? ''));
                $amt = (float)$r->amount;
                if ($leg === 'L') $L += $amt;
                elseif ($leg === 'R') $R += $amt;
            }
            $matchedTotal = min($L,$R);

            foreach ($slabs as $slab) {
                if ($matchedTotal >= (float)$slab->threshold_volume) {
                    // awarded already?
                    $already = DB::table('star_rank_awards')
                        ->where('sponsor_id',$sid)
                        ->where('rank_no', $slab->rank_no)
                        ->exists();

                    if ($already) {
                        continue;
                    }

                    if ($this->option('dry')) {
                        $this->line(sprintf(
                            'DRY → sponsor=%d rank=%d award=%.2f (threshold=%.2f matched=%.2f)',
                            $sid, $slab->rank_no, $slab->reward_amount, $slab->threshold_volume, $matchedTotal
                        ));
                        continue;
                    }

                    DB::beginTransaction();
                    try {
                        // 1) award history (idempotent ensured by exists check; also add UNIQUE(sponsor_id,rank_no) in DB)
                        DB::table('star_rank_awards')->insert([
                            'sponsor_id'       => $sid,
                            'rank_no'          => $slab->rank_no,
                            'threshold_volume' => $slab->threshold_volume,
                            'reward_amount'    => $slab->reward_amount,
                            'awarded_at'       => $asOf,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);

                        // 2) payout ledger (_payout)
                        // method/type aapke UI ke hisaab se; status = 'paid' (turant credit)
                        DB::table('_payout')->insert([
                            'user_id'      => $sid,             // legacy column (if still used)
                            'to_user_id'   => $sid,             // receiver of reward
                            'from_user_id' => null,              // system award (no specific buyer)
                            'amount'       => number_format((float)$slab->reward_amount, 2, '.', ''),
                            'status'       => 'paid',
                            'method'       => 'star_rank',
                            'type'         => 'star_award',
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);

                        // 3) credit wallet (single-row balance table)
                        $inc = number_format((float)$slab->reward_amount, 2, '.', '');
                        $affected = DB::update(
                            "UPDATE wallet SET amount = amount + ? WHERE user_id = ?",
                            [$inc, $sid]
                        );
                        if ($affected === 0) {
                            DB::table('wallet')->insert([
                                'user_id'    => $sid,
                                'amount'     => $inc,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::commit();

                        $this->info(sprintf(
                            'AWARDED → SPONSOR=%d RANK=%d AMT=%.2f (asOf=%s)',
                            $sid, $slab->rank_no, $slab->reward_amount, $asOf->toDateTimeString()
                        ));
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        $this->error(sprintf(
                            'ERR → sponsor=%d rank=%d :: %s',
                            $sid, $slab->rank_no, $e->getMessage()
                        ));
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
