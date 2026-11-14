@include('pages.shop', [
    'catalogTitle' => preg_replace('/^for\s+/i', '', $category->name),
    'catalogDescription' => $category->description ?: 'Explore the collection and find your next favourite piece.',
])
