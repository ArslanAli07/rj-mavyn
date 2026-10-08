<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function index(): View
    {
        $customers = User::withCount('orders')
            ->orderByDesc('orders_count')
            ->paginate(20);

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $user): View
    {
        $user->load(['orders' => fn ($q) => $q->latest()->take(10)]);

        return view('admin.customers.show', compact('user'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'users', ['delete']);
        $customers = User::whereIn('id', $data['ids'])->get();
        AdminMutation::delete(User::whereIn('id', $customers->pluck('id')));

        return back()->with('success', $this->bulkMessage('delete', $customers->count(), 'customer'));
    }
}
