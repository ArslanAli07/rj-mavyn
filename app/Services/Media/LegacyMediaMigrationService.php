<?php

namespace App\Services\Media;

use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\LegacyMediaImport;
use App\Models\MediaAsset;
use App\Models\MediaRetentionHold;
use App\Models\MediaUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** CLI maintenance only; no request-controlled owner, storage, or slot definitions. */
class LegacyMediaMigrationService
{
    public function __construct(private LegacyMediaSource $sources, private LegacyMediaImporter $importer,
        private LegacyReceiptMigrator $receipts, private MediaPolicyRegistry $policies) {}

    public function run(bool $apply, callable $report): array
    {
        $counts = ['ready' => 0, 'migrated' => 0, 'skipped' => 0, 'errors' => 0, 'unreferenced_files' => 0];
        $known = [];
        $receiptPaths = Order::where('payment_receipt_disk', 'public')->whereNotNull('payment_receipt')->pluck('payment_receipt')
            ->merge(LegacyMediaImport::where('visibility', 'private')->pluck('source_path'))->unique()->all();

        foreach ($this->targets() as [$target, $slot, $field, $images]) {
            $label = $target->getMorphClass().' #'.$target->id.' '.$slot;
            foreach ($images as $image) {
                $known[$image['disk'].'/'.$image['path']] = true;
            }
            $this->attempt($label, function () use ($target, $slot, $field, $images, $receiptPaths, $apply, &$counts) {
                if ($target->mediaUsages()->where('slot', $slot)->exists()) {
                    $counts['skipped']++;

                    return 'Existing library selection preserved; legacy fallback retained.';
                }
                $policy = $this->policies->slot($slot, $target);
                if (count($images) > $policy['max_count']) {
                    throw new RuntimeException('Legacy gallery exceeds the slot limit; reconcile it before importing.');
                }
                foreach ($images as $image) {
                    $this->assertNotReceipt($image, $receiptPaths);
                    $file = $this->sources->inspect($image);
                    if ($file['size'] > $policy['max_bytes']) {
                        throw new RuntimeException('Legacy file exceeds the slot size limit.');
                    }
                    $this->checkJournal($image, $file['checksum']);
                }
                if (! $apply) {
                    $counts['ready']++;

                    return 'Ready to import '.count($images).' image reference(s).';
                }
                $assets = array_map(fn ($image) => $this->importer->image($image, $image['alt']), $images);
                DB::transaction(function () use ($target, $slot, $field, $images, $assets) {
                    $locked = $target->newQuery()->lockForUpdate()->findOrFail($target->id);
                    if ($this->images($locked, $field) !== $images || $locked->mediaUsages()->where('slot', $slot)->exists()) {
                        throw new RuntimeException('Content changed during migration; references were left untouched.');
                    }
                    $lockedAssets = MediaAsset::whereIn('id', array_map(fn ($asset) => $asset->id, $assets))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    foreach ($assets as $position => $asset) {
                        if ($lockedAssets->get($asset->id)?->status !== 'active') {
                            throw new RuntimeException('Imported image became unavailable.');
                        }
                        // Duplicate legacy paths collapse without changing first-occurrence ordering.
                        MediaUsage::firstOrCreate(['mediable_type' => $target->getMorphClass(), 'mediable_id' => $target->id,
                            'slot' => $slot, 'media_asset_id' => $asset->id],
                            ['sort_order' => $position, 'metadata' => ['alt_text' => $images[$position]['alt']]]);
                    }
                    if ($field === 'product_gallery') {
                        $locked->images()->delete();
                    } elseif ($locked instanceof Setting) {
                        $locked->update(['value' => $field === 'setting_gallery' ? '[]' : null]);
                    } else {
                        $locked->update([$field => null]);
                    }
                });
                Cache::forget('all_settings');
                Cache::forget('homepage_gallery');
                $counts['migrated']++;

                return 'Imported and associated; public source files preserved.';
            }, $counts, $report);
        }

        OrderItem::whereNotNull('product_image')->orWhereNotNull('media_asset_id')->orderBy('id')->chunkById(100, function ($items) use ($receiptPaths, $apply, &$counts, &$known, $report) {
            foreach ($items as $item) {
                $source = $item->product_image ? $this->sources->resolve($item->product_image) : null;
                if ($source) {
                    $known[$source['disk'].'/'.$source['path']] = true;
                }
                $this->attempt('order-item #'.$item->id, function () use ($item, $source, $receiptPaths, $apply, &$counts) {
                    $asset = $item->media_asset_id ? MediaAsset::with('media')->findOrFail($item->media_asset_id) : null;
                    if (! $asset) {
                        $this->assertNotReceipt($source, $receiptPaths);
                        $file = $this->sources->inspect($source);
                        $this->checkJournal($source, $file['checksum']);
                    } else {
                        $this->verifyAsset($asset);
                        $hold = MediaRetentionHold::where('reference', 'order-item-'.$item->id)->first();
                        if ($hold && $hold->media_asset_id !== $asset->id) {
                            throw new RuntimeException('Historical retention conflicts with the order image.');
                        }
                        if ($hold) {
                            $counts['skipped']++;

                            return 'Historical image and retention verified.';
                        }
                    }
                    if (! $apply) {
                        $counts['ready']++;

                        return 'Ready to retain the saved historical image.';
                    }
                    $asset ??= $this->importer->image($source, $item->product_title);
                    DB::transaction(function () use ($item, $asset) {
                        $locked = OrderItem::lockForUpdate()->findOrFail($item->id);
                        $asset = MediaAsset::lockForUpdate()->with('media')->findOrFail($asset->id);
                        if ($locked->product_image !== $item->product_image || $locked->media_asset_id !== $item->media_asset_id) {
                            throw new RuntimeException('Order image changed during migration.');
                        }
                        $this->verifyAsset($asset);
                        $media = $asset->getFirstMedia('original');
                        $snapshot = $locked->media_snapshot ?? ['title' => $locked->product_title, 'alt_text' => $locked->product_title,
                            'caption' => null, 'mime_type' => $media->mime_type, 'width' => $media->getCustomProperty('width'),
                            'height' => $media->getCustomProperty('height'), 'legacy_path' => $locked->product_image];
                        $locked->update(['media_asset_id' => $asset->id, 'media_snapshot' => $snapshot]);
                        $hold = MediaRetentionHold::firstOrCreate(['reference' => 'order-item-'.$item->id], [
                            'media_asset_id' => $asset->id, 'order_item_id' => $item->id, 'snapshot' => $snapshot]);
                        if ($hold->media_asset_id !== $asset->id) {
                            throw new RuntimeException('Historical retention conflicts with the order image.');
                        }
                    });
                    $counts['migrated']++;

                    return 'Saved historical image retained independently of the current product.';
                }, $counts, $report);
            }
        });

        foreach ($receiptPaths as $path) {
            $source = ['disk' => 'public', 'path' => $path];
            $known['public/'.$path] = true;
            $this->attempt('receipt '.hash('sha256', $path), function () use ($source, $apply, &$counts) {
                $this->sources->assertPrivateStorage();
                // Never remove a file still used by public content or historical paths.
                if ($this->publiclyReferenced($source)) {
                    throw new RuntimeException('Receipt path is also public content; reconcile the privacy conflict manually.');
                }
                if (! $apply) {
                    $journal = LegacyMediaImport::where('source_key', $this->sources->key($source, 'private'))->first();
                    if ($journal) {
                        $destination = ['disk' => 'local', 'path' => $journal->destination_path];
                        if ($journal->status !== 'pending' || is_file($this->sources->path($destination, false))) {
                            $this->sources->verify($destination, $journal->checksum);
                        }
                        if (is_file($this->sources->path($source, false))) {
                            $this->sources->verify($source, $journal->checksum);
                        } elseif ($journal->status === 'pending') {
                            throw new RuntimeException('Missing public receipt before reference migration.');
                        }
                    } else {
                        $this->sources->inspect($source, false);
                    }
                    $counts['ready']++;

                    return 'Receipt storage verified; apply completes any pending private copy/cleanup.';
                }
                $this->receipts->migrate($source);
                $counts['migrated']++;

                return 'Private receipt verified; public copy removed.';
            }, $counts, $report);
        }

        Order::whereNotNull('payment_receipt')->where('payment_receipt_disk', '!=', 'public')->chunkById(100, function ($orders) use (&$counts, $report) {
            foreach ($orders as $order) {
                $this->attempt('private receipt for order #'.$order->id, function () use ($order) {
                    if ($order->payment_receipt_disk !== 'local') {
                        throw new RuntimeException('Unsupported receipt storage; reconcile it manually.');
                    }
                    $this->sources->assertPrivateStorage();
                    $this->sources->inspect(['disk' => 'local', 'path' => $order->payment_receipt], false);

                    return 'Private original and file policy verified.';
                }, $counts, $report);
            }
        });

        // Audit every imported original, including imports whose content has since been replaced.
        LegacyMediaImport::where('visibility', 'public')->where('status', 'imported')->chunkById(100, function ($imports) use (&$counts, &$known, $report) {
            foreach ($imports as $import) {
                $known[$import->source_disk.'/'.$import->source_path] = true;
                $this->attempt('import #'.$import->id, function () use ($import) {
                    $this->verifyAsset(MediaAsset::findOrFail($import->media_asset_id));
                    $this->sources->verify(['disk' => $import->destination_disk, 'path' => $import->destination_path], $import->checksum);
                    $this->sources->verify(['disk' => $import->source_disk, 'path' => $import->source_path], $import->checksum);

                    return 'Source and imported original checksums verified.';
                }, $counts, $report);
            }
        });
        $this->attempt('Public file inventory', function () use (&$counts, $known, $report) {
            foreach (Storage::disk('public')->allFiles() as $path) {
                if (! str_starts_with($path, 'media/') && ! isset($known['public/'.$path]) && ! str_starts_with(basename($path), '.')) {
                    $counts['unreferenced_files']++;
                    $report('Unreferenced public file: '.$path.'; preserved for manual reconciliation.', true);
                }
            }

            return 'Unreferenced files preserved.';
        }, $counts, $report);

        return $counts;
    }

    private function targets(): \Generator
    {
        foreach ([Product::class => ['catalog.product.main' => 'main_image', 'catalog.product.gallery' => 'product_gallery'],
            Category::class => ['catalog.category.hero' => 'image'],
            HomeSlide::class => ['content.hero.desktop' => 'image_path', 'content.hero.mobile' => 'mobile_image_path']] as $class => $slots) {
            foreach ($class::orderBy('id')->lazyById(100) as $target) {
                foreach ($slots as $slot => $field) {
                    $images = $this->images($target, $field);
                    if ($images) {
                        yield [$target, $slot, $field, $images];
                    }
                }
            }
        }
        foreach (['site_logo' => 'store.logo', 'site_favicon' => 'store.favicon', 'homepage_gallery' => 'content.gallery'] as $key => $slot) {
            $target = Setting::where('key', $key)->first();
            $field = $key === 'homepage_gallery' ? 'setting_gallery' : 'value';
            if ($target && ($images = $this->images($target, $field))) {
                yield [$target, $slot, $field, $images];
            }
        }
    }

    private function images(Model $target, string $field): array
    {
        $alt = $target->title ?? $target->name ?? $target->key ?? 'Legacy image';
        if ($field === 'product_gallery') {
            return $target->images()->orderBy('sort_order')->orderBy('id')->get()->map(fn ($image) => $this->sources->resolve($image->image, false) + ['alt' => $alt, 'legacy_id' => $image->id])->all();
        }
        if ($field === 'setting_gallery') {
            $images = json_decode($target->value ?? '[]', true);
            if (! is_array($images) || ! array_is_list($images)) {
                return [['disk' => 'legacy_assets', 'path' => '', 'alt' => $alt]];
            }

            return array_map(function ($image) use ($alt) {
                if (! is_array($image) || ! is_string($image['src'] ?? null) || ! is_string($image['alt'] ?? $alt)) {
                    return ['disk' => 'legacy_assets', 'path' => '', 'alt' => $alt];
                }

                return $this->sources->resolve($image['src']) + ['alt' => $image['alt'] ?? $alt];
            }, $images);
        }

        return $target->$field ? [$this->sources->resolve($target->$field) + ['alt' => $alt]] : [];
    }

    private function assertNotReceipt(array $source, array $receiptPaths): void
    {
        if ($source['disk'] === 'public' && (str_starts_with($source['path'], 'receipts/') || in_array($source['path'], $receiptPaths, true))) {
            throw new RuntimeException('Private receipt cannot become a public image.');
        }
    }

    private function publiclyReferenced(array $source): bool
    {
        if (LegacyMediaImport::where('visibility', 'public')->where('source_disk', $source['disk'])
            ->where('source_path', $source['path'])->exists()) {
            return true;
        }
        foreach ($this->targets() as [, , , $images]) {
            foreach ($images as $image) {
                if ($image['disk'] === $source['disk'] && $image['path'] === $source['path']) {
                    return true;
                }
            }
        }
        foreach (OrderItem::whereNotNull('product_image')->lazyById(100) as $item) {
            if ($this->sources->resolve($item->product_image) === $source) {
                return true;
            }
        }

        return false;
    }

    private function checkJournal(array $source, string $checksum): void
    {
        $journal = LegacyMediaImport::where('source_key', $this->sources->key($source, 'public'))->first();
        if ($journal && ! hash_equals($journal->checksum, $checksum)) {
            throw new RuntimeException('Legacy source checksum changed; manual reconciliation required.');
        }
    }

    private function verifyAsset(MediaAsset $asset): void
    {
        $media = $asset->getFirstMedia('original');
        if ($asset->status !== 'active' || $asset->visibility !== 'public' || ! $media
            || ! in_array($media->mime_type, MediaPolicyRegistry::IMAGE_MIMES, true)
            || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            throw new RuntimeException('Historical/imported original is unavailable; restore it before proceeding.');
        }
    }

    private function attempt(string $label, callable $operation, array &$counts, callable $report): void
    {
        try {
            $report($label.': '.$operation(), false);
        } catch (Throwable $exception) {
            $counts['errors']++;
            $report($label.': '.$exception->getMessage(), true);
        }
    }
}
