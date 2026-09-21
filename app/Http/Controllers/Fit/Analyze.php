<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Calories\FoodAnalysisException;
use App\Services\Calories\FoodPhotoAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Analyze extends Controller
{
    public function __invoke(Request $request, FoodPhotoAnalyzer $analyzer): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:5120'],
        ], [
            'photo.required' => 'Alege o poză.',
            'photo.mimetypes' => 'Poza trebuie să fie JPEG, PNG, GIF sau WebP.',
            'photo.max' => 'Poza poate avea maxim 5 MB.',
        ]);

        $photo = $request->file('photo');

        try {
            $result = $analyzer->analyze($photo->get(), $photo->getMimeType());
        } catch (FoodAnalysisException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }
}
