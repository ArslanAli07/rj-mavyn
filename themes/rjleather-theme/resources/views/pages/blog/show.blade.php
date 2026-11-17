@section('title', $post->title)
<x-app-layout :title="$post->meta_title ?: $post->title.' | '.setting('site_name', config('site.name', 'RJ Motorbike'))" :meta-description="$post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($post->content), 150))" :og-image="$post->featured_image_url" og-type="article">
@push('head')
    {!! seo_schema(app(\App\Services\Seo\StructuredDataService::class)->articleGraph($post)) !!}
@endpush

    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span>
            <a href="{{ route('blog.index') }}">Blogs</a><span>/</span>
            <span aria-current="page">{{ $post->title }}</span>
        </nav>
    </div>

    <article class="rj-section rj-section--tight" style="max-width:840px;margin:0 auto;padding-inline:20px;">
        <header style="margin-bottom:32px;">
            <div style="display:flex;align-items:center;gap:12px;font-size:13px;color:rgba(0,0,0,0.55);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:14px;">
                <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('F d, Y') ?? 'Recently Published' }}</time>
                <span>&bull;</span>
                <span>{{ $post->reading_time }} min read</span>
                <span>&bull;</span>
                <span>RJ Motorbike</span>
            </div>
            <h1 class="rj-display" style="font-size:36px;line-height:1.2;margin:0 0 18px;color:var(--rj-ink, #111);">
                {{ $post->title }}
            </h1>
            @if($post->excerpt)
                <p class="rj-lede" style="font-size:18px;line-height:1.6;color:rgba(0,0,0,0.75);margin:0;">
                    {{ $post->excerpt }}
                </p>
            @endif
        </header>

        @if($post->featured_image)
            <div style="aspect-ratio:16/9;overflow:hidden;border-radius:6px;margin-bottom:40px;background:#f3f3f3;">
                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" style="width:100%;height:100%;object-fit:cover;">
            </div>
        @endif

        <div class="rj-article-content" style="font-size:16px;line-height:1.8;color:rgba(0,0,0,0.85);">
            {!! $post->content !!}
        </div>

        {{-- Contextual CTA Banner --}}
        <div style="margin:48px 0;padding:32px;background:#181615;color:#fff;border-radius:6px;text-align:center;">
            <span style="font-size:12px;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.7);display:block;margin-bottom:8px;">Bespoke Leather Craft</span>
            <h3 style="font-size:24px;margin:0 0 12px;color:#fff;">Looking for a Custom Made-to-Measure Fit?</h3>
            <p style="font-size:14px;color:rgba(255,255,255,0.8);max-width:540px;margin:0 auto 20px;">
                Every jacket, suit, and pant at RJ Motorbike is handmade from premium full-grain hides, cut precisely to your measurements with free worldwide shipping.
            </p>
            <a href="{{ route('shop') }}" class="rj-button" style="background:#fff;color:#181615;">Explore the Collection</a>
        </div>

        @if($recentPosts->isNotEmpty())
            <footer style="margin-top:64px;border-top:1px solid rgba(0,0,0,0.1);padding-top:40px;">
                <h3 style="font-size:20px;margin-bottom:24px;">More from the Blog</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:24px;">
                    @foreach($recentPosts as $recent)
                        <div style="border:1px solid rgba(0,0,0,0.06);padding:16px;border-radius:4px;">
                            <span style="font-size:11px;color:rgba(0,0,0,0.5);text-transform:uppercase;">{{ $recent->reading_time }} min read</span>
                            <h4 style="font-size:16px;margin:8px 0;line-height:1.4;">
                                <a href="{{ route('blog.show', $recent->slug) }}" style="color:inherit;text-decoration:none;">{{ $recent->title }}</a>
                            </h4>
                            <a href="{{ route('blog.show', $recent->slug) }}" style="font-size:12px;font-weight:700;color:var(--rj-accent, #c53030);text-decoration:none;">Read &rarr;</a>
                        </div>
                    @endforeach
                </div>
            </footer>
        @endif
    </article>
</x-app-layout>

