<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\DeliveryZone;
use App\Services\LocalDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(): View
    {
        $user = auth()->user();

        // Load last 5 orders for the recent orders list.
        $orders = $user->orders()->latest()->take(5)->get();

        // Single aggregated query replaces 3 separate count()/sum() calls.
        $orderStats = $user->orders()
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "delivered" THEN grand_total ELSE 0 END) as spent')
            ->first();

        $wishlistCount = $user->wishlists()->count();

        return view('account.dashboard', [
            'user' => $user,
            'orders' => $orders,
            'totalOrders' => $orderStats->total ?? 0,
            'totalSpent' => $orderStats->spent ?? 0,
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
            'delivery_zone_id' => 'required|exists:delivery_zones,id',
            'address_line' => 'required|string|max:500',
            'is_default' => 'boolean',
        ]);

        $zone = DeliveryZone::find($data['delivery_zone_id']);
        $data['area'] = $zone?->name;

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
            'delivery_zone_id' => 'required|exists:delivery_zones,id',
            'address_line' => 'required|string|max:500',
        ]);

        $zone = DeliveryZone::find($data['delivery_zone_id']);
        $data['area'] = $zone?->name;

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
