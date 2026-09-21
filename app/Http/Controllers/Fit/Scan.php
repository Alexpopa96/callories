<?php

namespace App\Http\Controllers\Fit;

use App\Http\Controllers\Controller;
use App\Services\Fit\DayStats;
use App\Services\Fit\ScanQuota;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Scan extends Controller
{
    public function __invoke(Request $request, ScanQuota $quota): Response
    {
        $date = DayStats::resolveDate($request->query('date'));

        return Inertia::render('Fit/Scan', [
            'date' => $date->toDateString(),
            'dateLabel' => DayStats::label($date),
            'isToday' => $date->isToday(),
            'scansLeft' => $quota->remaining($request->user()),
        ]);
    }
}
