<x-admin-layout title="Inquiry Detail">

    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between border-b border-cream-100 pb-4">
        <div>
            <h1 class="text-2xl font-heading font-bold text-espresso-800">Inquiry Detail</h1>
            <p class="text-xs text-espresso-500 mt-1">Review full message received from {{ $message->name }}.</p>
        </div>
        <a href="{{ route('admin.contact-messages.index') }}" class="btn-secondary py-2 px-4 text-xs">
            ← Back to Messages
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        {{-- Message Body --}}
        <div class="lg:col-span-8 bg-white border border-cream-150 rounded-2xl p-6 sm:p-8 shadow-sm space-y-4 text-sm">
            <div class="border-b border-cream-100 pb-4 mb-4">
                <span class="text-xs text-espresso-400 font-semibold uppercase">Subject</span>
                <h2 class="font-heading font-bold text-espresso-800 text-lg mt-0.5">{{ $message->subject }}</h2>
            </div>

            <div>
                <span class="text-xs text-espresso-400 font-semibold uppercase block mb-2">Message Content</span>
                <div class="bg-cream-50/50 rounded-2xl p-5 border border-cream-100 leading-relaxed font-light text-espresso-750">
                    {!! nl2br(e($message->message)) !!}
                </div>
            </div>
        </div>

        {{-- Sidebar information / Actions --}}
        <div class="lg:col-span-4 bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4 text-xs">
            <h3 class="font-heading font-bold text-sm text-espresso-800 border-b border-cream-100 pb-2 mb-4">Sender Profile</h3>
            
            <div class="space-y-3 font-medium text-espresso-705">
                <div class="flex justify-between border-b border-cream-50 pb-2">
                    <span>Name:</span>
                    <span class="font-bold text-espresso-850">{{ $message->name }}</span>
                </div>
                <div class="flex justify-between border-b border-cream-50 pb-2">
                    <span>Email:</span>
                    <a href="mailto:{{ $message->email }}" class="font-bold text-primary-600 hover:underline">{{ $message->email }}</a>
                </div>
                <div class="flex justify-between border-b border-cream-50 pb-2">
                    <span>Phone:</span>
                    <span class="font-bold text-espresso-850">{{ $message->phone ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Date:</span>
                    <span class="font-bold text-espresso-850">{{ $message->created_at->format('Y-m-d H:i') }}</span>
                </div>
            </div>

            <hr class="border-cream-100">

            <div class="space-y-2">
                <form action="{{ route('admin.contact-messages.resolve', $message->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-gold w-full justify-center py-2.5 text-xs font-semibold
                                                 {{ $message->is_resolved ? 'bg-espresso-805 hover:bg-espresso-700' : '' }}">
                        {{ $message->is_resolved ? 'Mark as Unresolved' : 'Mark as Resolved' }}
                    </button>
                </form>
            </div>
        </div>

    </div>

</x-admin-layout>
