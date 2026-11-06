<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\SeatPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function show(Event $event, SeatPlan $plan): View
    {
        return view('plan', ['event' => $event, 'plan' => $plan->snapshot($event), 'layout' => $event->venueLayout()]);
    }

    public function data(Event $event, SeatPlan $plan): JsonResponse
    {
        return response()->json($plan->snapshot($event));
    }
}
