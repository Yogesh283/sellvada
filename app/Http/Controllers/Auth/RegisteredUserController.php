<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class RegisteredUserController extends Controller
{
    /** Show register page */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /** Handle registration with binary placement (0 or NULL = empty) */
    public function store(Request $request): RedirectResponse
    {
        // allow ?refer_by=CODE autofill
        $request->merge([
            'refer_by' => trim($request->input('refer_by') ?? $request->query('refer_by') ?? ''),
        ]);

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|lowercase|email|max:255|unique:users,email',
            'phone'     => 'required|string|max:20|unique:users,phone',
            'password'  => ['required', 'confirmed', Rules\Password::defaults()],
            'refer_by'  => 'required|string',
            'side'      => 'required|in:L,R',
            'spillover' => 'sometimes|boolean',
        ], [
            'refer_by.required' => 'Referral ID (Sponsor code) is required.',
            'phone.required'    => 'Phone number is required.',
            'phone.unique'      => 'This phone number is already registered.',
        ]);

        // robust sponsor lookup (referral_id / referral_code / email / id)
        $ref = strtoupper($data['refer_by']);
        $sponsor = User::query()
            ->whereRaw('UPPER(referral_id) = ?', [$ref])
            ->orWhereRaw('UPPER(referral_code) = ?', [$ref])
            ->orWhere('email', $ref)
            ->when(ctype_digit($ref), fn($q) => $q->orWhere('id', (int)$ref))
            ->first();

        if (!$sponsor) {
            throw ValidationException::withMessages([
                'refer_by' => 'This Referral ID (Sponsor code) is not available.',
            ]);
        }

        $preferSide = $data['side'];                 // 'L' or 'R'
        $useSpill   = (bool)($data['spillover'] ?? true);

        // decide placement (treat 0 or NULL as empty)
        $placement = $this->resolvePlacement($sponsor->id, $preferSide, $useSpill);
        if (!$placement) {
            throw ValidationException::withMessages([
                'side' => 'No free slot on selected side for this sponsor.',
            ]);
        }
        [$parentId, $finalSide] = $placement;

        $newReferralId = $this->generateReferralCode();

        // create user + set parent pointer atomically
        $user = DB::transaction(function () use ($data, $sponsor, $parentId, $finalSide, $newReferralId, $ref) {
            // lock parent row so slot can't be stolen midway
            $parent = User::where('id', $parentId)->lockForUpdate()->first();

            $col = $finalSide === 'R' ? 'right_user_id' : 'left_user_id';
            $isEmpty = is_null($parent->$col) || (int)$parent->$col === 0;
            if (!$isEmpty) {
                throw ValidationException::withMessages([
                    'side' => 'Selected slot was just taken. Please try again.',
                ]);
            }

            /** @var \App\Models\User $user */
            $user = User::create([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'phone'          => $data['phone'],   // <-- saved here
                'password'       => Hash::make($data['password']),
                'referral_id'    => $newReferralId,
                'refer_by'       => $ref,              // sponsor’s referral code (string)
                'sponsor_id'     => $sponsor->id,      // ✅ store upline id
                'parent_id'      => $parentId,         // binary tree parent
                'position'       => $finalSide,        // L/R
                'Password_plain' => $data['password'], // (not recommended, but keeping as you had)
            ]);

            // set the pointer; treat NULL or 0 as empty
            $updated = User::where('id', $parentId)
                ->where(function ($q) use ($col) {
                    $q->whereNull($col)->orWhere($col, 0);
                })
                ->update([$col => $user->id]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'side' => 'Selected slot was just taken. Please try again.',
                ]);
            }

            // (optional) ensure wallet row now, if not using model event
            DB::table('wallet')->updateOrInsert(
                ['user_id' => $user->id],
                ['amount' => 0, 'type' => 'main', 'updated_at' => now(), 'created_at' => now()]
            );

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('dashboard');
    }

    /* ------------------- Helpers ------------------- */

    /** Unique referral code */
    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_id', $code)->exists());
        return $code;
    }

    /** is slot empty? (NULL or 0) */
    private function isSlotEmpty(?int $val): bool
    {
        return is_null($val) || (int)$val === 0;
    }

    /** Resolve placement; tries preferred side, else spillover within that branch */
    private function resolvePlacement(int $sponsorId, string $preferredSide, bool $spill): ?array
    {
        $side = ($preferredSide === 'R') ? 'R' : 'L';

        // read sponsor pointers
        $s = User::select('id', 'left_user_id', 'right_user_id')->find($sponsorId);
        if (!$s) return null;

        // direct slot?
        if ($side === 'L' && $this->isSlotEmpty($s->left_user_id)) {
            return [$s->id, 'L'];
        }
        if ($side === 'R' && $this->isSlotEmpty($s->right_user_id)) {
            return [$s->id, 'R'];
        }

        if (!$spill) return null;

        // spillover: start from chosen branch root, not full tree
        $branchRoot = $side === 'L' ? $s->left_user_id : $s->right_user_id;
        if ($this->isSlotEmpty($branchRoot)) {
            // should have been caught above, but keep guard
            return [$s->id, $side];
        }

        return $this->findSpilloverSlot((int)$branchRoot, $side);
    }

    /**
     * EXTREME spillover inside a branch:
     * Go ONLY along the chosen side (L/R) down to the deepest available slot.
     * Returns [parent_id, side] where side is 'L' or 'R'.
     */
    private function findSpilloverSlot(int $branchRootUserId, string $preferredSide = 'L'): ?array
    {
        $side = ($preferredSide === 'R') ? 'R' : 'L';
        $currentId = $branchRootUserId;

        while (true) {
            $n = User::select('id', 'left_user_id', 'right_user_id')
                ->where('id', $currentId)
                ->first();

            if (!$n) {
                return null; // broken branch
            }

            $col = $side === 'L' ? 'left_user_id' : 'right_user_id';

            // place here if preferred side is empty
            if ($this->isSlotEmpty($n->$col)) {
                return [$n->id, $side];
            }

            // otherwise keep going down the same side
            $nextId = (int) $n->$col;

            // safety: if somehow 0 slips through
            if ($nextId === 0) {
                return [$n->id, $side];
            }

            $currentId = $nextId;
        }
    }
}
