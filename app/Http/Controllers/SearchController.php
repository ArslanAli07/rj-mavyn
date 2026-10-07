<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->get('q', '');

        $products = collect();
        if (strlen($q) >= 2) {
            $query = Product::active()->with(['category', 'variants', 'optionGroups.values']);

            // Use FULLTEXT MATCH...AGAINST when available (MySQL/MariaDB).
            // Falls back to indexed LIKE on title + short_description only.
            // The longText `description` column was removed — it can't be indexed
            // and forces a full table scan on every search.
            if (DB::getDriverName() === 'mysql') {
                $query->whereRaw(
                    "MATCH(title, short_description) AGAINST(? IN BOOLEAN MODE)",
                    [$q . '*']
                );
            } else {
                $query->where(function ($q2) use ($q) {
                    $q2->where('title', 'LIKE', "%{$q}%")
                       ->orWhere('short_description', 'LIKE', "%{$q}%");
                });
            }

            $products = $query
                ->select('id', 'title', 'slug', 'price', 'discount_price', 'main_image', 'category_id', 'short_description', 'is_active')
                ->paginate(12)
                ->withQueryString();
        }

        return view('pages.search', compact('products', 'q'));
    }

    public function suggestions(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $products = Product::active()
            ->where('title', 'LIKE', "%{$q}%")
            ->select('id', 'title', 'slug', 'price', 'discount_price', 'main_image')
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'id'    => $p->id,
                'title' => $p->title,
                'url'   => route('product.show', $p->slug),
                'price' => format_price($p->effective_price),
                'image' => $p->main_image_url,
            ]);

        return response()->json($products);
    }
}
