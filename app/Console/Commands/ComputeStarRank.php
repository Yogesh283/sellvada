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
                        // 1) award history
                        DB::table('star_rank_awards')->insert([
                            'sponsor_id'       => $sid,
                            'rank_no'          => $slab->rank_no,
                            'threshold_volume' => $slab->threshold_volume,
                            'reward_amount'    => $slab->reward_amount,
                            'awarded_at'       => $asOf,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);

                        // === NET/GROSS calculation (20% cut) ===
                        $gross = (float)$slab->reward_amount;                 // 100%
                        $net   = round($gross * 0.80, 2);                     // 80% to wallet
                        $grossStr = number_format($gross, 2, '.', '');
                        $netStr   = number_format($net,   2, '.', '');
                        $deduct   = number_format($gross - $net, 2, '.', ''); // optional meta

                        // 2) payout ledger (_payout) — keep GROSS (100%) for reporting
                        DB::table('_payout')->insert([
                            'user_id'      => $sid,
                            'to_user_id'   => $sid,
                            'from_user_id' => null,
                            'amount'       => $grossStr,       // store full reward
                            'status'       => 'paid',
                            'method'       => 'star_rank',
                            'type'         => 'star_award',
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);

                        // 3) credit wallet with NET only (20% deduction applied)
                        $affected = DB::update(
                            "UPDATE wallet SET amount = amount + ? WHERE user_id = ?",
                            [$netStr, $sid]
                        );
                        if ($affected === 0) {
                            DB::table('wallet')->insert([
                                'user_id'    => $sid,
                                'amount'     => $netStr,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        // (Optional) deduction ko meta table me log karna ho to:
                        // DB::table('_payout_meta')->insert([
                        //   'user_id'   => $sid,
                        //   'payout_type' => 'star_award',
                        //   'key'       => 'deduction_20_percent',
                        //   'value'     => $deduct,
                        //   'created_at'=> now(),
                        //   'updated_at'=> now(),
                        // ]);

                        DB::commit();

                        $this->info(sprintf(
                            'AWARDED → SPONSOR=%d RANK=%d GROSS=%.2f NET=%.2f (asOf=%s)',
                            $sid, $slab->rank_no, $gross, $net, $asOf->toDateTimeString()
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
