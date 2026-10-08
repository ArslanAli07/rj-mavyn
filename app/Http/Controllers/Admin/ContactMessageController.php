<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AdminMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function index(): View
    {
        $messages = ContactMessage::latest()->paginate(20);

        return view('admin.contact-messages.index', compact('messages'));
    }

    public function show(ContactMessage $message): View
    {
        return view('admin.contact-messages.show', compact('message'));
    }

    public function resolve(ContactMessage $message): RedirectResponse
    {
        $message->update([
            'is_resolved' => ! $message->is_resolved,
            'resolved_at' => $message->is_resolved ? null : now(),
        ]);

        return back()->with('success', $message->is_resolved ? 'Marked as resolved.' : 'Marked as unresolved.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'contact_messages', ['resolve', 'reopen', 'delete']);
        $messages = ContactMessage::whereIn('id', $data['ids'])->get();
        $ids = $messages->pluck('id');

        if ($data['bulk_action'] === 'delete') {
            AdminMutation::delete(ContactMessage::whereIn('id', $ids));
        } elseif ($data['bulk_action'] === 'resolve') {
            AdminMutation::update(ContactMessage::whereIn('id', $ids), [
                'is_resolved' => true,
                'resolved_at' => now(),
            ]);
        } else {
            AdminMutation::update(ContactMessage::whereIn('id', $ids), [
                'is_resolved' => false,
                'resolved_at' => null,
            ]);
        }

        return back()->with('success', $this->bulkMessage($data['bulk_action'], $messages->count(), 'message'));
    }
}
