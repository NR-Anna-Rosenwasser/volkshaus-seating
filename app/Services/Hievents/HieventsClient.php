<?php

namespace App\Services\Hievents;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Read access to the Hi.Events API. See docs/hievents-api.md.
 */
class HieventsClient
{
    private const PER_PAGE = 100;

    /**
     * Every order of the event, with attendees but without question answers.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function orders(): Generator
    {
        $page = 1;

        do {
            $response = $this->request()
                ->get($this->eventPath('orders'), ['per_page' => self::PER_PAGE, 'page' => $page])
                ->throw()
                ->json();

            yield from $response['data'];
        } while ($page++ < ($response['meta']['last_page'] ?? 1));
    }

    /**
     * One order, including its question answers.
     *
     * @return array<string, mixed>
     */
    public function order(int|string $id): array
    {
        return $this->request()->get($this->eventPath("orders/{$id}"))->throw()->json('data');
    }

    private function eventPath(string $path): string
    {
        return 'events/'.config('seating.event_id').'/'.$path;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('seating.hievents.base_url'), '/').'/api')
            ->withToken((string) config('seating.hievents.api_key'))
            ->acceptJson()
            ->timeout(20)
            ->retry(3, 500, throw: false);
    }
}
