<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * The admin products list's quick photos dialog: swap one product or variant
 * photo for a new file in place, or add one to the product's gallery or a
 * variant's, without opening the edit screen.
 *
 * Also fetches a pasted image link server-side (fetchRemote), so the browser
 * can crop pictures from hosts that send no CORS headers — Google Merchant
 * Center's and Google Images' among them.
 */
class ProductPhotoService
{
    /** Bigger than any product photo worth recovering, small enough to hold in memory. */
    private const REMOTE_MAX_BYTES = 10 * 1024 * 1024;

    /**
     * The dialog's picture of a product: its own gallery first, then each
     * variant's, every group listed even when it has no photo yet so one can
     * be added to it.
     *
     * @return list<array{variant_id: int|null, label: string, photos: list<array{id: int, url: string, broken: bool}>}>
     */
    public function groups(Product $product): array
    {
        $product->load(['images', 'variants.options', 'variants.media']);

        $photos = fn ($media) => $media
            ->where('type', 'image')
            ->sortBy('order')
            ->map(fn (Media $m) => ['id' => $m->id, 'url' => $m->url, 'broken' => $m->broken_at !== null])
            ->values()
            ->all();

        return [
            ['variant_id' => null, 'label' => 'Galerie du produit', 'photos' => $photos($product->images)],
            ...$product->variants->map(fn (ProductVariant $variant) => [
                'variant_id' => $variant->id,
                'label' => $variant->label() ?: "Déclinaison #{$variant->id}",
                'photos' => $photos($variant->media),
            ])->all(),
        ];
    }

    /** One of this product's variants, or null when the id belongs elsewhere. */
    public function variant(Product $product, int $variantId): ?ProductVariant
    {
        return $product->variants()->whereKey($variantId)->first();
    }

    /**
     * The photo with this id on the product itself or on one of its variants.
     */
    public function find(Product $product, int $mediaId): ?Media
    {
        return Media::query()
            ->whereKey($mediaId)
            ->where('type', 'image')
            ->where(fn ($q) => $q
                ->whereMorphedTo('mediable', $product)
                ->orWhereHasMorph('mediable', [ProductVariant::class], fn ($v) => $v->where('product_id', $product->id)))
            ->first();
    }

    /**
     * Store `$file` and point `$media` at it — same slot, same order — then
     * remove the old file. The photo counts as healthy again at once, so the
     * product leaves the "Images cassées" filter without waiting for a scan.
     */
    public function replace(Media $media, UploadedFile $file): Media
    {
        $disk = config('filesystems.default');
        $oldDisk = $media->disk;
        $oldPath = $media->path;

        $path = $file->store($this->folderOf($media), ['disk' => $disk, 'visibility' => 'public']);

        $media->forceFill([
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'broken_at' => null,
            'checked_at' => now(),
        ])->save();

        // A lost file usually cannot be deleted either; that must not undo the swap.
        try {
            Storage::disk($oldDisk)->delete($oldPath);
        } catch (Throwable $e) {
            Log::warning("Could not delete replaced media file {$oldPath}: ".$e->getMessage());
        }

        return $media;
    }

    /** Append `$file` to the end of the product's own gallery, or of one variant's. */
    public function add(Product $product, UploadedFile $file, ?ProductVariant $variant = null): Media
    {
        $owner = $variant ?? $product;
        $folder = $variant ? "products/{$product->id}/variants/{$variant->id}" : "products/{$product->id}";

        $disk = config('filesystems.default');
        $path = $file->store($folder, ['disk' => $disk, 'visibility' => 'public']);
        $highest = $owner->media()->max('order');

        return $owner->media()->create([
            'type' => 'image',
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'order' => $highest === null ? 0 : (int) $highest + 1,
            // Read just now, from the upload itself.
            'checked_at' => now(),
        ]);
    }

    /**
     * Download the image at a pasted link.
     *
     * Admin-only, but the server sits next to the database and SeaweedFS, so
     * only public hosts are fetched — redirects included.
     *
     * @return array{body: string, mime: string}
     *
     * @throws ValidationException
     */
    public function fetchRemote(string $url): array
    {
        $parts = parse_url($url);

        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            $this->fail('Collez un lien d\'image commençant par http:// ou https://.');
        }

        $this->assertPublicHost($parts['host']);

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'image/*'])
                ->withOptions(['allow_redirects' => [
                    'max' => 3,
                    'on_redirect' => fn ($request, $response, UriInterface $uri) => $this->assertPublicHost($uri->getHost()),
                ]])
                ->get($url);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            $this->fail('Impossible de télécharger cette image. Vérifiez le lien.');
        }

        $body = $response->successful() ? $response->body() : '';

        if ($body === '') {
            $this->fail('Ce lien ne renvoie pas d\'image.');
        }

        if (strlen($body) > self::REMOTE_MAX_BYTES) {
            $this->fail('Cette image dépasse 10 Mo.');
        }

        $info = @getimagesizefromstring($body);

        if ($info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $this->fail('Ce lien ne renvoie pas une image JPEG, PNG, WebP ou GIF.');
        }

        return ['body' => $body, 'mime' => $info['mime']];
    }

    /** Overridable in tests, which cannot rely on DNS. */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return gethostbynamel($host) ?: [];
    }

    private function assertPublicHost(string $host): void
    {
        $ips = $this->resolve(trim($host, '[]'));

        $public = $ips !== [] && collect($ips)->every(fn (string $ip) => filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false);

        if (! $public) {
            $this->fail('Ce lien ne pointe pas vers un site public.');
        }
    }

    /** Where uploads for this photo's owner live — the same folders the edit screen uses. */
    private function folderOf(Media $media): string
    {
        $owner = $media->mediable;

        return $owner instanceof ProductVariant
            ? "products/{$owner->product_id}/variants/{$owner->id}"
            : "products/{$media->mediable_id}";
    }

    /** @throws ValidationException */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['url' => $message]);
    }
}
