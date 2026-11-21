<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Services\Media\ContentMediaService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function about(): Response
    {
        return response()
            ->view('pages.about')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function gallery(): Response
    {
        $galleryImages = app(ContentMediaService::class)->gallery();
        $galleryItems = $galleryImages;

        return response()
            ->view('pages.gallery', compact('galleryImages', 'galleryItems'))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /** FAQ content is cached for 24 hours in-app. */
    public function faq(): Response
    {
        $faqs = Cache::remember('faqs', 86400, fn () => Faq::active()->orderBy('display_order')->get()->groupBy('category')
        );

        return response()
            ->view('pages.faq', compact('faqs'))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function shipping(): Response
    {
        return response()
            ->view('legal.shipping')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function delivery(): Response
    {
        return $this->shipping();
    }

    public function privacy(): Response
    {
        return response()
            ->view('legal.privacy')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function terms(): Response
    {
        return response()
            ->view('legal.terms')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function returns(): Response
    {
        return response()
            ->view('legal.returns')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}
