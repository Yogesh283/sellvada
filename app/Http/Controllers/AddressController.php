<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AddressController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $addresses = Address::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Address/Index', [
            'addresses' => $addresses,
            'countries' => ['India'], // dropdown ke liye
        ]);
    }

    public function store(Request $r)
    {
        $user = Auth::user();

        $data = $r->validate([
            'name'      => ['required','string','max:100'],
            'phone'     => ['required','string','max:20'],
            'line1'     => ['required','string','max:255'],
            'line2'     => ['nullable','string','max:255'],
            'city'      => ['required','string','max:100'],
            'state'     => ['required','string','max:100'],
            'pincode'   => ['required','string','max:20'],
            'country'   => ['required','string','max:100'],
            'is_default'=> ['sometimes','boolean'],
        ]);

        // make default? pehle dusron ka default unset
        if (!empty($data['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $data['user_id'] = $user->id;
        $data['is_default'] = !empty($data['is_default']);

        Address::create($data);

        return back()->with('success', 'Address added.');
    }

    public function update(Request $r, Address $address)
    {
        $user = Auth::user();
        abort_unless($address->user_id === $user->id, 403);

        $data = $r->validate([
            'name'      => ['required','string','max:100'],
            'phone'     => ['required','string','max:20'],
            'line1'     => ['required','string','max:255'],
            'line2'     => ['nullable','string','max:255'],
            'city'      => ['required','string','max:100'],
            'state'     => ['required','string','max:100'],
            'pincode'   => ['required','string','max:20'],
            'country'   => ['required','string','max:100'],
            'is_default'=> ['sometimes','boolean'],
        ]);

        if (!empty($data['is_default'])) {
            Address::where('user_id', $user->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update([
            ...$data,
            'is_default' => !empty($data['is_default']),
        ]);

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Address $address)
    {
        $user = Auth::user();
        abort_unless($address->user_id === $user->id, 403);

        $wasDefault = $address->is_default;
        $address->delete();

        // agar default delete ho gaya, kisi ek ko default bana do (optional)
        if ($wasDefault) {
            $next = Address::where('user_id', $user->id)->latest()->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return back()->with('success', 'Address deleted.');
    }

    public function makeDefault(Address $address)
    {
        $user = Auth::user();
        abort_unless($address->user_id === $user->id, 403);

        Address::where('user_id', $user->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address set.');
    }
}
