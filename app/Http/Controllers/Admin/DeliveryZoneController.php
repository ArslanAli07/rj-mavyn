<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryZoneRequest;
use App\Models\DeliveryZone;
use App\Services\AdminMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryZoneController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function index(): View
    {
        $zones = DeliveryZone::orderBy('display_order')->paginate(30);

        return view('admin.delivery-zones.index', compact('zones'));
    }

    public function create(): View
    {
        return view('admin.delivery-zones.create');
    }

    public function store(DeliveryZoneRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        DeliveryZone::create($data);

        return redirect()->route('admin.delivery-zones.index')->with('success', 'Delivery zone created!');
    }

    public function edit(DeliveryZone $deliveryZone): View
    {
        return view('admin.delivery-zones.edit', compact('deliveryZone'));
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $deliveryZone): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $deliveryZone->update($data);

        return redirect()->route('admin.delivery-zones.index')->with('success', 'Delivery zone updated!');
    }

    public function destroy(DeliveryZone $deliveryZone): RedirectResponse
    {
        // Check if there are orders using this zone
        if ($deliveryZone->orders()->exists()) {
            return back()->with('error', 'Cannot delete this zone as it is referenced in past orders.');
        }

        $deliveryZone->delete();

        return back()->with('success', 'Delivery zone deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'delivery_zones', ['activate', 'deactivate', 'delete']);
        $zones = DeliveryZone::whereIn('id', $data['ids'])->get();

        if ($data['bulk_action'] !== 'delete') {
            AdminMutation::update(DeliveryZone::whereIn('id', $zones->pluck('id')), [
                'is_active' => $data['bulk_action'] === 'activate',
            ]);

            return back()->with('success', $this->bulkMessage($data['bulk_action'], $zones->count(), 'delivery zone'));
        }

        $deleted = 0;
        $skipped = 0;
        foreach ($zones as $zone) {
            if ($zone->orders()->exists()) {
                $skipped++;

                continue;
            }
            $zone->delete();
            $deleted++;
        }

        $message = $this->bulkMessage('delete', $deleted, 'delivery zone');
        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they are used on past orders.";
        }

        return back()->with($deleted > 0 ? 'success' : 'error', $message);
    }
}
