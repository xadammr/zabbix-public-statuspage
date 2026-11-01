<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $manifest = [
            'name' => config('statuspage.name'),
            'short_name' => config('statuspage.short_name'),
            'theme_color' => config('statuspage.theme_color'),
            'background_color' => config('statuspage.theme_color'),
            'display' => 'standalone',
        ];

        if (config('statuspage.favicon_url')) {
            $manifest['icons'] = [[
                'src' => config('statuspage.favicon_url'),
                'sizes' => 'any',
            ]];
        }

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }
}
