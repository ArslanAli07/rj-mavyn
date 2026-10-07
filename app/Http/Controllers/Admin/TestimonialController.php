<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    use Concerns\HandlesBulkActions;
    public function index(): View
    {
        $testimonials = Testimonial::latest()->paginate(20);
        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function create(): View { return view('admin.testimonials.create'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name'     => 'required|string|max:100',
            'customer_location' => 'nullable|string|max:100',
            'review'            => 'required|string|max:1000',
            'rating'            => 'required|integer|min:1|max:5',
            'is_approved'       => 'boolean',
            'is_featured'       => 'boolean',
        ]);
        $data['is_approved'] = $request->boolean('is_approved', true);
        $data['is_featured'] = $request->boolean('is_featured');
        Testimonial::create($data);
        Cache::forget('home_testimonials');
        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial created!');
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $request->validate([
            'customer_name'     => 'required|string|max:100',
            'customer_location' => 'nullable|string|max:100',
            'review'            => 'required|string|max:1000',
            'rating'            => 'required|integer|min:1|max:5',
            'is_approved'       => 'boolean',
            'is_featured'       => 'boolean',
        ]);
        $data['is_approved'] = $request->boolean('is_approved');
        $data['is_featured'] = $request->boolean('is_featured');
        $testimonial->update($data);
        Cache::forget('home_testimonials');
        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial updated!');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();
        Cache::forget('home_testimonials');
        return back()->with('success', 'Testimonial deleted.');
    }

    public function approve(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update(['is_approved' => !$testimonial->is_approved]);
        Cache::forget('home_testimonials');
        $msg = $testimonial->is_approved ? 'Testimonial approved.' : 'Testimonial hidden.';
        return back()->with('success', $msg);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'testimonials', ['approve', 'hide', 'feature', 'unfeature', 'delete']);
        $testimonials = Testimonial::whereIn('id', $data['ids'])->get();
        $ids = $testimonials->pluck('id');

        match ($data['bulk_action']) {
            'approve'   => Testimonial::whereIn('id', $ids)->update(['is_approved' => true]),
            'hide'      => Testimonial::whereIn('id', $ids)->update(['is_approved' => false]),
            'feature'   => Testimonial::whereIn('id', $ids)->update(['is_featured' => true]),
            'unfeature' => Testimonial::whereIn('id', $ids)->update(['is_featured' => false]),
            'delete'    => Testimonial::whereIn('id', $ids)->delete(),
        };

        Cache::forget('home_testimonials');

        return back()->with('success', $this->bulkMessage($data['bulk_action'], $testimonials->count(), 'testimonial'));
    }
}
