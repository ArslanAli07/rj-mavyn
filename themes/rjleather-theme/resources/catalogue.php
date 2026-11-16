<?php

/**
 * RJ Motorbike catalogue snapshot.
 *
 * The storefront now reads the tree from the categories table (parent_id),
 * so this file is a reference of the intended structure rather than runtime
 * configuration. RjLeatherCatalogSeeder maintains the same structure from the
 * supplier export: group categories own their subcategories and the
 * collections are the top-level editorial sets.
 */

return [
    'groups' => [
        'For Men' => ['Chappals', 'Chaps', 'Coats', 'Gloves', 'Jackets', 'Leather Handbags', 'Leather Kilts', 'Movies Leather Jacket for Men', 'Pants', 'Vests'],
        'For Women' => ['Chaps', 'Coats', 'Gloves', 'Jackets', 'Leather Kilts & Skirts', 'Movies Leather Jacket for Women', 'Pants', 'Vests'],
        'MotoGP' => ['Brands', 'Footwear', 'Gloves', 'Jackets', 'Pants', 'Race', 'Race Suits'],
        'Motorcycle' => ['Footwear', 'Gloves', 'Jackets', 'Pants', 'Race Suit', 'Vests'],
    ],
    'collections' => [
        'Leather Collection' => [],
        'Moto Speeds' => [],
        'Customize' => ['Fashion'],
    ],
];
