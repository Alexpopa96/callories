<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreItemPhoto extends Controller
{
    /**
     * A picture chosen by the user for one product of a meal; the item keeps its URL once the meal is saved.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:5120'],
        ], [
            'photo.max' => 'Poza poate avea maxim 5 MB.',
            'photo.mimetypes' => 'Alege o poză JPG, PNG, GIF sau WEBP.',
        ]);

        $path = $request->file('photo')->store("meals/{$request->user()->id}/items", 'public');

        return response()->json(['image_url' => '/storage/'.$path]);
    }
}
