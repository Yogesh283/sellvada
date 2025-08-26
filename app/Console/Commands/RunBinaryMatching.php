<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Binary matching with 2 closings per day:
 *   Closing-1: 06:00–11:59:59 (pay max 1 pair)
 *   Closing-2: 12:00–17:59:59 (pay max 1 pair)
 *
 * Pair prices:
 *   SILVER  = 3,000
 *   GOLD    = 18,000
 *   DIAMOND = 48,000
 *
 * Sponsor qualification (self-purchase based):
 *   - To earn SILVER  => sponsor must have at least SILVER (silver/gold/diamond)
 *   - To earn GOLD    => sponsor must have GOLD or DIAMOND
 *   - To earn DIAMOND => sponsor must have DIAMOND
 *
 * Usage:
 *   php artisan binary:match 1
 *   php artisan binary:match 2
 *   php artisan binary:match 1 --date=2025-08-25
 */
class RunBinaryMatching extends Command
{
    /** sales table name */
    protected string $sellTable = 'sell'; // change to 'sells' if your table is plural

    protected $signature = 'binary:match {closing : 1 or 2} {--date=}';
    protected $description = 'Compute binary matching (1 pair per closing) with sponsor qualification';

    /** plan ordering for qualification */
    private array $order = ['silver' => 1, 'gold' => 2, 'diamond' => 3];

    /** pair prices + caps per plan */
    private array $plans = [
        'silver'  => ['types' => ['silver'],                    'pair' => 3000.00,  'closing_cap' => 3000.00,  'daily_cap' => 6000.00],
        'gold'    => ['types' => ['gold', 'silver'],            'pair' => 18000.00, 'closing_cap' => 18000.00, 'daily_cap' => 36000.00],
        'diamond' => ['types' => ['diamond', 'gold', 'silver'], 'pair' => 48000.00, 'closing_cap' => 48000.00, 'daily_cap' => 96000.00],
    ];

    public function handle(): int
    {
        $closing = (int) $this->argument('closing');
        if (!in_array($closing, [1, 2], true)) {
            $this->error('closing must be 1 or 2');
            return self::INVALID;
        }

        // date to run for (IST)
        $day = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        // windows per your rule:
        // 1 => 06:00–11:59:59,  2 => 12:00–17:59:59
        if ($closing === 1) {
            $start = $day->copy()->setTime(6, 0, 0);
            $end   = $day->copy()->setTime(11, 59, 59);
        } else {
            $start = $day->copy()->setTime(12, 0, 0);
            $end   = $day->copy()->setTime(17, 59, 59);
        }

        foreach ($this->plans as $planName => $cfg) {
            // sponsors who have eligible sales in this window for this plan
            $sponsorIds = DB::table($this->sellTable)
                ->whereIn('type', $cfg['types'])
                ->whereBetween('created_at', [$start, $end])
                ->distinct()
                ->pluck('sponsor_id');

            foreach ($sponsorIds as $sid) {
                // volumes by leg for this window
                $rows = DB::table($this->sellTable)
                    ->select('leg', 'amount')
                    ->where('sponsor_id', $sid)
                    ->whereIn('type', $cfg['types'])
                    ->whereBetween('created_at', [$start, $end])
                    ->get();

                $volL = $rows->where('leg', 'L')->sum(fn ($r) => (float) $r->amount);
                $volR = $rows->where('leg', 'R')->sum(fn ($r) => (float) $r->amount);
                $matched = min($volL, $volR);

                // how many full pairs formed in this window?
                $pairs = (int) floor($matched / (float) $cfg['pair']);

                // sponsor qualification (based on own purchases up to the end of this window)
                $qualified = $this->sponsorQualifiedBySelfPurchase($sid, $planName, $end);

                // per your rule: pay for *only 1 pair* per closing (if at least 1 pair & qualified)
                $payoutForClosing = ($pairs >= 1 && $qualified) ? (float) $cfg['pair'] : 0.0;

                // defensive: also respect daily cap across closings
                $alreadyToday = (float) DB::table('binary_payouts')
                    ->where('sponsor_id', $sid)
                    ->where('plan', $planName)
                    ->where('closing_date', $day->toDateString())
                    ->sum('payout');

                $payable = max(0.0, min($payoutForClosing, (float) $cfg['daily_cap'] - $alreadyToday));

                // write/update ledger (unique key: sponsor_id+plan+date+closing_no)
                DB::table('binary_payouts')->updateOrInsert(
                    [
                        'sponsor_id'   => $sid,
                        'plan'         => $planName,
                        'closing_date' => $day->toDateString(),
                        'closing_no'   => $closing,
                    ],
                    [
                        'volume_left'  => $volL,
                        'volume_right' => $volR,
                        'matched'      => $matched,
                        'payout'       => $payable,
                        'updated_at'   => now(),
                        'created_at'   => now(),
                    ]
                );

                $this->info(sprintf(
                    'PLAN=%s SPONSOR=%d CLOSE=%d DATE=%s L=%.2f R=%.2f MATCHED=%.2f PAIRS=%d QUAL=%s PAYABLE=%.2f',
                    strtoupper($planName),
                    $sid,
                    $closing,
                    $day->toDateString(),
                    $volL,
                    $volR,
                    $matched,
                    $pairs,
                    $qualified ? 'YES' : 'NO',
                    $payable
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Sponsor qualification via SELF purchase:
     * finds highest package sponsor has bought (on/before $asOf),
     * and checks it meets/exceeds the plan requirement.
     */
    private function sponsorQualifiedBySelfPurchase(int $sponsorId, string $planName, Carbon $asOf): bool
    {
        // highest plan the sponsor has bought
        $types = DB::table($this->sellTable)
            ->where('buyer_id', $sponsorId)                 // sponsor’s own purchases
            ->whereIn('type', ['silver', 'gold', 'diamond'])
            ->where('status', 'paid')
            ->where('created_at', '<=', $asOf)
            ->pluck('type')
            ->map(fn ($t) => strtolower((string) $t))
            ->unique()
            ->all();

        $sLevel = 0;
        foreach ($types as $t) {
            $sLevel = max($sLevel, $this->order[$t] ?? 0);
        }

        $need = $this->order[$planName] ?? 99;
        return $sLevel >= $need;
    }
}
