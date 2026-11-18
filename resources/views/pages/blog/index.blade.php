@section('title', 'Guides & Articles')
<x-app-layout :title="'Motorcycle Leather Guides & Sizing Advice | '.setting('site_name', config('site.name', 'RJ Motorbike'))" :meta-description="'Expert buyer guides, sizing instructions, and care advice for handmade leather motorcycle jackets, race suits, and bespoke riding gear from RJ Motorbike.'" og-type="website">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">Guides &amp; Articles</span>
        </nav>
    </div>

    <section class="rj-section rj-section--tight">
        <div class="rj-container">
            <div class="rj-section-head rj-section-head--center" style="margin-bottom: 48px;">
                <div class="rj-section-head__copy">
                    <span class="rj-eyebrow">The Workshop Journal</span>
                    <h1 class="rj-display rj-title" style="font-size:38px">Motorcycle Leather Guides &amp; Rider Advice</h1>
                    <p class="rj-lede">Master craftsman sizing instructions, race suit safety standards, leather grading, and style heritage.</p>
                </div>
            </div>

            @if($posts->isEmpty())
                <div class="rj-empty" style="max-width:560px;margin-inline:auto;text-align:center;padding:48px 0;">
                    <p><strong>Articles are being published.</strong><br>Check back soon for master craftsman sizing guides and rider advice.</p>
                    <p style="margin-top:14px"><a href="{{ route('shop') }}" class="rj-button">Browse Collection</a></p>
                </div>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:32px;">
                    @foreach($posts as $post)
                        <article style="background:var(--rj-card-bg, #fff);border:1px solid rgba(0,0,0,0.08);border-radius:4px;overflow:hidden;display:flex;flex-direction:column;transition:transform 0.2s ease, box-shadow 0.2s ease;">
                            <a href="{{ route('blog.show', $post->slug) }}" style="display:block;aspect-ratio:16/9;overflow:hidden;background:#f3f3f3;">
                                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" style="width:100%;height:100%;object-fit:cover;transition:transform 0.3s ease;" loading="lazy">
                            </a>
                            <div style="padding:24px;display:flex;flex-direction:column;flex-grow:1;">
                                <div style="display:flex;align-items:center;gap:12px;font-size:12px;color:rgba(0,0,0,0.5);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">
                                    <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('M d, Y') ?? 'Recent' }}</time>
                                    <span>&bull;</span>
                                    <span>{{ $post->reading_time }} min read</span>
                                </div>
                                <h2 style="font-size:20px;font-weight:700;line-height:1.3;margin:0 0 12px;color:var(--rj-ink, #111);">
                                    <a href="{{ route('blog.show', $post->slug) }}" style="color:inherit;text-decoration:none;">{{ $post->title }}</a>
                                </h2>
                                <p style="font-size:14px;line-height:1.6;color:rgba(0,0,0,0.7);margin:0 0 20px;flex-grow:1;">
                                    {{ $post->excerpt ?: Str::limit(strip_tags($post->content), 130) }}
                                </p>
                                <div>
                                    <a href="{{ route('blog.show', $post->slug) }}" style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--rj-accent, #c53030);text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                        Read Guide <svg class="icon" style="width:12px;height:12px;" aria-hidden="true"><use href="#icon-arrow"/></svg>
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if($posts->hasPages())
                    <div style="margin-top:48px;display:flex;justify-content:center;">
                        {{ $posts->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>
</x-app-layout>
