<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['actor_id' => 'nullable|integer|min:1', 'entity_type' => 'nullable|string|max:100']);
        $logs = AdminAuditLog::query()->when($filters['actor_id'] ?? null, fn ($q, $id) => $q->where('actor_id', $id))
            ->when($filters['entity_type'] ?? null, fn ($q, $type) => $q->where('entity_type', $type))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.audit', compact('logs'));
    }
}
