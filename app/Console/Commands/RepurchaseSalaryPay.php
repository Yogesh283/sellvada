<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryPay extends Command
{
    protected $signature = 'repurchase:pay
        {--date= : target date YYYY-MM-DD (typically Monday)}';

    protected $description = 'Pay due Repurchase Salary installments (weekly payout mode)';

    public function handle(): int
    {
        $dateStr = $this->option('date') ?: now()->toDateString();
        try {
            $payDate = Carbon::parse($dateStr)->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Invalid date. Use YYYY-MM-DD');
            return self::FAILURE;
        }

        $dueMarker = $payDate->toDateString();
        $this->line("Processing VIP Weekly Salary payouts due on: {$dueMarker}");
        $payoutType = 'VIP_salary_weekly';

        $dues = DB::table('repurchase_salary_installments as i')
            ->join('repurchase_salary_qualifications as q', 'q.id', '=', 'i.qualification_id')
            ->whereDate('i.due_month', $dueMarker)
            ->whereNull('i.paid_at')
            ->where('q.status', 'active')
            ->select('i.*', 'q.months_total', 'q.months_paid', 'q.id as qid')
            ->get();

        if ($dues->isEmpty()) {
            $this->line("No due installments for {$dueMarker}.");
            return self::SUCCESS;
        }

        foreach ($dues as $row) {
            DB::beginTransaction();
            try {
                // gross / net calculation
                $gross = (float)$row->amount;
                $net = round($gross * 0.80, 2); // 20% deduction
                $grossStr = number_format($gross, 2, '.', '');
                $netStr   = number_format($net, 2, '.', '');
                $deduction = round($gross - $net, 2);
                $deductionStr = number_format($deduction, 2, '.', '');

                // idempotency check: prevent duplicate payout
                $exists = DB::table('_payout')
                    ->where('to_user_id', $row->sponsor_id)
                    ->where('type', $payoutType)
                    ->whereDate('created_at', $dueMarker)
                    ->where('amount', $netStr)
                    ->exists();

                if ($exists) {
                    DB::table('repurchase_salary_installments')
                        ->where('id', $row->id)
                        ->update(['paid_at' => now(), 'updated_at' => now()]);
                    DB::table('repurchase_salary_qualifications')
                        ->where('id', $row->qid)
                        ->increment('months_paid');
                    DB::commit();
                    $this->line("SKIP(already) sponsor={$row->sponsor_id} gross={$gross} net={$netStr}");
                    continue;
                }

                // payout entry
                DB::table('_payout')->insert([
                    'user_id'     => $row->sponsor_id,
                    'to_user_id'  => $row->sponsor_id,
                    'from_user_id'=> 0, // system
                    'amount'      => $netStr,   // only net credited
                    'status'      => 'paid',
                    'method'      => $payoutType,
                    'type'        => $payoutType,
                    'remark'      => "Gross={$grossStr}, Deduction={$deductionStr}",
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                // wallet update with NET
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

                // mark installment paid
                DB::table('repurchase_salary_installments')
                    ->where('id', $row->id)
                    ->update(['paid_at' => now(), 'updated_at' => now()]);

                // increment months_paid
                DB::table('repurchase_salary_qualifications')
                    ->where('id', $row->qid)
                    ->increment('months_paid');

                // check qualification complete
                $after = DB::table('repurchase_salary_qualifications')
                    ->where('id', $row->qid)
                    ->first();
                if ($after && (int)$after->months_paid >= (int)$after->months_total) {
                    DB::table('repurchase_salary_qualifications')
                        ->where('id', $row->qid)
                        ->update(['status' => 'completed', 'updated_at' => now()]);
                }

                DB::commit();
                $this->info("PAID → sponsor={$row->sponsor_id} due={$dueMarker} GROSS={$grossStr} DEDUCT={$deductionStr} NET={$netStr}");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("ERR pay sponsor={$row->sponsor_id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
