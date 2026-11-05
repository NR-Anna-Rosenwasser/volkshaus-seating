<x-filament-panels::page>
    {{-- The map and its panels are driven by Alpine, so Livewire must leave them alone. --}}
    <div
        wire:ignore
        class="admin-map"
        x-data="adminMap({ plan: @js($plan), firstReason: @js(array_key_first($reasons)) })"
    >
        <div class="admin-map__main">
            <div class="admin-map__bar">
                <button type="button" class="map-button admin-map__wide" x-show="zoomed" x-cloak x-on:click="showOverview()">Übersicht</button>
                <button type="button" class="map-button admin-map__wide" x-bind:aria-pressed="box" x-bind:class="box && 'is-on'" x-on:click="toggleBox()">Bereich wählen</button>
                <span class="admin-map__spacer"></span>
                <button type="button" class="map-button admin-map__round" aria-label="Verkleinern" x-on:click="zoomBy(1 / 1.5)">−</button>
                <button type="button" class="map-button admin-map__round" aria-label="Vergrössern" x-on:click="zoomBy(1.5)">+</button>
            </div>

            <div x-ref="viewport" class="seat-map-viewport">
                <x-seat-map :layout="$layout" :states="$plan['seats']" />
            </div>

            <p class="admin-map__status" role="status" x-text="status()"></p>
        </div>

        <aside class="admin-map__side">
            <div class="admin-card">
                <p class="admin-card__big"><span x-text="sellable"></span> verkaufbare Plätze</p>
                <p><span x-text="sold"></span> Tickets verkauft. Die Kapazität in Hi.Events muss den verkaufbaren Plätzen entsprechen.</p>
            </div>

            <nav class="admin-map__sections" aria-label="Bereiche">
                @foreach ($layout->sections() as $section)
                    <button type="button" class="section-button" x-on:click="showSection(@js($section['id']))">{{ $section['label'] }}</button>
                @endforeach
            </nav>

            <div class="admin-card" x-cloak x-show="moving">
                <p class="admin-card__big">Neuer Platz gesucht</p>
                <p>Tippe auf einen freien Platz für <strong x-text="moving?.person"></strong>.</p>
                <x-filament::button color="gray" x-on:click="moving = null">Abbrechen</x-filament::button>
            </div>

            <div class="admin-card" x-cloak x-show="detail && !moving">
                <p class="admin-card__big" x-text="detail?.label"></p>
                <p><strong x-text="detail?.person"></strong></p>
                <p>Bestellung <span x-text="detail?.orderNumber"></span> von <span x-text="detail?.buyer"></span></p>
                <ul class="admin-card__list">
                    <template x-for="seat in detail?.group ?? []" x-bind:key="seat"><li x-text="seat"></li></template>
                </ul>
                <div class="admin-card__actions">
                    <x-filament::button x-on:click="startMove()">Verschieben</x-filament::button>
                    <x-filament::button color="gray" tag="a" x-bind:href="detail?.orderUrl">Zur Bestellung</x-filament::button>
                </div>
            </div>

            <div class="admin-card" x-cloak x-show="selected.length > 0 && !moving">
                <p class="admin-card__big"><span x-text="selected.length"></span> Plätze ausgewählt</p>

                <label class="admin-field">
                    <span>Grund</span>
                    <select x-model="reason">
                        @foreach ($reasons as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-field">
                    <span>Notiz (optional)</span>
                    <input type="text" maxlength="255" x-model="note">
                </label>

                <div class="admin-card__actions">
                    <x-filament::button x-bind:disabled="busy" x-on:click="block()">Sperren</x-filament::button>
                    <x-filament::button color="gray" x-bind:disabled="busy" x-on:click="unblock()">Freigeben</x-filament::button>
                    <x-filament::button color="gray" outlined x-on:click="clearSelection()">Abwählen</x-filament::button>
                </div>
            </div>

            <ul class="admin-legend" aria-label="Legende">
                <li><span class="legend-dot legend-dot--selected"></span>Ausgewählt</li>
                <li><span class="legend-dot" data-state="free"></span>Frei</li>
                <li><span class="legend-dot admin-legend__taken"></span>Belegt</li>
                <li><span class="legend-dot legend-dot--obstructed" data-state="free"></span>Sichtbehinderung</li>
                @foreach ($reasons as $value => $label)
                    <li><span class="legend-dot admin-legend__reason" data-reason="{{ $value }}"></span>{{ $label }}</li>
                @endforeach
                <li><span class="legend-dot legend-dot--pillar"></span>Säule</li>
            </ul>
        </aside>
    </div>
</x-filament-panels::page>
