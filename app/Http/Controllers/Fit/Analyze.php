<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Calories\FoodAnalysisException;
use App\Services\Calories\FoodPhotoAnalyzer;
use App\Services\Fit\ScanQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Analyze extends Controller
{
    public function __invoke(Request $request, FoodPhotoAnalyzer $analyzer, ScanQuota $quota): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:5120'],
        ], [
            'photo.required' => 'Alege o poză.',
            'photo.mimetypes' => 'Poza trebuie să fie JPEG, PNG, GIF sau WebP.',
            'photo.max' => 'Poza poate avea maxim 5 MB.',
        ]);

        $user = $request->user();

        if ($quota->exhausted($user)) {
            return response()->json([
                'message' => "Ai folosit cele {$quota->limit()} analize de azi. Poți adăuga masa manual sau încerca mâine.",
                'scans_left' => 0,
            ], 429);
        }

        $quota->consume($user);
        $photo = $request->file('photo');

        try {
            $result = $analyzer->analyze($photo->get(), $photo->getMimeType());
        } catch (FoodAnalysisException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([...$result, 'scans_left' => $quota->remaining($user)]);
    }
}
