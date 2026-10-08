<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\AdminMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function index(): View
    {
        $subscribers = NewsletterSubscriber::latest()->paginate(30);
        $total = NewsletterSubscriber::count();
        $active = NewsletterSubscriber::active()->count();

        return view('admin.newsletter.index', compact('subscribers', 'total', 'active'));
    }

    public function export()
    {
        $subscribers = NewsletterSubscriber::active()->get(['email', 'name', 'subscribed_at']);

        $csv = "Email,Name,Subscribed At\n";
        foreach ($subscribers as $s) {
            $csv .= "\"{$s->email}\",\"{$s->name}\",\"{$s->subscribed_at}\"\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="newsletter_subscribers_'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'newsletter_subscribers', ['activate', 'unsubscribe', 'delete']);
        $subscribers = NewsletterSubscriber::whereIn('id', $data['ids'])->get();
        $ids = $subscribers->pluck('id');

        if ($data['bulk_action'] === 'delete') {
            AdminMutation::delete(NewsletterSubscriber::whereIn('id', $ids));
        } elseif ($data['bulk_action'] === 'activate') {
            AdminMutation::update(NewsletterSubscriber::whereIn('id', $ids), [
                'is_active' => true,
                'unsubscribed_at' => null,
            ]);
        } else {
            AdminMutation::update(NewsletterSubscriber::whereIn('id', $ids), [
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);
        }

        return back()->with('success', $this->bulkMessage($data['bulk_action'], $subscribers->count(), 'subscriber'));
    }
}
