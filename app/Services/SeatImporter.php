<?php

namespace App\Services;

use App\Enums\BlockReason;
use App\Models\Event;
use App\Models\Seat;
use InvalidArgumentException;

class SeatImporter
{
    /**
     * Seat flags that start out blocked. Checked in this order.
     */
    private const DEFAULT_BLOCKS = [
        'foh_var2' => BlockReason::Tech,
        'wheelchair' => BlockReason::Wheelchair,
    ];

    private const REQUIRED_FIELDS = ['id', 'section', 'row', 'number', 'segment', 'x', 'y'];

    /**
     * Gives the event the plan in the JSON file and returns how many seats it holds.
     *
     * Default blocks are written only when a seat is first inserted, so importing
     * again never undoes an admin's blocks or releases, or touches assignments.
     */
    public function import(Event $event, string $path): int
    {
        $layout = $this->read($path);
        $seats = $layout['seats'];

        $event->update(['layout' => $layout]);

        foreach (array_chunk($seats, 200) as $chunk) {
            Seat::query()->upsert(
                array_map(fn (array $seat): array => $this->toRow($event, $seat), $chunk),
                ['event_id', 'code'],
                ['section', 'row', 'number', 'segment', 'x', 'y', 'flags'],
            );
        }

        return count($seats);
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Seat data file not found: {$path}");
        }

        $layout = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $seats = $layout['seats'] ?? null;

        if (! is_array($seats) || $seats === []) {
            throw new InvalidArgumentException("Seat data file has no seats: {$path}");
        }

        foreach ($seats as $index => $seat) {
            foreach (self::REQUIRED_FIELDS as $field) {
                if (! isset($seat[$field])) {
                    throw new InvalidArgumentException("Seat at index {$index} is missing \"{$field}\".");
                }
            }
        }

        $ids = array_column($seats, 'id');
        $duplicates = array_keys(array_filter(array_count_values($ids), fn (int $count) => $count > 1));

        if ($duplicates !== []) {
            throw new InvalidArgumentException('Duplicate seat ids: '.implode(', ', $duplicates));
        }

        return $layout;
    }

    /**
     * @param  array<string, mixed>  $seat
     * @return array<string, mixed>
     */
    private function toRow(Event $event, array $seat): array
    {
        $flags = $seat['flags'] ?? [];

        return [
            'event_id' => $event->id,
            'code' => $seat['id'],
            'section' => $seat['section'],
            'row' => $seat['row'],
            'number' => $seat['number'],
            'segment' => $seat['segment'],
            'x' => $seat['x'],
            'y' => $seat['y'],
            'flags' => json_encode($flags, JSON_THROW_ON_ERROR),
            'block_reason' => $this->defaultBlock($flags)?->value,
        ];
    }

    /**
     * @param  list<string>  $flags
     */
    private function defaultBlock(array $flags): ?BlockReason
    {
        foreach (self::DEFAULT_BLOCKS as $flag => $reason) {
            if (in_array($flag, $flags, true)) {
                return $reason;
            }
        }

        return null;
    }
}
