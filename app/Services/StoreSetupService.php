<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one-time shop creation a seller runs before they can reach any other
 * /seller route (see EnsureStoreIsSetup).
 *
 * Lives here rather than in the controller so the slug rule has a single
 * definition — Admin\UserController creates stores too, and a shop whose slug
 * was built one way there and another way here would be reachable at a
 * different URL depending on who created it.
 */
class StoreSetupService
{
    public function __construct(private StoreVerificationService $verification) {}

    /**
     * Only `store_name` and `type` are guaranteed — a seller may open the shop
     * from the first step and fill the rest in from settings later.
     *
     * @param  array{store_name: string, type: string, category_id?: ?int, city_id?: ?int, description?: ?string}  $data
     */
    public function create(User $seller, array $data): Store
    {
        return DB::transaction(function () use ($seller, $data) {
            $store = Store::create([
                'user_id' => $seller->id,
                'store_name' => $data['store_name'],
                'slug' => $this->uniqueSlug($data['store_name']),
                'type' => $data['type'],
                'category_id' => $data['category_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'description' => $data['description'] ?? null,
                'verification_status' => Store::VERIFICATION_PENDING,
                /*
                 * Not publicly listable until an admin approves it —
                 * Buyer\StoreController filters on this. The column defaults
                 * to 'active', which would have put an unreviewed shop
                 * straight into the storefront.
                 */
                'status' => 'inactive',
            ]);

            // A shop filled in completely at setup goes straight into the
            // queue; one opened from step one waits until settings completes
            // it. submit() decides, so the rule lives in one place.
            return $this->verification->submit($store);
        });
    }

    /**
     * A slug no other shop already holds.
     *
     * `stores.slug` carries a UNIQUE index while `store_name` is only unique
     * as a string, and Str::slug() flattens accents and punctuation — so
     * "Café Zen" and "Cafe Zen" are two allowed names that both reduce to
     * "cafe-zen". The old code slugged the name directly and let the second
     * one hit the database, turning a legitimate signup into a 500.
     *
     * The stub covers a name made only of characters Str::slug() strips
     * (punctuation, emoji, or a script it cannot transliterate), which would
     * otherwise yield an empty slug and an unreachable shop URL.
     */
    private function uniqueSlug(string $storeName): string
    {
        $base = Str::slug($storeName) ?: 'atelier';
        $slug = $base;
        $i = 2;

        while (Store::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
