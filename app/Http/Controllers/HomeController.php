<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\Testimonial;
use App\Services\Media\ContentMediaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        /*
         * The landing page shows each group of the catalogue. The products of a
         * group live in the categories underneath it, so the group's own
         * products are collected across the whole branch.
         */
        $homeCategories = Category::active()
            ->where('show_on_homepage', true)
            ->whereNull('parent_id')
            ->orderBy('display_order')
            ->get();

        foreach ($homeCategories as $category) {
            $category->setRelation('products', $this->branchProducts($category));
        }

        $testimonials = Cache::remember('home_testimonials', 3600, fn () => Testimonial::featured()->orderBy('display_order')->take(6)->get()
        );

        $slides = Cache::remember('home_slides_list', 3600, fn () => HomeSlide::active()->orderBy('display_order')->get()
        );

        $galleryImages = app(ContentMediaService::class)->gallery();

        return view('pages.home', compact(
            'homeCategories', 'testimonials', 'slides', 'galleryImages'
        ));
    }

    /**
     * The products of a group and everything filed underneath it.
     */
    private function branchProducts(Category $category)
    {
        return Product::active()
            ->whereIn('category_id', $category->descendantIds())
            ->with(['variants', 'optionGroups.values'])
            ->orderByDesc('is_featured')
            ->orderByDesc('is_bestseller')
            ->orderBy('display_order')
            ->latest()
            ->limit(12)
            ->get();
    }
}
