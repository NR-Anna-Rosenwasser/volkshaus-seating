<?php

namespace App\Http\Controllers;

use App\Services\SeatPlan;
use App\Support\VenueLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function show(SeatPlan $plan, VenueLayout $layout): View
    {
        return view('plan', ['plan' => $plan->snapshot(), 'layout' => $layout]);
    }

    public function data(SeatPlan $plan): JsonResponse
    {
        return response()->json($plan->snapshot());
    }
}
