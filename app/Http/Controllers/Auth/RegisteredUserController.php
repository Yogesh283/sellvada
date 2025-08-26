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

    /** Handle registration with binary placement */
    public function store(Request $request): RedirectResponse
    {
        // Allow ?refer_by=CODE to auto-fill if the form field is empty
        $request->merge([
            'refer_by' => trim($request->input('refer_by') ?? $request->query('refer_by') ?? ''),
        ]);

        // Validate basic fields (do NOT use exists yet so we can customize message/case)
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|lowercase|email|max:255|unique:users,email',
            'password'  => ['required', 'confirmed', Rules\Password::defaults()],
            'refer_by'  => 'required|string',
            'side'      => 'required|in:L,R',
            'spillover' => 'sometimes|boolean',
        ], [
            'refer_by.required' => 'Referral ID (Sponsor code) is required.',
        ]);

        // Normalize referral code for case-insensitive match
        $referBy = strtoupper($data['refer_by']);

        // Find sponsor by referral_id (case-insensitive)
        $sponsor = User::whereRaw('UPPER(referral_id) = ?', [$referBy])->first();
        if (!$sponsor) {
            throw ValidationException::withMessages([
                'refer_by' => 'This Referral ID (Sponsor code) is not available.',
            ]);
        }

        $preferSide = $data['side'];
        $useSpill   = (bool)($data['spillover'] ?? true);

        // Resolve final placement; if not found, return a field error (no abort/exception page)
        $placement = $this->resolvePlacement($sponsor->id, $preferSide, $useSpill);
        if (!$placement) {
            throw ValidationException::withMessages([
                'side' => 'No free slot on selected side for this sponsor.',
            ]);
        }

        [$parentId, $finalSide] = $placement;

        $newReferralId = $this->generateReferralCode();

        $user = DB::transaction(function () use ($data, $parentId, $finalSide, $newReferralId, $referBy) {
            /** @var \App\Models\User $user */
            $user = User::create([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'password'       => Hash::make($data['password']),
                'referral_id'    => $newReferralId, // new code for this user
                'refer_by'       => $referBy,       // sponsor’s referral_id
                'parent_id'      => $parentId,      // tree parent
                'position'       => $finalSide,     // L/R
                'Password_plain' => $data['password'], // NOTE: storing plain text is unsafe
            ]);

            // Set parent pointers (left_user_id / right_user_id) only if empty
            if ($finalSide === 'L') {
                User::where('id', $parentId)->whereNull('left_user_id')->update(['left_user_id' => $user->id]);
            } else {
                User::where('id', $parentId)->whereNull('right_user_id')->update(['right_user_id' => $user->id]);
            }

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
            $code = strtoupper(Str::random(8)); // e.g., 8-char alnum
        } while (User::where('referral_id', $code)->exists());
        return $code;
    }

    /** Check if direct slot is free (no exception throwing) */
    private function directSlotFree(int $parentId, string $side): bool
    {
        $p = User::select('left_user_id', 'right_user_id')->find($parentId);
        if (!$p) return false;

        return $side === 'L'
            ? ($p->left_user_id === null)
            : ($p->right_user_id === null);
        }

    /**
     * Decide final placement:
     * 1) Try sponsor on preferred side
     * 2) If occupied & spillover=true → BFS over the same branch to find first free slot
     * 3) If nothing found → return null (caller will raise a field error)
     */
    private function resolvePlacement(int $sponsorId, string $preferredSide, bool $spill): ?array
    {
        $side = ($preferredSide === 'R') ? 'R' : 'L';

        if ($this->directSlotFree($sponsorId, $side)) {
            return [$sponsorId, $side];
        }

        if ($spill) {
            $slot = $this->findSpilloverSlot($sponsorId, $side);
            if ($slot) return [$slot['parent_id'], $slot['side']];
        }

        return null;
    }

    /** BFS spillover: level-order traversal to find first free L/R under the branch */
    private function findSpilloverSlot(int $startParentId, string $preferredSide = 'L'): ?array
    {
        $preferredSide = ($preferredSide === 'R') ? 'R' : 'L';
        $queue = [$startParentId];

        while ($queue) {
            $p = array_shift($queue);
            $parent = User::select('id', 'left_user_id', 'right_user_id')->find($p);
            if (!$parent) continue;

            // Try preferred side first, then the other
            if ($preferredSide === 'L') {
                if ($parent->left_user_id === null)  return ['parent_id' => $p, 'side' => 'L'];
                if ($parent->right_user_id === null) return ['parent_id' => $p, 'side' => 'R'];
            } else {
                if ($parent->right_user_id === null) return ['parent_id' => $p, 'side' => 'R'];
                if ($parent->left_user_id === null)  return ['parent_id' => $p, 'side' => 'L'];
            }

            // Level-order: push children
            if ($parent->left_user_id)  $queue[] = (int) $parent->left_user_id;
            if ($parent->right_user_id) $queue[] = (int) $parent->right_user_id;
        }

        return null;
    }
}
