<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateMessageNotifications extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $request->user()->update(['notify_messages' => $data['enabled']]);

        return back();
    }
}
