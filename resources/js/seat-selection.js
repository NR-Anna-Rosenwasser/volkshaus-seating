import seatMap from './seat-map';

const REFRESH_MS = 10_000;

/**
 * The seat map plus choosing seats. Nothing is held while choosing: the
 * server decides on save, and a conflict sends the buyer back here.
 */
export default ({ needed, initial, url, suggestUrl, saveUrl, passUrl, csrf, sections, total }) => {
    const map = seatMap({ url, refreshMs: REFRESH_MS, sections, total });

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
            return this.zoomed ? 'Tippt auf einen Platz, um ihn zu wählen.' : 'Tippt auf einen Bereich, um ihn zu vergrössern.';
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

        labels() {
            return this.selected.map((id) => this.seatLabel(this.seatElement(id)));
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

        complete() {
            return this.selected.length === this.needed;
        },

        afterRefresh() {
            const lost = this.selected.filter((id) => this.seatElement(id)?.dataset.state !== 'free');

            if (lost.length > 0) {
                this.dropLost(lost);
            }
        },

        dropLost(seats) {
            this.selected = this.selected.filter((id) => !seats.includes(id));
            this.notice = 'Diese Plätze wurden gerade vergeben. Bitte wählt andere.';
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
                    this.notice = 'Es sind nicht mehr genug Plätze frei.';

                    return;
                }

                this.selected = seats;
                this.paint();
                this.showBounds(seats);

                if (!this.together()) {
                    this.notice = 'Für eure Gruppe gibt es keine Plätze mehr am Stück. Das ist die beste Aufteilung.';
                }
            } catch {
                this.notice = 'Das hat nicht geklappt. Bitte versucht es noch einmal.';
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
                    this.notice = 'Das Speichern hat nicht geklappt. Bitte versucht es noch einmal.';
                }
            } catch {
                this.notice = 'Das Speichern hat nicht geklappt. Bitte prüft eure Verbindung.';
            } finally {
                this.busy = false;
            }
        },
    };
};
