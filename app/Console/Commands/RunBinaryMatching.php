<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunBinaryMatching extends Command
{
    protected string $sellTable = 'sell';

    protected $signature   = 'binary:match {closing : 1 or 2} {--date=}';
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

        // IST windows
        if ($closing === 1) {
            $start = $day->copy()->setTime(6, 0, 0);
            $end   = $day->copy()->setTime(11, 59, 59);
        } else {
            $start = $day->copy()->setTime(12, 0, 0);
            $end   = $day->copy()->setTime(17, 59, 59);
        }

        foreach ($this->plans as $planName => $cfg) {
            // distinct, valid sponsor ids only
            $sponsorIds = DB::table($this->sellTable)
                ->whereIn('type', $cfg['types'])
                ->where('status', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('sponsor_id')
                ->pluck('sponsor_id')
                ->filter(fn ($v) => !is_null($v))
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values();

            foreach ($sponsorIds as $sid) {
                // window volumes for this sponsor
                $rows = DB::table($this->sellTable)
                    ->select('leg', 'amount')
                    ->where('sponsor_id', $sid)
                    ->whereIn('type', $cfg['types'])
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->get();

                // case-safe leg sum
                $volL = 0.0; $volR = 0.0;
                foreach ($rows as $r) {
                    $leg = strtoupper((string)($r->leg ?? ''));
                    $amt = (float)$r->amount;
                    if ($leg === 'L') { $volL += $amt; }
                    elseif ($leg === 'R') { $volR += $amt; }
                }

                $matched = min($volL, $volR);
                $pairs   = (int) floor($matched / (float) $cfg['pair']);

                // qualification via self purchase (plan order respected)
                $qualified = $this->sponsorQualifiedBySelfPurchase($sid, $planName, $end);

                // 1 pair per closing if qualified
                $payoutForClosing = ($pairs >= 1 && $qualified) ? (float) $cfg['pair'] : 0.0;
                // per-closing cap (here equal to pair amount)
                $payoutForClosing = min($payoutForClosing, (float)$cfg['closing_cap']);

                // daily cap across both closings
                $alreadyToday = (float) DB::table('binary_payouts')
                    ->where('sponsor_id', $sid)
                    ->where('plan', $planName)
                    ->where('closing_date', $day->toDateString())
                    ->sum('payout');

                $remainingToday = max(0.0, (float) $cfg['daily_cap'] - $alreadyToday);
                $payable = ($remainingToday > 0) ? min($payoutForClosing, $remainingToday) : 0.0;

                if ($payable <= 0) {
                    // still record volumes for visibility
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
                            'payout'       => 0.0,
                            'updated_at'   => now(),
                            'created_at'   => now(),
                        ]
                    );
                    $this->line(sprintf('NO PAY → PLAN=%s SPONSOR=%d CLOSE=%d DATE=%s (volL=%.2f volR=%.2f matched=%.2f)',
                        strtoupper($planName), $sid, $closing, $day->toDateString(), $volL, $volR, $matched));
                    continue;
                }

                // figure out weaker leg buyer (from_user_id) inside this window
                $minSide = ($volL <= $volR) ? 'L' : 'R';
                $triggerBuyerId = DB::table($this->sellTable)
                    ->where('sponsor_id', $sid)
                    ->whereIn('type', $cfg['types'])
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$start, $end])
                    ->where('leg', $minSide)
                    ->orderByDesc('created_at')
                    ->value('buyer_id'); // may be null if weird, that's fine

                DB::beginTransaction();
                try {
                    // 1) detail table (idempotent per slot)
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

                    // 2) payout ledger (guard duplicate per user+day+closing)
                    $method = 'closing_' . $closing;
                    $exists = DB::table('_payout')
                        ->where('user_id', $sid)
                        ->where('type', 'binary_matching')
                        ->where('method', $method)
                        ->whereDate('created_at', $day->toDateString())
                        ->exists();

                    if (!$exists) {
                        DB::table('_payout')->insert([
                            'user_id'      => $sid,              // legacy
                            'to_user_id'   => $sid,              // ✅ receiver
                            'from_user_id' => $triggerBuyerId,   // ✅ source buyer on weaker leg (can be null)
                            'amount'       => $payable,
                            'status'       => 'pending',
                            'method'       => $method,           // closing_1 / closing_2
                            'type'         => 'binary_matching',
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);

                        // 3) credit wallet
                        $inc = number_format($payable, 2, '.', '');
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
                    }

                    DB::commit();
                    $this->info(sprintf(
                        'PAID → PLAN=%s SPONSOR=%d CLOSE=%d DATE=%s PAYABLE=%.2f FROM=%s',
                        strtoupper($planName), $sid, $closing, $day->toDateString(), $payable, $triggerBuyerId ?? 'null'
                    ));
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error(sprintf(
                        'ERR → PLAN=%s SPONSOR=%d CLOSE=%d DATE=%s :: %s',
                        strtoupper($planName), $sid, $closing, $day->toDateString(), $e->getMessage()
                    ));
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Qualified if sponsor has self-purchase at or above plan level by $asOf.
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

        $selfLevel = 0;
        foreach ($types as $t) {
            $selfLevel = max($selfLevel, $this->order[$t] ?? 0);
        }

        $need = $this->order[$planName] ?? 99;
        return $selfLevel >= $need;
    }
}
