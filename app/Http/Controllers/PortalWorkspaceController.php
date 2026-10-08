<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class PortalWorkspaceController extends Controller
{
    public function __invoke(string $slug): RedirectResponse
    {
        $workspaceExists = collect(config('portal.units'))
            ->contains(fn (array $unit): bool => collect($unit['workspaces'])->contains('slug', $slug));

        abort_unless($workspaceExists, 404);

        return to_route('dashboard');
    }
}
