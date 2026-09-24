<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\MealBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpdateMeal extends Controller
{
    public function __invoke(Request $request, int $meal): RedirectResponse
    {
        $meal = $request->user()->meals()->findOrFail($meal);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            ...MealBuilder::itemRules(),
            'notes' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
        ], [
            'photo.max' => 'Poza poate avea maxim 5 MB.',
            'photo.mimetypes' => 'Alege o poză JPG, PNG, GIF sau WEBP.',
        ]);

        // the photo is only replaced or removed when the changes are saved
        $previousPhoto = $meal->photo_path;
        $photoPath = match (true) {
            $request->hasFile('photo') => $request->file('photo')->store("meals/{$meal->user_id}", 'public'),
            $request->boolean('remove_photo') => null,
            default => $previousPhoto,
        };

        $meal->update([
            ...MealBuilder::attributes($data['items'], $data['title'] ?? null),
            ...(array_key_exists('notes', $data) ? ['notes' => $data['notes']] : []),
            'photo_path' => $photoPath,
        ]);

        if ($previousPhoto && $previousPhoto !== $photoPath) {
            Storage::disk('public')->delete($previousPhoto);
        }

        return redirect('/today?date='.CarbonImmutable::parse($meal->eaten_on)->toDateString())->with('success', 'Masa a fost actualizată.');
    }
}
