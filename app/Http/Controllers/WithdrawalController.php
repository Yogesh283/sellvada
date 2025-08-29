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
            'balance'  => $balance,
            'rows'     => $rows,
            'methods'  => ['UPI','BANK'],
            // optional: min/max rules for UI
            'minAmt'   => 200.00,
            'chargePct'=> 0.00,  // set % if you want fee
            'chargeFix'=> 0.00,  // set fixed fee if you want fee
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

        // conditional required
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

        $uid   = $r->user()->id;
        $amt   = (float)$data['amount'];
        $min   = 200.00; // your rule
        if ($amt < $min) {
            return back()->withErrors(['amount' => "Minimum withdrawal is ₹{$min}."])->withInput();
        }

        // check wallet balance (not debiting here; debit on approve)
        $balance = (float) (DB::table('wallet')->where('user_id',$uid)->where('type','main')->value('amount') ?? 0);
        if ($balance < $amt) {
            return back()->withErrors(['amount' => "Insufficient balance. Available ₹".number_format($balance,2)])->withInput();
        }

        // calculate fee if any (simple example: 0)
        $charge = 0.00;

        DB::table('withdrawals')->insert([
            'user_id'      => $uid,
            'amount'       => number_format($amt, 2, '.', ''),
            'charge'       => number_format($charge, 2, '.', ''),
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

        return back()->with('success', 'Withdrawal request submitted.');
    }
}
