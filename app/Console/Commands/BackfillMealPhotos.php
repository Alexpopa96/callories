<?php

namespace App\Console\Commands;

use App\Models\Meal;
use App\Services\Fit\BarcodeLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillMealPhotos extends Command
{
    protected $signature = 'fit:backfill-meal-photos {--dry-run : Only show which meals would get a picture}
        {--any-name : Search by name for every item, not only the "Product — Brand" ones from a scan}';

    protected $description = 'Give meals of scanned products that have no photo the product picture from Open Food Facts.';

    /** Keeps the name searches gentle on Open Food Facts. */
    private const SEARCH_PAUSE_SECONDS = 2;

    public function handle(BarcodeLookup $lookup): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $anyName = (bool) $this->option('any-name');
        $updated = 0;
        $missing = 0;

        Meal::query()->whereNull('photo_path')->chunkById(100, function ($meals) use ($lookup, $dryRun, $anyName, &$updated, &$missing) {
            foreach ($meals as $meal) {
                $products = collect($meal->items)->filter(fn (array $item) => $anyName ? ! empty($item['name']) : $this->isScannedProduct($item));

                if ($products->isEmpty()) {
                    continue;
                }

                $imageUrl = null;

                foreach ($products as $item) {
                    if ($imageUrl = $this->imageFor($lookup, $item)) {
                        break;
                    }
                }

                if (! $imageUrl) {
                    $missing++;
                    $this->line("  – #{$meal->id} {$meal->title}: fără poză în Open Food Facts");

                    continue;
                }

                if ($dryRun) {
                    $updated++;
                    $this->line("  ✓ #{$meal->id} {$meal->title}: {$imageUrl}");

                    continue;
                }

                $path = $lookup->storeImage($imageUrl, "meals/{$meal->user_id}");

                if (! $path) {
                    $missing++;
                    $this->warn("  ! #{$meal->id} {$meal->title}: poza nu a putut fi descărcată");

                    continue;
                }

                $meal->update(['photo_path' => $path]);
                $updated++;
                $this->line("  ✓ #{$meal->id} {$meal->title}");
            }
        });

        $this->info(($dryRun ? 'Ar primi poză' : 'Au primit poză').": {$updated} mese. Fără poză găsită: {$missing}.");

        return self::SUCCESS;
    }

    /**
     * Items saved from a barcode scan: they either carry the code, or (for meals saved before
     * the code was kept) have the "Product — Brand" name built from Open Food Facts.
     */
    private function isScannedProduct(array $item): bool
    {
        return ! empty($item['barcode']) || Str::contains($item['name'] ?? '', ' — ');
    }

    private function imageFor(BarcodeLookup $lookup, array $item): ?string
    {
        try {
            if (! empty($item['barcode'])) {
                return $lookup->find($item['barcode'])['image_url'] ?? null;
            }

            $image = $lookup->imageForName($item['name']);
            sleep(self::SEARCH_PAUSE_SECONDS);

            return $image;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
