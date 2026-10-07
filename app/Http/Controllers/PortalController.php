<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PortalController extends Controller
{
    public function __invoke(): View
    {
        return view('portal.index', [
            'modules' => config('portal.modules'),
            'units' => config('portal.units'),
            'footer' => config('portal.footer'),
            'heroAsset' => collect(config('portal.assets', []))->firstWhere('key', 'hero-building'),
        ]);
    }
}
