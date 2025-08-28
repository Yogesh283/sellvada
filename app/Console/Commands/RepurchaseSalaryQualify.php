<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryQualify extends Command
{
    protected $signature = 'repurchase:qualify {--month=} {--dry}';
    protected $description = 'Compute VIP qualification for Repurchase Salary per month and create 3 installments';

    // sirf repurchase business count
    private array $repurchaseTypes = ['repurchase'];

    public function handle(): int
    {
        // target month = previous month by default
        $monthStr = $this->option('month');
        $month = $monthStr ? Carbon::parse($monthStr.'-01') : now()->subMonthNoOverflow()->startOfMonth();
        $mStart = $month->copy()->startOfMonth();
        $mEnd   = $month->copy()->endOfMonth();

        // load slabs ASC by threshold
        $slabs = DB::table('repurchase_salary_slabs')
            ->orderBy('threshold_volume', 'asc')->get();

        if ($slabs->isEmpty()) {
            $this->warn('No slabs configured.');
            return self::SUCCESS;
        }

        // all sponsors who have PAID repurchase sales in this month
        $sponsorIds = DB::table('sell')
            ->where('status', 'paid')
            ->whereIn('type', $this->repurchaseTypes)
            ->whereBetween('created_at', [$mStart, $mEnd])
            ->whereNotNull('sponsor_id')
            ->pluck('sponsor_id')
            ->map(fn($v)=>(int)$v)->filter()->unique()->values();

        if ($sponsorIds->isEmpty()) {
            $this->line("No sponsors with repurchase in {$mStart->format('Y-m')}.");
            return self::SUCCESS;
        }

        foreach ($sponsorIds as $sid) {
            // sum L/R for the month
            $rows = DB::table('sell')
                ->select('leg', 'amount')
                ->where('status','paid')
                ->whereIn('type', $this->repurchaseTypes)
                ->where('sponsor_id', $sid)
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->get();

            $volL=0.0; $volR=0.0;
            foreach ($rows as $r) {
                $leg = strtoupper((string)($r->leg ?? ''));
                $amt = (float)$r->amount;
                if ($leg === 'L') $volL += $amt;
                elseif ($leg === 'R') $volR += $amt;
            }
            $matched = min($volL, $volR);

            // highest slab satisfied
            $qualified = null;
            foreach ($slabs as $s) {
                if ($matched >= (float)$s->threshold_volume) {
                    $qualified = $s; // keep going to get highest
                }
            }
            if (!$qualified) {
                $this->line("SKIP sponsor={$sid} matched={$matched} — no VIP slab.");
                continue;
            }

            // upsert qualification
            $firstPayMonth = $mStart->copy()->addMonthNoOverflow()->startOfMonth(); // pay from next month
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
                        'sponsor_id'        => $sid,
                        'period_month'      => $mStart->toDateString(),
                        'vip_no'            => $qualified->vip_no,
                        'salary_amount'     => $qualified->salary_amount,
                        'months_total'      => 3,
                        'months_paid'       => 0,
                        'first_payout_month'=> $firstPayMonth->toDateString(),
                        'status'            => 'active',
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    // create 3 installments: next 3 months
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
                    // upgrade if higher VIP now
                    if ((int)$qualified->vip_no > (int)$existing->vip_no) {
                        DB::table('repurchase_salary_qualifications')
                            ->where('id', $existing->id)
                            ->update([
                                'vip_no'        => $qualified->vip_no,
                                'salary_amount' => $qualified->salary_amount,
                                'updated_at'    => now(),
                            ]);
                        // update future unpaid installments to new amount
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
