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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserController extends Controller
{

    public function create()
    {
        return Inertia::render('admin/users/create', [
            'cities'     => City::orderBy('name', 'asc')->get(),
            'categories' => Category::whereNull('parent_id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'  => ['required', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'unique:users,phone_number'],
            'role'        => ['required', 'in:seller,buyer'],
            'store_name'  => ['required_if:role,seller', 'nullable', 'string', 'unique:stores,store_name'],
            'category_id' => ['required_if:role,seller', 'nullable', 'exists:categories,id'],
            'city_id'     => ['required_if:role,seller', 'nullable', 'exists:cities,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $plainPassword = config('auth.default_password', Str::random(10));

        $user = User::create([
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'email'        => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'role'         => $data['role'],
            'password'     => Hash::make($plainPassword),
            'email_verified_at' => now(),
        ]);

        if ($data['role'] === 'seller') {
            Store::create([
                'user_id'     => $user->id,
                'store_name'  => $data['store_name'],
                'slug'        => Str::slug($data['store_name']) . '-' . Str::random(5),
                'category_id' => $data['category_id'],
                'city_id'     => $data['city_id'],
                'description' => $data['description'] ?? null,
            ]);

            try {
                $user->notify(new WelcomeUser($plainPassword));
            } catch (\Exception $e) {
                \Log::warning("Welcome email failed for user {$user->id}: " . $e->getMessage());
            }
        }

        return redirect()->route('admin.users.show', $user)->with('success', 'Utilisateur créé avec succès.');
    }

    public function index(Request $request)
    {
        $query = User::query()
            ->where('role', '!=', 'super_admin')
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
