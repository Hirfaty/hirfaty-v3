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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
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
            'description' => ['nullable', 'string', 'max:1000'],
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
     * Send the standard Fortify "reset your password" email — the same link
     * a user gets from the public forgot-password form — so the admin can
     * get someone into an account they can't otherwise sign into (e.g. one
     * created here with a random generated password).
     */
    public function sendResetLink(User $user)
    {
        if ($user->role === 'super_admin') {
            abort(403);
        }

        Password::sendResetLink(['email' => $user->email]);

        return back()->with('success', "Lien de réinitialisation envoyé à {$user->email}.");
    }

    public function destroy(User $user)
    {
        if ($user->role === 'super_admin') {
            abort(403);
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted.');
    }
}
