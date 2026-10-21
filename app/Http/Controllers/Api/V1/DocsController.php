<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/** Serves the OpenAPI 3.1 specification (docs/api/openapi.yaml). */
class DocsController extends Controller
{
    public function openapi(): Response
    {
        $path = base_path('docs/api/openapi.yaml');
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
