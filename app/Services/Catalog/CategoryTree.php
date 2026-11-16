<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reads the category tree once per hour and hands it to the storefront.
 *
 * Categories are stored flat with a parent reference, so the navigation tree,
 * the branch of a group and the breadcrumb trail are all derived from the same
 * cached list.
 */
final class CategoryTree
{
    /** @return Collection<int, Category> */
    public function flat(): Collection
    {
        return Cache::remember('active_categories', 3600, fn () => Category::active()->orderBy('display_order')->orderBy('id')->get());
    }

    /**
     * The roots of the tree, each with up to two levels of children loaded.
     *
     * @return Collection<int, Category>
     */
    public function tree(): Collection
    {
        $categories = $this->flat();
        $byParent = $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $build = function (?int $parentId, int $depth) use (&$build, $byParent): Collection {
            $siblings = $byParent->get($parentId ?? 0, collect());
            // The curated order only applies beneath the roots; the top-level
            // groups keep their stored (display_order) navigation order.
            if ($parentId !== null) {
                $siblings = $this->orderSiblings($siblings);
            }

            return $siblings
                ->map(function (Category $category) use (&$build, $depth) {
                    $category->setRelation('children', $depth > 0 ? $build($category->id, $depth - 1) : collect());

                    return $category;
                })
                ->values();
        };

        return $build(null, 2);
    }

    /**
     * The categories directly beneath the given one.
     *
     * @return Collection<int, Category>
     */
    public function branch(Category $category): Collection
    {
        return $this->orderSiblings($this->flat()->where('parent_id', $category->id));
    }

    /**
     * Curated sibling order: the hero product types lead (race suits, jackets,
     * pants…), niche and housekeeping groups (brands, accessories) trail.
     * Siblings that match no keyword keep their stored order, alphabetically,
     * after the matched ones.
     *
     * @param  Collection<int, Category>  $siblings
     * @return Collection<int, Category>
     */
    public function orderSiblings(Collection $siblings): Collection
    {
        return $siblings->sort(function (Category $a, Category $b): int {
            $rankA = $this->priorityRank($a->name);
            $rankB = $this->priorityRank($b->name);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return strcasecmp($a->name, $b->name);
        })->values();
    }

    /** Rank of a category name against the curated priority keywords. */
    private function priorityRank(string $name): int
    {
        $key = mb_strtolower(trim($name));
        $best = null;
        foreach (self::PRIORITY_KEYWORDS as $rank => $keyword) {
            if (! str_contains($key, $keyword)) {
                continue;
            }
            // The longest keyword wins, so "Movies Jackets" matches the
            // specific "movies jacket" slot rather than the generic "jacket".
            if ($best === null || mb_strlen($keyword) > mb_strlen($best['keyword'])) {
                $best = ['rank' => $rank, 'keyword' => $keyword];
            }
        }

        return $best ? $best['rank'] : count(self::PRIORITY_KEYWORDS);
    }

    /**
     * Ordered keyword list — earlier entries outrank later ones.
     */
    private const PRIORITY_KEYWORDS = [
        'jacket',         // Jackets + every jacket style
        'race suit',      // Race Suits / Racing Suits
        'race',           // the standalone Race group
        'movies jacket',  // Movies Jackets, Movies Leather Jacket…
        'movie jacket',
        'movies leather',
        'film leather',   // Film Leather Jacket for Women
        'pants',
        'trouser',
        'chaps',
        'suit',           // Suits (race suits matched above)
        'coat',
        'blazer',
        'trench',
        'vest',
        'kilt',
        'skirt',
        'hoodie',
        'sweatshirt',
        'footwear',
        'boot',
        'shoe',
        'glove',
        'helmet',
        'handbag',
        'bag',
        'accessor',
        'gear',
        'brand',          // Brands last
    ];

    /**
     * The path from the root down to the given category.
     *
     * @return Collection<int, Category>
     */
    public function trail(Category $category): Collection
    {
        $byId = $this->flat()->keyBy('id');
        $trail = collect([$category]);
        $node = $category;
        while ($node->parent_id !== null) {
            $node = $byId->get($node->parent_id);
            if (! $node) {
                break;
            }
            $trail->prepend($node);
        }

        return $trail->values();
    }

    /**
     * The root the given category belongs to.
     */
    public function root(Category $category): ?Category
    {
        return $this->trail($category)->first();
    }

    public function forget(): void
    {
        Cache::forget('active_categories');
        Cache::forget('nav_categories');
    }
}
