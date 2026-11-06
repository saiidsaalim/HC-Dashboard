<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** @var array<int, int> */
    protected const PER_PAGE_OPTIONS = [20, 40, 60];

    protected function perPage(Request $request, string $parameter = 'per_page'): int
    {
        $requestedPerPage = $request->integer($parameter);

        return in_array($requestedPerPage, self::PER_PAGE_OPTIONS, true)
            ? $requestedPerPage
            : self::PER_PAGE_OPTIONS[0];
    }
}
