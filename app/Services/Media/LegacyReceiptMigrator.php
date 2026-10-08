<?php

namespace App\Services\Media;

use App\Models\LegacyMediaImport;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LegacyReceiptMigrator
{
    public function __construct(private LegacyMediaSource $sources) {}

    public function migrate(array $source): void
    {
        try {
            $this->migrateReceipt($source);
        } catch (\Throwable $exception) {
            LegacyMediaImport::where('source_key', $this->sources->key($source, 'private'))->update(['last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function migrateReceipt(array $source): void
    {
        $this->sources->assertPrivateStorage();
        $journal = LegacyMediaImport::where('source_key', $this->sources->key($source, 'private'))->first();
        if (! $journal) {
            $file = $this->sources->inspect($source, false);
            $extension = match ($file['mime']) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf',
            };
            $key = $this->sources->key($source, 'private');
            $journal = LegacyMediaImport::create(['source_key' => $key, 'source_disk' => 'public', 'source_path' => $source['path'],
                'checksum' => $file['checksum'], 'visibility' => 'private', 'destination_disk' => 'local',
                'destination_path' => 'receipts/legacy/'.$key.'.'.$extension]);
        }
        $destination = ['disk' => 'local', 'path' => $journal->destination_path];
        $targetPath = $this->sources->path($destination, false);
        if (! is_file($targetPath)) {
            $this->sources->verify($source, $journal->checksum);
            $stream = fopen($this->sources->path($source), 'rb');
            try {
                if (! Storage::disk('local')->put($journal->destination_path.'.copy', $stream, ['visibility' => 'private'])) {
                    throw new RuntimeException('Private receipt copy failed. The public original was retained.');
                }
            } finally {
                fclose($stream);
            }
            $this->sources->verify(['disk' => 'local', 'path' => $journal->destination_path.'.copy'], $journal->checksum);
            if (! Storage::disk('local')->move($journal->destination_path.'.copy', $journal->destination_path)) {
                throw new RuntimeException('Private receipt staging could not be finalized. Rerun migration.');
            }
        }
        $this->sources->verify($destination, $journal->checksum);
        $sourceExists = is_file($this->sources->path($source, false));
        if ($sourceExists) {
            $this->sources->verify($source, $journal->checksum);
        } elseif ($journal->status === 'pending') {
            throw new RuntimeException('Public receipt disappeared before references were migrated.');
        }
        if ($journal->status === 'completed' && ! $sourceExists
            && ! Order::where('payment_receipt_disk', 'public')->where('payment_receipt', $source['path'])->exists()) {
            return;
        }
        DB::transaction(function () use ($journal) {
            // A shared legacy path moves all its order references together.
            $orders = Order::where('payment_receipt_disk', 'public')->where('payment_receipt', $journal->source_path)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($orders as $order) {
                $order->update(['payment_receipt_disk' => 'local', 'payment_receipt' => $journal->destination_path]);
            }
            $journal->update(['status' => 'copied', 'last_error' => null]);
        });
        // Committed copy/reference intent survives an interrupted or failed unlink.
        if (Order::where('payment_receipt_disk', 'public')->where('payment_receipt', $source['path'])->exists()) {
            throw new RuntimeException('A public receipt reference remains; cleanup deferred.');
        }
        if ($sourceExists) {
            $this->sources->verify($destination, $journal->checksum);
            $this->sources->verify($source, $journal->checksum);
            if (! Storage::disk('public')->delete($source['path']) || is_file($this->sources->path($source, false))) {
                throw new RuntimeException('Private receipt is ready, but public-copy cleanup failed. Rerun migration.');
            }
        }
        $journal->update(['status' => 'completed', 'last_error' => null]);
    }
}
