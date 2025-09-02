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

    // level order
    private array $order = ['silver' => 1, 'gold' => 2, 'diamond' => 3];

    // amount per package (single pair)
    private array $planAmount = ['silver' => 3000.00, 'gold' => 18000.00, 'diamond' => 48000.00];

    // >>> ADDED: capping per closing and per day <<<
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

        // build window for selected closing
        [$start, $end] = $this->makeWindow($day, $closing);
        $this->info("Now(IST): {$now->toDateTimeString()} | Window: {$start->toDateTimeString()} → {$end->toDateTimeString()}");

        // ===== smart fallback: same closing-window में data वाले latest दिन पर shift करो =====
        if (!$this->option('date')) {
            $rowsInWindow = $this->countRowsInWindow($start, $end);
            if ($rowsInWindow === 0) {
                $latestDay = $this->findLatestDateForClosingWindow($closing);
                if ($latestDay) {
                    [$start, $end] = $this->makeWindow($latestDay, $closing);
                    $this->warn("No rows in today's window; switched to {$latestDay->toDateString()} | {$start->toTimeString()} → {$end->toTimeString()}");
                }
            }
        }

        $paidRows = $this->countRowsInWindow($start, $end);
        $this->info("Paid rows in final window: {$paidRows}");

        // ===== sponsors in this (final) window =====
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

            // 1) self qualification (highest self purchase ≤ window end)
            $selfLvl  = $this->getSelfLevel($sid, $end);      // 0/1/2/3
            $selfPlan = $this->lvlToPlan($selfLvl);
            if (!$selfPlan) {
                $this->recordNoPay($sid, 'none', $start, $closing);
                $this->line("NO SELF → SPONSOR={$sid}");
                continue;
            }

            // 2) highest package on L/R in this window
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
                $this->line("NO L/R → SPONSOR={$sid} SELF={$selfPlan}");
                continue;
            }

            // 3) allowed check by self plan
            $allowed = [
                'silver'  => ['silver'],
                'gold'    => ['silver','gold'],
                'diamond' => ['silver','gold','diamond'],
            ];
            $ok = in_array($leftPlan,  $allowed[$selfPlan], true)
               && in_array($rightPlan, $allowed[$selfPlan], true);

            if (!$ok) {
                $this->recordNoPay($sid, $selfPlan, $start, $closing);
                $this->line("NOT ALLOWED → SPONSOR={$sid} SELF={$selfPlan} L={$leftPlan} R={$rightPlan}");
                continue;
            }

            // 4) payout (single pair; no capping yet)
            $reason  = 'smallest_package_one_pair';
            if ($selfPlan==='gold' && $leftPlan==='gold' && $rightPlan==='gold') {
                $payable = $this->planAmount['gold'];
                $reason  = 'all_gold';
            } elseif ($selfPlan==='diamond' && $leftPlan==='diamond' && $rightPlan==='diamond') {
                $payable = $this->planAmount['diamond'];
                $reason  = 'all_diamond';
            } else {
                $minLvl  = min($selfLvl, $leftLvl, $rightLvl);
                $minPlan = $this->lvlToPlan($minLvl);
                $payable = $minPlan ? (float)$this->planAmount[$minPlan] : 0.0;
            }

            if ($payable <= 0) {
                $this->recordNoPay($sid, $selfPlan, $start, $closing);
                $this->line("NO PAY → SPONSOR={$sid} SELF={$selfPlan} L={$leftPlan} R={$rightPlan}");
                continue;
            }

            // >>> ADDED: CAP CONDITION (per closing + per day) <<<
            $capClose = $this->capPerClosing[$selfPlan] ?? 0.0;  // per-closing cap
            $capDay   = $this->capPerDay[$selfPlan]     ?? 0.0;  // per-day cap

            $method = 'closing_'.$closing;

            // paid already in THIS closing (same date)
            $paidInThisClosing = (float) DB::table('_payout')
                ->where('user_id', $sid)
                ->where('type', 'binary_matching')
                ->where('method', $method)
                ->whereDate('created_at', $start->toDateString())
                ->sum('amount');

            // paid already TODAY (both closings)
            $paidInDay = (float) DB::table('_payout')
                ->where('user_id', $sid)
                ->where('type', 'binary_matching')
                ->whereDate('created_at', $start->toDateString())
                ->sum('amount');

            // remaining headroom
            $remainClosing = max(0.0, $capClose - $paidInThisClosing);
            $remainDay     = max(0.0, $capDay   - $paidInDay);
            $headroom      = min($remainClosing, $remainDay);

            // apply cap
            $payable = min($payable, $headroom);

            // if cap exhausted -> no pay this closing
            if ($payable <= 0) {
                $this->recordNoPay($sid, $selfPlan, $start, $closing);
                $this->line("CAPPED OUT → SPONSOR={$sid} SELF={$selfPlan} CLOSE={$closing} DATE={$start->toDateString()}");
                continue;
            }
            // <<< END CAP CONDITION >>>

            // weaker leg (lower level; tie→L) → pick latest buyer there for ledger
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
                // detail
                DB::table('binary_payouts')->updateOrInsert(
                    ['sponsor_id'=>$sid,'plan'=>$selfPlan,'closing_date'=>$start->toDateString(),'closing_no'=>$closing],
                    ['volume_left'=>0,'volume_right'=>0,'matched'=>0,'payout'=>$payable,'updated_at'=>now(),'created_at'=>now()]
                );

                // ledger (guard duplicate)
                $exists = DB::table('_payout')
                    ->where('user_id', $sid)
                    ->where('type', 'binary_matching')
                    ->where('method', $method)
                    ->whereDate('created_at', $start->toDateString())
                    ->exists();

                if (!$exists) {
                    DB::table('_payout')->insert([
                        'user_id'      => $sid,
                        'to_user_id'   => $sid,
                        'from_user_id' => $triggerBuyerId,
                        'amount'       => $payable,
                        'status'       => 'pending',
                        'method'       => $method,
                        'type'         => 'binary_matching',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    // wallet credit
                    $inc = number_format($payable, 2, '.', '');
                    $affected = DB::update("UPDATE wallet SET amount = amount + ? WHERE user_id = ?", [$inc, $sid]);
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
                    'PAID → SPONSOR=%d SELF=%s L=%s R=%s CLOSE=%d DATE=%s PAYABLE=%.2f (%s) FROM=%s',
                    $sid, strtoupper($selfPlan), $leftPlan, $rightPlan,
                    $closing, $start->toDateString(), $payable, $reason, $triggerBuyerId ?? 'null'
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

    /** Build window for a given day+closing (IST). */
    private function makeWindow(Carbon $day, int $closing): array
    {
        if ($closing === 1) {
            return [$day->copy()->setTime(6, 0, 0), $day->copy()->setTime(11, 59, 59)];
        }
        return [$day->copy()->setTime(12, 0, 0), $day->copy()->setTime(17, 59, 59)];
    }

    /** Count rows in window for quick debug. */
    private function countRowsInWindow(Carbon $start, Carbon $end): int
    {
        return DB::table($this->sellTable)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /** Find latest DATE that actually has rows inside the selected closing window. */
    private function findLatestDateForClosingWindow(int $closing): ?Carbon
    {
        [$from, $to] = $closing === 1
            ? ['06:00:00','11:59:59']
            : ['12:00:00','17:59:59'];

        // latest date where TIME(created_at) is inside the closing window
        $latest = DB::table($this->sellTable)
            ->where('status', 'paid')
            ->whereRaw("TIME(created_at) BETWEEN ? AND ?", [$from, $to])
            ->max(DB::raw('DATE(created_at)'));

        return $latest ? Carbon::parse($latest, 'Asia/Kolkata')->startOfDay() : null;
    }

    /** Highest self purchase level by $asOf (0=none,1=silver,2=gold,3=diamond). */
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

    /** Zero-payout row for visibility. */
    private function recordNoPay(int $sid, string $plan, Carbon $dayStart, int $closing): void
    {
        DB::table('binary_payouts')->updateOrInsert(
            ['sponsor_id'=>$sid,'plan'=>$plan,'closing_date'=>$dayStart->toDateString(),'closing_no'=>$closing],
            ['volume_left'=>0,'volume_right'=>0,'matched'=>0,'payout'=>0.0,'updated_at'=>now(),'created_at'=>now()]
        );
    }

    /** Map level → plan. */
    private function lvlToPlan(int $lvl): ?string
    {
        return $lvl >= 3 ? 'diamond' : ($lvl === 2 ? 'gold' : ($lvl === 1 ? 'silver' : null));
    }
}
