<?php
// inc/category_functions.php

/**
 * Build a category tree from a flat array of categories.
 * Each category must have 'id', 'parent_id', and 'name'.
 * @param array $categories
 * @param int|null $parentId
 * @return array
 */
function buildCategoryTree(array $categories, $parentId = 0) {
    $branch = [];
    foreach ($categories as $category) {
        if ((int)($category['parent_id'] ?? 0) === (int)$parentId) {
            $children = buildCategoryTree($categories, $category['id']);
            if ($children) {
                $category['children'] = $children;
            }
            $branch[] = $category;
        }
    }
    return $branch;
}

/**
 * Generate a URL-friendly slug from a string (e.g., for category or course names).
 * @param string $string
 * @return string
 */
function generateSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
} 