
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletDepositAdminController extends Controller
{
    public function index(Request $r)
    {
        $q = DB::table('wallet_deposits')
            ->join('users','users.id','=','wallet_deposits.user_id')
            ->select('wallet_deposits.*','users.name as user_name','users.email');

        if ($r->filled('status')) {
            $q->where('wallet_deposits.status',$r->string('status'));
        }

        $rows = $q->orderByDesc('wallet_deposits.id')->paginate(20);
        return response()->json($rows); // abhi JSON; baad me UI laga denge
    }

    public function approve(int $id)
    {
        DB::transaction(function () use ($id) {
            $row = DB::table('wallet_deposits')->lockForUpdate()->find($id);
            if (!$row) abort(404);
            if ($row->status === 'approved') return;
            if ($row->status === 'rejected') abort(400, 'Already rejected');

            // 1) mark approved
            DB::table('wallet_deposits')->where('id',$id)->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'updated_at'  => now(),
            ]);

            // 2) credit wallet (type = main)
            $inc = number_format((float)$row->amount, 2, '.', '');

            $affected = DB::update(
                "UPDATE wallet SET amount = amount + ? WHERE user_id = ? AND type = 'main'",
                [$inc, $row->user_id]
            );

            if ($affected === 0) {
                DB::table('wallet')->insert([
                    'user_id'    => $row->user_id,
                    'amount'     => $inc,
                    'type'       => 'main',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3) optional: payout/ledger
            if (DB::getSchemaBuilder()->hasTable('_payout')) {
                DB::table('_payout')->insert([
                    'user_id'      => $row->user_id,
                    'to_user_id'   => $row->user_id,
                    'from_user_id' => null,
                    'amount'       => $inc,
                    'status'       => 'paid',
                    'method'       => 'wallet_deposit',
                    'type'         => 'wallet_deposit',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            if (DB::getSchemaBuilder()->hasTable('wallet_ledger')) {
                DB::table('wallet_ledger')->insert([
                    'user_id'    => $row->user_id,
                    'type'       => 'credit',
                    'source'     => 'deposit',
                    'amount'     => $inc,
                    'ref_id'     => $row->id,
                    'meta'       => json_encode([
                        'method'    => $row->method,
                        'reference' => $row->reference,
                        'note'      => $row->note,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Deposit approved & wallet credited.');
    }

    public function reject(int $id, Request $r)
    {
        DB::table('wallet_deposits')->where('id',$id)->update([
            'status'      => 'rejected',
            'rejected_at' => now(),
            'reject_note' => (string)$r->input('reason',''),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Deposit rejected.');
    }
}
