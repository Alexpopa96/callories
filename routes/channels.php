<?php

use Illuminate\Support\Facades\Broadcast;

// each user listens only to their own channel: chat events there carry ids, never message text
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
