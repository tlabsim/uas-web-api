<?php

namespace App\Services;

use App\Models\EntityPageCategory;
use App\Models\EntityStaticPage;
use Illuminate\Support\Collection;

class EntityNavigationService
{
    public function buildForEntity(int $entityId): array
    {
        $categories = EntityPageCategory::with([
            'subcategories' => fn ($query) => $query->orderBy('menu_order')->orderBy('subcategory_name'),
        ])
            ->where('entity_id', $entityId)
            ->orderBy('menu_order')
            ->orderBy('category_name')
            ->get();

        $pages = EntityStaticPage::query()
            ->where('entity_id', $entityId)
            ->where('page_status', 'Published')
            ->where('is_menu', true)
            ->orderBy('menu_order')
            ->orderBy('page_title')
            ->get();

        return [
            'items' => $this->mergeNavigation($categories, $pages)->values()->all(),
            'meta' => [
                'entity_id' => $entityId,
                'category_count' => $categories->count(),
                'static_page_menu_count' => $pages->count(),
            ],
        ];
    }

    /**
     * @param Collection<int, EntityPageCategory> $categories
     * @param Collection<int, EntityStaticPage> $pages
     * @return Collection<int, array<string, mixed>>
     */
    protected function mergeNavigation(Collection $categories, Collection $pages): Collection
    {
        $items = $categories
            ->filter(fn (EntityPageCategory $category) => (bool) $category->is_menu)
            ->map(function (EntityPageCategory $category) {
                return [
                    'id' => $category->id,
                    'type' => 'category',
                    'label' => $this->resolveLabel($category->menu_text, $category->category_name),
                    'href' => $category->link_url ?: '#',
                    'menu_order' => (int) ($category->menu_order ?? 999),
                    'category_id' => $category->id,
                    'category_slug' => $category->category_slug,
                    'children' => $category->subcategories
                        ->filter(fn ($subcategory) => (bool) $subcategory->is_menu)
                        ->map(function ($subcategory) {
                            return [
                                'id' => $subcategory->id,
                                'type' => 'subcategory',
                                'label' => $this->resolveLabel($subcategory->menu_text, $subcategory->subcategory_name),
                                'href' => $subcategory->link_url ?: '#',
                                'menu_order' => (int) ($subcategory->menu_order ?? 999),
                                'subcategory_id' => $subcategory->id,
                                'subcategory_slug' => $subcategory->subcategory_slug,
                                'children' => [],
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $topLevelPages = [];

        foreach ($pages as $page) {
            $pageItem = [
                'id' => $page->id,
                'type' => 'static_page',
                'label' => $this->resolveLabel($page->menu_text, $page->page_title),
                'href' => '/' . ltrim((string) $page->page_slug, '/'),
                'menu_order' => (int) ($page->menu_order ?? 999),
                'page_id' => $page->id,
                'page_slug' => $page->page_slug,
                'page_category' => $page->page_category,
                'page_subcategory' => $page->page_subcategory,
                'children' => [],
            ];

            if ($page->page_subcategory) {
                $attached = false;

                foreach ($items as $itemIndex => $item) {
                    foreach (($item['children'] ?? []) as $childIndex => $child) {
                        if (($child['subcategory_id'] ?? null) === (int) $page->page_subcategory) {
                            $items[$itemIndex]['children'][$childIndex]['children'][] = $pageItem;
                            $attached = true;
                            break 2;
                        }
                    }
                }

                if ($attached) {
                    continue;
                }
            }

            if ($page->page_category) {
                $attached = false;

                foreach ($items as $itemIndex => $item) {
                    if (($item['category_id'] ?? null) === (int) $page->page_category) {
                        $items[$itemIndex]['children'][] = $pageItem;
                        $attached = true;
                        break;
                    }
                }

                if ($attached) {
                    continue;
                }
            }

            $topLevelPages[] = $pageItem;
        }

        $items = collect($items)
            ->map(function (array $item) {
                $item['children'] = collect($item['children'])
                    ->map(function (array $child) {
                        $child['children'] = collect($child['children'] ?? [])
                            ->sortBy(fn (array $grandchild) => [
                                (int) ($grandchild['menu_order'] ?? 999),
                                $grandchild['label'] ?? '',
                            ])
                            ->values()
                            ->all();

                        return $child;
                    })
                    ->sortBy(fn (array $child) => [
                        (int) ($child['menu_order'] ?? 999),
                        $child['label'] ?? '',
                    ])
                    ->values()
                    ->all();

                return $item;
            })
            ->concat($topLevelPages)
            ->sortBy(fn (array $item) => [
                (int) ($item['menu_order'] ?? 999),
                $item['type'] === 'category' ? 0 : 1,
                $item['label'] ?? '',
            ])
            ->values();

        return $items;
    }

    protected function resolveLabel(?string $menuText, string $fallback): string
    {
        $menuText = trim((string) $menuText);

        return $menuText !== '' ? $menuText : $fallback;
    }
}
