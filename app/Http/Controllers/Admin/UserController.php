<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\WelcomeUser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function create()
    {
        return Inertia::render('admin/users/create', [
            'cities' => City::orderBy('name', 'asc')->get(),
            'categories' => Category::whereNull('parent_id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'unique:users,phone_number'],
            'role' => ['required', 'in:seller,buyer,professional_buyer'],
            'store_name' => ['required_if:role,seller', 'nullable', 'string', 'unique:stores,store_name'],
            // Defaults to 'artist' below rather than being required — the
            // wizard always sends a choice (preselected), but any other
            // caller (API, tests) shouldn't be forced to pick one.
            'store_type' => ['nullable', Rule::in(Store::TYPES)],
            'category_id' => ['required_if:role,seller', 'nullable', 'exists:categories,id'],
            'city_id' => ['required_if:role,seller', 'nullable', 'exists:cities,id'],
            // One field, two destinations: for a seller it describes the shop
            // (stores.description); for a buyer/pro it describes the person
            // and lands on users.bio.
            'description' => ['nullable', 'string', 'max:2500'],
            // Optional at creation — there is no admin store-edit screen yet, so
            // requiring these would block onboarding whenever a photo isn't
            // ready on hand. Size/dimension caps come from config/media.php,
            // the same source ValidProductMedia uses for product photos.
            'logo' => ['nullable', 'image', 'max:'.config('media.image.max_kb'), $this->imageDimensionRule()],
            'banner' => ['nullable', 'image', 'max:'.config('media.image.max_kb'), $this->imageDimensionRule()],
        ]);

        $plainPassword = config('auth.default_password', Str::random(10));

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role' => $data['role'],
            // Sellers describe their shop instead, on the store record below.
            'bio' => $data['role'] === 'seller' ? null : ($data['description'] ?? null),
            'password' => Hash::make($plainPassword),
            'email_verified_at' => now(),
        ]);

        if ($data['role'] === 'seller') {
            $store = Store::create([
                'user_id' => $user->id,
                'store_name' => $data['store_name'],
                'slug' => Str::slug($data['store_name']).'-'.Str::random(5),
                'type' => $data['store_type'] ?? 'artist',
                'category_id' => $data['category_id'],
                'city_id' => $data['city_id'],
                'description' => $data['description'] ?? null,
            ]);

            if ($request->hasFile('logo')) {
                $store->update(['logo' => $this->storeShopImage($request->file('logo'), $store->id)]);
            }

            if ($request->hasFile('banner')) {
                $store->update(['banner' => $this->storeShopImage($request->file('banner'), $store->id)]);
            }

            // try {
            //     $user->notify(new WelcomeUser($plainPassword));
            // } catch (\Exception $e) {
            //     \Log::warning("Welcome email failed for user {$user->id}: ".$e->getMessage());
            // }
        }

        return redirect()->route('admin.users.show', $user)->with('success', 'Utilisateur créé avec succès.');
    }

    /**
     * Cap logo/banner uploads to the same resolution ceiling as product
     * photos (config/media.php) — rejects raw camera/scan exports without
     * being strict enough to bother a normal phone photo.
     */
    private function imageDimensionRule(): Dimensions
    {
        return Rule::dimensions()
            ->maxWidth(config('media.image.max_width'))
            ->maxHeight(config('media.image.max_height'));
    }

    /**
     * Store a single shop image (logo or banner) and return its public URL.
     */
    private function storeShopImage(UploadedFile $file, int $storeId): string
    {
        $disk = config('filesystems.default');
        $path = $file->store("stores/{$storeId}", [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        return Storage::disk($disk)->url($path);
    }

    public function edit(User $user)
    {
        if ($user->role === 'super_admin') {
            abort(403);
        }

        $user->load('store');

        return Inertia::render('admin/users/edit', [
            'user' => $user,
            'cities' => City::orderBy('name', 'asc')->get(),
            'categories' => Category::whereNull('parent_id')->get(),
            /*
             * Whether this account may still leave the seller role. Computed
             * here rather than in the form so the UI and the server agree on
             * one answer — see assertRoleChangeIsSafe().
             */
            'canLeaveSellerRole' => $user->role !== 'seller' || $this->sellerRoleIsReleasable($user),
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($user->role === 'super_admin') {
            abort(403);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // Ignore this user's own row so re-saving doesn't collide with itself.
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', Rule::unique('users', 'phone_number')->ignore($user->id)],
            'role' => ['required', 'in:seller,buyer,professional_buyer'],
            'store_name' => [
                'required_if:role,seller', 'nullable', 'string',
                Rule::unique('stores', 'store_name')->ignore($user->store?->id),
            ],
            'store_type' => ['nullable', Rule::in(Store::TYPES)],
            'category_id' => ['required_if:role,seller', 'nullable', 'exists:categories,id'],
            'city_id' => ['required_if:role,seller', 'nullable', 'exists:cities,id'],
            'description' => ['nullable', 'string', 'max:2500'],
            'logo' => ['nullable', 'image', 'max:'.config('media.image.max_kb'), $this->imageDimensionRule()],
            'banner' => ['nullable', 'image', 'max:'.config('media.image.max_kb'), $this->imageDimensionRule()],
        ]);

        $this->assertRoleChangeIsSafe($user, $data['role']);

        DB::transaction(function () use ($user, $data, $request) {
            $becomesSeller = $data['role'] === 'seller';

            $user->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'role' => $data['role'],
                // Sellers describe their shop on the store record instead.
                'bio' => $becomesSeller ? null : ($data['description'] ?? null),
            ]);

            /*
             * Verification is a statement about a *professional buyer*, so it
             * is dropped when the account stops being one — otherwise
             * switching back later would silently restore wholesale pricing
             * without anyone reviewing the account a second time.
             *
             * Assigned directly rather than through update(): the column is
             * deliberately left out of User's Fillable list because it gates
             * which price list a buyer is billed on, and mass assignment would
             * quietly ignore it here anyway.
             */
            if ($data['role'] !== 'professional_buyer' && $user->professional_verified_at !== null) {
                $user->professional_verified_at = null;
                $user->save();
            }

            if ($becomesSeller) {
                $store = $user->store;

                if ($store) {
                    $store->update([
                        'store_name' => $data['store_name'],
                        'type' => $data['store_type'] ?? $store->type,
                        'category_id' => $data['category_id'],
                        'city_id' => $data['city_id'],
                        'description' => $data['description'] ?? null,
                    ]);
                } else {
                    // Promoted to seller: give them the shop the seller area
                    // (EnsureStoreIsSetup) requires before it will let them in.
                    $store = Store::create([
                        'user_id' => $user->id,
                        'store_name' => $data['store_name'],
                        'slug' => Str::slug($data['store_name']).'-'.Str::random(5),
                        'type' => $data['store_type'] ?? 'artist',
                        'category_id' => $data['category_id'],
                        'city_id' => $data['city_id'],
                        'description' => $data['description'] ?? null,
                    ]);
                }

                if ($request->hasFile('logo')) {
                    $store->update(['logo' => $this->storeShopImage($request->file('logo'), $store->id)]);
                }

                if ($request->hasFile('banner')) {
                    $store->update(['banner' => $this->storeShopImage($request->file('banner'), $store->id)]);
                }
            }
        });

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Utilisateur mis à jour.');
    }

    /**
     * Can this seller still be moved off the seller role?
     *
     * Only while their shop holds nothing. A shop with products or orders
     * belongs to someone who needs the seller area to fulfil them.
     */
    private function sellerRoleIsReleasable(User $user): bool
    {
        $store = $user->store;

        if (! $store) {
            return true;
        }

        return $store->products()->count() === 0
            && $store->orders()->count() === 0;
    }

    /**
     * Demoting a seller does not delete their shop — the row, its products and
     * its orders all survive — but it does lock them out of `/seller/*`, so
     * nobody can dispatch the orders buyers have already paid for. Refuse
     * while there is anything to fulfil, and say what to do instead.
     *
     * @throws ValidationException
     */
    private function assertRoleChangeIsSafe(User $user, string $newRole): void
    {
        if ($user->role !== 'seller' || $newRole === 'seller') {
            return;
        }

        if ($this->sellerRoleIsReleasable($user)) {
            return;
        }

        $store = $user->store;

        throw ValidationException::withMessages([
            'role' => "Impossible de changer le rôle : la boutique « {$store->store_name} » compte "
                .$store->products()->count().' produit(s) et '
                .$store->orders()->count().' commande(s). '
                .'Bloquez le compte si vous voulez en couper l’accès.',
        ]);
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->where('role', '!=', 'super_admin')
            ->withCount('products')
            // Shop name, type, and city are shown as columns for sellers —
            // eager load so the table doesn't N+1 per row.
            ->with('store:id,user_id,store_name,type,city_id', 'store.city:id,name');

        if ($search = $request->input('search')) {
            // Name, email, phone, or the seller's shop name all match — the
            // admin rarely knows which of those they're typing.
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery->where('store_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        // Counts for the "all / buyer / pro / seller" filter tabs — computed
        // once, unfiltered by search, so switching tabs never shows a stale 0.
        $roleCounts = User::where('role', '!=', 'super_admin')
            ->selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'totalUsers' => $roleCounts->sum(),
            'roleCounts' => [
                'buyer' => $roleCounts->get('buyer', 0),
                'professional_buyer' => $roleCounts->get('professional_buyer', 0),
                'seller' => $roleCounts->get('seller', 0),
            ],
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function show(User $user)
    {
        if ($user->role === 'seller') {
            $user->load(['store.city', 'store.category', 'store.products.images', 'store.products.category']);
            // Earnings summary
            $earnings = [
                'total' => $user->store?->earnings()->sum('net_amount') ?? 0,
                'pending' => $user->store?->earnings()->where('status', 'pending')->sum('net_amount') ?? 0,
                'available' => $user->store?->earnings()->where('status', 'available')->sum('net_amount') ?? 0,
                'paid_out' => $user->store?->earnings()->where('status', 'paid_out')->sum('net_amount') ?? 0,
            ];
            // Order stats
            $orderStats = [
                'total' => $user->store?->orders()->count() ?? 0,
                'pending' => $user->store?->orders()->where('status', 'pending')->count() ?? 0,
                'delivered' => $user->store?->orders()->where('status', 'delivered')->count() ?? 0,
                'cancelled' => $user->store?->orders()->where('status', 'cancelled')->count() ?? 0,
            ];
            // Product stats
            $productStats = [
                'total' => $user->store?->products()->count() ?? 0,
                'published' => $user->store?->products()->where('status', 'published')->count() ?? 0,
                'pending_review' => $user->store?->products()->where('status', 'pending_review')->count() ?? 0,
                'rejected' => $user->store?->products()->where('status', 'rejected')->count() ?? 0,
            ];
            // Recent orders
            $recentOrders = $user->store?->orders()
                ->with(['buyer', 'items.product'])
                ->latest()
                ->limit(5)
                ->get() ?? [];

            return Inertia::render('admin/users/showSeller', [
                'user' => $user,
                'earnings' => $earnings,
                'orderStats' => $orderStats,
                'productStats' => $productStats,
                'recentOrders' => $recentOrders, ]);
        }

        // ── Buyer ─────────────────────────────────────────────────
        $user->load([
            'orders.store',
            'orders.items.product.images',
        ]);

        $orderStats = [
            'total' => $user->orders()->count(),
            'pending' => $user->orders()->where('status', 'pending')->count(),
            'delivered' => $user->orders()->where('status', 'delivered')->count(),
            'cancelled' => $user->orders()->where('status', 'cancelled')->count(),
        ];

        $totalSpent = $user->orders()
            ->where('status', 'delivered')
            ->sum('total_amount');

        return Inertia::render('admin/users/showBuyer', [
            'user' => $user,
            'orderStats' => $orderStats,
            'totalSpent' => $totalSpent,
        ]);

    }

    public function ban(User $user)
    {
        if ($user->role === 'super_admin') {
            abort(403);
        }

        $user->is_banned = ! $user->is_banned;
        $user->save();

        // $user->update(['is_banned' => !$user->is_banned]);

        $action = $user->is_banned ? 'banned' : 'unbanned';

        return back()->with('success', "User {$action} successfully.");
    }

    /**
     * Toggle whether a professional buyer is marked verified. Nothing in the
     * buying flow currently checks this — it exists purely so the admin can
     * flag accounts they've reviewed, ahead of that check being wired in.
     */
    public function verifyProfessional(User $user)
    {
        if ($user->role !== 'professional_buyer') {
            abort(403);
        }

        $user->professional_verified_at = $user->professional_verified_at ? null : now();
        $user->save();

        $action = $user->professional_verified_at ? 'verified' : 'unverified';

        return back()->with('success', "Professional buyer {$action}.");
    }

    /**
     * Mint a password reset link and hand it straight back to the admin to
     * deliver however they like, instead of emailing it.
     *
     * Uses the same token repository as the public forgot-password flow, so
     * the link is byte-for-byte what Fortify would have mailed and lands on
     * the same /reset-password/{token} screen — only the delivery step is
     * skipped. Deliberately does NOT go through Password::sendResetLink(),
     * which would both send mail and apply the broker's 60-second throttle;
     * createToken() has no throttle, so an admin can regenerate on demand.
     *
     * Each call replaces the user's stored token, which invalidates any link
     * generated earlier — surfaced to the admin in the dialog.
     */
    public function resetLink(User $user)
    {
        if ($user->role === 'super_admin') {
            abort(403);
        }

        $broker = config('auth.defaults.passwords');

        return back()->with('reset_link', [
            'url' => route('password.reset', [
                'token' => Password::broker()->createToken($user),
                'email' => $user->email,
            ]),
            'email' => $user->email,
            'expires_in_minutes' => (int) config("auth.passwords.{$broker}.expire"),
        ]);
    }

    /**
     * Hard-deletes a user. Every FK back to `users` (orders, stores,
     * products, order_items, earnings...) is ON DELETE CASCADE, so a buyer
     * with order history would take their Orders and the seller's Earnings
     * on those orders down with them; a seller would take their whole store,
     * catalog, and every order any buyer ever placed with them. That's
     * financial/transaction history, not something a misclick should erase.
     *
     * So: refuse to delete anyone with order history and point the admin at
     * Ban instead (same effect — account can't be used — without touching
     * data). Only an account with zero orders (a seller who never sold
     * anything, a buyer who never checked out, a test account, ...) can be
     * hard-deleted.
     */
    public function destroy(User $user)
    {
        if ($user->role === 'super_admin') {
            abort(403);
        }

        $orderCount = $user->role === 'seller'
            ? ($user->store?->orders()->count() ?? 0)
            : $user->orders()->count();

        if ($orderCount > 0) {
            return back()->with(
                'error',
                "Impossible de supprimer : {$orderCount} commande(s) lié(e)s à ce compte. Bloquez-le plutôt pour couper son accès sans perdre l'historique."
            );
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Utilisateur supprimé.');
    }
}
