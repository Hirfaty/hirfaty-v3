<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures behind the admin dashboard.
 */
class AdminDashboardService
{
    /** How many buckets the registration chart shows for each period. */
    private const REGISTRATION_BUCKETS = [
        'day' => 30,
        'week' => 12,
        'month' => 12,
        'year' => 5,
    ];

    private const DEFAULT_REGISTRATION_PERIOD = 'month';

    /**
     * Who the registration chart counts. `all` is every account but staff;
     * the others are a `users.role` — by the role the account holds today,
     * so an approved trade buyer counts as a pro from the day they signed up.
     */
    private const REGISTRATION_ROLES = ['all', 'seller', 'buyer', 'professional_buyer'];

    /**
     * Money is what buyers pay for their orders — products plus delivery,
     * the same total the order page shows — and nothing else. Hirfaty's
     * commission and the artisan's share are not shown to admins: only the
     * CEO deals with them for now. The `platform_fee` / `seller_earnings`
     * columns and the `earnings` ledger are still written on every checkout
     * (CheckoutService); this is a display decision, not a data one.
     *
     * @return array<string, int|float>
     */
    public function stats(): array
    {
        // `delivery_fee` sits on the first order of a split basket and is 0
        // on the others, so summing it per order counts each fee once.
        $paid = DB::raw('total_amount + delivery_fee');

        return [
            'total_orders' => Order::count(),
            'total_revenue' => (float) Order::where('status', 'delivered')->sum($paid),
            'pending_revenue' => (float) Order::whereNotIn('status', ['delivered', 'cancelled'])->sum($paid),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            // 'pending_review' is the actual Product::status value — this
            // previously filtered on 'pending', a status that never exists,
            // so this count (and the list below) were always zero.
            'pending_products' => Product::where('status', 'pending_review')->count(),
            // Staff accounts are not "registered users" — the same rule the
            // users table and the registration chart apply.
            'total_users' => $this->registeredUsers()->count(),
            'total_sellers' => User::where('role', 'seller')->count(),
            'total_buyers' => User::where('role', 'buyer')->count(),
            // Same count as the "En attente" tab of the workshops page, which
            // the tile links to.
            'pending_sellers' => Store::where('verification_status', Store::VERIFICATION_PENDING)->count(),
            // Trade requests awaiting a decision — the users table's
            // `professional_request_status=pending` queue.
            'pending_professional_buyers' => User::where('professional_request_status', User::PRO_REQUEST_PENDING)->count(),
        ];
    }

    /** @return Collection<int, User> */
    public function latestUsers(): Collection
    {
        return $this->registeredUsers()
            ->latest()
            ->take(6)
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at']);
    }

    /** @return Collection<int, Product> */
    public function pendingProducts(): Collection
    {
        return Product::with(['store', 'category', 'media'])
            ->where('status', 'pending_review')
            ->latest()
            ->take(5)
            ->get();
    }

    /**
     * The registration chart: which period and role it shows, how many
     * accounts that role has in all, and the signups per bucket.
     *
     * Both filters come straight from the query string. An unknown value (or
     * `?period[]=…`) falls back to the default rather than failing the whole
     * dashboard — and `?role=super_admin` is unknown, so staff are never
     * counted.
     *
     * @return array{period: string, role: string, total: int, trend: list<array{start: string, count: int}>}
     */
    public function registrations(mixed $period, mixed $role): array
    {
        $period = is_string($period) && array_key_exists($period, self::REGISTRATION_BUCKETS)
            ? $period
            : self::DEFAULT_REGISTRATION_PERIOD;

        $role = is_string($role) && in_array($role, self::REGISTRATION_ROLES, true)
            ? $role
            : 'all';

        return [
            'period' => $period,
            'role' => $role,
            'total' => $this->registeredUsers($role)->count(),
            'trend' => $this->registrationTrend($period, $role),
        ];
    }

    /**
     * New accounts per day, week, month or year, oldest bucket first and
     * always including the empty ones — the chart would otherwise close the
     * gap and draw a quiet stretch as a straight line.
     *
     * Each bucket is identified by the date it starts on; the page formats
     * it. Weeks start on Monday whatever the locale says.
     *
     * The database only groups by calendar day — DATE() reads the same on
     * SQLite, Postgres and MySQL, unlike any week or month function — and
     * the days are folded into the wider buckets here.
     *
     * @return list<array{start: string, count: int}>
     */
    private function registrationTrend(string $period, string $role): array
    {
        $current = $this->bucketStart(now(), $period);

        $starts = collect(range(self::REGISTRATION_BUCKETS[$period] - 1, 0))
            ->map(fn (int $ago) => $current->copy()->subUnit($period, $ago));

        $perDay = $this->registeredUsers($role)
            ->where('created_at', '>=', $starts->first())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');

        $perBucket = [];

        foreach ($perDay as $day => $total) {
            $bucket = $this->bucketStart(CarbonImmutable::parse($day), $period)->toDateString();
            $perBucket[$bucket] = ($perBucket[$bucket] ?? 0) + (int) $total;
        }

        return $starts
            ->map(fn (CarbonInterface $start) => [
                'start' => $start->toDateString(),
                'count' => $perBucket[$start->toDateString()] ?? 0,
            ])
            ->all();
    }

    private function bucketStart(CarbonInterface $date, string $period): CarbonInterface
    {
        return match ($period) {
            'day' => $date->copy()->startOfDay(),
            'week' => $date->copy()->startOfWeek(CarbonInterface::MONDAY),
            'month' => $date->copy()->startOfMonth(),
            'year' => $date->copy()->startOfYear(),
        };
    }

    /**
     * Every account but staff, or only those holding `$role`.
     *
     * @return Builder<User>
     */
    private function registeredUsers(string $role = 'all'): Builder
    {
        return User::where('role', '!=', 'super_admin')
            ->when($role !== 'all', fn (Builder $query) => $query->where('role', $role));
    }
}
