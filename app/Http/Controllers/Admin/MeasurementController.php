<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementSubmission;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeasurementController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter');
        $submissions = MeasurementSubmission::with('order')
            ->when($filter === 'unmatched', fn ($q) => $q->whereNull('order_id'))
            ->when($filter === 'unreviewed', fn ($q) => $q->whereNull('reviewed_at'))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.measurements.index', [
            'submissions' => $submissions,
            'filter' => $filter,
            'unmatched' => MeasurementSubmission::whereNull('order_id')->count(),
            'unreviewed' => MeasurementSubmission::whereNull('reviewed_at')->count(),
        ]);
    }

    public function show(MeasurementSubmission $measurement): View
    {
        return view('admin.measurements.show', ['submission' => $measurement->load('order')]);
    }

    /** Link to an order (by order number) and/or mark as reviewed. */
    public function update(Request $request, MeasurementSubmission $measurement): RedirectResponse
    {
        $data = $request->validate(['order_number' => 'nullable|string|max:64', 'reviewed' => 'sometimes|boolean']);
        $changes = [];
        if ($request->filled('order_number')) {
            $order = Order::where('order_number', strtoupper(trim($data['order_number'])))->first();
            if (! $order) {
                return back()->withErrors(['order_number' => 'No order has that number.'])->withInput();
            }
            $changes['order_id'] = $order->id;
        }
        if ($request->has('reviewed')) {
            $changes['reviewed_at'] = $request->boolean('reviewed') ? now() : null;
        }
        $measurement->update($changes);

        return back()->with('success', 'Measurement submission updated.');
    }

    public function photo(MeasurementSubmission $measurement, int $index): BinaryFileResponse
    {
        $path = $measurement->photos[$index] ?? abort(404);
        abort_unless(str_starts_with($path, 'measurements/'.$measurement->id.'/') && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }
}
