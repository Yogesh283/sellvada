<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryQualify extends Command
{
    protected $signature = 'repurchase:qualify
        {--month= : target month YYYY-MM}
        {--require-self=0 : require self repurchase (1) or not (0)}
        {--dry : dry run (no DB writes)}
        {--debug : show per-sponsor breakdown}';

    protected $description = 'Compute VIP qualification for Repurchase Salary (placement-based) with carry forward system.';

    // summary counters
    protected array $summary = [
        'total_candidates'   => 0,
        'qualified_count'    => 0,
        'qualified_created'  => 0,
        'qualified_updated'  => 0,
    ];

    public function handle(): int
    {
        $dry        = (bool) $this->option('dry');
        $requireSelf= ((int)$this->option('require-self')) ? 1 : 0;
        $debug      = (bool) $this->option('debug');

        // load slabs
        $slabs = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabs->isEmpty()) {
            $this->warn('No slabs configured in repurchase_salary_slabs table.');
            return self::SUCCESS;
        }

        // month
        $monthStr = $this->option('month') ?: now()->format('Y-m');
        try {
            $month = Carbon::parse($monthStr . '-01');
        } catch (\Throwable $e) {
            $this->error('Invalid month format. Use YYYY-MM');
            return self::FAILURE;
        }

        $mStart = $month->copy()->startOfMonth()->toDateTimeString();
        $mEnd   = $month->copy()->endOfMonth()->toDateTimeString();

        $this->line("Processing MONTH: {$month->format('Y-m')} (require-self={$requireSelf})");

        // candidates
        $cands = DB::table('users')
            ->whereNotNull('referral_id')
            ->select('id','referral_id','left_user_id','right_user_id')
            ->get();

        if ($cands->isEmpty()) {
            $this->line("No candidates found.");
            return self::SUCCESS;
        }

        foreach ($cands as $u) {
            $this->summary['total_candidates']++;
            $sid       = (int)$u->id;
            $leftRoot  = (int)($u->left_user_id ?? 0);
            $rightRoot = (int)($u->right_user_id ?? 0);

            // optional self check
            if ($requireSelf) {
                $hasSelf = DB::table('repurchase')
                    ->where('buyer_id', $sid)
                    ->where('status','paid')
                    ->whereBetween('created_at', [$mStart, $mEnd])
                    ->exists();
                if (!$hasSelf) {
                    $this->line("SKIP sponsor={$sid} — no SELF repurchase in month.");
                    continue;
                }
            }

            // current month business
            $placement = $this->computePlacementTotals($sid, $leftRoot, $rightRoot, $mStart, $mEnd);
            $volL = $placement['sell_left'] + $placement['rep_left'];
            $volR = $placement['sell_right'] + $placement['rep_right'];

            // load previous carry
            $carryRow = DB::table('repurchase_carry')->where('sponsor_id', $sid)->first();
            $carryPrevL = (float)($carryRow->left ?? 0);
            $carryPrevR = (float)($carryRow->right ?? 0);

            // add old carry
            $volL += $carryPrevL;
            $volR += $carryPrevR;

            // matched business consume
            $matched   = min($volL, $volR);
            $leftoverL = $volL - $matched;
            $leftoverR = $volR - $matched;

            if ($debug || $dry) {
                $this->line("DEBUG sponsor={$sid} sellL={$placement['sell_left']} sellR={$placement['sell_right']} repL={$placement['rep_left']} repR={$placement['rep_right']} prevCarryL={$carryPrevL} prevCarryR={$carryPrevR} matched={$matched} leftoverL={$leftoverL} leftoverR={$leftoverR}");
            }

            // highest slab
            $qualified = null;
            foreach ($slabs as $s) {
                if ($matched >= (float)$s->threshold_volume) {
                    $qualified = $s;
                }
            }

            if (!$qualified) {
                // even if not qualified, update carry forward
                if (!$dry) {
                    DB::table('repurchase_carry')->updateOrInsert(
                        ['sponsor_id' => $sid],
                        [
                            'left' => $leftoverL,
                            'right'=> $leftoverR,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
                $this->line("SKIP sponsor={$sid} matched={$matched} — no VIP slab.");
                continue;
            }

            $this->summary['qualified_count']++;

            // qualification month
            $firstPayMonth = Carbon::parse($mStart)->copy()->startOfMonth()->toDateString();
            $periodMarker  = Carbon::parse($mStart)->toDateString();

            if ($dry) {
                $this->line("DRY → sponsor={$sid} VIP={$qualified->vip_no} salary={$qualified->salary_amount} matched={$matched}");
                $this->summary['qualified_created']++;
                continue;
            }

            DB::beginTransaction();
            try {
                // save carry forward (after consuming matched)
                DB::table('repurchase_carry')->updateOrInsert(
                    ['sponsor_id' => $sid],
                    [
                        'left' => $leftoverL,
                        'right'=> $leftoverR,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $existing = DB::table('repurchase_salary_qualifications')
                    ->where('sponsor_id', $sid)
                    ->where('period_month', $periodMarker)
                    ->lockForUpdate()
                    ->first();

                if (!$existing) {
                    // create new qualification
                    $qid = DB::table('repurchase_salary_qualifications')->insertGetId([
                        'sponsor_id'        => $sid,
                        'period_month'      => $periodMarker,
                        'vip_no'            => $qualified->vip_no,
                        'salary_amount'     => $qualified->salary_amount,
                        'months_total'      => 3,
                        'months_paid'       => 0,
                        'first_payout_month'=> $firstPayMonth,
                        'status'            => 'active',
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    // installments (this month + next 2)
                    for ($i=0; $i<3; $i++) {
                        $due = Carbon::parse($firstPayMonth)
                            ->copy()->addMonthsNoOverflow($i)
                            ->startOfMonth()->toDateString();

                        DB::table('repurchase_salary_installments')->insert([
                            'qualification_id' => $qid,
                            'sponsor_id'       => $sid,
                            'due_month'        => $due,
                            'amount'           => number_format((float)$qualified->salary_amount, 2, '.', ''),
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }

                    $this->info("QUALIFIED → sponsor={$sid} VIP={$qualified->vip_no} salary={$qualified->salary_amount}");
                    $this->summary['qualified_created']++;
                } else {
                    // update existing qualification
                    DB::table('repurchase_salary_qualifications')
                        ->where('id', $existing->id)
                        ->update([
                            'vip_no'        => $qualified->vip_no,
                            'salary_amount' => $qualified->salary_amount,
                            'updated_at'    => now(),
                        ]);

                    DB::table('repurchase_salary_installments')
                        ->where('qualification_id', $existing->id)
                        ->whereNull('paid_at')
                        ->update([
                            'amount'     => number_format((float)$qualified->salary_amount, 2, '.', ''),
                            'updated_at' => now(),
                        ]);

                    $this->info("UPDATED → sponsor={$sid} VIP changed to {$qualified->vip_no}");
                    $this->summary['qualified_updated']++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("ERR sponsor={$sid}: " . $e->getMessage());
            }
        }

        // summary
        $this->line("=== SUMMARY ===");
        $this->line("Total candidates scanned: " . $this->summary['total_candidates']);
        $this->line("Qualified (found slab)    : " . $this->summary['qualified_count']);
        $this->line("  -> created new quals    : " . $this->summary['qualified_created']);
        $this->line("  -> updated existing     : " . $this->summary['qualified_updated']);

        return self::SUCCESS;
    }

    /**
     * Compute placement totals (sell + repurchase)
     */
    protected function computePlacementTotals(int $sponsorId, int $leftRoot, int $rightRoot, string $start, string $end): array
    {
        $placementCte = "
WITH RECURSIVE team AS (
  SELECT id, left_user_id, right_user_id, 'NA' AS root_leg
  FROM users WHERE id = ?

  UNION ALL

  SELECT u.id, u.left_user_id, u.right_user_id,
         CASE WHEN u.id = ? THEN 'L'
              WHEN u.id = ? THEN 'R'
              ELSE t.root_leg END AS root_leg
  FROM users u
  JOIN team t ON u.id IN (t.left_user_id, t.right_user_id)
)
";

        // sells
        $sellSql = $placementCte . "
SELECT
  SUM(CASE WHEN UPPER(COALESCE(t.root_leg,'NA'))='L' THEN COALESCE(s.amount,0) ELSE 0 END) AS sell_left,
  SUM(CASE WHEN UPPER(COALESCE(t.root_leg,'NA'))='R' THEN COALESCE(s.amount,0) ELSE 0 END) AS sell_right
FROM team t
LEFT JOIN sell s ON s.buyer_id = t.id AND s.status='paid' AND s.created_at BETWEEN ? AND ?
";
        $sellRow = DB::selectOne($sellSql, [$sponsorId, $leftRoot, $rightRoot, $start, $end]);

        // repurchases
        $repSql = $placementCte . "
SELECT
  SUM(CASE WHEN UPPER(COALESCE(t.root_leg,'NA'))='L' THEN COALESCE(r.amount,0) ELSE 0 END) AS rep_left,
  SUM(CASE WHEN UPPER(COALESCE(t.root_leg,'NA'))='R' THEN COALESCE(r.amount,0) ELSE 0 END) AS rep_right
FROM team t
LEFT JOIN repurchase r ON r.buyer_id = t.id AND r.status='paid' AND r.created_at BETWEEN ? AND ?
";
        $repRow = DB::selectOne($repSql, [$sponsorId, $leftRoot, $rightRoot, $start, $end]);

        return [
            'sell_left'  => (float)($sellRow->sell_left ?? 0),
            'sell_right' => (float)($sellRow->sell_right ?? 0),
            'rep_left'   => (float)($repRow->rep_left ?? 0),
            'rep_right'  => (float)($repRow->rep_right ?? 0),
        ];
    }
}
