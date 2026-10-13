<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        if (auth()->check()) {
            $wishlist = auth()->user()->wishlists()->with('product.category', 'product.variants', 'product.optionGroups.values')->latest()->get();
        } else {
            $request->validate(['ids' => 'nullable|string|max:2000']);
            $ids = $request->query('ids') ? explode(',', $request->query('ids')) : [];
            $products = Product::active()->whereIn('id', $ids)->with(['category', 'variants', 'optionGroups.values'])->get();
            $wishlist = $products->map(function ($product) {
                return (object)[
                    'id' => 'guest-' . $product->id,
                    'product_id' => $product->id,
                    'product' => $product
                ];
            });
        }
        return view('pages.wishlist', compact('wishlist'));
    }

    public function toggle(Product $product): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $added = false;
            $message = 'Removed from wishlist.';
        } else {
            Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
            $added = true;
            $message = 'Added to wishlist!';
        }

        return response()->json([
            'success' => true,
            'added'   => $added,
            'message' => $message,
            'count'   => $user->wishlists()->count(),
        ]);
    }
}

