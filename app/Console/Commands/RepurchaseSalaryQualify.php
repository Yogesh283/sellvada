<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryQualify extends Command
{
    protected $signature = 'repurchase:qualify {--month=} {--dry}';
    protected $description = 'Compute VIP qualification for Repurchase Salary per month (TEAM volumes from repurchase) and create 3 installments';

    public function handle(): int
    {
        // Target month = previous month by default
        $monthStr = $this->option('month');
        $month    = $monthStr ? Carbon::parse($monthStr.'-01') : now()->subMonthNoOverflow()->startOfMonth();
        $mStart   = $month->copy()->startOfMonth();
        $mEnd     = $month->copy()->endOfMonth();

        // Slabs config table (ASC by threshold)
        $slabs = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabs->isEmpty()) {
            $this->warn('No slabs configured.');
            return self::SUCCESS;
        }

        // Candidates = users who have a referral_id (tree root)
        $cands = DB::table('users')
            ->whereNotNull('referral_id')
            ->select('id','referral_id')
            ->get();

        if ($cands->isEmpty()) {
            $this->line("No candidates found.");
            return self::SUCCESS;
        }

        foreach ($cands as $u) {
            $sid        = (int) $u->id;
            $myReferral = (string) $u->referral_id;

            /* -------------------- SELF QUALIFICATION CHECK --------------------
             * Requirement: sponsor (login user) must have at least ONE self
             * repurchase (status='paid') in the SAME month (mStart..mEnd).
             * If not, skip qualification even if team matched volume exists.
             * ----------------------------------------------------------------- */
            $hasSelfRepurchase = DB::table('repurchase')
                ->where('buyer_id', $sid)
                ->where('status', 'paid')
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->exists();

            if (!$hasSelfRepurchase) {
                $this->line(sprintf(
                    "SKIP sponsor=%d month=%s — no SELF repurchase found.",
                    $sid, $mStart->format('Y-m')
                ));
                continue;
            }

            // Team L/R volume from `repurchase` in this month
            $sql = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position, 1 AS lvl
  FROM users
  WHERE refer_by = ?

  UNION ALL
  SELECT u.id, u.referral_id, u.refer_by, u.position, t.lvl + 1
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
SELECT
  UPPER(COALESCE(u.position,'NA')) AS leg,
  SUM(r.amount)                     AS amount
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
            $rows = DB::select($sql, [$myReferral, $mStart, $mEnd]);

            $volL = 0.0; $volR = 0.0;
            foreach ($rows as $r) {
                if ($r->leg === 'L') $volL += (float) $r->amount;
                if ($r->leg === 'R') $volR += (float) $r->amount;
            }
            $matched = min($volL, $volR);

            // Highest slab satisfied
            $qualified = null;
            foreach ($slabs as $s) {
                if ($matched >= (float) $s->threshold_volume) {
                    $qualified = $s; // keep highest
                }
            }
            if (!$qualified) {
                $this->line("SKIP sponsor={$sid} matched={$matched} — no VIP slab.");
                continue;
            }

            // First payout month = next month
            $firstPayMonth = $mStart->copy()->addMonthNoOverflow()->startOfMonth();

            if ($this->option('dry')) {
                $this->line(sprintf(
                    "DRY → sponsor=%d month=%s VIP=%d salary=%.2f matched=%.2f",
                    $sid, $mStart->format('Y-m'), $qualified->vip_no, $qualified->salary_amount, $matched
                ));
                continue;
            }

            DB::beginTransaction();
            try {
                $existing = DB::table('repurchase_salary_qualifications')
                    ->where('sponsor_id', $sid)
                    ->where('period_month', $mStart->toDateString())
                    ->lockForUpdate()
                    ->first();

                if (!$existing) {
                    $qid = DB::table('repurchase_salary_qualifications')->insertGetId([
                        'sponsor_id'         => $sid,
                        'period_month'       => $mStart->toDateString(),
                        'vip_no'             => $qualified->vip_no,
                        'salary_amount'      => $qualified->salary_amount,
                        'months_total'       => 3,
                        'months_paid'        => 0,
                        'first_payout_month' => $firstPayMonth->toDateString(),
                        'status'             => 'active',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    // Create 3 monthly installments (next 3 months)
                    for ($i=0; $i<3; $i++) {
                        $due = $firstPayMonth->copy()->addMonthsNoOverflow($i)->startOfMonth();
                        DB::table('repurchase_salary_installments')->insert([
                            'qualification_id' => $qid,
                            'sponsor_id'       => $sid,
                            'due_month'        => $due->toDateString(),
                            'amount'           => number_format((float)$qualified->salary_amount, 2, '.', ''),
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }

                    $this->info("QUALIFIED → sponsor={$sid} month={$mStart->format('Y-m')} VIP={$qualified->vip_no}");
                } else {
                    // Upgrade if higher VIP now
                    if ((int)$qualified->vip_no > (int)$existing->vip_no) {
                        DB::table('repurchase_salary_qualifications')
                            ->where('id', $existing->id)
                            ->update([
                                'vip_no'        => $qualified->vip_no,
                                'salary_amount' => $qualified->salary_amount,
                                'updated_at'    => now(),
                            ]);

                        // Update future unpaid installments to new amount
                        DB::table('repurchase_salary_installments')
                            ->where('qualification_id', $existing->id)
                            ->whereNull('paid_at')
                            ->update([
                                'amount'     => number_format((float)$qualified->salary_amount, 2, '.', ''),
                                'updated_at' => now(),
                            ]);

                        $this->info("UPGRADED → sponsor={$sid} month={$mStart->format('Y-m')} VIP={$qualified->vip_no}");
                    } else {
                        $this->line("EXISTS → sponsor={$sid} month={$mStart->format('Y-m')} VIP={$existing->vip_no}");
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("ERR sponsor={$sid}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
