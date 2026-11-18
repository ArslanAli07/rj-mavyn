{{-- Shared create/edit fields for a blog post. Expects $post (null on create). --}}
@php
    $post = $post ?? null;
    $publishedAt = old('published_at', $post?->published_at?->format('Y-m-d\TH:i'));
@endphp

<div>
    <label for="title" class="form-label">Title *</label>
    <input type="text" id="title" name="title" value="{{ old('title', $post?->title) }}" required maxlength="255" class="form-control small">
    @error('title') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
</div>

<div class="d-grid admin-cols-1 admin-cols-sm-2 gap-4">
    <div>
        <label for="slug" class="form-label">URL slug</label>
        <input type="text" id="slug" name="slug" value="{{ old('slug', $post?->slug) }}" maxlength="255" placeholder="Generated from the title when empty" class="form-control small">
        <p class="small text-body-secondary mt-1">Shown as /blog/<em>slug</em>. Changing it on a live post breaks existing links.</p>
        @error('slug') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="published_at" class="form-label">Publish date</label>
        <input type="datetime-local" id="published_at" name="published_at" value="{{ $publishedAt }}" class="form-control small">
        <p class="small text-body-secondary mt-1">Defaults to now when published. A future date schedules the post.</p>
        @error('published_at') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label for="excerpt" class="form-label">Excerpt</label>
    <textarea id="excerpt" name="excerpt" rows="2" class="form-control small">{{ old('excerpt', $post?->excerpt) }}</textarea>
    <p class="small text-body-secondary mt-1">Short summary shown on the blog listing.</p>
    @error('excerpt') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
</div>

<div>
    <label for="content" class="form-label">Content (HTML) *</label>
    <textarea id="content" name="content" rows="16" required class="form-control small font-monospace">{{ old('content', $post?->content) }}</textarea>
    <p class="small text-body-secondary mt-1">Use &lt;h2&gt;/&lt;h3&gt; for headings. The page already has an &lt;h1&gt; (the title), so don't add another.</p>
    @error('content') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
</div>

<div>
    <label for="featured_image" class="form-label">Featured image URL or path</label>
    <input type="text" id="featured_image" name="featured_image" value="{{ old('featured_image', $post?->featured_image) }}" maxlength="255" placeholder="e.g. https://… or images/blog/guide.jpg (blank uses the theme hero)" class="form-control small">
    @error('featured_image') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
</div>

<div class="border-top border-light-subtle pt-3 admin-stack-4">
    <h2 class="h6 fw-semibold text-body mb-0">Search engine listing</h2>
    <div>
        <label for="meta_title" class="form-label">Meta title</label>
        <input type="text" id="meta_title" name="meta_title" value="{{ old('meta_title', $post?->meta_title) }}" maxlength="255" placeholder="Defaults to “{title} | {{ setting('site_name', config('site.name', 'RJ Motorbike')) }}”" class="form-control small">
        <p class="small text-body-secondary mt-1">Aim for 50–60 characters.</p>
        @error('meta_title') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="meta_description" class="form-label">Meta description</label>
        <textarea id="meta_description" name="meta_description" rows="2" maxlength="500" class="form-control small">{{ old('meta_description', $post?->meta_description) }}</textarea>
        <p class="small text-body-secondary mt-1">Aim for 140–160 characters. Defaults to the excerpt.</p>
        @error('meta_description') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="meta_keywords" class="form-label">Meta keywords</label>
        <input type="text" id="meta_keywords" name="meta_keywords" value="{{ old('meta_keywords', $post?->meta_keywords) }}" maxlength="500" class="form-control small">
        @error('meta_keywords') <p class="text-danger small mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="pt-2">
    <input type="hidden" name="is_published" value="0">
    <label class="d-flex align-items-center gap-2 admin-clickable user-select-none small fw-semibold text-body">
        <input type="checkbox" name="is_published" value="1" class="form-check-input" @checked(old('is_published', $post?->is_published ?? true))>
        <span>Published (visible on the blog and in the sitemap)</span>
    </label>
</div>
