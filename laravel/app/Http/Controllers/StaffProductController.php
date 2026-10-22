<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffProductController extends Controller
{
    public function index(Request $request, GymAdministrationController $products): View
    {
        return $products->index($request, 'products');
    }

    public function create(Request $request, GymAdministrationController $products): View
    {
        return $products->edit($request, 'products');
    }

    public function edit(Request $request, int $product, GymAdministrationController $products): View
    {
        return $products->edit($request, 'products', $product);
    }

    public function store(Request $request, GymAdministrationController $products): RedirectResponse
    {
        return $products->save($request, 'products');
    }

    public function update(Request $request, int $product, GymAdministrationController $products): RedirectResponse
    {
        return $products->save($request, 'products', $product);
    }

    public function image(Request $request, int $product, ?int $image = null): RedirectResponse
    {
        return app(GymAdministrationController::class)->image($request, $product, $image);
    }
}
