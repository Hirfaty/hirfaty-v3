<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Rules\ValidProductMedia;
use App\Services\MediaHealthService;
use App\Services\ProductPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The products list's photo shortcuts: replace or add one photo from a
 * dialog, fetch a pasted image link for it, and re-run the broken-image check.
 */
class ProductPhotoController extends Controller
{
    /** Replace `media_id` with the uploaded photo, or add it when none is given. */
    public function store(Request $request, Product $product, ProductPhotoService $photos): RedirectResponse
    {
        $data = $request->validate([
            'media_id' => ['nullable', 'integer'],
            // Same rules as the edit screen's gallery, photos only.
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', new ValidProductMedia],
        ]);

        if ($data['media_id'] ?? null) {
            $media = $photos->find($product, (int) $data['media_id']);
            abort_if($media === null, 404);

            $photos->replace($media, $request->file('file'));

            return back()->with('success', 'Photo remplacée.');
        }

        $photos->add($product, $request->file('file'));

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
