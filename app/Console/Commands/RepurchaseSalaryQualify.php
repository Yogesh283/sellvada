<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryQualify extends Command
{
    protected $signature = 'repurchase:qualify
        {--mode=monthly : monthly|weekly}
        {--date= : For weekly mode: any date in target week (YYYY-MM-DD). For monthly mode you may pass YYYY-MM}
        {--require-self=0 : set 1 to require SELF repurchase in period (default: 0)} 
        {--dry : dry run (no DB writes)}';

    protected $description = 'Compute VIP qualification for Repurchase Salary. Modes: monthly (default) or weekly (Mon-Sun).';

    public function handle(): int
    {
        $mode = strtolower($this->option('mode') ?? 'monthly');
        $requireSelf = (int)$this->option('require-self') === 1;
        $dry = (bool)$this->option('dry');

        if ($mode === 'weekly') {
            return $this->handleWeekly($requireSelf, $dry);
        }

        // monthly
        $dateOpt = $this->option('date');
        $month = $dateOpt ? Carbon::parse($dateOpt.'-01')->startOfMonth() : now()->subMonthNoOverflow()->startOfMonth();
        $mStart = $month->copy()->startOfMonth();
        $mEnd   = $month->copy()->endOfMonth();

        $this->line("Processing MONTH: ".$mStart->format('Y-m-d')." → ".$mEnd->format('Y-m-d')." (monthly mode)  require-self=" . ($requireSelf ? '1' : '0'));

        $slabs = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabs->isEmpty()) {
            $this->warn('No slabs configured.');
            return self::SUCCESS;
        }

        $cands = DB::table('users')->whereNotNull('referral_id')->select('id','referral_id')->get();
        if ($cands->isEmpty()) {
            $this->line("No candidates found.");
            return self::SUCCESS;
        }

        foreach ($cands as $u) {
            $sid = (int)$u->id;
            $myReferral = (string)$u->referral_id;

            // optional: require self repurchase in window
            if ($requireSelf) {
                $hasSelfRepurchase = DB::table('repurchase')
                    ->where('buyer_id', $sid)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$mStart, $mEnd])
                    ->exists();
                if (!$hasSelfRepurchase) {
                    $this->line("SKIP sponsor={$sid} month={$mStart->format('Y-m')} — no SELF repurchase found (require-self=1).");
                    continue;
                }
            }

            // compute team repurchase volumes in the month (referral tree)
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
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amount
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid' AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
            $rows = DB::select($sql, [$myReferral, $mStart, $mEnd]);

            $volL = 0.0; $volR = 0.0;
            foreach ($rows as $r) {
                if ($r->leg === 'L') $volL += (float)$r->amount;
                if ($r->leg === 'R') $volR += (float)$r->amount;
            }
            $matched = min($volL, $volR);

            // pick highest slab satisfied
            $qualified = null;
            foreach ($slabs as $s) {
                if ($matched >= (float) $s->threshold_volume) $qualified = $s;
            }
            if (!$qualified) {
                $this->line("SKIP sponsor={$sid} matched={$matched} — no VIP slab.");
                continue;
            }

            $firstPayMonth = $mStart->copy()->addMonthNoOverflow()->startOfMonth();

            if ($dry) {
                $this->line(sprintf(
                    "DRY → sponsor=%d month=%s VIP=%s salary=%.2f matched=%.2f",
                    $sid, $mStart->format('Y-m'), $qualified->vip_no ?? '?', $qualified->salary_amount ?? 0, $matched
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

                $vip_no = $qualified->vip_no ?? null;
                $salary_amount = $qualified->salary_amount ?? 0;

                if (!$existing) {
                    $qid = DB::table('repurchase_salary_qualifications')->insertGetId([
                        'sponsor_id' => $sid,
                        'period_month' => $mStart->toDateString(),
                        'vip_no' => $vip_no,
                        'salary_amount' => $salary_amount,
                        'months_total' => 3,
                        'months_paid' => 0,
                        'first_payout_month' => $firstPayMonth->toDateString(),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    for ($i=0; $i<3; $i++) {
                        $due = $firstPayMonth->copy()->addMonthsNoOverflow($i)->startOfMonth();
                        DB::table('repurchase_salary_installments')->insert([
                            'qualification_id' => $qid,
                            'sponsor_id' => $sid,
                            'due_month' => $due->toDateString(),
                            'amount' => number_format((float)$salary_amount, 2, '.', ''),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $this->info("QUALIFIED → sponsor={$sid} month={$mStart->format('Y-m')} VIP={$vip_no} salary={$salary_amount} matched={$matched}");
                } else {
                    if ((int)($qualified->vip_no ?? 0) > (int)($existing->vip_no ?? 0)) {
                        DB::table('repurchase_salary_qualifications')->where('id', $existing->id)
                            ->update(['vip_no' => $vip_no, 'salary_amount' => $salary_amount, 'updated_at' => now() ]);

                        DB::table('repurchase_salary_installments')
                            ->where('qualification_id', $existing->id)
                            ->whereNull('paid_at')
                            ->update([ 'amount' => number_format((float)$salary_amount, 2, '.', ''), 'updated_at' => now() ]);

                        $this->info("UPGRADED → sponsor={$sid} month={$mStart->format('Y-m')} VIP={$vip_no} salary={$salary_amount}");
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

    protected function handleWeekly(bool $requireSelf = false, bool $dry = false) : int
    {
        $dateOpt = $this->option('date') ?: $this->option('date'); // support --date
        if ($dateOpt) {
            $any = Carbon::parse($dateOpt);
        } else {
            // default: previous calendar week
            $any = now()->subWeek()->startOfWeek();
        }

        $weekStart = $any->copy()->startOfWeek(); // Monday 00:00
        $weekEnd = $weekStart->copy()->endOfWeek(); // Sunday 23:59:59

        $this->line("Processing WEEK: {$weekStart->format('Y-m-d')} → {$weekEnd->format('Y-m-d')} (require-self=" . ($requireSelf ? '1' : '0') . ")");

        $slabs = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabs->isEmpty()) {
            $this->warn('No slabs configured.');
            return self::SUCCESS;
        }

        $cands = DB::table('users')->whereNotNull('referral_id')->select('id','referral_id')->get();
        if ($cands->isEmpty()) {
            $this->line("No candidates found.");
            return self::SUCCESS;
        }

        foreach ($cands as $u) {
            $sid = (int)$u->id;
            $myReferral = (string)$u->referral_id;

            if ($requireSelf) {
                $hasSelf = DB::table('repurchase')
                    ->where('buyer_id', $sid)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->exists();
                if (!$hasSelf) {
                    $this->line("SKIP sponsor={$sid} — no SELF repurchase in week (require-self=1).");
                    continue;
                }
            }

            $sql = "
WITH RECURSIVE team AS (
  SELECT id, referral_id, refer_by, position
  FROM users
  WHERE refer_by = ?

  UNION ALL

  SELECT u.id, u.referral_id, u.refer_by, u.position
  FROM users u
  JOIN team t ON u.refer_by = t.referral_id
)
SELECT UPPER(COALESCE(u.position,'NA')) AS leg, COALESCE(SUM(r.amount),0) AS amount
FROM team u
JOIN repurchase r ON r.buyer_id = u.id
WHERE r.status='paid'
  AND r.created_at BETWEEN ? AND ?
GROUP BY UPPER(COALESCE(u.position,'NA'));
";
            $rows = DB::select($sql, [$myReferral, $weekStart, $weekEnd]);

            $volL = 0.0; $volR = 0.0;
            foreach ($rows as $r) {
                if ($r->leg === 'L') $volL += (float)$r->amount;
                if ($r->leg === 'R') $volR += (float)$r->amount;
            }
            $matched = min($volL, $volR);

            $qualified = null;
            foreach ($slabs as $s) {
                if ($matched >= (float)$s->threshold_volume) $qualified = $s;
            }
            if (!$qualified) {
                $this->line("SKIP sponsor={$sid} matched={$matched} — no slab.");
                continue;
            }

            // first payout Monday = next Monday after this weekStart
            $firstPayMonday = $weekStart->copy()->addWeek()->startOfWeek();

            if ($dry) {
                $this->line("DRY → sponsor={$sid} week={$weekStart->format('Y-m-d')} VIP={$qualified->vip_no} salary={$qualified->salary_amount} matched={$matched}");
                continue;
            }

            DB::beginTransaction();
            try {
                $existing = DB::table('repurchase_salary_qualifications')
                    ->where('sponsor_id', $sid)
                    ->where('period_month', $weekStart->toDateString()) // use field as week marker
                    ->lockForUpdate()
                    ->first();

                $vip_no = $qualified->vip_no ?? null;
                $salary_amount = $qualified->salary_amount ?? 0;

                if (!$existing) {
                    $qid = DB::table('repurchase_salary_qualifications')->insertGetId([
                        'sponsor_id' => $sid,
                        'period_month' => $weekStart->toDateString(),
                        'vip_no' => $vip_no,
                        'salary_amount' => $salary_amount,
                        'months_total' => 3,
                        'months_paid' => 0,
                        'first_payout_month' => $firstPayMonday->toDateString(),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    for ($i=0;$i<3;$i++) {
                        $due = $firstPayMonday->copy()->addWeeks($i)->startOfWeek();
                        DB::table('repurchase_salary_installments')->insert([
                            'qualification_id' => $qid,
                            'sponsor_id' => $sid,
                            'due_month' => $due->toDateString(),
                            'amount' => number_format((float)$salary_amount, 2, '.', ''),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $this->info("QUALIFIED(weekly) → sponsor={$sid} week={$weekStart->format('Y-m-d')} VIP={$vip_no} salary={$salary_amount} matched={$matched}");
                } else {
                    if ((int)$vip_no > (int)($existing->vip_no ?? 0)) {
                        DB::table('repurchase_salary_qualifications')->where('id', $existing->id)
                            ->update(['vip_no' => $vip_no, 'salary_amount' => $salary_amount, 'updated_at' => now() ]);

                        DB::table('repurchase_salary_installments')
                            ->where('qualification_id', $existing->id)
                            ->whereNull('paid_at')
                            ->update([ 'amount' => number_format((float)$salary_amount, 2, '.', ''), 'updated_at' => now() ]);

                        $this->info("UPGRADED(weekly) → sponsor={$sid} week={$weekStart->format('Y-m-d')} VIP={$vip_no} salary={$salary_amount}");
                    } else {
                        $this->line("EXISTS(weekly) → sponsor={$sid} week={$weekStart->format('Y-m-d')} VIP={$existing->vip_no}");
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("ERR sponsor={$sid}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
