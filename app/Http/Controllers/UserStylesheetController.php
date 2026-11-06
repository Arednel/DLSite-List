<?php

namespace App\Http\Controllers;

use App\Support\UserStylesheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class UserStylesheetController extends Controller
{
    public function __invoke(UserStylesheet $stylesheet): BinaryFileResponse
    {
        return response()->file($stylesheet->path(), [
            'Content-Type' => 'text/css; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
