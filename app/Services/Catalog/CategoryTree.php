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
            return $byParent->get($parentId ?? 0, collect())
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
        return $this->flat()->where('parent_id', $category->id)->values();
    }

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
