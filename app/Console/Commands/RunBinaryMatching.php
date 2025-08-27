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

    // plan order for qualification check
    private array $order = ['silver' => 1, 'gold' => 2, 'diamond' => 3];

    // plan configs
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

        // Closing windows (IST example from your prior logic)
        if ($closing === 1) {
            $start = $day->copy()->setTime(6, 0, 0);
            $end   = $day->copy()->setTime(11, 59, 59);
        } else {
            $start = $day->copy()->setTime(12, 0, 0);
            $end   = $day->copy()->setTime(17, 59, 59);
        }

        foreach ($this->plans as $planName => $cfg) {

            // Sponsors who have PAID sales in this closing window for this plan family
            $sponsorIds = DB::table($this->sellTable)
                ->whereIn('type', $cfg['types'])
                ->where('status', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->distinct()
                ->pluck('sponsor_id');

            foreach ($sponsorIds as $sid) {
                // Get leg volumes for the window (PAID only)
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

                // Number of full pairs available in the window
                $pairs = (int) floor($matched / (float) $cfg['pair']);

                // Qualification via self purchase (plan order respected)
                $qualified = $this->sponsorQualifiedBySelfPurchase($sid, $planName, $end);

                // 1 pair per closing if qualified (else 0)
                $payoutForClosing = ($pairs >= 1 && $qualified) ? (float) $cfg['pair'] : 0.0;

                // Apply DAILY cap across both closings
                $alreadyToday = (float) DB::table('binary_payouts')
                    ->where('sponsor_id', $sid)
                    ->where('plan', $planName)
                    ->where('closing_date', $day->toDateString())
                    ->sum('payout');

                $remainingToday = max(0.0, (float) $cfg['daily_cap'] - $alreadyToday);
                $payable = ($remainingToday > 0)
                    ? min($payoutForClosing, $remainingToday)
                    : 0.0;

                if ($payable > 0) {
                    DB::beginTransaction();
                    try {
                        // 1) Upsert into binary_payouts (per user, plan, day, closing)
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

                        // 2) Insert into _payout (avoid duplicate per user+day+closing)
                        $method = 'closing_' . $closing; // closing_1 / closing_2

                        $exists = DB::table('_payout')
                            ->where('user_id', $sid)
                            ->where('type', 'binary_matching')
                            ->where('method', $method)
                            ->whereDate('created_at', $day->toDateString())
                            ->exists();

                        if (!$exists) {
                            // 2a) Create payout row
                            DB::table('_payout')->insert([
                                'user_id'    => $sid,
                                'amount'     => $payable,
                                'status'     => 'pending',          // adjust as per your payout flow
                                'method'     => $method,            // closing_1 / closing_2
                                'type'       => 'binary_matching',  // categorize payout
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            // 3) CREDIT WALLET (balance table, single row per user)
                            // If row exists: amount += payable; else create with amount = payable
                            $affected = DB::update(
                                "UPDATE wallet SET amount = amount + ? WHERE user_id = ?",
                                [number_format($payable, 2, '.', ''), $sid]
                            );

                            if ($affected === 0) {
                                DB::table('wallet')->insert([
                                    'user_id'    => $sid,
                                    'amount'     => number_format($payable, 2, '.', ''),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }

                        } else {
                            // already paid/recorded for this slot → skip wallet credit
                            // (agar re-run pe adjust karna ho, yahan delta calculate karke wallet me update kar sakte ho)
                        }

                        DB::commit();

                        $this->info(sprintf(
                            'PAID → PLAN=%s SPONSOR=%d CLOSE=%d DATE=%s PAYABLE=%.2f (binary_payouts + _payout + wallet+)',
                            strtoupper($planName),
                            $sid,
                            $closing,
                            $day->toDateString(),
                            $payable
                        ));
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        $this->error(sprintf(
                            'ERR  → PLAN=%s SPONSOR=%d CLOSE=%d DATE=%s :: %s',
                            strtoupper($planName),
                            $sid,
                            $closing,
                            $day->toDateString(),
                            $e->getMessage()
                        ));
                    }
                }
                // if $payable == 0 → no write, no row in _payout, no wallet credit
            }
        }

        return self::SUCCESS;
    }

    /**
     * Checks if sponsor is qualified via self purchase at or before $asOf.
     * Required self plan level must be >= the current planName level.
     */
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
