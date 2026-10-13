<x-admin-layout title="Audit history">
<h1 class="mb-3 admin-page-title">Audit history</h1>
<form method="GET" class="mb-3 d-flex gap-2"><input class="form-control border rounded p-2 num" name="actor_id" type="number" placeholder="Staff ID" value="{{ request('actor_id') }}"><input class="form-control border rounded p-2" name="entity_type" placeholder="Entity type" value="{{ request('entity_type') }}"><button class="btn btn-primary">Filter</button></form>
@forelse($logs as $log)
<article class="bg-white border rounded-2 p-3 mb-3">
<p class="fw-semibold">{{ $log->actor_name }} (#{{ $log->actor_id }}) · {{ $log->action }} · {{ $log->entity_type }} #{{ $log->entity_id }}</p>
<p class="small">{{ $log->created_at }} · {{ $log->route }}</p>
<pre class="small overflow-auto mt-2">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</article>
@empty <p>No audit records match.</p> @endforelse
{{ $logs->links('pagination::bootstrap-5') }}
</x-admin-layout>
