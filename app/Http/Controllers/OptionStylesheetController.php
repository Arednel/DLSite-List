<?php

namespace App\Http\Controllers;

use App\Support\OptionStylesheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class OptionStylesheetController extends Controller
{
    public function __invoke(OptionStylesheet $stylesheet): BinaryFileResponse
    {
        return response()->file($stylesheet->path(), [
            'Content-Type' => 'text/css; charset=UTF-8',
        ]);
    }
}
