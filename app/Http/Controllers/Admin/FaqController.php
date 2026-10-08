<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\AdminMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function index(): View
    {
        $faqs = Faq::orderBy('display_order')->paginate(30);

        return view('admin.faqs.index', compact('faqs'));
    }

    public function create(): View
    {
        return view('admin.faqs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:100',
            'display_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        Faq::create($data);

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ created!');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.edit', compact('faq'));
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'nullable|string|max:100',
            'display_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $faq->update($data);

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ updated!');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'FAQ deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'faqs', ['activate', 'deactivate', 'delete']);
        $faqs = Faq::whereIn('id', $data['ids'])->get();

        if ($data['bulk_action'] === 'delete') {
            AdminMutation::delete(Faq::whereIn('id', $faqs->pluck('id')));
        } else {
            AdminMutation::update(Faq::whereIn('id', $faqs->pluck('id')), [
                'is_active' => $data['bulk_action'] === 'activate',
            ]);
        }

        return back()->with('success', $this->bulkMessage($data['bulk_action'], $faqs->count(), 'FAQ'));
    }
}
