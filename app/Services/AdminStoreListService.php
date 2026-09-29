<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin workshops table and its download — one filter builder for both,
 * so the file is what the admin is looking at, only unpaginated.
 */
class AdminStoreListService
{
    private const TYPE_LABELS = [
        'artist' => 'Artiste',
        'company' => 'Entreprise',
        'cooperative' => 'Coopérative',
    ];

    private const VERIFICATION_LABELS = [
        Store::VERIFICATION_PENDING => 'En attente',
        Store::VERIFICATION_APPROVED => 'Vérifié',
        Store::VERIFICATION_REJECTED => 'Refusé',
    ];

    /** Same wording as the "manque : …" hint on the admin page. */
    private const FIELD_LABELS = [
        'store_name' => 'nom',
        'category_id' => 'métier',
        'city_id' => 'ville',
        'description' => 'présentation',
    ];

    public function __construct(
        private readonly SpreadsheetExportService $spreadsheets,
    ) {}

    /**
     * The filters and nothing else — no eager loads, no ordering.
     *
     * @param  array{search?: ?string, status?: ?string}  $filters
     * @return Builder<Store>
     */
    public function query(array $filters): Builder
    {
        return Store::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('store_name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('verification_status', $status));
    }

    /**
     * Every workshop the filters match, newest first, as CSV or Excel.
     *
     * @param  'csv'|'xlsx'  $format
     */
    public function export(string $format, array $filters): StreamedResponse|BinaryFileResponse
    {
        return $this->spreadsheets->download(
            $format,
            'ateliers',
            [
                'ID',
                'Atelier',
                'Type',
                'Catégorie',
                'Ville',
                'Propriétaire',
                'Email',
                'Téléphone',
                'Vérification',
                'Motif de refus',
                'En ligne',
                'Produits',
                'Champs manquants',
                'Soumis le',
                'Approuvé le',
                'Créé le',
                'Lien public',
            ],
            fn () => $this->query($filters)
                ->with(['user', 'category', 'city:id,name'])
                ->withCount('products')
                ->lazyByIdDesc(500)
                ->map(fn (Store $store) => $this->row($store)),
        );
    }

    /** @return list<string|int> */
    private function row(Store $store): array
    {
        $owner = $store->user;

        return [
            $store->id,
            $store->store_name,
            self::TYPE_LABELS[$store->type] ?? (string) $store->type,
            $store->category?->translated_name ?? '',
            $store->city?->name ?? '',
            trim(($owner?->first_name ?? '').' '.($owner?->last_name ?? '')),
            $owner?->email ?? '',
            $owner?->phone_number ?? '',
            self::VERIFICATION_LABELS[$store->verification_status] ?? (string) $store->verification_status,
            $store->rejection_reason ?? '',
            $store->status === 'active' ? 'Oui' : 'Non',
            (int) $store->products_count,
            implode(', ', array_map(fn (string $field) => self::FIELD_LABELS[$field] ?? $field, $store->missingProfileFields())),
            $store->submitted_at?->format('Y-m-d H:i') ?? '',
            $store->approved_at?->format('Y-m-d H:i') ?? '',
            $store->created_at?->format('Y-m-d H:i') ?? '',
            $store->status === 'active' ? route('stores.show', ['locale' => 'fr', 'slug' => $store->slug]) : '',
        ];
    }
}
