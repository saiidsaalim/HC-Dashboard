<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalWorkspaceController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse
    {
        $workspace = collect(config('portal.units'))
            ->flatMap(fn (array $unit) => $unit['workspaces'])
            ->firstWhere('slug', $slug);

        abort_unless(is_array($workspace), 404);

        $user = $request->user();

        abort_unless($user instanceof User && $user->hasPermission($workspace['permission']), 403);

        $request->session()->put('active_workspace', $slug);

        return to_route($workspace['home_route'] ?? 'dashboard');
    }
}
