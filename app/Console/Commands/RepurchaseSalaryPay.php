<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RepurchaseSalaryPay extends Command
{
    protected $signature = 'repurchase:pay {--month=}';
    protected $description = 'Pay due Repurchase Salary installments for the given month (wallet + _payout)';

    public function handle(): int
    {
        $monthStr = $this->option('month');
        $month = $monthStr ? Carbon::parse($monthStr.'-01') : now()->startOfMonth();
        $mStart = $month->copy()->startOfMonth();
        $mEnd   = $month->copy()->endOfMonth();

        // due installments for this month and not paid
        $dues = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q', 'q.id', '=', 'i.qualification_id')
            ->whereDate('i.due_month', $mStart->toDateString())
            ->whereNull('i.paid_at')
            ->where('q.status', 'active')
            ->select('i.*', 'q.months_total', 'q.months_paid', 'q.id as qid')
            ->get();

        if ($dues->isEmpty()) {
            $this->line("No due installments for ".$mStart->format('Y-m').'.');
            return self::SUCCESS;
        }

        foreach ($dues as $row) {
            DB::beginTransaction();
            try {
                // guard: if already paid this month via _payout (idempotency)
                $exists = DB::table('_payout')
                    ->where('to_user_id', $row->sponsor_id)
                    ->where('type', 'repurchase_salary')
                    ->whereDate('created_at', '>=', $mStart->toDateString())
                    ->whereDate('created_at', '<=', $mEnd->toDateString())
                    ->exists();

                if ($exists) {
                    // still mark installment as paid to keep consistent if needed
                    DB::table('repurchase_salary_installments')
                        ->where('id', $row->id)
                        ->update(['paid_at' => now(), 'updated_at' => now()]);

                    DB::table('repurchase_salary_qualifications')
                        ->where('id', $row->qid)
                        ->increment('months_paid');

                    DB::commit();
                    $this->line("SKIP(already) sponsor={$row->sponsor_id} amount={$row->amount}");
                    continue;
                }

                // ===== 20% deduction rules =====
                $gross = (float) $row->amount;                  // 100% for ledger
                $net   = round($gross * 0.80, 2);               // 80% credited to wallet
                $grossStr = number_format($gross, 2, '.', '');
                $netStr   = number_format($net,   2, '.', '');
                // $deduct = number_format($gross - $net, 2, '.', ''); // (optional) if you log meta

                // 1) payout ledger (_payout) — keep GROSS for reporting
                DB::table('_payout')->insert([
                    'user_id'      => $row->sponsor_id,     // legacy
                    'to_user_id'   => $row->sponsor_id,
                    'from_user_id' => null,                 // system award
                    'amount'       => $grossStr,            // store full reward
                    'status'       => 'paid',
                    'method'       => 'repurchase_salary',
                    'type'         => 'repurchase_salary',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // 2) credit wallet with NET only (after 20% deduction)
                $affected = DB::update(
                    "UPDATE wallet SET amount = amount + ? WHERE user_id = ?",
                    [$netStr, $row->sponsor_id]
                );
                if ($affected === 0) {
                    DB::table('wallet')->insert([
                        'user_id'    => $row->sponsor_id,
                        'amount'     => $netStr,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }


                // 3) mark installment paid + bump qualification counters
                DB::table('repurchase_salary_installments')
                    ->where('id', $row->id)
                    ->update(['paid_at' => now(), 'updated_at' => now()]);

                DB::table('repurchase_salary_qualifications')
                    ->where('id', $row->qid)
                    ->increment('months_paid');

                // complete if all paid
                $after = DB::table('repurchase_salary_qualifications')->find($row->qid);
                if ($after && (int)$after->months_paid >= (int)$after->months_total) {
                    DB::table('repurchase_salary_qualifications')
                        ->where('id', $row->qid)
                        ->update(['status' => 'completed', 'updated_at' => now()]);
                }

                DB::commit();
                $this->info("PAID → sponsor={$row->sponsor_id} month={$mStart->format('Y-m')} GROSS={$grossStr} NET={$netStr}");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("ERR pay sponsor={$row->sponsor_id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
