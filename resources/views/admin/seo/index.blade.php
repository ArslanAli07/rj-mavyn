<x-admin-layout title="Manage Page SEO Meta Tags">

    {{-- Header --}}
    <div class="mb-8 border-b border-cream-100 pb-4">
        <h1 class="text-2xl font-heading font-bold text-espresso-800">SEO Meta Settings</h1>
        <p class="text-xs text-espresso-500 mt-1">Configure search engine titles, descriptions, and keywords for major public pages.</p>
    </div>

    {{-- Sitemap / Robots --}}
    <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-heading font-bold text-espresso-800 text-base">Sitemap &amp; Robots.txt</h2>
                <p class="text-xs text-espresso-500 mt-1 leading-relaxed">
                    Powered by Spatie Sitemap. The sitemap auto-regenerates when products are created, updated, or deleted.
                    @if($sitemapInfo['sitemap_updated_at'])
                        <span class="block mt-1 text-espresso-600">Last generated: <strong>{{ $sitemapInfo['sitemap_updated_at'] }}</strong></span>
                    @else
                        <span class="block mt-1 text-amber-700">Not generated yet — click regenerate to create it.</span>
                    @endif
                </p>
                <div class="flex flex-wrap gap-3 mt-3 text-xs">
                    <a href="{{ $sitemapInfo['sitemap_url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline font-semibold">
                        View sitemap.xml
                    </a>
                    <a href="{{ $sitemapInfo['robots_url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline font-semibold">
                        View robots.txt
                    </a>
                </div>
            </div>
            <form action="{{ route('admin.seo.sitemap.regenerate') }}" method="POST">
                @csrf
                <button type="submit" class="btn-gold py-2.5 px-6 text-xs font-semibold whitespace-nowrap">
                    Regenerate Sitemap
                </button>
            </form>
        </div>
    </div>

    <form action="{{ route('admin.seo.update') }}" method="POST" class="space-y-6" x-data="{ activeTab: 'home' }">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            {{-- Tabs selection list --}}
            <div class="lg:col-span-4 bg-white border border-cream-150 rounded-2xl p-4 shadow-sm space-y-1">
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider px-3 mb-2 block">Configure Pages</span>
                @foreach($pages as $key => $label)
                    <button type="button" @click="activeTab = '{{ $key }}'"
                            :class="activeTab === '{{ $key }}' ? 'bg-primary-500 text-white shadow-sm' : 'text-espresso-750 hover:bg-cream-50'"
                            class="w-full text-left px-4 py-2.5 rounded-xl text-xs font-semibold transition-all">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Form settings details --}}
            <div class="lg:col-span-8 bg-white border border-cream-150 rounded-2xl p-6 sm:p-8 shadow-sm">
                @foreach($pages as $key => $label)
                    <div x-show="activeTab === '{{ $key }}'" class="space-y-4">
                        <h3 class="font-heading font-bold text-base text-espresso-800 border-b border-cream-100 pb-2 mb-4">{{ $label }} Metadata</h3>

                        <div>
                            <label for="meta_title_{{ $key }}" class="label-luxury">Search Title (meta_title)</label>
                            <input type="text" id="meta_title_{{ $key }}" name="pages[{{ $key }}][meta_title]" 
                                   value="{{ old("pages.{$key}.meta_title", $metas->get($key)?->meta_title) }}"
                                   placeholder="e.g. Store name — Product catalog"
                                   class="input-luxury text-sm">
                        </div>

                        <div>
                            <label for="meta_description_{{ $key }}" class="label-luxury">Search Description (meta_description)</label>
                            <textarea id="meta_description_{{ $key }}" name="pages[{{ $key }}][meta_description]" rows="3"
                                      placeholder="Write a brief summary search engines will show in search results..."
                                      class="input-luxury text-sm">{{ old("pages.{$key}.meta_description", $metas->get($key)?->meta_description) }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="meta_keywords_{{ $key }}" class="label-luxury">Keywords (Comma separated)</label>
                                <input type="text" id="meta_keywords_{{ $key }}" name="pages[{{ $key }}][meta_keywords]" 
                                       value="{{ old("pages.{$key}.meta_keywords", $metas->get($key)?->meta_keywords) }}"
                                       placeholder="e.g. products, categories, store name"
                                       class="input-luxury text-sm">
                            </div>
                            <div>
                                <label for="og_image_{{ $key }}" class="label-luxury">Social Share Image URL (og:image)</label>
                                <input type="text" id="og_image_{{ $key }}" name="pages[{{ $key }}][og_image]" 
                                       value="{{ old("pages.{$key}.og_image", $metas->get($key)?->og_image) }}"
                                       placeholder="e.g. https://yourdomain.com/images/share.jpg"
                                       class="input-luxury text-sm">
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex gap-3 pt-6 border-t border-cream-100 mt-6 justify-end">
                    <button type="submit" class="btn-gold py-2.5 px-6 text-xs font-semibold">
                        Save SEO Settings
                    </button>
                </div>
            </div>

        </div>

    </form>

</x-admin-layout>
