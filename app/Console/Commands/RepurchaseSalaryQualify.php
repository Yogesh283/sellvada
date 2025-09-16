<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryQualify extends Command
{
    /**
     * signature:
     *  --date=YYYY-MM-DD   (any date inside target week; defaults to previous week)
     *  --require-self=0|1  (default 0 -> self repurchase NOT required)
     *  --dry               (dry run - no DB writes)
     */
    protected $signature = 'repurchase:qualify
        {--date= : any date inside target week YYYY-MM-DD (defaults to last week)}
        {--require-self=0 : require self repurchase (1) or not (0)}
        {--dry : dry run, do not write to DB}';

    protected $description = 'Weekly qualification for Repurchase Salary (placement-tree, counts sell + repurchase).';

    public function handle(): int
    {
        $dateStr = $this->option('date');
        $requireSelf = (int) $this->option('require-self') ? 1 : 0;
        $dry = (bool) $this->option('dry');

        // parse week date (default: previous week)
        try {
            $any = $dateStr ? Carbon::parse($dateStr) : now()->subWeek()->startOfWeek();
        } catch (\Throwable $e) {
            $this->error('Invalid date format. Use YYYY-MM-DD');
            return self::FAILURE;
        }

        // ensure Monday->Sunday week
        $weekStart = $any->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $this->line("Processing WEEK: {$weekStart->format('Y-m-d')} → {$weekEnd->format('Y-m-d')} (require-self={$requireSelf})");

        // load slabs
        $slabs = DB::table('repurchase_salary_slabs')->orderBy('threshold_volume','asc')->get();
        if ($slabs->isEmpty()) {
            $this->warn('No slabs configured in repurchase_salary_slabs table.');
            return self::SUCCESS;
        }

        // get candidates: users who have placement roots (left/right or at least are registered with referral_id)
        // We'll iterate users which may become sponsors. Use users with referral_id not null (same approach as earlier).
        $cands = DB::table('users')->whereNotNull('referral_id')->select('id','left_user_id','right_user_id')->get();
        if ($cands->isEmpty()) {
            $this->line("No candidate users found.");
            return self::SUCCESS;
        }

        // Prepare placement CTE (we will replace ? binds in DB::select)
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

        // For each candidate, compute placement-tree weekly sums (sell + repurchase)
        foreach ($cands as $u) {
            $sponsorId = (int) $u->id;

            // if sponsor has no placement children at all, it still might have placement roots NULL -> skip quickly
            $leftRoot  = (int) ($u->left_user_id ?? 0);
            $rightRoot = (int) ($u->right_user_id ?? 0);
            if ($leftRoot === 0 && $rightRoot === 0) {
                // no placement subtree, skip
                $this->line("SKIP sponsor={$sponsorId} — no placement roots.");
                continue;
            }

            // If require-self set, check if sponsor did self repurchase in week
            if ($requireSelf) {
                $hasSelf = DB::table('repurchase')
                    ->where('buyer_id', $sponsorId)
                    ->where('status','paid')
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->exists();
                if (!$hasSelf) {
                    $this->line("SKIP sponsor={$sponsorId} — no SELF repurchase in week.");
                    continue;
                }
            }

            // Query: for placement tree, compute sum(sell) and sum(repurchase) per leg
            $sql = $placementCte . "
SELECT UPPER(COALESCE(t.root_leg,'NA')) AS leg,
       COALESCE(SUM(s.amt),0) AS sell_amt,
       COALESCE(SUM(r.amt),0) AS rep_amt
FROM team t
LEFT JOIN (
  SELECT buyer_id, SUM(amount) AS amt FROM sell WHERE status='paid' AND created_at BETWEEN ? AND ? GROUP BY buyer_id
) s ON s.buyer_id = t.id
LEFT JOIN (
  SELECT buyer_id, SUM(amount) AS amt FROM repurchase WHERE status='paid' AND created_at BETWEEN ? AND ? GROUP BY buyer_id
) r ON r.buyer_id = t.id
GROUP BY UPPER(COALESCE(t.root_leg,'NA'))
";
            // binds: sponsorId, leftRoot, rightRoot, weekStart, weekEnd, weekStart, weekEnd
            $binds = [$sponsorId, $leftRoot, $rightRoot, $weekStart->toDateTimeString(), $weekEnd->toDateTimeString(), $weekStart->toDateTimeString(), $weekEnd->toDateTimeString()];
            $rows = DB::select($sql, $binds);

            $volL = 0.0; $volR = 0.0;
            foreach ($rows as $r) {
                $sum = (float) ($r->sell_amt ?? 0) + (float) ($r->rep_amt ?? 0);
                if ($r->leg === 'L') $volL += $sum;
                if ($r->leg === 'R') $volR += $sum;
            }

            $matched = min($volL, $volR);

            // find highest slab qualified
            $qualified = null;
            foreach ($slabs as $s) {
                if ($matched >= (float) $s->threshold_volume) $qualified = $s;
            }

            if (!$qualified) {
                $this->line("SKIP sponsor={$sponsorId} matched={$matched} — no slab.");
                continue;
            }

            // compute first payout Monday (next Monday after this week)
            $firstPayMonday = $weekStart->copy()->addWeek()->startOfWeek(Carbon::MONDAY)->startOfDay();

            if ($dry) {
                $this->line("DRY → sponsor={$sponsorId} week={$weekStart->format('Y-m-d')} VIP={$qualified->vip_no} salary={$qualified->salary_amount} matched={$matched}");
                continue;
            }

            // write qualification & 3 weekly installments
            DB::beginTransaction();
            try {
                $existing = DB::table('repurchase_salary_qualifications')
                    ->where('sponsor_id', $sponsorId)
                    ->where('period_month', $weekStart->toDateString())
                    ->lockForUpdate()
                    ->first();

                if (!$existing) {
                    $qid = DB::table('repurchase_salary_qualifications')->insertGetId([
                        'sponsor_id' => $sponsorId,
                        'period_month' => $weekStart->toDateString(),
                        'vip_no' => $qualified->vip_no,
                        'salary_amount' => $qualified->salary_amount,
                        'months_total' => 3,
                        'months_paid' => 0,
                        'first_payout_month' => $firstPayMonday->toDateString(),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    for ($i=0; $i<3; $i++) {
                        $due = $firstPayMonday->copy()->addWeeks($i)->startOfWeek(Carbon::MONDAY)->startOfDay();
                        DB::table('repurchase_salary_installments')->insert([
                            'qualification_id' => $qid,
                            'sponsor_id' => $sponsorId,
                            'due_month' => $due->toDateString(),
                            'amount' => number_format((float)$qualified->salary_amount, 2, '.', ''),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $this->info("QUALIFIED(weekly) → sponsor={$sponsorId} week={$weekStart->format('Y-m-d')} VIP={$qualified->vip_no} salary={$qualified->salary_amount} matched={$matched}");
                } else {
                    // upgrade logic (if new VIP higher than existing)
                    if ((int)$qualified->vip_no > (int)$existing->vip_no) {
                        DB::table('repurchase_salary_qualifications')->where('id', $existing->id)->update([
                            'vip_no' => $qualified->vip_no,
                            'salary_amount' => $qualified->salary_amount,
                            'updated_at' => now(),
                        ]);
                        DB::table('repurchase_salary_installments')->where('qualification_id', $existing->id)->whereNull('paid_at')->update([
                            'amount' => number_format((float)$qualified->salary_amount, 2, '.', ''),
                            'updated_at' => now()
                        ]);
                        $this->info("UPGRADED(weekly) → sponsor={$sponsorId} week={$weekStart->format('Y-m-d')} VIP={$qualified->vip_no}");
                    } else {
                        $this->line("EXISTS(weekly) → sponsor={$sponsorId} week={$weekStart->format('Y-m-d')} VIP={$existing->vip_no}");
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("ERR sponsor={$sponsorId}: ".$e->getMessage());
            }
        } // end foreach candidates

        return self::SUCCESS;
    }
}
