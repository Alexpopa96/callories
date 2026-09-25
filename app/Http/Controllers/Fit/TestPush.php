<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Fit\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestPush extends Controller
{
    public const ALLOWED_EMAILS = ['alexandru@mail.com'];

    public static function allowed(User $user): bool
    {
        return in_array($user->email, self::ALLOWED_EMAILS, true);
    }

    public function __invoke(Request $request, PushSender $push): JsonResponse
    {
        abort_unless(self::allowed($request->user()), 403);

        if (! $push->configured()) {
            return response()->json(['sent' => 0, 'reason' => 'Cheile VAPID lipsesc pe server.'], 422);
        }

        $sent = $push->send($request->user(), [
            'title' => 'Test notificare',
            'body' => 'Dacă vezi asta, notificările funcționează.',
            'url' => '/me',
        ]);

        return response()->json(['sent' => $sent]);
    }
}
