<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use App\Services\ProductConfigurationService;
use App\Services\ProductCustomizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private CartService $cart,
        private ProductConfigurationService $configurations,
        private CheckoutAttemptService $attempts,
    ) {}

    public function index(): View
    {
        $items = $this->cart->items();
        $totals = $this->cart->totals();
        $coupon = $this->cart->getAppliedCoupon();

        return view('pages.cart', compact('items', 'totals', 'coupon'));
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'option_value_ids' => 'nullable|array',
            'option_value_ids.*' => 'integer|exists:product_option_values,id',
            'special_request' => 'nullable|string|max:255',
            'options.special_request' => 'nullable|string|max:255',
            'quantity' => 'integer|min:1|max:20',
            'option_inputs' => 'nullable|array',
            'option_inputs.*' => 'nullable|string|max:120',
            'pick_order' => 'nullable|array',
            'pick_order.*' => 'nullable|string|max:120',
            'design_files' => 'nullable|array|max:'.ProductCustomizationService::MAX_FILES,
            'design_files.*' => 'file|mimes:png,jpg,jpeg,pdf|max:'.ProductCustomizationService::MAX_KB,
        ], ['design_files.*.mimes' => 'Design files must be PNG, JPG or PDF.', 'design_files.*.max' => 'Each design file can be up to '.(ProductCustomizationService::MAX_KB / 1024).' MB.']);

        $product = Product::active()
            ->with(['variants', 'optionGroups.values'])
            ->findOrFail($request->product_id);

        if (! $product->in_stock) {
            $msg = 'This product is currently out of stock.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $specialRequest = $request->input('special_request')
            ?? $request->input('options.special_request');

        $customizations = app(ProductCustomizationService::class);
        $storedFiles = [];
        $added = false;
        try {
            $customization = $customizations->fromRequest(
                $product,
                $request->input('option_value_ids', []),
                (array) $request->input('option_inputs', []),
                (array) $request->input('pick_order', [])
            );
            // Validate everything (options, required text, product rules) before any file is written.
            $config = $this->configurations->resolve(
                $product,
                $request->integer('product_variant_id') ?: null,
                $request->input('option_value_ids', []),
                $specialRequest,
                $customization
            );

            $uploads = array_values(array_filter((array) $request->file('design_files', [])));
            if ($uploads !== []) {
                if (! $product->allows_design_upload) {
                    throw ValidationException::withMessages(['design_files' => 'Design files are not accepted for this product.']);
                }
                $storedFiles = $customizations->storeFiles($uploads);
                $customization = [...($customization ?? []), 'files' => $storedFiles];
            }

            $this->cart->add(
                $product,
                $request->integer('quantity', 1),
                $config['variant'],
                $request->input('option_value_ids', []),
                $specialRequest,
                $customization
            );
            $added = true;
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Invalid product configuration.',
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        } finally {
            // Never leave design files behind when the line was not added (validation or any other failure).
            if (! $added) {
                $customizations->deleteFiles(['files' => $storedFiles]);
            }
        }

        $msg = "{$product->title} added to cart!";

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $msg, 'count' => $this->cart->count()])
            : back()->with('success', $msg);
    }

    public function update(Request $request, int $item): RedirectResponse|JsonResponse
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:0|max:20',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'option_value_ids' => 'nullable|array',
            'option_value_ids.*' => 'integer|exists:product_option_values,id',
            'special_request' => 'nullable|string|max:255',
        ]);

        $quantity = $request->has('quantity') ? $request->integer('quantity') : null;
        $variantId = $request->has('product_variant_id') ? $request->integer('product_variant_id') : null;
        $optionValueIds = $request->has('option_value_ids') ? $request->input('option_value_ids', []) : null;
        $updateSpecialRequest = $request->has('special_request');

        try {
            $this->cart->update(
                $item,
                $quantity,
                $variantId,
                $optionValueIds,
                $request->input('special_request'),
                $updateSpecialRequest
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Invalid product configuration.',
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        $totals = $this->cart->totals();

        return $request->expectsJson()
            ? response()->json(['success' => true, 'totals' => $totals, 'count' => $this->cart->count()])
            : back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, int $item): RedirectResponse|JsonResponse
    {
        $this->cart->remove($item);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'count' => $this->cart->count()])
            : back()->with('success', 'Item removed from cart.');
    }

    public function applyCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['code' => 'required|string|max:50', 'email' => 'nullable|email|max:255']);
        $result = $this->cart->applyCoupon($request->code, $request->input('email'));

        if ($request->expectsJson()) {
            $result['totals'] = $this->cart->totals();
            if ($result['success']) {
                $result['idempotency_key'] = $this->attempts->refreshDraft($request->input('idempotency_key'));
            }
        }

        return $request->expectsJson()
            ? response()->json($result, $result['success'] ? 200 : 422)
            : back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function removeCoupon(Request $request): RedirectResponse|JsonResponse
    {
        $this->cart->removeCoupon();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'totals' => $this->cart->totals(),
                'idempotency_key' => $this->attempts->refreshDraft($request->input('idempotency_key')),
            ]);
        }

        return back()->with('success', 'Coupon removed.');
    }
}
