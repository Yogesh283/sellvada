<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class P2PTransferController extends Controller
{
    /**
     * Show P2P transfer page (Inertia) and pass transactions (only actual table columns).
     */
    public function show(Request $request)
{
    $user = Auth::user();

    // compute balance directly from wallet
    $balance = (float) DB::table('wallet')
        ->where('user_id', $user->id)
        ->sum('amount');

    // Paginate transfers (10 per page). This returns a LengthAwarePaginator
    $p2p_transfers = DB::table('p2p_transfers as p')
        ->where(function($q) use ($user) {
            $q->where('p.from_user_id', $user->id)
              ->orWhere('p.to_user_id', $user->id);
        })
        ->select(
            'p.id',
            'p.from_user_id',
            'p.to_user_id',
            'p.amount',
            'p.remark',
            'p.wallet_debit_id',
            'p.wallet_credit_id',
            'p.created_at',
            'p.updated_at'
        )
        ->orderBy('p.created_at', 'desc')
        ->paginate(10); // <- 10 per page

    // return Inertia with paginator
    return Inertia::render('P2PTransfer', [
        'initialBalance' => $balance,
        'csrf' => csrf_token(),
        'transactions' => $p2p_transfers, // paginator object
        'debug' => [
            'user_id' => $user->id,
            'wallet_sum' => $balance,
            'source' => 'wallet.sum',
        ],
    ]);
}


    /**
     * Transfer action (existing transactional logic).
     */
    public function transfer(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'recipient' => 'required|string',
            'amount'    => 'required|numeric|min:0.01',
            'remark'    => 'nullable|string|max:255',
        ]);

        // find recipient by id / email / name (adjust as needed)
        $candidate = trim($data['recipient']);
        $recipient = null;
        if (ctype_digit($candidate)) {
            $recipient = DB::table('users')->where('id', (int)$candidate)->first();
        }
        if (! $recipient) {
            $recipient = DB::table('users')
                ->where('email', $candidate)
                ->orWhere('name', $candidate)
                ->first();
        }

        if (! $recipient) {
            return response()->json(['message' => 'Recipient not found.'], 404);
        }

        if ($recipient->id == $user->id) {
            return response()->json(['message' => 'You cannot transfer to yourself.'], 422);
        }

        $amount = round((float)$data['amount'], 2);
        if ($amount <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        try {
            $result = DB::transaction(function () use ($user, $recipient, $amount, $data) {
                $now = Carbon::now();

                // Lock sender's main wallet row
                $senderWallet = DB::table('wallet')
                    ->where('user_id', $user->id)
                    ->where('type', 'main')
                    ->lockForUpdate()
                    ->first();

                if (! $senderWallet) {
                    throw new \RuntimeException('sender_wallet_missing');
                }

                // Lock recipient's main wallet row (if missing, create)
                $recipientWallet = DB::table('wallet')
                    ->where('user_id', $recipient->id)
                    ->where('type', 'main')
                    ->lockForUpdate()
                    ->first();

                if (! $recipientWallet) {
                    $recipientWalletId = DB::table('wallet')->insertGetId([
                        'user_id' => $recipient->id,
                        'amount' => 0.00,
                        'type' => 'main',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $recipientWallet = DB::table('wallet')->where('id', $recipientWalletId)->lockForUpdate()->first();
                }

                $senderBalance = (float) $senderWallet->amount;

                if ($senderBalance < $amount) {
                    throw new \RuntimeException('insufficient_balance');
                }

                // Update sender wallet: subtract amount
                DB::table('wallet')
                    ->where('id', $senderWallet->id)
                    ->update([
                        'amount' => DB::raw("amount - " . (float)$amount),
                        'updated_at' => $now,
                    ]);

                // Update recipient wallet: add amount
                DB::table('wallet')
                    ->where('id', $recipientWallet->id)
                    ->update([
                        'amount' => DB::raw("amount + " . (float)$amount),
                        'updated_at' => $now,
                    ]);

                // Insert p2p transfer record linking to the existing wallet rows
                $p2pId = DB::table('p2p_transfers')->insertGetId([
                    'from_user_id' => $user->id,
                    'to_user_id' => $recipient->id,
                    'amount' => $amount,
                    'remark' => $data['remark'] ?? null,
                    'wallet_debit_id' => $senderWallet->id,
                    'wallet_credit_id' => $recipientWallet->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // fetch new sender wallet amount to return
                $newSenderWallet = DB::table('wallet')->where('id', $senderWallet->id)->first();
                $newBalance = (float) $newSenderWallet->amount;

                return [
                    'p2p_id' => $p2pId,
                    'balance' => $newBalance,
                ];
            }, 5);

        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            if ($msg === 'insufficient_balance') {
                return response()->json(['message' => 'Insufficient balance.'], 422);
            }
            if ($msg === 'sender_wallet_missing') {
                return response()->json(['message' => 'Sender wallet not found. Contact support.'], 500);
            }
            Log::error("P2P runtime error: " . $e->getMessage(), ['from' => $user->id, 'to' => $recipient->id ?? null, 'amount' => $amount]);
            return response()->json(['message' => 'Transfer failed.'], 500);
        } catch (\Exception $e) {
            Log::error("P2P transfer exception: " . $e->getMessage(), ['from' => $user->id, 'to' => $recipient->id ?? null, 'amount' => $amount]);
            return response()->json(['message' => 'Transfer failed. Please contact support.'], 500);
        }

        return response()->json([
            'message' => 'Transfer successful.',
            'p2p_id' => $result['p2p_id'],
            'balance' => $result['balance'],
            'source' => 'wallet.main',
        ], 200);
    }

    /**
     * Return balance JSON
     */
    public function balance()
    {
        $user = Auth::user();

        $balance = (float) DB::table('wallet')
            ->where('user_id', $user->id)
            ->sum('amount');

        return response()->json(['balance' => $balance], 200);
    }

    /**
     * Optional: JSON endpoint to return transactions (used by frontend refresh).
     * If you don't want this, simply do not add the route.
     */
    public function transactionsJson()
    {
        $user = Auth::user();

        $rows = DB::table('p2p_transfers')
            ->where('from_user_id', $user->id)
            ->orWhere('to_user_id', $user->id)
            ->select('id','from_user_id','to_user_id','amount','remark','wallet_debit_id','wallet_credit_id','created_at','updated_at')
            ->orderBy('created_at','desc')
            ->limit(50)
            ->get();

        return response()->json(['count' => $rows->count(), 'rows' => $rows]);
    }
}
