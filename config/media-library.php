<?php

use App\Jobs\Media\ProcessMediaConversions;
use App\Services\Media\JournalFileRemover;
use Spatie\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\Blurred;
use Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator;

return [
    'file_remover_class' => JournalFileRemover::class,
    'jobs' => ['perform_conversions' => ProcessMediaConversions::class],
    'responsive_images' => [
        'width_calculator' => FileSizeOptimizedWidthCalculator::class,
        'tiny_placeholder_generator' => Blurred::class,
        'use_tiny_placeholders' => false,
    ],
    'disk_name' => 'media_public',
    'image_driver' => 'gd',
    'queue_conversions_by_default' => true,
    'queue_conversions_after_database_commit' => true,
    'version_urls' => true,
];
