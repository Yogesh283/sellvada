<?php

namespace App\Http\Controllers;

use App\Models\Sell;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SellController extends Controller
{
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'items'             => ['required','array','min:1'],
            'items.*.id'        => ['required'],
            'items.*.name'      => ['required','string','max:100'],
            'items.*.price'     => ['required','numeric','min:0'],
            'items.*.qty'       => ['required','integer','min:1'],
            'items.*.variant'   => ['nullable','string','max:100'],
            'items.*.type'      => ['nullable','string','max:32'],
            'coupon'            => ['nullable','string','max:32'],
            'shipping'          => ['nullable','numeric','min:0'],
        ]);

        $user = Auth::user();
        if (!$user) abort(401);

        $sponsorId = $this->resolveSponsorId($user);
        $leg = $user->position ?: null;

        $subTotal = 0;
        foreach ($data['items'] as $it) {
            $subTotal += (float)$it['price'] * (int)$it['qty'];
        }

        $coupon = strtoupper(trim((string)($data['coupon'] ?? '')));
        $discount = $coupon === 'FLAT10' ? round($subTotal * 0.10, 2) : 0.0;

        $taxable  = max(0, $subTotal - $discount);
        $tax      = round($taxable * 0.05, 2);
        $shipping = $request->filled('shipping') ? (float)$data['shipping'] : 49.0;

        $DIRECT_PCT = 0.0;

        DB::transaction(function () use ($data, $user, $sponsorId, $leg, $DIRECT_PCT, $coupon, $tax, $shipping) {
            foreach ($data['items'] as $it) {
                $type = null;
                if (!empty($it['type'])) {
                    $type = strtolower(trim($it['type']));
                } elseif (preg_match('/\(([^)]+)\)\s*$/', (string)$it['name'], $m)) {
                    $type = strtolower(trim($m[1]));
                }

                $lineAmount = round((float)$it['price'] * (int)$it['qty'], 2);
                $income     = $sponsorId ? round($lineAmount * $DIRECT_PCT, 2) : 0.0;

                Sell::create([
                    'buyer_id'          => $user->id,
                    'sponsor_id'        => $sponsorId,
                    'income_to_user_id' => $sponsorId,
                    'leg'               => $leg,
                    'product'           => $it['name'],
                    'amount'            => $lineAmount,
                    'income'            => $income,
                    'income_type'       => 'DIRECT',
                    'level'             => null,
                    'order_no'          => 'ORD-'.Str::upper(Str::random(8)),
                    'status'            => 'paid',
                    'type'              => $type,
                    'details'           => [
                        'qty'      => (int)$it['qty'],
                        'price'    => (float)$it['price'],
                        'variant'  => $it['variant'] ?? null,
                        'coupon'   => $coupon ?: null,
                        'tax'      => $tax,
                        'shipping' => $shipping,
                        'type'     => $type,
                    ],
                ]);
            }
        });

        return back()->with('success', 'Order placed successfully!');
    }


     
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $perPage = $request->integer('per_page') ?: 15;

        $orders = \App\Models\Sell::query()
            ->where('buyer_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        // lightweight transform (optional)
        $orders->getCollection()->transform(function ($o) {
            return [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'product' => $o->product,
                'type' => $o->type,
                'amount' => (float) $o->amount,
                'status' => $o->status,
                'created_at' => $o->created_at ? $o->created_at->toDateTimeString() : null,
            ];
        });

        return Inertia::render('Shop/MyOrders', [
            'orders' => $orders,
        ]);
    }

    // ... other methods ...
}


    function resolveSponsorId(User $user): ?int
    {
        if ($user->sponsor_id) return (int) $user->sponsor_id;

        $ref = trim((string)($user->refer_by ?? ''));
        if ($ref !== '') {
            $s = User::query()
                ->whereRaw('LOWER(referral_id) = ?', [strtolower($ref)])
                ->orWhereRaw('LOWER(referral_code) = ?', [strtolower($ref)])
                ->orWhere('email', $ref)
                ->when(ctype_digit($ref), fn($q) => $q->orWhere('id', (int)$ref))
                ->first();

            if ($s) {
                $user->sponsor_id = $s->id; // backfill
                $user->save();
                return (int) $s->id;
            }
        }
        return null;
    }

