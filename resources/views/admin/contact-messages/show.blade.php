<x-admin-layout title="Inquiry detail">

    {{-- Header --}}
    <div class="mb-5 d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h1 class="text-body admin-page-title">Inquiry detail</h1>
            <p class="small text-body-secondary mt-1">Review full message received from {{ $message->name }}.</p>
        </div>
        <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-outline-secondary py-2 px-3 small">
            ← Back to messages
        </a>
    </div>

    <div class="d-grid admin-cols-1 admin-cols-lg-12 gap-5 align-items-start">
        
        {{-- Message Body --}}
        <div class="card admin-span-lg-8 bg-white border border-light-subtle rounded-2 p-4 p-sm-5 shadow-sm admin-stack-4">
            <div class="border-bottom border-light-subtle pb-3 mb-3">
                <span class="small text-body-secondary fw-semibold">Subject</span>
                <h2 class="text-body mt-1 admin-section-title">{{ $message->subject }}</h2>
            </div>

            <div>
                <span class="small text-body-secondary fw-semibold d-block mb-2">Message Content</span>
                <div class="bg-body-tertiary rounded-2 p-4 border border-light-subtle lh-lg fw-normal text-body">
                    {!! nl2br(e($message->message)) !!}
                </div>
            </div>
        </div>

        {{-- Sidebar information / Actions --}}
        <div class="card admin-span-lg-4 bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4">
            <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">Sender profile</h3>
            
            <div class="admin-stack-3 fw-medium text-body">
                <div class="d-flex justify-content-between border-bottom border-light-subtle pb-2">
                    <span>Name:</span>
                    <span class="fw-semibold text-body">{{ $message->name }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom border-light-subtle pb-2">
                    <span>Email:</span>
                    <a href="mailto:{{ $message->email }}" class="fw-semibold text-primary link-underline-opacity-100-hover">{{ $message->email }}</a>
                </div>
                <div class="d-flex justify-content-between border-bottom border-light-subtle pb-2">
                    <span>Phone:</span>
                    <span class="fw-semibold text-body">{{ $message->phone ?? 'N/A' }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Date:</span>
                    <span class="fw-semibold text-body">{{ store_time($message->created_at)->format('Y-m-d H:i') }}</span>
                </div>
            </div>

            <hr class="border-light-subtle">

            <div class="admin-stack-2">
                <form action="{{ route('admin.contact-messages.resolve', $message->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-primary w-100 justify-content-center py-2 small fw-semibold {{ $message->is_resolved ? 'bg-body-tertiary ' : '' }}">
                        {{ $message->is_resolved ? 'Mark as Unresolved' : 'Mark as Resolved' }}
                    </button>
                </form>
            </div>
        </div>

    </div>

</x-admin-layout>
