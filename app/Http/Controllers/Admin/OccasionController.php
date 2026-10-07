<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Occasion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OccasionController extends Controller
{
    use Concerns\HandlesBulkActions;
    public function index(): View
    {
        $occasions = Occasion::withCount('products')->orderBy('display_order')->get();
        return view('admin.occasions.index', compact('occasions'));
    }

    public function create(): View { return view('admin.occasions.create'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'nullable|unique:occasions,slug',
            'description'   => 'nullable|string',
            'icon'          => 'nullable|string|max:10',
            'display_order' => 'integer|min:0',
            'is_active'     => 'boolean',
        ]);
        $data['slug']      = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);
        Occasion::create($data);
        return redirect()->route('admin.occasions.index')->with('success', 'Occasion created!');
    }

    public function edit(Occasion $occasion): View
    {
        return view('admin.occasions.edit', compact('occasion'));
    }

    public function update(Request $request, Occasion $occasion): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string',
            'icon'          => 'nullable|string|max:10',
            'display_order' => 'integer|min:0',
            'is_active'     => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $occasion->update($data);
        return redirect()->route('admin.occasions.index')->with('success', 'Occasion updated!');
    }

    public function destroy(Occasion $occasion): RedirectResponse
    {
        $occasion->delete();
        return back()->with('success', 'Occasion deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'occasions', ['activate', 'deactivate', 'delete']);
        $occasions = Occasion::whereIn('id', $data['ids'])->get();

        if ($data['bulk_action'] === 'delete') {
            Occasion::whereIn('id', $occasions->pluck('id'))->delete();
        } else {
            Occasion::whereIn('id', $occasions->pluck('id'))->update([
                'is_active' => $data['bulk_action'] === 'activate',
            ]);
        }

        return back()->with('success', $this->bulkMessage($data['bulk_action'], $occasions->count(), 'occasion'));
    }
}
