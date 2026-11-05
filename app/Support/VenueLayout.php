<?php

namespace App\Support;

/**
 * The static geometry of the hall, read from the seat data file. Who sits
 * where lives in the database; this class only knows how the room looks.
 */
final class VenueLayout
{
    private const SEAT_PADDING = 16;

    private const VIEW_PADDING_X = 30;

    private const VIEW_PADDING_Y = 12;

    private const ROW_LABEL_GAP = 9;

    private const SECTION_LABEL_GAP = 16;

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private readonly string $path) {}

    public function seatRadius(): float
    {
        return (float) $this->data()['coordinate_system']['seat_radius'];
    }

    /**
     * @return array{label: string, x0: float, y0: float, x1: float, y1: float}
     */
    public function stage(): array
    {
        return $this->data()['stage'];
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    public function pillars(): array
    {
        return $this->data()['pillars'];
    }

    /**
     * Seats of one section, in file order.
     *
     * @return list<array{id: string, row: int, number: int, x: float, y: float, flags: list<string>}>
     */
    public function seats(string $section): array
    {
        return array_values(array_filter(
            $this->data()['seats'],
            fn (array $seat): bool => $seat['section'] === $section,
        ));
    }

    /**
     * Sections with the box they occupy and where their labels go.
     *
     * @return list<array{
     *     id: string,
     *     label: string,
     *     box: array{x: float, y: float, width: float, height: float},
     *     labelX: float,
     *     labelY: float,
     *     rowLabels: list<array{text: int, x: float, y: float}>
     * }>
     */
    public function sections(): array
    {
        return array_map(function (array $section): array {
            $seats = $this->seats($section['id']);
            $xs = array_column($seats, 'x');
            $ys = array_column($seats, 'y');
            $x0 = min($xs) - self::SEAT_PADDING;
            $x1 = max($xs) + self::SEAT_PADDING;
            $y0 = min($ys) - self::SEAT_PADDING;
            $y1 = max($ys) + self::SEAT_PADDING;

            return [
                'id' => $section['id'],
                'label' => $section['label'],
                'box' => ['x' => $x0, 'y' => $y0, 'width' => $x1 - $x0, 'height' => $y1 - $y0],
                'labelX' => ($x0 + $x1) / 2,
                'labelY' => max($ys) + self::SECTION_LABEL_GAP,
                'rowLabels' => $this->rowLabels($seats, $section['row_label_axis'] === 'vertical'),
            ];
        }, $this->data()['sections']);
    }

    public function sectionLabel(string $id): string
    {
        return collect($this->data()['sections'])->firstWhere('id', $id)['label'] ?? $id;
    }

    /**
     * Sections whose rows run away from the stage.
     *
     * @return list<string>
     */
    public function verticalSections(): array
    {
        return collect($this->data()['sections'])
            ->where('row_label_axis', 'vertical')
            ->pluck('id')
            ->all();
    }

    public function centerX(): float
    {
        return ($this->stage()['x0'] + $this->stage()['x1']) / 2;
    }

    /**
     * The SVG viewBox that fits the whole hall.
     *
     * @return array{x: float, y: float, width: float, height: float}
     */
    public function viewBox(): array
    {
        $seats = $this->data()['seats'];
        $stage = $this->stage();

        $x0 = min(min(array_column($seats, 'x')), $stage['x0']) - self::VIEW_PADDING_X;
        $x1 = max(max(array_column($seats, 'x')), $stage['x1']) + self::VIEW_PADDING_X;
        $y0 = min(min(array_column($seats, 'y')), $stage['y0']) - self::VIEW_PADDING_Y;
        $y1 = max(array_column($seats, 'y')) + self::SECTION_LABEL_GAP + self::VIEW_PADDING_Y;

        return ['x' => $x0, 'y' => $y0, 'width' => $x1 - $x0, 'height' => $y1 - $y0];
    }

    /**
     * Row numbers: at both ends of horizontal rows, above vertical ones.
     *
     * @param  list<array{row: int, x: float, y: float}>  $seats
     * @return list<array{text: int, x: float, y: float}>
     */
    private function rowLabels(array $seats, bool $vertical): array
    {
        $labels = [];

        foreach (collect($seats)->groupBy('row') as $row => $rowSeats) {
            if ($vertical) {
                $top = $rowSeats->sortBy('y')->first();
                $labels[] = ['text' => $row, 'x' => $top['x'], 'y' => $top['y'] - self::ROW_LABEL_GAP - 2];

                continue;
            }

            $first = $rowSeats->sortBy('x')->first();
            $last = $rowSeats->sortBy('x')->last();
            $labels[] = ['text' => $row, 'x' => $first['x'] - self::ROW_LABEL_GAP, 'y' => $first['y']];
            $labels[] = ['text' => $row, 'x' => $last['x'] + self::ROW_LABEL_GAP, 'y' => $last['y']];
        }

        return $labels;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return $this->data ??= json_decode((string) file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);
    }
}
