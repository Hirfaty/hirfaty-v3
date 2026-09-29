<?php

namespace App\Services;

use App\Models\PendingRegistration;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin users table, its downloads, and the signups still in progress.
 *
 * One query builder serves both, so an export is always exactly what the
 * admin is looking at — same search, same role tab, same trade-request
 * filter — only unpaginated.
 */
class AdminUserListService
{
    public function __construct(
        private readonly SpreadsheetExportService $spreadsheets,
    ) {}

    private const ROLE_LABELS = [
        'buyer' => 'Acheteur',
        'professional_buyer' => 'Acheteur pro',
        'seller' => 'Vendeur',
    ];

    private const STORE_TYPE_LABELS = [
        'artist' => 'Artiste',
        'company' => 'Entreprise',
        'cooperative' => 'Coopérative',
    ];

    private const PRO_REQUEST_LABELS = [
        User::PRO_REQUEST_PENDING => 'En attente',
        User::PRO_REQUEST_APPROVED => 'Approuvée',
        User::PRO_REQUEST_DECLINED => 'Refusée',
    ];

    private const STORE_VERIFICATION_LABELS = [
        'pending' => 'En attente',
        'approved' => 'Vérifiée',
        'rejected' => 'Refusée',
    ];

    /**
     * Left unordered on purpose: the table sorts by `created_at`, while the
     * exports walk the rows with lazyByIdDesc(), which pages on `id` and
     * would skip or repeat rows under any other ordering placed ahead of it.
     *
     * @param  array{search?: ?string, role?: ?string, professional_request_status?: ?string}  $filters
     * @return Builder<User>
     */
    public function query(array $filters): Builder
    {
        $query = User::query()
            ->where('role', '!=', 'super_admin')
            ->withCount('products')
            /*
             * Shop name, type, and city are shown as columns for sellers —
             * eager load so the table doesn't N+1 per row.
             *
             * `verification_status` rides along for the "Vérifié par Hirfaty"
             * tick beside the shop name. It is an explicit column in the
             * select list because this is a constrained eager load: a column
             * missing from here arrives `undefined` on the page rather than
             * erroring, which is exactly how the tick would silently never
             * render.
             */
            ->with(
                'store:id,user_id,store_name,type,city_id,verification_status',
                'store.city:id,name',
            );

        if ($search = $filters['search'] ?? null) {
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

        if ($role = $filters['role'] ?? null) {
            $query->where('role', $role);
        }

        /*
         * A queue view, not a role: the request status outlives the decision
         * (a declined buyer stays `buyer`, an approved one becomes
         * `professional_buyer`), so this filters on
         * `professional_request_status` directly rather than folding it into
         * the role tabs. Kept as its own parameter so it composes with a role
         * filter and a search.
         */
        if ($requestStatus = $filters['professional_request_status'] ?? null) {
            $query->where('professional_request_status', $requestStatus);
        }

        return $query;
    }

    /**
     * Signups that started but never became an account: the visitor typed
     * their email (and maybe the code) and left before choosing a password.
     *
     * They live in `pending_registrations`, not `users` — see
     * RegistrationCodeService for why the account only exists at the end —
     * which is why they never showed up in the users table.
     *
     * An address that has since become an account some other way (Google,
     * an admin creating it by hand) is left out: that signup is not pending,
     * it is just an abandoned row.
     *
     * @return Builder<PendingRegistration>
     */
    public function pendingSignups(?string $search = null): Builder
    {
        return PendingRegistration::query()
            ->whereNotExists(fn ($users) => $users
                ->selectRaw('1')
                ->from('users')
                ->whereColumn('users.email', 'pending_registrations.email'))
            ->when($search, fn ($query) => $query->where('email', 'like', "%{$search}%"))
            ->latest('updated_at');
    }

    /**
     * One row of the pending-signups table. Built by hand rather than
     * serialising the model, so nothing about the code or the resume token
     * can ride along.
     *
     * @return array<string, mixed>
     */
    public function presentPendingSignup(PendingRegistration $pending): array
    {
        return [
            'id' => $pending->id,
            'email' => $pending->email,
            'intent' => $pending->intent,
            'verified' => $pending->isVerified(),
            'code_expired' => $pending->hasExpired(),
            'locale' => $pending->locale,
            'started_at' => $pending->created_at?->toIso8601String(),
            'last_sent_at' => $pending->last_sent_at?->toIso8601String(),
        ];
    }

    /**
     * Every user matching the filters, newest first, as CSV or Excel. Rows
     * are walked in chunks so the whole user base is never loaded at once.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function export(string $format, array $filters): StreamedResponse|BinaryFileResponse
    {
        return $this->spreadsheets->download(
            $format,
            'utilisateurs',
            $this->headings(),
            fn () => $this->query($filters)->lazyByIdDesc(500)->map(fn (User $user) => $this->row($user)),
        );
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [
            'ID',
            'Prénom',
            'Nom',
            'Email',
            'Téléphone',
            'Rôle',
            'Statut',
            'Email vérifié',
            'Demande pro',
            'Pro vérifié le',
            'Boutique',
            'Type de boutique',
            'Ville',
            'Boutique vérifiée',
            'Produits',
            'Inscrit le',
        ];
    }

    /** @return list<string|int> */
    private function row(User $user): array
    {
        $store = $user->store;

        return [
            $user->id,
            $user->first_name ?? '',
            $user->last_name ?? '',
            $user->email ?? '',
            $user->phone_number ?? '',
            self::ROLE_LABELS[$user->role] ?? $user->role,
            $user->is_banned ? 'Bloqué' : 'Actif',
            $user->email_verified_at ? 'Oui' : 'Non',
            self::PRO_REQUEST_LABELS[$user->professional_request_status] ?? '',
            $this->date($user->professional_verified_at),
            $store?->store_name ?? '',
            $store ? (self::STORE_TYPE_LABELS[$store->type] ?? $store->type) : '',
            $store?->city?->name ?? '',
            $store ? (self::STORE_VERIFICATION_LABELS[$store->verification_status] ?? $store->verification_status) : '',
            $user->role === 'seller' ? (int) $user->products_count : '',
            $this->date($user->created_at),
        ];
    }

    private function date(?DateTimeInterface $date): string
    {
        return $date?->format('Y-m-d H:i') ?? '';
    }
}
