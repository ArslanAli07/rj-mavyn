<x-admin-layout title="Audit history">
<h1 class="text-2xl font-bold mb-4">Audit history</h1>
<form method="GET" class="mb-4 flex gap-2"><input class="border rounded p-2" name="actor_id" type="number" placeholder="Staff ID" value="{{ request('actor_id') }}"><input class="border rounded p-2" name="entity_type" placeholder="Entity type" value="{{ request('entity_type') }}"><button class="btn-primary">Filter</button></form>
@forelse($logs as $log)
<article class="bg-white border rounded-xl p-4 mb-3">
<p class="font-semibold">{{ $log->actor_name }} (#{{ $log->actor_id }}) · {{ $log->action }} · {{ $log->entity_type }} #{{ $log->entity_id }}</p>
<p class="text-sm">{{ $log->created_at }} · {{ $log->route }}</p>
<pre class="text-xs overflow-auto mt-2">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</article>
@empty <p>No audit records match.</p> @endforelse
{{ $logs->links() }}
</x-admin-layout>
