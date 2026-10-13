<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['email' => 'required|string|email|max:255', 'name' => 'nullable|string|max:255']);

        $existing = NewsletterSubscriber::where('email', $request->email)->first();

        if ($existing) {
            if ($existing->is_active) {
                $msg = 'You are already subscribed to our newsletter!';
            } else {
                $existing->update(['is_active' => true, 'subscribed_at' => now(), 'unsubscribed_at' => null]);
                $msg = 'Welcome back! You have been re-subscribed.';
            }
        } else {
            NewsletterSubscriber::create([
                'email'         => $request->email,
                'name'          => $data['name'] ?? null,
                'is_active'     => true,
                'subscribed_at' => now(),
            ]);
            $msg = 'Thank you for subscribing! 🎂 Stay tuned for sweet updates.';
        }

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $msg])
            : back()->with('success', $msg);
    }
}
