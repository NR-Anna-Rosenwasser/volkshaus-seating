<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $events = Event::running()
            ->filter(fn (Event $event): bool => ($event->starts_at === null || $event->starts_at->isFuture()) && $event->layout !== null)
            ->sortBy('starts_at')
            ->values();

        return $events->count() === 1
            ? redirect()->route('plan', $events->first())
            : view('home', ['events' => $events]);
    }
}
