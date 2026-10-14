<?php

namespace App\Http\Controllers;

use App\Services\Themes\ThemeAssets;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ThemeAssetController extends Controller
{
    public function __invoke(Request $request, ThemeAssets $assets, string $theme, string $revision, string $path): BinaryFileResponse
    {
        try {
            $asset = $assets->resource($theme, $revision, $path);
        } catch (InvalidArgumentException|RuntimeException|JsonException) {
            abort(404);
        }
        $mime = match (pathinfo($asset['path'], PATHINFO_EXTENSION)) {
            'css' => 'text/css; charset=UTF-8', 'js' => 'text/javascript; charset=UTF-8',
            'svg' => 'image/svg+xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
            'ttf' => 'font/ttf', 'otf' => 'font/otf', 'ico' => 'image/x-icon',
            'mp4' => 'video/mp4', 'webm' => 'video/webm',
            'webmanifest' => 'application/manifest+json',
            default => 'image/'.(in_array(pathinfo($asset['path'], PATHINFO_EXTENSION), ['jpg', 'jpeg'], true) ? 'jpeg' : pathinfo($asset['path'], PATHINFO_EXTENSION)),
        };
        $response = response()->file($asset['path'], ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff']);
        $response->setPublic()->setMaxAge(31536000)->setSharedMaxAge(31536000)->setImmutable();
        $response->setEtag($asset['sha256']);
        $response->isNotModified($request);

        return $response;
    }
}
