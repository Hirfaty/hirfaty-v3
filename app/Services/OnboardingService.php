<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The short intro a buyer sees once, right after verifying their email.
 *
 * Completing and skipping both end the same way — `onboarded_at` is stamped
 * either way, because the column answers "have we already asked?", not "did
 * they engage?". Anything else and a skipped tour would reappear on every
 * login, which is the opposite of skippable.
 */
class OnboardingService
{
    public function __construct(
        private ProfessionalAccountService $professional,
    ) {}

    /**
     * Finish onboarding: save the picked interests, optionally file a trade
     * request, and mark the tour done.
     *
     * @param  array<int, int>  $categoryIds
     */
    public function complete(User $user, array $categoryIds = [], ?string $professionalMessage = null): void
    {
        DB::transaction(function () use ($user, $categoryIds, $professionalMessage) {
            /*
             * Filter to ids that actually exist rather than trusting the
             * payload: sync() on a bad id throws a FK violation, and a
             * mistyped category shouldn't cost someone their whole signup.
             */
            $valid = Category::whereIn('id', $categoryIds)->pluck('id')->all();
            $user->interests()->sync($valid);

            if ($professionalMessage !== null && $user->canRequestProfessional()) {
                $this->professional->request($user, $professionalMessage);
            }

            $this->markSeen($user);
        });
    }

    /**
     * Dismiss the tour without answering anything.
     */
    public function skip(User $user): void
    {
        $this->markSeen($user);
    }

    private function markSeen(User $user): void
    {
        // Idempotent: re-hitting the endpoint keeps the first timestamp, so
        // the date stays a true "first seen" if it's ever reported on.
        if ($user->onboarded_at === null) {
            $user->forceFill(['onboarded_at' => now()])->save();
        }
    }
}
