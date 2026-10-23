<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Rules\ValidProductMedia;
use App\Services\MediaHealthService;
use App\Services\ProductPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The products list's photo shortcuts: replace or add one photo from a
 * dialog, fetch a pasted image link for it, and re-run the broken-image check.
 */
class ProductPhotoController extends Controller
{
    /**
     * The dialog loads the photos itself rather than reading the list's rows:
     * a product it just fixed may leave a filtered list ("Images cassées")
     * while the dialog is still open on it.
     */
    public function index(Product $product, ProductPhotoService $photos): JsonResponse
    {
        return response()->json(['groups' => $photos->groups($product)]);
    }

    /**
     * Replace `media_id` with the uploaded photo, or add it — to `variant_id`'s
     * photos when given, else to the product's gallery.
     */
    public function store(Request $request, Product $product, ProductPhotoService $photos): RedirectResponse
    {
        $data = $request->validate([
            'media_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            // Same rules as the edit screen's gallery, photos only.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', new ValidProductMedia],
        ]);

        if ($data['media_id'] ?? null) {
            $media = $photos->find($product, (int) $data['media_id']);
            abort_if($media === null, 404);

            $photos->replace($media, $request->file('file'));

            return back()->with('success', 'Photo remplacée.');
        }

        $variant = null;

        if ($data['variant_id'] ?? null) {
            $variant = $photos->variant($product, (int) $data['variant_id']);
            abort_if($variant === null, 404);
        }

        $photos->add($product, $request->file('file'), $variant);

        return back()->with('success', 'Photo ajoutée.');
    }

    /** The image behind a pasted link, served from here so the browser may crop it. */
    public function remote(Request $request, ProductPhotoService $photos): Response
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:4000']]);

        $image = $photos->fetchRemote($data['url']);

        return response($image['body'], 200, [
            'Content-Type' => $image['mime'],
            'Cache-Control' => 'no-store',
        ]);
    }

    public function checkImages(MediaHealthService $health): RedirectResponse
    {
        $count = $health->queueScan();

        return back()->with('success', $count === 0
            ? 'Aucune photo à vérifier.'
            : "Vérification de {$count} photos lancée — rechargez la page dans quelques minutes.");
    }
}
