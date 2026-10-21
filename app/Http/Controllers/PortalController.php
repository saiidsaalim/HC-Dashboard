<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $canOpenWorkspaces = $user instanceof User && $user->hasPermission('workspace.view');
        $units = collect(config('portal.units'))
            ->map(function (array $unit) use ($canOpenWorkspaces, $user): array {
                $workspaces = collect($unit['workspaces'])
                    ->filter(fn (array $workspace): bool => $canOpenWorkspaces
                        && $user instanceof User
                        && $user->hasPermission($workspace['permission']))
                    ->values()
                    ->all();

                return [...$unit, 'workspaces' => $workspaces];
            })
            ->filter(fn (array $unit): bool => $unit['workspaces'] !== [])
            ->values()
            ->all();

        return view('portal.index', [
            'units' => $units,
            'footer' => config('portal.footer'),
        ]);
    }
}
