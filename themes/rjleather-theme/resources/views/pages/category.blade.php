@include('pages.shop', [
    'catalogTitle' => preg_replace('/^for\s+/i', '', $category->name),
    'catalogDescription' => $category->description ?: 'Explore the collection and find your next favourite piece.',
    // The head gets the curated SEO copy; the visible H1 stays the plain name.
    'metaTitle' => $category->meta_title ?: null,
    'metaDescription' => $category->meta_description ?: ($category->description ?: null),
])
