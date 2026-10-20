<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CityService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where the shops are. Read-only: a city enters the registry from the
 * delivery-rates page, the same place that decides whether it can be
 * delivered to.
 */
class CityController extends Controller
{
    public function index(CityService $cities): Response
    {
        return Inertia::render('admin/cities/index', $cities->adminIndex());
    }
}
