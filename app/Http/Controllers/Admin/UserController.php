<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        if ($request->input('step') === 'product') {
            return $this->storeProducts($request);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'unique:users,phone_number'],
            'role' => ['required', 'in:seller,buyer'],
            'store_name' => ['required_if:role,seller', 'nullable', 'string', 'unique:stores,store_name'],
            'category_id' => ['required_if:role,seller', 'nullable', 'exists:categories,id'],
            'city_id' => ['required_if:role,seller', 'nullable', 'exists:cities,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role' => $data['role'],
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ]);

        $store = null;

        if ($data['role'] === 'seller') {
            $store = Store::create([
                'user_id' => $user->id,
                'store_name' => $data['store_name'],
                'slug' => Str::slug($data['store_name']).'-'.Str::random(5),
                'category_id' => $data['category_id'],
                'city_id' => $data['city_id'],
                'description' => $data['description'] ?? null,
            ]);
        }

        return response()->json([
            'user_id' => $user->id,
            'store_id' => $store?->id,
            'invite_url' => $this->generateInviteUrl($user),
        ]);
    }

    /**
     * Generate (or regenerate) a link that lets a user set their own password.
     */
    private function generateInviteUrl(User $user): string
    {
        $token = Password::createToken($user);

        return url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));
    }

    /**
     * Get a fresh, copyable set-password link for an existing user (admin can fetch this anytime).
     */
    public function inviteLink(User $user)
    {
        return response()->json([
            'invite_url' => $this->generateInviteUrl($user),
        ]);
    }

    /**
     * Second step of the onboarding wizard: add initial products (saved as drafts) for a newly created store.
     */
    private function storeProducts(Request $request)
    {
        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.description' => ['nullable', 'string'],
            'products.*.base_price' => ['required', 'numeric', 'min:0'],
            'products.*.sale_price' => ['nullable', 'numeric', 'min:0', 'lt:products.*.base_price'],
            'products.*.stock' => ['required', 'integer', 'min:0'],
            'products.*.category_id' => ['required', 'exists:categories,id'],
            'products.*.media' => ['nullable', 'array', 'max:5'],
            'products.*.media.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,mp4,mov',
                function ($attribute, $value, $fail) {
                    $isVideo = str_starts_with($value->getMimeType(), 'video');
                    $maxSize = $isVideo ? 51200 : 5120; // 50MB for video, 5MB for image
                    if ($value->getSize() > $maxSize * 1024) {
                        $fail($isVideo
                            ? 'La vidéo ne doit pas dépasser 50 Mo.'
                            : "L'image ne doit pas dépasser 5 Mo.");
                    }
                },
            ],
        ]);

        $store = Store::findOrFail($data['store_id']);

        foreach ($data['products'] as $index => $productData) {
            $product = $store->products()->create([
                'name' => $productData['name'],
                'slug' => Str::slug($productData['name']).'-'.Str::random(5),
                'description' => $productData['description'] ?? null,
                'base_price' => $productData['base_price'],
                'sale_price' => $productData['sale_price'] ?? null,
                'stock' => $productData['stock'],
                'category_id' => $productData['category_id'],
                'status' => 'draft',
            ]);

            $files = $request->file("products.{$index}.media", []);

            foreach ($files as $mediaIndex => $file) {
                $isVideo = str_starts_with($file->getMimeType(), 'video');
                $path = $file->store('products/'.$product->id, [
                    'disk' => config('filesystems.default'),
                    'visibility' => 'public',
                ]);

                $product->media()->create([
                    'type' => $isVideo ? 'video' : 'image',
                    'path' => $path,
                    'url' => Storage::disk(config('filesystems.default'))->url($path),
                    'disk' => config('filesystems.default'),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'order' => $mediaIndex,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->where('role', '!=', 'super_admin')
            // Needed so the list can link straight to "create a product for this seller".
            ->with('store:id,user_id')
            ->withCount([
                'orders',
                'store',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($request->input('banned')) {
            $query->where('is_banned', true);
        }

        $users = $query->latest()->paginate(20)->withQueryString();
        $totalUsers = User::where('role', '!=', 'super_admin')->count();
        $newUsersThisWeek = User::where('role', '!=', 'super_admin')
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'totalUsers' => $totalUsers,
            'newUsersThisWeek' => $newUsersThisWeek,
            'filters' => $request->only(['search', 'role', 'banned']),
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
