<x-admin-layout title="Workspace">
<h1 class="mb-3 admin-page-title">Your workspace</h1>
<p class="mb-4">Choose an area you have access to.</p>
<div class="d-grid gap-3 admin-cols-sm-3">
@foreach(['admin.dashboard' => 'Dashboard', 'admin.products.index' => 'Catalog', 'admin.orders.index' => 'Orders', 'admin.customers.index' => 'Customers', 'admin.media.index' => 'Media', 'admin.staff.index' => 'Staff access', 'admin.audit.index' => 'Audit history'] as $route => $label)
    @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), $route))
        <a class="p-3 bg-white rounded-2 border" href="{{ route($route) }}">{{ $label }}</a>
    @endif
@endforeach
</div>
</x-admin-layout>
