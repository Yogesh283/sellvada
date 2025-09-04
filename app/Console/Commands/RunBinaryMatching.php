<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunBinaryMatching extends Command
{
    protected string $sellTable = 'sell';

    protected $signature   = 'binary:match {closing : 1 or 2} {--date=}';
    protected $description = 'Compute package-based single-pair binary payout from sell table (with self qualification)';

    // canonical level order (all LOWERCASE)
    private array $order = ['silver' => 1, 'gold' => 2, 'diamond' => 3];

    // amount per package (single pair) - LOWERCASE keys
    private array $planAmount = ['silver' => 3000.00, 'gold' => 18000.00, 'diamond' => 48000.00];

    // capping - LOWERCASE keys
    private array $capPerClosing = ['silver'=>3000.00, 'gold'=>18000.00, 'diamond'=>48000.00];
    private array $capPerDay     = ['silver'=>6000.00, 'gold'=>36000.00, 'diamond'=>96000.00];

    public function handle(): int
    {
        $closing = (int) $this->argument('closing');
        if (!in_array($closing, [1, 2], true)) {
            $this->error('closing must be 1 or 2');
            return self::INVALID;
        }

        $tz  = 'Asia/Kolkata';
        if ($this->option('date')) {
            $day = Carbon::parse($this->option('date'), $tz)->startOfDay();
            $now = Carbon::parse($this->option('date'), $tz)->endOfDay();
        } else {
            $now = Carbon::now($tz);
            $day = $now->copy()->startOfDay();
        }

        [$start, $end] = $this->makeWindow($day, $closing);
        $this->info("Now(IST): {$now->toDateTimeString()} | Window: {$start->toDateTimeString()} → {$end->toDateTimeString()}");

        // smart fallback to latest date that has rows in same closing window
        if (!$this->option('date')) {
            if ($this->countRowsInWindow($start, $end) === 0) {
                $latestDay = $this->findLatestDateForClosingWindow($closing);
                if ($latestDay) {
                    [$start, $end] = $this->makeWindow($latestDay, $closing);
                    $this->warn("No rows in today's window; switched to {$latestDay->toDateString()} | {$start->toTimeString()} → {$end->toTimeString()}");
                }
            }
        }

        $this->info("Paid rows in final window: ".$this->countRowsInWindow($start, $end));

        // sponsors in this (final) window
        $sponsorIds = DB::table($this->sellTable)
            ->whereIn(DB::raw('LOWER(type)'), ['silver','gold','diamond'])
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('sponsor_id')
            ->pluck('sponsor_id')
            ->filter(fn ($v) => (int)$v > 0)
            ->unique()
            ->values();

        $this->info('Sponsors in window: '.count($sponsorIds));

        foreach ($sponsorIds as $sidRaw) {
            $sid = (int) $sidRaw;

            // 1) Self qualification
            $selfLvl  = $this->getSelfLevel($sid, $end); // 0/1/2/3
            $selfPlan = $this->lvlToPlan($selfLvl);      // 'silver' | 'gold' | 'diamond' | null
            if (!$selfPlan) {
                $this->recordNoPay($sid, 'none', $start, $closing);
                $this->line("NO SELF → SPONSOR={$sid}");
                continue;
            }

            // 2) Highest plan on L/R in this window
            $lr = DB::table($this->sellTable)
                ->selectRaw("
                    UPPER(leg) as leg,
                    MAX(CASE LOWER(type)
                         WHEN 'silver' THEN 1
                         WHEN 'gold' THEN 2
                         WHEN 'diamond' THEN 3
                         ELSE 0 END) as lvl
                ")
                ->where('sponsor_id', $sid)
                ->where('status', 'paid')
                ->whereIn(DB::raw('LOWER(type)'), ['silver','gold','diamond'])
                ->whereBetween('created_at', [$start, $end])
                ->whereIn(DB::raw('UPPER(leg)'), ['L','R'])
                ->groupBy(DB::raw('UPPER(leg)'))
                ->pluck('lvl','leg');

            $leftLvl   = (int)($lr['L'] ?? 0);
            $rightLvl  = (int)($lr['R'] ?? 0);
            $leftPlan  = $this->lvlToPlan($leftLvl);
            $rightPlan = $this->lvlToPlan($rightLvl);

            if (!$leftPlan || !$rightPlan) {
                $this->recordNoPay($sid, $selfPlan, $start, $closing);
                $this->line("NO L/R → SPONSOR={$sid} SELF=".strtoupper($selfPlan));
                continue;
            }

            // 3) Allowed + payout
            $payable = 0.0;
            $reason  = '';

            if ($selfPlan === 'silver') {
                if ($leftLvl === 1 && $rightLvl === 1) {
                    $payable = $this->planAmount['silver'];
                    $reason  = 'silver_pair_only';
                } else {
                    $this->recordNoPay($sid, $selfPlan, $start, $closing);
                    $this->line("NOT ALLOWED (SILVER needs S/S) → SPONSOR={$sid} L=".strtoupper($leftPlan)." R=".strtoupper($rightPlan));
                    continue;
                }
            } elseif ($selfPlan === 'gold') {
                if ($leftLvl === 3 || $rightLvl === 3) {
                    $this->recordNoPay($sid, $selfPlan, $start, $closing);
                    $this->line("NOT ALLOWED (GOLD with DIAMOND leg) → SPONSOR={$sid} L=".strtoupper($leftPlan)." R=".strtoupper($rightPlan));
                    continue;
                }
                if ($leftLvl === 2 && $rightLvl === 2) {
                    $payable = $this->planAmount['gold'];
                    $reason  = 'both_gold';
                } elseif ($leftLvl >= 1 && $rightLvl >= 1) {
                    $payable = $this->planAmount['silver'];
                    $reason  = 'fallback_silver_pair_on_gold_self';
                } else {
                    $this->recordNoPay($sid, $selfPlan, $start, $closing);
                    $this->line("NO MATCH (GOLD) → SPONSOR={$sid} L=".strtoupper($leftPlan)." R=".strtoupper($rightPlan));
                    continue;
                }
            } else { // diamond
                if ($leftLvl === 3 && $rightLvl === 3) {
                    $payable = $this->planAmount['diamond'];
                    $reason  = 'both_diamond';
                } elseif ($leftLvl >= 2 && $rightLvl >= 2) {
                    $payable = $this->planAmount['gold'];
                    $reason  = 'both_at_least_gold';
                } elseif ($leftLvl >= 1 && $rightLvl >= 1) {
                    $payable = $this->planAmount['silver'];
                    $reason  = 'both_at_least_silver';
                } else {
                    $this->recordNoPay($sid, $selfPlan, $start, $closing);
                    $this->line("NO MATCH (DIAMOND) → SPONSOR={$sid} L=".strtoupper($leftPlan)." R=".strtoupper($rightPlan));
                    continue;
                }
            }

            // 4) Caps (per closing + per day)
            $capClose = $this->capPerClosing[$selfPlan] ?? 0.0;
            $capDay   = $this->capPerDay[$selfPlan]     ?? 0.0;
            $method   = 'closing_'.$closing;

            $paidInThisClosing = (float) DB::table('_payout')
                ->where('user_id', $sid)
                ->where('type', 'binary_matching')
                ->where('method', $method)
                ->whereDate('created_at', $start->toDateString())
                ->sum('amount');

            $paidInDay = (float) DB::table('_payout')
                ->where('user_id', $sid)
                ->where('type', 'binary_matching')
                ->whereDate('created_at', $start->toDateString())
                ->sum('amount');

            $remainClosing = max(0.0, $capClose - $paidInThisClosing);
            $remainDay     = max(0.0, $capDay   - $paidInDay);
            $headroom      = min($remainClosing, $remainDay);

            $payable = min($payable, $headroom);

            if ($payable <= 0) {
                $this->recordNoPay($sid, $selfPlan, $start, $closing);
                $this->line("CAPPED OUT → SPONSOR={$sid} SELF=".strtoupper($selfPlan)." CLOSE={$closing} DATE={$start->toDateString()}");
                continue;
            }

            // 5) Ledger reference (weaker leg; tie→L)
            $minSide = ($leftLvl <= $rightLvl) ? 'L' : 'R';
            $triggerBuyerId = DB::table($this->sellTable)
                ->where('sponsor_id', $sid)
                ->where('status', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->where(DB::raw('UPPER(leg)'), $minSide)
                ->orderByDesc('created_at')
                ->value('buyer_id');

            DB::beginTransaction();
            try {
                DB::table('binary_payouts')->updateOrInsert(
                    ['sponsor_id'=>$sid,'plan'=>$selfPlan,'closing_date'=>$start->toDateString(),'closing_no'=>$closing],
                    ['volume_left'=>0,'volume_right'=>0,'matched'=>0,'payout'=>$payable,'updated_at'=>now(),'created_at'=>now()]
                );

                $exists = DB::table('_payout')
                    ->where('user_id', $sid)
                    ->where('type', 'binary_matching')
                    ->where('method', $method)
                    ->whereDate('created_at', $start->toDateString())
                    ->exists();

                if (!$exists) {
                    $gross = number_format($payable, 2, '.', '');
                    DB::table('_payout')->insert([
                        'user_id'      => $sid,
                        'to_user_id'   => $sid,
                        'from_user_id' => $triggerBuyerId,
                        'amount'       => $gross,         // keep GROSS in ledger
                        'status'       => 'pending',
                        'method'       => $method,
                        'type'         => 'binary_matching',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    // Credit NET (80%) to wallet
                    $net       = number_format($payable * 0.80, 2, '.', '');
                    $affected  = DB::update("UPDATE wallet SET amount = amount + ? WHERE user_id = ?", [$net, $sid]);
                    if ($affected === 0) {
                        DB::table('wallet')->insert([
                            'user_id'    => $sid,
                            'amount'     => $net,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::commit();
                $this->info(sprintf(
                    'PAID → SPONSOR=%d SELF=%s L=%s R=%s CLOSE=%d DATE=%s GROSS=%.2f (NET=%.2f) %s FROM=%s',
                    $sid, strtoupper($selfPlan), strtoupper($leftPlan), strtoupper($rightPlan),
                    $closing, $start->toDateString(), $payable, $payable * 0.80, $reason, $triggerBuyerId ?? 'null'
                ));
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error(sprintf(
                    'ERR → SPONSOR=%d CLOSE=%d DATE=%s :: %s',
                    $sid, $closing, $start->toDateString(), $e->getMessage()
                ));
            }
        }

        return self::SUCCESS;
    }

    private function makeWindow(Carbon $day, int $closing): array
    {
        if ($closing === 1) {
            // 12 AM - 12 PM
            return [$day->copy()->setTime(0, 0, 0), $day->copy()->setTime(11, 59, 59)];
        }
        // 12 PM - 12 AM
        return [$day->copy()->setTime(12, 0, 0), $day->copy()->setTime(23, 59, 59)];
    }

    private function countRowsInWindow(Carbon $start, Carbon $end): int
    {
        return DB::table($this->sellTable)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function findLatestDateForClosingWindow(int $closing): ?Carbon
    {
        [$from, $to] = $closing === 1 ? ['00:00:00','11:59:59'] : ['12:00:00','23:59:59'];

        $latest = DB::table($this->sellTable)
            ->where('status', 'paid')
            ->whereRaw("TIME(created_at) BETWEEN ? AND ?", [$from, $to])
            ->max(DB::raw('DATE(created_at)'));

        return $latest ? Carbon::parse($latest, 'Asia/Kolkata')->startOfDay() : null;
    }

    private function getSelfLevel(int $sponsorId, Carbon $asOf): int
    {
        $types = DB::table($this->sellTable)
            ->where('buyer_id', $sponsorId)
            ->whereIn(DB::raw('LOWER(type)'), ['silver','gold','diamond'])
            ->where('status', 'paid')
            ->where('created_at', '<=', $asOf)
            ->pluck('type')
            ->map(fn ($t) => strtolower((string)$t))
            ->unique()
            ->all();

        $level = 0;
        foreach ($types as $t) {
            $level = max($level, $this->order[$t] ?? 0);
        }
        return $level;
    }

    private function recordNoPay(int $sid, string $plan, Carbon $dayStart, int $closing): void
    {
        DB::table('binary_payouts')->updateOrInsert(
            ['sponsor_id'=>$sid,'plan'=>$plan,'closing_date'=>$dayStart->toDateString(),'closing_no'=>$closing],
            ['volume_left'=>0,'volume_right'=>0,'matched'=>0,'payout'=>0.0,'updated_at'=>now(),'created_at'=>now()]
        );
    }

    private function lvlToPlan(int $lvl): ?string
    {
        return $lvl >= 3 ? 'diamond' : ($lvl === 2 ? 'gold' : ($lvl === 1 ? 'silver' : null));
    }
}
