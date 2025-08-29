<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DepositController extends Controller
{
    /**
     * Show deposit form + history.
     */
    public function index(Request $request)
    {
        $userId  = Auth::id();

        $balance = (float) (DB::table('wallet')->where('user_id', $userId)->value('amount') ?? 0);

        $deposits = DB::table('deposit_requests')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function ($r) {
                $r->amount = (float) $r->amount;
                return $r;
            });

        return Inertia::render('Wallet/Deposit', [
            'balance'  => $balance,
            'deposits' => $deposits,
            'methods'  => ['UPI', 'BankTransfer', 'Cash'],
        ]);
    }

    /**
     * Create a new deposit request (PENDING).
     * (Admin approval will credit wallet.)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'amount'    => ['required','numeric','min:1'],
            'method'    => ['required','string','max:32'],
            'reference' => ['nullable','string','max:64'],
            'note'      => ['nullable','string','max:500'],
            'receipt'   => ['nullable','image','max:4096'], // up to ~4MB
        ]);

        $path = null;
        if ($request->hasFile('receipt')) {
            // requires: php artisan storage:link
            $path = $request->file('receipt')->store('receipts', 'public');
        }

        DB::table('deposit_requests')->insert([
            'user_id'      => $user->id,
            'amount'       => number_format((float)$data['amount'], 2, '.', ''),
            'method'       => $data['method'],
            'reference'    => $data['reference'] ?? null,
            'note'         => $data['note'] ?? null,
            'receipt_path' => $path,
            'status'       => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return redirect()->back()->with('success', 'Deposit request submitted. Waiting for approval.');
    }

    /**
     * (Optional) Admin approves a deposit and credits wallet.
     * Put this behind admin middleware/authorization before using.
     */
    public function approve(Request $request, int $id)
    {
        $adminId = Auth::id();

        DB::transaction(function () use ($id, $adminId) {
            $row = DB::table('deposit_requests')->lockForUpdate()->find($id);
            if (!$row || $row->status !== 'pending') {
                abort(404, 'Deposit not found or already processed.');
            }

            // 1) mark approved
            DB::table('deposit_requests')->where('id', $id)->update([
                'status'      => 'approved',
                'approved_by' => $adminId,
                'approved_at' => now(),
                'updated_at'  => now(),
            ]);

            // 2) credit wallet
            $inc = number_format((float)$row->amount, 2, '.', '');
            $affected = DB::update("UPDATE wallet SET amount = amount + ? WHERE user_id = ?", [$inc, $row->user_id]);
            if ($affected === 0) {
                DB::table('wallet')->insert([
                    'user_id'    => $row->user_id,
                    'amount'     => $inc,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3) add ledger record
            DB::table('_payout')->insert([
                'user_id'      => $row->user_id,   // legacy col, if used
                'to_user_id'   => $row->user_id,
                'from_user_id' => null,
                'amount'       => $inc,
                'status'       => 'paid',
                'method'       => $row->method,
                'type'         => 'deposit',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        return back()->with('success', 'Deposit approved & wallet credited.');
    }
}
