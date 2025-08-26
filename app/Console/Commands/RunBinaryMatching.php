<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunBinaryMatching extends Command
{
    protected string $sellTable = 'sell';

    protected $signature = 'binary:match {closing : 1 or 2} {--date=}';
    protected $description = 'Compute binary matching (1 pair per closing) with sponsor qualification';

    private array $order = ['silver' => 1, 'gold' => 2, 'diamond' => 3];

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

        $day = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        if ($closing === 1) {
            $start = $day->copy()->setTime(6, 0, 0);
            $end   = $day->copy()->setTime(11, 59, 59);
        } else {
            $start = $day->copy()->setTime(12, 0, 0);
            $end   = $day->copy()->setTime(17, 59, 59);
        }

        foreach ($this->plans as $planName => $cfg) {

            // ✅ sirf paid sales se sponsors pick karo
            $sponsorIds = DB::table($this->sellTable)
                ->whereIn('type', $cfg['types'])
                ->where('status', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->distinct()
                ->pluck('sponsor_id');

            foreach ($sponsorIds as $sid) {

                // ✅ sirf paid sales ka volume lo
                $rows = DB::table($this->sellTable)
                    ->select('leg', 'amount')
                    ->where('sponsor_id', $sid)
                    ->whereIn('type', $cfg['types'])
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->get();

                $volL = (float) $rows->where('leg', 'L')->sum(fn ($r) => (float) $r->amount);
                $volR = (float) $rows->where('leg', 'R')->sum(fn ($r) => (float) $r->amount);
                $matched = min($volL, $volR);

                // full pairs
                $pairs = (int) floor($matched / (float) $cfg['pair']);

                // qualification (self-purchase)
                $qualified = $this->sponsorQualifiedBySelfPurchase($sid, $planName, $end);

                // 1 pair per closing if qualified
                $payoutForClosing = ($pairs >= 1 && $qualified) ? (float) $cfg['pair'] : 0.0;

                // daily cap apply
                $alreadyToday = (float) DB::table('binary_payouts')
                    ->where('sponsor_id', $sid)
                    ->where('plan', $planName)
                    ->where('closing_date', $day->toDateString())
                    ->sum('payout');

                $remainingToday = max(0.0, (float) $cfg['daily_cap'] - $alreadyToday);
                $payable = ($remainingToday > 0)
                    ? min($payoutForClosing, $remainingToday)
                    : 0.0;

                // ✅ IMPORTANT: sirf jab payout > 0 ho tabhi entry banye
                if ($payable > 0) {
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

                    // optional: chaho to yeh line bhi hata sakते हो
                    $this->info(sprintf(
                        'PAID → PLAN=%s SPONSOR=%d CLOSE=%d DATE=%s PAYABLE=%.2f',
                        strtoupper($planName),
                        $sid,
                        $closing,
                        $day->toDateString(),
                        $payable
                    ));
                }
                // else: no row, no log
            }
        }

        return self::SUCCESS;
    }

    private function sponsorQualifiedBySelfPurchase(int $sponsorId, string $planName, Carbon $asOf): bool
    {
        $types = DB::table($this->sellTable)
            ->where('buyer_id', $sponsorId)
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
