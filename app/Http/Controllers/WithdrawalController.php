<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WithdrawalController extends Controller
{
    public function index(Request $r)
    {
        $uid = $r->user()->id;

        // wallet balance (type = main)
        $balance = (float) (DB::table('wallet')
            ->where('user_id', $uid)
            ->where('type', 'main')
            ->value('amount') ?? 0);

        // recent requests by user
        $rows = DB::table('withdrawals')
            ->where('user_id', $uid)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return Inertia::render('Wallet/Withdraw', [
            'balance'   => $balance,
            'rows'      => $rows,
            'methods'   => ['UPI', 'BANK'],
            'minAmt'    => 200.00,
            'chargePct' => 0.00,
            'chargeFix' => 0.00,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'amount'       => ['required','numeric','min:1'],
            'method'       => ['required','in:UPI,BANK'],
            'upi_id'       => ['nullable','string','max:100'],
            'account_name' => ['nullable','string','max:100'],
            'bank_name'    => ['nullable','string','max:100'],
            'account_no'   => ['nullable','string','max:64'],
            'ifsc'         => ['nullable','string','max:32'],
        ]);

        // conditional fields
        if ($data['method'] === 'UPI') {
            $r->validate(['upi_id' => ['required','string','max:100']]);
        } else {
            $r->validate([
                'account_name' => ['required','string','max:100'],
                'bank_name'    => ['required','string','max:100'],
                'account_no'   => ['required','string','max:64'],
                'ifsc'         => ['required','string','max:32'],
            ]);
        }

        $uid = $r->user()->id;
        $amt = round((float)$data['amount'], 2);
        $min = 200.00;

        if ($amt < $min) {
            return back()->withErrors(['amount' => "Minimum withdrawal is ₹{$min}."])->withInput();
        }

        DB::beginTransaction();
        try {
            // Atomically decrement wallet
            $updated = DB::table('wallet')
                ->where('user_id', $uid)
                ->where('type', 'main')
                ->where('amount', '>=', $amt)
                ->decrement('amount', $amt);

            if (!$updated) {
                DB::rollBack();
                $balance = (float) (DB::table('wallet')->where('user_id',$uid)->where('type','main')->value('amount') ?? 0);
                return back()->withErrors([
                    'amount' => "Insufficient balance. Available ₹".number_format($balance,2)
                ])->withInput();
            }

            $charge = 0.00;
            $net = round($amt - $charge, 2);

            // insert withdrawal
            $withdrawId = DB::table('withdrawals')->insertGetId([
                'user_id'      => $uid,
                'amount'       => number_format($amt, 2, '.', ''),
                'charge'       => number_format($charge, 2, '.', ''),
                'net_amount'   => number_format($net, 2, '.', ''),
                'method'       => $data['method'],
                'upi_id'       => $data['method']==='UPI' ? $data['upi_id'] : null,
                'account_name' => $data['method']==='BANK' ? $data['account_name'] : null,
                'bank_name'    => $data['method']==='BANK' ? $data['bank_name'] : null,
                'account_no'   => $data['method']==='BANK' ? $data['account_no'] : null,
                'ifsc'         => $data['method']==='BANK' ? $data['ifsc'] : null,
                'status'       => 'pending',
                'requested_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // insert wallet transaction (audit)
            DB::table('wallet_transactions')->insert([
                'user_id'      => $uid,
                'type'         => 'debit',
                'amount'       => number_format($amt, 2, '.', ''),
                'balance_after'=> DB::table('wallet')->where('user_id', $uid)->where('type', 'main')->value('amount'),
                'remark'       => "Withdrawal request #{$withdrawId}",
                'related_id'   => $withdrawId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::commit();
            return back()->with('success', 'Withdrawal request submitted and amount reserved from wallet.');

        }catch (\Exception $ex) {
    DB::rollBack();
    \Log::error('Withdraw store error: '.$ex->getMessage(), [
        'user_id' => $uid,
        'data'    => $data,
        'trace'   => $ex->getTraceAsString(),
    ]);

    if (config('app.debug')) {
        // return the actual exception message to help debugging locally
        return back()->withErrors(['general' => $ex->getMessage() . ' on line ' . $ex->getLine()]);
    }

    return back()->withErrors(['general' => 'An error occurred, please try again.'])->withInput();
}

    }
}
