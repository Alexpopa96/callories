<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\Workout;
use App\Services\Fit\WorkoutQuota;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowWorkout extends Controller
{
    public function __invoke(Request $request, WorkoutQuota $quota): Response
    {
        $user = $request->user();

        $history = $user->workouts()->orderByDesc('date')->orderByDesc('id')->limit(20)->get();

        return Inertia::render('Fit/Workout', [
            'questionsLeft' => $quota->remaining($user),
            'history' => $history->map(fn (Workout $workout) => [
                'id' => $workout->id,
                'date' => $workout->date->toDateString(),
                'title' => $workout->title,
                'exercises' => $workout->exercises,
            ])->values(),
        ]);
    }
}
