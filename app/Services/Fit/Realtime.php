<?php

namespace App\Services\Fit;

use Throwable;

class Realtime
{
    /**
     * Broadcasts after the response is sent, so a slow or stopped websocket server never delays or breaks
     * the request; clients fall back to polling in that case.
     */
    public static function broadcast(object $event): void
    {
        defer(function () use ($event) {
            try {
                broadcast($event);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
