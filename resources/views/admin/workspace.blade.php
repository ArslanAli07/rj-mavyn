<x-admin-layout title="Workspace">
<h1 class="text-2xl font-bold mb-4">Your workspace</h1>
<p class="mb-6">Choose an area you have access to.</p>
<div class="grid gap-3 sm:grid-cols-3">
@foreach(['admin.dashboard' => 'Dashboard', 'admin.products.index' => 'Catalog', 'admin.orders.index' => 'Orders', 'admin.customers.index' => 'Customers', 'admin.media.index' => 'Media', 'admin.staff.index' => 'Staff access', 'admin.audit.index' => 'Audit history'] as $route => $label)
    @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), $route))
        <a class="p-4 bg-white rounded-xl border" href="{{ route($route) }}">{{ $label }}</a>
    @endif
@endforeach
</div>
</x-admin-layout>
