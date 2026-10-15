<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\DeliveryZone;
use App\Services\LocalDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mavyn\Contracts\Pricing\PriceBook;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        $user = auth()->user();

        // Load last 5 orders for the recent orders list.
        $orders = $user->orders()->latest()->take(5)->get();

        $spentByCurrency = $user->orders()->where('status', 'delivered')->selectRaw('currency, SUM(grand_total) as spent')->groupBy('currency')->pluck('spent', 'currency')->all();
        $totalOrders = $user->orders()->count();

        $wishlistCount = $user->wishlists()->count();

        return view('account.dashboard', [
            'user' => $user,
            'orders' => $orders,
            'totalOrders' => $totalOrders,
            'spentByCurrency' => $spentByCurrency,
            'totalSpent' => $spentByCurrency[app(PriceBook::class)->context()->currency] ?? '0.00',
            'wishlistCount' => $wishlistCount,
        ]);
    }

    public function profile(): View
    {
        return view('account.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|string|email|max:255|unique:users,email,'.auth()->id(),
        ]);
        $user = $request->user('web');
        $user->fill($data);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return back()->with('success', 'Profile updated successfully!');
    }

    public function password(): View
    {
        return view('account.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);
        auth()->user()->update(['password' => bcrypt($request->password)]);

        return back()->with('success', 'Password changed successfully!');
    }

    public function addresses(): View
    {
        $addresses = auth()->user()->addresses()->latest()->get();
        $zones = collect(app(LocalDeliveryService::class)->zones());

        return view('account.addresses', compact('addresses', 'zones'));
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'delivery_zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'area' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30',
            'address_line' => 'required|string|max:500',
            'is_default' => 'boolean',
        ]);

        $zone = $this->addressZone($data['delivery_zone_id'] ?? null);
        $data['delivery_zone_id'] = $zone?->id;
        $data['area'] = $zone?->name ?? ($data['area'] ?? null);

        if (! empty($data['is_default'])) {
            auth()->user()->addresses()->update(['is_default' => false]);
        }

        auth()->user()->addresses()->create($data);

        return back()->with('success', 'Address saved!');
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        if ($address->user_id !== auth()->id()) {
            abort(403);
        }
        $data = $request->validate([
            'label' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'delivery_zone_id' => 'nullable|integer|exists:delivery_zones,id',
            'area' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30',
            'address_line' => 'required|string|max:500',
        ]);

        $zone = $this->addressZone($data['delivery_zone_id'] ?? null);
        $data['delivery_zone_id'] = $zone?->id;
        $data['area'] = $zone?->name ?? ($data['area'] ?? null);

        $address->update($data);

        return back()->with('success', 'Address updated!');
    }

    public function deleteAddress(Address $address): RedirectResponse
    {
        if ($address->user_id !== auth()->id()) {
            abort(403);
        }
        $address->delete();

        return back()->with('success', 'Address deleted.');
    }

    private function addressZone(?int $id): ?DeliveryZone
    {
        if ($id === null) {
            return null;
        }
        // Legacy zone associations remain guarded; generic addresses need no plugin.
        abort_unless(plugin_active('mavyn.local-delivery'), 404);

        return DeliveryZone::where('is_active', true)->findOrFail($id);
    }

    public function setDefaultAddress(Address $address): RedirectResponse
    {
        if ($address->user_id !== auth()->id()) {
            abort(403);
        }
        auth()->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address updated!');
    }

    public function wishlist(): View
    {
        $wishlist = auth()->user()->wishlists()->with('product.category', 'product.variants', 'product.optionGroups.values')->latest()->get();

        return view('account.wishlist', compact('wishlist'));
    }
}
