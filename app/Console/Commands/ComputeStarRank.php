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

    // NOTE: yahan types me repurchase ka type bhi add kar do agar aapke DB me alag se stored hai
    private array $includeTypes = ['silver','gold','diamond','repurchase'];

    public function handle(): int
    {
        $asOf = $this->option('date')
            ? Carbon::parse($this->option('date'))->endOfDay()
            : now();

        // slabs ascending
        $slabs = DB::table('star_rank_slabs')->orderBy('rank_no')->get();

        // jin sponsors ke paas paid sales hain asOf tak
        $sponsorIds = DB::table('sell')
            ->where('status','paid')
            ->whereIn('type', $this->includeTypes)
            ->where('created_at','<=',$asOf)
            ->distinct()->pluck('sponsor_id');

        foreach ($sponsorIds as $sid) {
            // cumulative L/R volume
            $rows = DB::table('sell')
                ->select('leg','amount')
                ->where('status','paid')
                ->whereIn('type', $this->includeTypes)
                ->where('sponsor_id', $sid)
                ->where('created_at','<=',$asOf)
                ->get();

            $L = (float) $rows->where('leg','L')->sum(fn($r)=>(float)$r->amount);
            $R = (float) $rows->where('leg','R')->sum(fn($r)=>(float)$r->amount);
            $matchedTotal = min($L,$R);

            foreach ($slabs as $slab) {
                if ($matchedTotal >= (int)$slab->threshold_volume) {
                    // check awarded?
                    $already = DB::table('star_rank_awards')
                        ->where('sponsor_id',$sid)
                        ->where('rank_no', $slab->rank_no)
                        ->exists();

                    if (!$already) {
                        if ($this->option('dry')) {
                            $this->line("DRY → sponsor=$sid rank={$slab->rank_no} award={$slab->reward_amount}");
                            continue;
                        }

                        DB::transaction(function() use ($sid,$slab,$asOf) {
                            DB::table('star_rank_awards')->insert([
                                'sponsor_id'       => $sid,
                                'rank_no'          => $slab->rank_no,
                                'threshold_volume' => $slab->threshold_volume,
                                'reward_amount'    => $slab->reward_amount,
                                'awarded_at'       => $asOf,
                                'created_at'       => now(),
                                'updated_at'       => now(),
                            ]);

                            // TODO: yahan apne wallet/ledger me credit karo:
                            // DB::table('wallet_transactions')->insert([...]);
                        });

                        $this->info(sprintf(
                            'AWARDED → SPONSOR=%d RANK=%d AMT=%.2f (asOf=%s)',
                            $sid, $slab->rank_no, $slab->reward_amount, $asOf->toDateTimeString()
                        ));
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
