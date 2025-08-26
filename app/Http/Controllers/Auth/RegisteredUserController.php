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
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|lowercase|email|max:255|unique:users,email',
            'password'  => ['required','confirmed', Rules\Password::defaults()],
            'refer_by'  => 'nullable|string',     // sponsor ka referral_id; na mile to query param ya logged-in se bhi le sakte
            'side'      => 'required|in:L,R',     // 'L' ya 'R' jaha place karna hai
            'spillover' => 'sometimes|boolean',   // agar preferred side bhara hai to branch me first free
        ]);


        $referBy = $data['refer_by'] ?? $request->query('refer_by'); // e.g. /register?refer_by=ABCD1234
        abort_if(!$referBy, 422, 'Missing refer_by (referral id).');

        $sponsor = User::where('referral_id', $referBy)->first();
        abort_if(!$sponsor, 422, 'Invalid sponsor referral id.');


        $preferSide = $data['side'];
        $useSpill   = (bool)($data['spillover'] ?? true);

        [$parentId, $side] = $this->resolvePlacement($sponsor->id, $preferSide, $useSpill);


        $newReferralId = $this->generateReferralCode();

        $user = DB::transaction(function () use ($data, $parentId, $side, $newReferralId, $referBy) {
            /** @var \App\Models\User $user */
            $user = User::create([
                'name'        => $data['name'],
                'email'       => $data['email'],
                'password'    => Hash::make($data['password']),
                'referral_id' => $newReferralId,
                'refer_by'    => $referBy,     // kis referral_id se aaya
                'parent_id'   => $parentId,    // jiske niche place hua
                'position'    => $side,        // L/R
                'Password_plain' =>$data['password'],
            ]);

            // Parent pointers set (left_user_id / right_user_id)
            if ($side === 'L') {
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
            $code = strtoupper(Str::random(8)); // e.g. 8-char alnum
        } while (User::where('referral_id', $code)->exists());
        return $code;
    }

    /** Direct slot free? (fast via parent pointers) */
    private function directSlotFree(int $parentId, string $side): bool
    {
        $p = User::select('left_user_id','right_user_id')->findOrFail($parentId);
        return $side === 'L' ? ($p->left_user_id === null) : ($p->right_user_id === null);
    }

    /**
     * Decide final placement:
     * 1) Try sponsor on preferred side
     * 2) If occupied & spillover=true → BFS traverse same branch to find first free slot
     * 3) Else 422
     */
    private function resolvePlacement(int $sponsorId, string $preferredSide, bool $spill): array
    {
        $side = ($preferredSide === 'R') ? 'R' : 'L';

        if ($this->directSlotFree($sponsorId, $side)) {
            return [$sponsorId, $side];
        }

        if ($spill) {
            $slot = $this->findSpilloverSlot($sponsorId, $side);
            if ($slot) return [$slot['parent_id'], $slot['side']];
        }

        abort(422, 'No free slot on selected side for this sponsor.');
    }

    /** BFS spillover: level-order traversal to find first free L/R under the branch */
    private function findSpilloverSlot(int $startParentId, string $preferredSide = 'L'): ?array
    {
        $preferredSide = ($preferredSide === 'R') ? 'R' : 'L';
        $queue = [$startParentId];

        while ($queue) {
            $p = array_shift($queue);
            $parent = User::select('id','left_user_id','right_user_id')->find($p);
            if (!$parent) continue;

            // Try preferred side first, then the other
            if ($preferredSide === 'L') {
                if ($parent->left_user_id === null)  return ['parent_id' => $p, 'side' => 'L'];
                if ($parent->right_user_id === null) return ['parent_id' => $p, 'side' => 'R'];
            } else {
                if ($parent->right_user_id === null) return ['parent_id' => $p, 'side' => 'R'];
                if ($parent->left_user_id === null)  return ['parent_id' => $p, 'side' => 'L'];
            }

            // Level-order: push children to queue
            if ($parent->left_user_id)  $queue[] = (int)$parent->left_user_id;
            if ($parent->right_user_id) $queue[] = (int)$parent->right_user_id;
        }

        return null;
    }
}
