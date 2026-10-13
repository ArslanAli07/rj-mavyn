<x-admin-layout title="Manage page SEO meta tags">

    {{-- Header --}}
    <div class="mb-5 border-bottom border-light-subtle pb-3">
        <h1 class="text-body admin-page-title">SEO meta settings</h1>
        <p class="small text-body-secondary mt-1">Configure search engine titles, descriptions, and keywords for major public pages.</p>
    </div>

    {{-- Sitemap / Robots --}}
    <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm mb-5">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-4">
            <div>
                <h2 class="text-body admin-section-title">Sitemap &amp; robots.txt</h2>
                <p class="small text-body-secondary mt-1 lh-lg">
                    Powered by Spatie Sitemap. The sitemap auto-regenerates when products are created, updated, or deleted.
                    @if($sitemapInfo['sitemap_updated_at'])
                        <span class="d-block mt-1 text-body-secondary">Last generated: <strong>{{ $sitemapInfo['sitemap_updated_at'] }}</strong></span>
                    @else
                        <span class="d-block mt-1 text-warning">Not generated yet — click regenerate to create it.</span>
                    @endif
                </p>
                <div class="d-flex flex-wrap gap-3 mt-3">
                    <a href="{{ $sitemapInfo['sitemap_url'] }}" target="_blank" rel="noopener" class="text-primary link-underline-opacity-100-hover fw-semibold">
                        View sitemap.xml
                    </a>
                    <a href="{{ $sitemapInfo['robots_url'] }}" target="_blank" rel="noopener" class="text-primary link-underline-opacity-100-hover fw-semibold">
                        View robots.txt
                    </a>
                </div>
            </div>
            <form action="{{ route('admin.seo.sitemap.regenerate') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold text-nowrap">
                    Regenerate sitemap
                </button>
            </form>
        </div>
    </div>

    <form action="{{ route('admin.seo.update') }}" method="POST" class="admin-stack-6" x-data="{ activeTab: 'home' }">
        @csrf
        @method('PUT')

        <div class="d-grid admin-cols-1 admin-cols-lg-12 gap-5 align-items-start">
            
            {{-- Tabs selection list --}}
            <div class="card admin-span-lg-4 bg-white border border-light-subtle rounded-2 p-3 shadow-sm admin-stack-1">
                <span class="text-body-secondary small fw-semibold admin-letter-spacing px-3 mb-2 d-block">Configure Pages</span>
                @foreach($pages as $key => $label)
                    <button type="button" @click="activeTab = '{{ $key }}'"
                            :class="activeTab === '{{ $key }}' ? 'bg-primary-subtle text-primary' : 'text-body '"
                            class="btn btn-sm btn-outline-secondary w-100 text-start px-3 py-2 rounded-2 small fw-semibold">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Form settings details --}}
            <div class="card admin-span-lg-8 bg-white border border-light-subtle rounded-2 p-4 p-sm-5 shadow-sm">
                @foreach($pages as $key => $label)
                    <div x-show.important="activeTab === '{{ $key }}'" class="admin-stack-4">
                        <h3 class="text-body border-bottom border-light-subtle pb-2 mb-3 admin-section-title">{{ $label }} Metadata</h3>

                        <div>
                            <label for="meta_title_{{ $key }}" class="form-label">Search title (meta_title)</label>
                            <input type="text" id="meta_title_{{ $key }}" name="pages[{{ $key }}][meta_title]" 
                                   value="{{ old("pages.{$key}.meta_title", $metas->get($key)?->meta_title) }}"
                                   placeholder="e.g. Store name — Product catalog"
                                   class="form-control small">
                        </div>

                        <div>
                            <label for="meta_description_{{ $key }}" class="form-label">Search description (meta_description)</label>
                            <textarea id="meta_description_{{ $key }}" name="pages[{{ $key }}][meta_description]" rows="3"
                                      placeholder="Write a brief summary search engines will show in search results..."
                                      class="form-control small">{{ old("pages.{$key}.meta_description", $metas->get($key)?->meta_description) }}</textarea>
                        </div>

                        <div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
                            <div>
                                <label for="meta_keywords_{{ $key }}" class="form-label">Keywords (comma separated)</label>
                                <input type="text" id="meta_keywords_{{ $key }}" name="pages[{{ $key }}][meta_keywords]" 
                                       value="{{ old("pages.{$key}.meta_keywords", $metas->get($key)?->meta_keywords) }}"
                                       placeholder="e.g. products, categories, store name"
                                       class="form-control small">
                            </div>
                            <div>
                                <label for="og_image_{{ $key }}" class="form-label">Social share image URL (og:image)</label>
                                <input type="text" id="og_image_{{ $key }}" name="pages[{{ $key }}][og_image]" 
                                       value="{{ old("pages.{$key}.og_image", $metas->get($key)?->og_image) }}"
                                       placeholder="e.g. https://yourdomain.com/images/share.jpg"
                                       class="form-control small">
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="d-flex gap-3 pt-4 border-top border-light-subtle mt-4 justify-content-end">
                    <button type="submit" class="btn btn-primary py-2 px-4 small fw-semibold">
                        Save SEO settings
                    </button>
                </div>
            </div>

        </div>

    </form>

</x-admin-layout>
