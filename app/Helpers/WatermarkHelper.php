<?php

namespace App\Helpers;

use App\Models\CompanyDetail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class WatermarkHelper
{
    /**
     * Apply watermark to a single image (from URL) and save both original and watermarked versions.
     *
     * @param string $imageUrl The URL of the image
     * @param int $realEstateId The ID of the real estate property
     * @param string $type Either 'cover' or 'gallery'
     * @return array Array containing ['original' => 'path', 'watermark' => 'path'] or null on failure
     */
    public static function processImage(string $imageUrl, int $realEstateId, string $type = 'gallery'): ?array
    {
        try {
            ini_set('memory_limit', '512M');

            if (empty($imageUrl)) {
                return null;
            }

            // Download raw image content
            $response = Http::withoutVerifying()->timeout(30)->get($imageUrl);
            if (!$response->successful()) {
                Log::error("WatermarkHelper: Failed to download image from: {$imageUrl}");
                return null;
            }

            $rawContent = $response->body();
            $imageInfo = getimagesizefromstring($rawContent);
            if (!$imageInfo) {
                Log::error("WatermarkHelper: Downloaded content is not a valid image: {$imageUrl}");
                return null;
            }

            $mime = $imageInfo['mime'] ?? 'image/jpeg';
            $ext = self::getExtensionFromMime($mime);

            // Generate deterministic filenames
            $hash = md5($imageUrl);
            $filename = "{$type}_{$hash}.{$ext}";

            $originalPath = "properties/{$realEstateId}/original/{$filename}";
            $watermarkedPath = "properties/{$realEstateId}/watermarked/{$filename}";

            // Save the original image to PRIVATE disk (not web-accessible)
            Storage::disk('local')->put($originalPath, $rawContent);

            // Create GD image resource from downloaded content
            $mainImage = imagecreatefromstring($rawContent);
            if (!$mainImage) {
                Log::error("WatermarkHelper: Failed to create GD image resource from content.");
                return null;
            }

            // Apply watermark to GD image
            $watermarkedImage = self::applyWatermark($mainImage, $mime);

            // Save watermarked image to public disk
            $savePath = Storage::disk('public')->path($watermarkedPath);
            // Ensure directory exists
            $dir = dirname($savePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            self::saveImageResource($watermarkedImage, $savePath, $mime);
            imagedestroy($mainImage);
            imagedestroy($watermarkedImage);

            return [
                'original' => $originalPath,
                'watermark' => $watermarkedPath,
            ];

        } catch (\Exception $e) {
            Log::error("WatermarkHelper: Exception during processImage: " . $e->getMessage(), [
                'url' => $imageUrl,
                'real_estate_id' => $realEstateId,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Apply watermark settings to a GD image resource and return the new image resource.
     *
     * @param resource|\GdImage $mainImage
     * @param string $mime
     * @return resource|\GdImage
     */
    private static function applyWatermark($mainImage, string $mime)
    {
        $company = CompanyDetail::first();
        if (!$company) {
            return self::cloneImageResource($mainImage);
        }

        // Determine watermark logo path
        if ($company->watermark_type === 'custom' && $company->watermark_image) {
            $watermarkField = $company->watermark_image;
        } elseif ($company->watermark_type === 'footer_logo' && $company->footer_logo) {
            $watermarkField = $company->footer_logo;
        } else {
            $watermarkField = $company->navbar_logo;
        }

        if (!$watermarkField || !Storage::disk('public')->exists($watermarkField)) {
            Log::info("WatermarkHelper: No watermark logo found on disk.");
            return self::cloneImageResource($mainImage);
        }

        $watermarkPath = Storage::disk('public')->path($watermarkField);
        $watermarkContent = file_get_contents($watermarkPath);
        $watermark = imagecreatefromstring($watermarkContent);
        if (!$watermark) {
            Log::error("WatermarkHelper: Failed to create GD image resource for watermark logo.");
            return self::cloneImageResource($mainImage);
        }

        // Get watermark settings
        $position = $company->watermark_position ?: 'center';
        $opacity = $company->watermark_opacity !== null ? $company->watermark_opacity : 60; // 0-100
        $sizePercent = $company->watermark_size !== null ? $company->watermark_size : 45; // 0-100

        $targetW = imagesx($mainImage);
        $targetH = imagesy($mainImage);

        $watermarkW = imagesx($watermark);
        $watermarkH = imagesy($watermark);

        // Calculate new watermark width (e.g. 20% of target image width)
        $newW = round($targetW * ($sizePercent / 100));
        // Keep aspect ratio
        $newH = round($watermarkH * ($newW / $watermarkW));

        // Create transparent canvas for resized watermark
        $resizedWatermark = imagecreatetruecolor($newW, $newH);
        imagealphablending($resizedWatermark, false);
        imagesavealpha($resizedWatermark, true);
        $transparent = imagecolorallocatealpha($resizedWatermark, 0, 0, 0, 127);
        imagefill($resizedWatermark, 0, 0, $transparent);

        // Resize watermark logo
        imagecopyresampled($resizedWatermark, $watermark, 0, 0, 0, 0, $newW, $newH, $watermarkW, $watermarkH);
        imagedestroy($watermark);

        // Apply opacity to transparent watermark resource
        self::adjustOpacity($resizedWatermark, $opacity);

        // Clone main image to keep original intact
        $resultImage = self::cloneImageResource($mainImage);

        // Calculate positioning
        $padding = 20;
        $x = 0;
        $y = 0;

        switch ($position) {
            case 'top-left':
                $x = $padding;
                $y = $padding;
                break;
            case 'top-right':
                $x = $targetW - $newW - $padding;
                $y = $padding;
                break;
            case 'bottom-left':
                $x = $padding;
                $y = $targetH - $newH - $padding;
                break;
            case 'bottom-right':
                $x = $targetW - $newW - $padding;
                $y = $targetH - $newH - $padding;
                break;
            case 'center':
            default:
                $x = round(($targetW - $newW) / 2);
                $y = round(($targetH - $newH) / 2);
                break;
        }

        // Apply watermark onto result image
        imagealphablending($resultImage, true);
        imagecopy($resultImage, $resizedWatermark, $x, $y, 0, 0, $newW, $newH);
        imagedestroy($resizedWatermark);

        return $resultImage;
    }

    /**
     * Adjust the transparency (opacity) of a transparent PNG watermark.
     *
     * @param resource|\GdImage $img
     * @param int $opacity (0 to 100)
     */
    private static function adjustOpacity($img, int $opacity)
    {
        $opacityMultiplier = $opacity / 100.0;
        imagealphablending($img, false);
        imagesavealpha($img, true);

        $w = imagesx($img);
        $h = imagesy($img);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $color = imagecolorat($img, $x, $y);
                $rgba = imagecolorsforindex($img, $color);

                // Alpha channel is 0 (opaque) to 127 (transparent).
                // Get transparency value (127 = fully transparent, 0 = fully opaque)
                $transparency = 127 - $rgba['alpha'];
                // Apply the user opacity setting
                $newTransparency = $transparency * $opacityMultiplier;
                // Convert back to GD alpha
                $newAlpha = 127 - $newTransparency;
                $newAlpha = min(127, max(0, $newAlpha));

                $newColor = imagecolorallocatealpha($img, $rgba['red'], $rgba['green'], $rgba['blue'], (int)$newAlpha);
                imagesetpixel($img, $x, $y, $newColor);
            }
        }
    }

    /**
     * Regenerate all watermarked images using their stored originals.
     */
    public static function regenerateAll()
    {
        try {
            ini_set('memory_limit', '512M');
            $records = \App\Models\ImageRealEstate::all();
            Log::info("WatermarkHelper: Starting regeneration of watermarked images for " . $records->count() . " records.");

            foreach ($records as $record) {
                // 1. Process cover image
                // Read original from PRIVATE local disk
                if ($record->cover_image_original && Storage::disk('local')->exists($record->cover_image_original)) {
                    $origPath = Storage::disk('local')->path($record->cover_image_original);
                    $rawContent = file_get_contents($origPath);

                    $imageInfo = getimagesizefromstring($rawContent);
                    if ($imageInfo) {
                        $mime = $imageInfo['mime'];
                        $mainImage = imagecreatefromstring($rawContent);

                        if ($mainImage) {
                            $watermarkedImage = self::applyWatermark($mainImage, $mime);
                            $savePath = Storage::disk('public')->path($record->cover_image_watermark);

                            // Ensure directory exists
                            $dir = dirname($savePath);
                            if (!is_dir($dir)) {
                                mkdir($dir, 0755, true);
                            }

                            self::saveImageResource($watermarkedImage, $savePath, $mime);
                            imagedestroy($mainImage);
                            imagedestroy($watermarkedImage);
                        }
                    }
                }

                // 2. Process gallery images
                $gallery = $record->images;
                if (is_array($gallery) && count($gallery) > 0) {
                    $updatedGallery = [];
                    foreach ($gallery as $imgRecord) {
                        $origRel = $imgRecord['original'] ?? null;
                        $watRel  = $imgRecord['watermark'] ?? null;

                        if ($origRel && Storage::disk('local')->exists($origRel) && $watRel) {
                            $origPath = Storage::disk('local')->path($origRel);
                            $rawContent = file_get_contents($origPath);

                            $imageInfo = getimagesizefromstring($rawContent);
                            if ($imageInfo) {
                                $mime = $imageInfo['mime'];
                                $mainImage = imagecreatefromstring($rawContent);

                                if ($mainImage) {
                                    $watermarkedImage = self::applyWatermark($mainImage, $mime);
                                    $savePath = Storage::disk('public')->path($watRel);

                                    // Ensure directory exists
                                    $dir = dirname($savePath);
                                    if (!is_dir($dir)) {
                                        mkdir($dir, 0755, true);
                                    }

                                    self::saveImageResource($watermarkedImage, $savePath, $mime);
                                    imagedestroy($mainImage);
                                    imagedestroy($watermarkedImage);
                                }
                            }
                            $updatedGallery[] = $imgRecord;
                        } else {
                            // Keep the record even if we couldn't reprocess
                            $updatedGallery[] = $imgRecord;
                        }
                    }

                    // Save the updated gallery back to the DB
                    $record->images = $updatedGallery;
                    $record->save();
                }

                // Free memory by calling garbage collection
                gc_collect_cycles();
            }

            Log::info("WatermarkHelper: Finished regeneration of all watermarked images.");
        } catch (\Exception $e) {
            Log::error("WatermarkHelper: Failed to regenerate watermarked images: " . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    /**
     * Trigger watermark regeneration in the background using a non-blocking process.
     */
    public static function regenerateAllInBackground()
    {
        $command = 'php artisan watermark:regenerate';
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            pclose(popen("start /B " . $command, "r"));
        } else {
            exec($command . " > /dev/null 2>&1 &");
        }
    }

    /**
     * Re-download and reprocess images for records that are missing watermarks
     * (cover image watermark file missing on disk, or gallery entries missing watermark).
     * Call this once after deploying to fix existing un-watermarked images.
     */
    public static function reprocessMissingWatermarks()
    {
        try {
            ini_set('memory_limit', '512M');
            $records = \App\Models\ImageRealEstate::all();
            Log::info("WatermarkHelper: Checking " . $records->count() . " records for missing watermarks.");

            foreach ($records as $record) {
                // --- Cover image ---
                $coverWatRel = $record->cover_image_watermark;
                $coverMissing = !$coverWatRel
                    || !Storage::disk('public')->exists($coverWatRel);

                if ($coverMissing && $record->cover_image_original && Storage::disk('local')->exists($record->cover_image_original)) {
                    $origPath    = Storage::disk('local')->path($record->cover_image_original);
                    $rawContent  = file_get_contents($origPath);
                    $imageInfo   = getimagesizefromstring($rawContent);

                    if ($imageInfo) {
                        $mime      = $imageInfo['mime'];
                        $mainImage = imagecreatefromstring($rawContent);

                        if ($mainImage) {
                            $watermarkedImage = self::applyWatermark($mainImage, $mime);
                            $savePath = Storage::disk('public')->path($coverWatRel);

                            $dir = dirname($savePath);
                            if (!is_dir($dir)) {
                                mkdir($dir, 0755, true);
                            }

                            self::saveImageResource($watermarkedImage, $savePath, $mime);
                            imagedestroy($mainImage);
                            imagedestroy($watermarkedImage);

                            Log::info("WatermarkHelper: Re-watermarked cover for real_estate_id={$record->real_estate_id}");
                        }
                    }
                }

                // --- Gallery images ---
                $gallery = $record->images;
                if (is_array($gallery) && count($gallery) > 0) {
                    $updatedGallery = [];
                    $galleryChanged = false;

                    foreach ($gallery as $imgRecord) {
                        $origRel = $imgRecord['original'] ?? null;
                        $watRel  = $imgRecord['watermark'] ?? null;

                        $watMissing = !$watRel || !Storage::disk('public')->exists($watRel);

                        if ($watMissing && $origRel && Storage::disk('local')->exists($origRel)) {
                            $origPath   = Storage::disk('local')->path($origRel);
                            $rawContent = file_get_contents($origPath);
                            $imageInfo  = getimagesizefromstring($rawContent);

                            if ($imageInfo) {
                                $mime      = $imageInfo['mime'];
                                $mainImage = imagecreatefromstring($rawContent);

                                if ($mainImage) {
                                    $watermarkedImage = self::applyWatermark($mainImage, $mime);

                                    // Build watermark path if missing
                                    if (!$watRel) {
                                        $hash    = md5($origRel);
                                        $ext     = self::getExtensionFromMime($mime);
                                        $watRel  = "properties/{$record->real_estate_id}/watermarked/gallery_{$hash}.{$ext}";
                                        $imgRecord['watermark'] = $watRel;
                                        $galleryChanged = true;
                                    }

                                    $savePath = Storage::disk('public')->path($watRel);
                                    $dir = dirname($savePath);
                                    if (!is_dir($dir)) {
                                        mkdir($dir, 0755, true);
                                    }

                                    self::saveImageResource($watermarkedImage, $savePath, $mime);
                                    imagedestroy($mainImage);
                                    imagedestroy($watermarkedImage);

                                    Log::info("WatermarkHelper: Re-watermarked gallery image for real_estate_id={$record->real_estate_id}");
                                }
                            }
                        }

                        $updatedGallery[] = $imgRecord;
                    }

                    if ($galleryChanged) {
                        $record->images = $updatedGallery;
                        $record->save();
                    }
                }
            }

            Log::info("WatermarkHelper: Finished checking/fixing missing watermarks.");
        } catch (\Exception $e) {
            Log::error("WatermarkHelper: reprocessMissingWatermarks failed: " . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Public wrappers — used by RealEstateController for on-the-fly reprocessing
    // -------------------------------------------------------------------------

    public static function applyWatermarkPublic($mainImage, string $mime)
    {
        return self::applyWatermark($mainImage, $mime);
    }

    public static function saveImageResourcePublic($img, string $path, string $mime): void
    {
        self::saveImageResource($img, $path, $mime);
    }

    public static function getExtPublic(string $mime): string
    {
        return self::getExtensionFromMime($mime);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Helper to clone a GD image resource.
     */
    private static function cloneImageResource($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $clone = imagecreatetruecolor($w, $h);
        
        imagealphablending($clone, false);
        imagesavealpha($clone, true);
        
        imagecopy($clone, $img, 0, 0, 0, 0, $w, $h);
        return $clone;
    }

    /**
     * Helper to save a GD image resource to disk.
     */
    private static function saveImageResource($img, string $path, string $mime)
    {
        switch ($mime) {
            case 'image/png':
                imagepng($img, $path);
                break;
            case 'image/webp':
                imagewebp($img, $path, 90);
                break;
            case 'image/avif':
                if (function_exists('imageavif')) {
                    imageavif($img, $path, 80);
                } else {
                    imagejpeg($img, $path, 90);
                }
                break;
            case 'image/jpeg':
            default:
                imagejpeg($img, $path, 90);
                break;
        }
    }

    /**
     * Helper to get extension from mime type.
     */
    private static function getExtensionFromMime(string $mime): string
    {
        switch ($mime) {
            case 'image/png':
                return 'png';
            case 'image/webp':
                return 'webp';
            case 'image/gif':
                return 'gif';
            case 'image/avif':
                return 'avif';
            case 'image/jpeg':
            default:
                return 'jpg';
        }
    }

    /**
     * Synchronize and process images for a real estate item.
     * Checks if cached images are up to date (by count, hashes, and disk presence).
     * If they need updating, deletes old files and runs processImage.
     *
     * @param array $item The item data returned from crm.item.get
     * @return array|null The cached ImageRealEstate record attributes or null
     */
    public static function syncItemImages(array $item): ?array
    {
        $itemId = $item['id'] ?? null;
        if (!$itemId) {
            return null;
        }

        // --- Cover image ---
        $coverRaw = $item['ufCrm8_1769500967'] ?? null;
        $coverImageUrl = null;
        if ($coverRaw) {
            if (is_array($coverRaw)) {
                $first = null;
                if (isset($coverRaw[0]) && is_array($coverRaw[0])) {
                    $first = $coverRaw[0];
                } elseif (isset($coverRaw['FILE']) && is_array($coverRaw['FILE'])) {
                    $first = $coverRaw['FILE'];
                } else {
                    $first = $coverRaw;
                }

                if (is_array($first)) {
                    $coverImageUrl = $first['urlMachine'] ?? $first['url'] ?? $first['SRC'] ?? null;
                }
            } elseif (is_string($coverRaw)) {
                $coverImageUrl = $coverRaw;
            }
        }

        // --- Gallery images ---
        $imagesRaw = $item['ufCrm8_1768601375'] ?? [];
        $imageUrls = [];
        if (is_array($imagesRaw)) {
            foreach ($imagesRaw as $img) {
                if (is_string($img)) {
                    $imageUrls[] = $img;
                } elseif (is_array($img)) {
                    $url = $img['urlMachine'] ?? $img['url'] ?? $img['SRC'] ?? null;
                    if ($url) {
                        $imageUrls[] = $url;
                    }
                }
            }
        }

        $cached = \App\Models\ImageRealEstate::where('real_estate_id', $itemId)->first();

        // Check if cached images are up to date
        $isUpToDate = false;
        if ($cached) {
            // Check cover image
            $coverOk = false;
            if (empty($coverImageUrl)) {
                $coverOk = empty($cached->cover_image_original);
            } else {
                $coverHash = md5($coverImageUrl);
                $coverOk = $cached->cover_image_original 
                    && strpos($cached->cover_image_original, $coverHash) !== false
                    && \Illuminate\Support\Facades\Storage::disk('local')->exists($cached->cover_image_original)
                    && $cached->cover_image_watermark
                    && \Illuminate\Support\Facades\Storage::disk('public')->exists($cached->cover_image_watermark);
            }

            // Check gallery images
            $galleryOk = false;
            $cachedGallery = is_array($cached->images) ? $cached->images : [];

            if (count($imageUrls) === count($cachedGallery)) {
                $galleryOk = collect($imageUrls)->every(function($url) use ($cachedGallery) {
                    $hash = md5($url);
                    return collect($cachedGallery)->contains(function($g) use ($hash) {
                        return !empty($g['original']) 
                            && strpos($g['original'], $hash) !== false
                            && \Illuminate\Support\Facades\Storage::disk('local')->exists($g['original'])
                            && !empty($g['watermark'])
                            && \Illuminate\Support\Facades\Storage::disk('public')->exists($g['watermark']);
                    });
                });
            }

            if ($coverOk && $galleryOk) {
                $isUpToDate = true;
            }
        }

        if ($isUpToDate) {
            return [
                'cover_image_original' => $cached->cover_image_original,
                'cover_image_watermark' => $cached->cover_image_watermark,
                'images' => $cached->images,
            ];
        }

        // Delete old files from disk if cached record exists
        if ($cached) {
            if ($cached->cover_image_original) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($cached->cover_image_original);
            }
            if ($cached->cover_image_watermark) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($cached->cover_image_watermark);
            }
            $gallery = is_array($cached->images) ? $cached->images : [];
            foreach ($gallery as $g) {
                if (!empty($g['original'])) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($g['original']);
                }
                if (!empty($g['watermark'])) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($g['watermark']);
                }
            }
        }

        // Process cover image
        $coverResult = $coverImageUrl
            ? self::processImage($coverImageUrl, $itemId, 'cover')
            : null;

        // Process gallery images
        $galleryResults = [];
        foreach ($imageUrls as $imgUrl) {
            $res = self::processImage($imgUrl, $itemId, 'gallery');
            if ($res) {
                $galleryResults[] = $res;
            }
        }

        // Create or update DB cache record
        $cached = \App\Models\ImageRealEstate::updateOrCreate(
            ['real_estate_id' => $itemId],
            [
                'cover_image_original'  => $coverResult['original']  ?? null,
                'cover_image_watermark' => $coverResult['watermark'] ?? null,
                'images'                => $galleryResults,
            ]
        );

        // Also update Property table directly if it exists
        \App\Models\Property::where('id', $itemId)->update([
            'cover_image_watermark' => $coverResult['watermark'] ?? null,
            'images'                => $galleryResults,
        ]);

        Log::info("WatermarkHelper: syncItemImages — processed and updated images for real_estate_id={$itemId}");

        return [
            'cover_image_original' => $cached->cover_image_original,
            'cover_image_watermark' => $cached->cover_image_watermark,
            'images' => $cached->images,
        ];
    }
}

