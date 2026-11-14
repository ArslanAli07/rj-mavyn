@include('pages.shop', ['catalogTitle' => 'Search', 'catalogDescription' => strlen($q ?? '') >= 2 ? 'Results for “'.$q.'”' : 'Discover your next leather piece.'])
