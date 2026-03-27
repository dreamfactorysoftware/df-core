<?php

namespace DreamFactory\Core\Http\Controllers;

use DreamFactory\Core\Components\DfResponse;
use DreamFactory\Core\Events\GenerateReportEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function generate()
    {
        $message = 'Option is not active! Please enable it first';

        if (config('df.generate_report')) {
            Event::dispatch(GenerateReportEvent::class);
            $message = 'Generating report! Please check log folder in after a few minutes';

        }

        return DfResponse::create($message);
    }
}
