<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::withCount(['stores', 'orders'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/cities/index', [
            'cities' => $cities,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cities,name'],
            'region' => ['nullable', 'string', 'max:255'],
        ]);

        City::create([
            'name' => $data['name'],
            'region' => $data['region'] ?? null,
        ]);

        return back()->with('success', "City \"{$data['name']}\" added to the registry.");
    }

    public function update(Request $request, City $city)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', "unique:cities,name,{$city->id}"],
            'region' => ['nullable', 'string', 'max:255'],
        ]);

        $city->update([
            'name' => $data['name'],
            'region' => $data['region'] ?? null,
        ]);

        return back()->with('success', 'City records updated.');
    }

    public function destroy(City $city)
    {
        if ($city->stores()->count() > 0 || $city->orders()->count() > 0) {
            return back()->withErrors([
                'delete' => "Cannot delete \"{$city->name}\" — it has stores or orders attached.",
            ]);
        }

        $city->delete();

        return back()->with('success', 'City deleted.');
    }
}
