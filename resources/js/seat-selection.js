import seatMap from './seat-map';

const REFRESH_MS = 10_000;

/**
 * The seat map plus choosing seats. Nothing is held while choosing: the
 * server decides on save, and a conflict sends the buyer back here.
 */
export default ({ needed, initial, url, suggestUrl, saveUrl, passUrl, csrf, sections, total }) => {
    const map = seatMap({ url, refreshMs: REFRESH_MS, sections, total });
    const say = (solo, group) => (needed === 1 ? solo : group);

    return {
        ...map,
        needed,
        selected: [...initial],
        notice: null,
        saved: false,
        busy: false,
        passUrl,

        init() {
            map.init.call(this);
            this.paint();
        },

        hint() {
            const verb = say('Tippe', 'Tippt');

            return this.zoomed ? `${verb} auf einen Platz, um ihn zu wählen.` : `${verb} auf einen Bereich, um ihn zu vergrössern.`;
        },

        paint() {
            this.$refs.viewport.querySelectorAll('.seat.is-selected').forEach((el) => el.classList.remove('is-selected'));
            this.selected.forEach((id) => this.seatElement(id)?.classList.add('is-selected'));
        },

        seatTapped(el) {
            const id = el.dataset.seat;

            this.notice = null;
            this.saved = false;

            if (this.selected.includes(id)) {
                this.selected = this.selected.filter((seat) => seat !== id);
            } else if (el.dataset.state !== 'free') {
                this.showSeat(el);

                return;
            } else if (this.needed === 1) {
                this.selected = [id];
            } else if (this.selected.length >= this.needed) {
                this.notice = `Ihr habt schon ${this.needed} Plätze gewählt. Tippt einen an, um ihn abzuwählen.`;

                return;
            } else {
                this.selected = [...this.selected, id];
            }

            this.paint();
        },

        selectedSeats() {
            return this.selected.map((id) => ({ id, label: this.seatLabel(this.seatElement(id)) }));
        },

        remove(id) {
            this.selected = this.selected.filter((seat) => seat !== id);
            this.notice = null;
            this.saved = false;
            this.paint();
        },

        clearAll() {
            this.selected = [];
            this.notice = null;
            this.saved = false;
            this.paint();
        },

        hasObstructed() {
            return this.selected.some((id) => this.seatElement(id)?.classList.contains('seat--obstructed'));
        },

        /** Same row and segment, without a gap. */
        together() {
            const seats = this.selected.map((id) => {
                const [section, row, number] = id.split('-');

                return { section, row, number: Number(number), segment: this.seatElement(id)?.dataset.segment };
            });

            const numbers = seats.map((seat) => seat.number).sort((a, b) => a - b);

            return seats.every((seat) => seat.section === seats[0].section && seat.row === seats[0].row && seat.segment === seats[0].segment)
                && numbers.every((number, index) => index === 0 || number === numbers[index - 1] + 1);
        },

        /** One free seat left between two of the group's own seats. */
        hasSingleGap() {
            return this.selected.some((id) => {
                const [prefix, row, number] = id.split('-');
                const between = this.seatElement(`${prefix}-${row}-${Number(number) + 1}`);
                const next = `${prefix}-${row}-${Number(number) + 2}`;

                return this.selected.includes(next)
                    && between?.dataset.state === 'free'
                    && !this.selected.includes(between.dataset.seat)
                    && between.dataset.segment === this.seatElement(id).dataset.segment
                    && this.seatElement(next).dataset.segment === this.seatElement(id).dataset.segment;
            });
        },

        complete() {
            return this.selected.length === this.needed;
        },

        canSave() {
            return this.complete() && !this.hasSingleGap() && !this.busy;
        },

        afterRefresh() {
            const lost = this.selected.filter((id) => this.seatElement(id)?.dataset.state !== 'free');

            if (lost.length > 0) {
                this.dropLost(lost);
            }
        },

        dropLost(seats) {
            this.selected = this.selected.filter((id) => !seats.includes(id));
            this.notice = say('Dieser Platz wurde gerade vergeben. Bitte wähle einen anderen.', 'Diese Plätze wurden gerade vergeben. Bitte wählt andere.');
            this.saved = false;
            this.paint();
        },

        async suggest() {
            this.busy = true;
            this.notice = null;
            this.saved = false;

            try {
                await this.refresh();

                const response = await fetch(suggestUrl, { headers: { Accept: 'application/json' } });

                if (response.status === 403) {
                    location.reload();

                    return;
                }

                const { seats } = await response.json();

                if (seats.length < this.needed) {
                    this.notice = say('Es ist kein Platz mehr frei.', 'Es sind nicht mehr genug Plätze frei.');

                    return;
                }

                this.selected = seats;
                this.paint();
                this.showBounds(seats);

                if (!this.together()) {
                    this.notice = 'Für eure Gruppe gibt es keine Plätze mehr am Stück. Das ist die beste Aufteilung.';
                }
            } catch {
                this.notice = say('Das hat nicht geklappt. Bitte versuche es noch einmal.', 'Das hat nicht geklappt. Bitte versucht es noch einmal.');
            } finally {
                this.busy = false;
            }
        },

        async save() {
            this.busy = true;
            this.notice = null;

            try {
                const response = await fetch(saveUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ seats: this.selected }),
                });

                if (response.status === 409) {
                    const { conflicts } = await response.json();

                    await this.refresh();
                    this.dropLost(conflicts);
                } else if (response.status === 403 || response.status === 419) {
                    location.reload();
                } else if (response.ok) {
                    this.saved = true;
                } else {
                    const body = await response.json().catch(() => ({}));

                    this.notice = body.errors?.seats?.[0] ?? say('Das Speichern hat nicht geklappt. Bitte versuche es noch einmal.', 'Das Speichern hat nicht geklappt. Bitte versucht es noch einmal.');
                }
            } catch {
                this.notice = say('Das Speichern hat nicht geklappt. Bitte prüfe deine Verbindung.', 'Das Speichern hat nicht geklappt. Bitte prüft eure Verbindung.');
            } finally {
                this.busy = false;
            }
        },
    };
};
