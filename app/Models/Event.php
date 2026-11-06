<?php

namespace App\Models;

use App\Support\DataRetention;
use App\Support\SelectionWindow;
use App\Support\VenueLayout;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'hievents_event_id', 'slug', 'name', 'venue_name', 'layout',
    'seated_product_ids', 'wheelchair_question_ids', 'wheelchair_notify_emails',
    'starts_at', 'doors_at', 'selection_opens_at', 'selection_deadline',
    'reminder_offsets_days', 'final_mail_offset_days', 'contact_email',
    'purge_after_days', 'purged_at',
])]
#[Hidden(['layout'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    private ?VenueLayout $venueLayout = null;

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->slug ??= static::freeSlug($event->name);
        });

        static::saved(function (Event $event): void {
            $event->venueLayout = null;
        });
    }

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'seated_product_ids' => 'array',
            'wheelchair_question_ids' => 'array',
            'wheelchair_notify_emails' => 'array',
            'reminder_offsets_days' => 'array',
            'starts_at' => 'datetime',
            'doors_at' => 'datetime',
            'selection_opens_at' => 'datetime',
            'selection_deadline' => 'datetime',
            'purged_at' => 'datetime',
            'final_mail_offset_days' => 'integer',
            'purge_after_days' => 'integer',
        ];
    }

    private static function freeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'event';
        // /admin/events/neu is the page for creating an event.
        $base = $base === 'neu' ? 'event-neu' : $base;
        $slug = $base;

        for ($suffix = 2; static::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Events that still sync, send mail and show up in lists.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('purged_at');
    }

    /**
     * Events the scheduler works on. The cut-off is a date, so this also drops
     * events whose purge is due but has not run yet.
     *
     * @return Collection<int, Event>
     */
    public static function running(): Collection
    {
        return static::active()
            ->orderBy('id')
            ->get()
            ->reject(fn (Event $event): bool => DataRetention::for($event)->isOver())
            ->values()
            ->toBase();
    }

    public function window(): SelectionWindow
    {
        return SelectionWindow::for($this);
    }

    public function venueLayout(): VenueLayout
    {
        return $this->venueLayout ??= new VenueLayout($this->layout ?? []);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }
}
