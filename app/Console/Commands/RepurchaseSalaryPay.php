<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RepurchaseSalaryPay extends Command
{
    protected $signature = 'repurchase:pay
        {--period=monthly : period to pay (monthly|weekly)}
        {--month= : target month YYYY-MM (for monthly)}
        {--date= : target date YYYY-MM-DD (for weekly payments; typically Monday)}
        ';
    protected $description = 'Pay due Repurchase Salary installments for the given period (monthly or weekly)';

    public function handle(): int
    {
        $period = strtolower($this->option('period') ?? 'monthly');
        if (!in_array($period, ['monthly','weekly'])) $period = 'monthly';

        if ($period === 'monthly') {
            $monthStr = $this->option('month');
            $month = $monthStr ? Carbon::parse($monthStr.'-01') : now()->startOfMonth();
            $mStart = $month->copy()->startOfMonth();
            $mEnd   = $month->copy()->endOfMonth();
            $this->line("Processing monthly payouts for: ".$mStart->format('Y-m'));
            // find due installments where due_month = month start
            $dueMarker = $mStart->toDateString(); // YYYY-MM-01
            $payoutType = 'repurchase_salary';
        } else {
            // weekly
            $dateStr = $this->option('date');
            $payDate = $dateStr ? Carbon::parse($dateStr)->startOfDay() : now()->startOfDay();
            $dueMarker = $payDate->toDateString(); // Monday date like YYYY-MM-DD
            $this->line("Processing weekly payouts due on: {$dueMarker}");
            $payoutType = 'repurchase_salary_weekly';
        }

        // pull installments due for this marker and not paid
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
                // idempotency: if _payout already created for this user with same type & date & amount, skip
                $exists = DB::table('_payout')
                    ->where('to_user_id', $row->sponsor_id)
                    ->where('type', $payoutType)
                    ->whereDate('created_at', $dueMarker)
                    ->where('amount', $row->amount)
                    ->exists();

                if ($exists) {
                    // ensure installment marked paid and qualification incremented
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

                // Use the installment.amount (which may have been updated by an earlier upgrade)
                $gross = (float) $row->amount;
                $net   = round($gross * 0.80, 2);
                $grossStr = number_format($gross, 2, '.', '');
                $netStr   = number_format($net,   2, '.', '');

                // Insert payout ledger (GROSS)
                DB::table('_payout')->insert([
                    'user_id'      => $row->sponsor_id,
                    'to_user_id'   => $row->sponsor_id,
                    'from_user_id' => null,
                    'amount'       => $grossStr,
                    'status'       => 'paid',
                    'method'       => $payoutType,
                    'type'         => $payoutType,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // Credit wallet with NET
                $affected = DB::update("UPDATE wallet SET amount = amount + ? WHERE user_id = ?", [$netStr, $row->sponsor_id]);
                if ($affected === 0) {
                    DB::table('wallet')->insert([
                        'user_id'    => $row->sponsor_id,
                        'amount'     => $netStr,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Mark installment paid + bump qualification months_paid
                DB::table('repurchase_salary_installments')
                    ->where('id', $row->id)
                    ->update(['paid_at' => now(), 'updated_at' => now()]);

                DB::table('repurchase_salary_qualifications')
                    ->where('id', $row->qid)
                    ->increment('months_paid');

                // If all paid -> mark qualification completed
                $after = DB::table('repurchase_salary_qualifications')->find($row->qid);
                if ($after && (int)$after->months_paid >= (int)$after->months_total) {
                    DB::table('repurchase_salary_qualifications')
                        ->where('id', $row->qid)
                        ->update(['status' => 'completed', 'updated_at' => now()]);
                }

                DB::commit();
                $this->info("PAID → sponsor={$row->sponsor_id} due={$dueMarker} GROSS={$grossStr} NET={$netStr}");
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("ERR pay sponsor={$row->sponsor_id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
