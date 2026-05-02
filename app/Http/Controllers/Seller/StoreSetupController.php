<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use Illuminate\Http\Request;
use App\Models\Store;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StoreSetupController extends Controller
{
    public function show() {
        $user = auth()->user()->store;
        $categories = Category::whereNull('parent_id')->get();
        $cities     = City::orderBy('name')->get();
        if ($user) {
            return redirect()->route('seller.dashboard');
        }
        // return view('seller.setup.store', compact('categories', 'cities'));
        return Inertia::render("seller/setup_store", compact('categories', 'cities'));
    
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'store_name'  => ['required', 'string', 'max:255', 'unique:stores'],
            'category_id' => ['required', 'exists:categories,id'],
            'city_id'     => ['required', 'exists:cities,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (auth()->user()->store) {
            return back()->withErrors([
                'store' => 'You already have a store.'
            ]);
        }

        Store::create([
            ...$data,
            'user_id' => auth()->id(),
            'slug'    => Str::slug($data['store_name']),
        ]);

        return redirect()->route('seller.dashboard')
            ->with('success', 'Your store is ready! Start adding products.');
    }


}
