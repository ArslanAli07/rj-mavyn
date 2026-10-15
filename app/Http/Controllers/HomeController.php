<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Testimonial;
use App\Services\Media\ContentMediaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $homeCategories = Category::active()
            ->where('show_on_homepage', true)
            ->orderBy('display_order')
            ->with(['products' => fn ($q) => $q->active()
                ->with(['variants', 'optionGroups.values'])
                ->select('id', 'category_id', 'title', 'slug', 'price', 'discount_price', 'main_image', 'short_description', 'is_active', 'is_featured', 'is_bestseller', 'is_new_arrival', 'display_order')
                ->orderBy('display_order')
                ->latest(),
            ])
            ->get();

        $testimonials = Cache::remember('home_testimonials', 3600, fn () => Testimonial::featured()->orderBy('display_order')->take(6)->get()
        );

        $slides = Cache::remember('home_slides_list', 3600, fn () => HomeSlide::active()->orderBy('display_order')->get()
        );

        $galleryImages = app(ContentMediaService::class)->gallery();

        return view('pages.home', compact(
            'homeCategories', 'testimonials', 'slides', 'galleryImages'
        ));
    }
}
