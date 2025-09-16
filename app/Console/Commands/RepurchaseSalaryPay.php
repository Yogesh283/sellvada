<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryPay extends Command
{
    protected $signature = 'repurchase:pay
        {--period=monthly : monthly or weekly}
        {--month= : target month YYYY-MM (for monthly)}
        {--date= : target date YYYY-MM-DD (for weekly payments; typically Monday)}';

    protected $description = 'Pay due Repurchase Salary installments for the given period (monthly or weekly)';

    public function handle(): int
    {
        $period = strtolower($this->option('period') ?? 'monthly');
        if (!in_array($period, ['monthly','weekly'])) $period = 'monthly';

        if ($period === 'monthly') {
            $monthStr = $this->option('month') ?: now()->format('Y-m');
            try {
                $month = Carbon::parse($monthStr . '-01');
            } catch (\Throwable $e) {
                $this->error('Invalid month. Use YYYY-MM');
                return self::FAILURE;
            }
            $dueMarker = $month->copy()->startOfMonth()->toDateString();
            $this->line("Processing monthly payouts for: " . $month->format('Y-m'));
            $payoutType = 'VIP_salary_weekly';
        } else {
            $dateStr = $this->option('date') ?: now()->toDateString();
            try {
                $payDate = Carbon::parse($dateStr)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Invalid date. Use YYYY-MM-DD');
                return self::FAILURE;
            }
            $dueMarker = $payDate->toDateString();
            $this->line("Processing weekly payouts due on: {$dueMarker}");
            $payoutType = 'VIP_salary_weekly';
        }

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
                // idempotency check: same sponsor, same type, same date, same net amount
                // We'll compute net first to compare
                $gross = (float)$row->amount;
                $net = round($gross * 0.80, 2); // 20% deduction
                $netStr = number_format($net, 2, '.', '');

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

                $grossStr = number_format($gross, 2, '.', '');
                $deduction = round($gross - $net, 2);
                $deductionStr = number_format($deduction, 2, '.', '');

                // payout record: store NET as amount so payout reports match wallet credit.
                // remark stores gross & deduction for audit.
                DB::table('_payout')->insert([
                    'user_id'     => $row->sponsor_id,
                    'to_user_id'  => $row->sponsor_id,
                    'from_user_id'=> 0, // system
                    'amount'      => $netStr,
                    'status'      => 'paid',
                    'method'      => $payoutType,
                    'type'        => $payoutType,
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

                // qualification complete check
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
