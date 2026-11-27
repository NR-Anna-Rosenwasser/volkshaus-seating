import seatMap from './seat-map';

const REFRESH_MS = 15_000;
const SVG_NS = 'http://www.w3.org/2000/svg';
const LISTED_SEATS = 8;

/**
 * The seat map for the admin: tap or drag seats to block them, tap an assigned
 * seat to see who sits there and move them.
 */
export default ({ plan, reasons, assigning }) => {
    const map = seatMap({ url: null, refreshMs: REFRESH_MS, sections: {}, total: {} });
    const firstReason = Object.keys(reasons)[0];
    let drawn = null;
    let active = null;

    return {
        ...map,
        selected: assigning ? [...assigning.seats] : [],
        full: false,
        reason: firstReason,
        note: '',
        reasons,
        // Set once the admin edits a field, so a changing selection leaves it alone.
        reasonTouched: false,
        noteTouched: false,
        mixedReason: false,
        mixedNote: false,
        sellable: plan.sellable,
        sold: plan.sold,
        detail: null,
        moving: null,
        box: false,
        busy: false,

        init() {
            map.init.call(this);
            this.applyPlan(plan);

            if (assigning) {
                this.showOrderSeats();
            }
        },

        /** Starts at the order's seats, or at the wheelchair seats if it needs those. */
        showOrderSeats() {
            const wheelchair = assigning.wheelchair
                ? [...this.$refs.viewport.querySelectorAll('[data-reason="wheelchair"]')].map((el) => el.dataset.seat)
                : [];
            const seats = assigning.seats.length > 0 ? assigning.seats : wheelchair;

            if (seats.length > 0) {
                this.showBounds(seats);
            }
        },

        status() {
            if (this.seat) {
                return this.seat;
            }

            if (this.box) {
                return 'Ziehe ein Rechteck über die Plätze.';
            }

            if (assigning) {
                return 'Tippe auf freie Plätze, um sie der Bestellung zuzuweisen.';
            }

            return this.zoomed ? 'Tippe auf Plätze, um sie auszuwählen.' : 'Tippe auf einen Bereich oder nutze «Bereich wählen».';
        },

        applyPlan(data) {
            for (const [id, state] of Object.entries(data.seats)) {
                const el = this.seatElement(id);

                if (el) {
                    el.dataset.state = state;
                    data.reasons[id] ? (el.dataset.reason = data.reasons[id]) : delete el.dataset.reason;
                    data.notes[id] ? (el.dataset.note = data.notes[id]) : delete el.dataset.note;
                    this.markNote(el);
                }
            }

            this.sellable = data.sellable;
            this.sold = data.sold;
            this.selected = this.selected.filter((id) => (assigning ? this.canAssign(this.seatElement(id)) : this.seatElement(id)?.dataset.state !== 'taken'));
            this.paint();
        },

        async refresh() {
            if (document.visibilityState === 'visible') {
                this.applyPlan(await this.$wire.mapData());
            }
        },

        paint() {
            this.$refs.viewport.querySelectorAll('.seat.is-selected').forEach((el) => el.classList.remove('is-selected'));
            this.selected.forEach((id) => this.seatElement(id)?.classList.add('is-selected'));
            this.syncForm();
        },

        /** A small dot on blocked seats that have a note, so missing names stand out. */
        markNote(el) {
            const marker = el.querySelector('.seat__note');

            if (!el.dataset.note) {
                marker?.remove();

                return;
            }

            if (marker) {
                return;
            }

            const circle = el.querySelector('circle');
            const r = circle.r.baseVal.value;
            const dot = document.createElementNS(SVG_NS, 'circle');

            dot.classList.add('seat__note');
            dot.setAttribute('cx', circle.cx.baseVal.value + r * 0.75);
            dot.setAttribute('cy', circle.cy.baseVal.value - r * 0.75);
            dot.setAttribute('r', r * 0.4);
            el.append(dot);
        },

        selectedSeats() {
            return this.selected.map((id) => this.seatElement(id)).filter(Boolean);
        },

        /** Fills reason and note with what the selected seats have now. */
        syncForm() {
            const seats = this.selectedSeats();

            if (seats.length === 0) {
                this.reasonTouched = false;
                this.noteTouched = false;
            }

            const blockedReasons = new Set(seats.filter((el) => el.dataset.reason).map((el) => el.dataset.reason));
            const notes = new Set(seats.map((el) => el.dataset.note ?? ''));

            this.mixedReason = blockedReasons.size > 1;
            this.mixedNote = notes.size > 1;

            if (!this.reasonTouched) {
                this.reason = blockedReasons.size === 1 ? [...blockedReasons][0] : this.mixedReason ? '' : firstReason;
            }

            if (!this.noteTouched) {
                this.note = notes.size === 1 ? [...notes][0] : '';
            }
        },

        allBlocked() {
            const seats = this.selectedSeats();

            return seats.length > 0 && seats.every((el) => el.dataset.reason);
        },

        /** Keeping each seat's reason only works for seats that have one. */
        canBlock() {
            return this.reason !== '' || this.allBlocked();
        },

        selectedRows() {
            return this.selectedSeats().slice(0, LISTED_SEATS).map((el) => ({
                id: el.dataset.seat,
                text: [this.seatLabel(el), el.dataset.reason ? reasons[el.dataset.reason] : 'frei', el.dataset.note].filter(Boolean).join(' · '),
            }));
        },

        moreSelected() {
            return Math.max(0, this.selected.length - LISTED_SEATS);
        },

        showSeat(el) {
            active = el;
            map.showSeat.call(this, el);
        },

        clearSeat() {
            active = null;
            map.clearSeat.call(this);
        },

        describeSeat() {
            map.describeSeat.call(this);

            if (active?.dataset.reason) {
                this.seat = [this.seat, reasons[active.dataset.reason], active.dataset.note].filter(Boolean).join(' · ');
            }
        },

        /** Free, a wheelchair seat, or already this order's own. */
        canAssign(el) {
            return el?.dataset.state === 'free' || el?.dataset.reason === 'wheelchair' || assigning?.seats.includes(el?.dataset.seat);
        },

        assignTapped(el) {
            const id = el.dataset.seat;

            this.full = false;

            if (this.selected.includes(id)) {
                this.selected = this.selected.filter((seat) => seat !== id);
            } else if (!this.canAssign(el)) {
                return;
            } else if (assigning.needed === 1) {
                this.selected = [id];
            } else if (this.selected.length >= assigning.needed) {
                this.full = true;

                return;
            } else {
                this.selected = [...this.selected, id];
            }

            this.paint();
        },

        selectedLabels() {
            return this.selected.map((id) => this.seatLabel(this.seatElement(id)));
        },

        async saveAssignment() {
            this.busy = true;

            if (!(await this.$wire.saveOrderSeats(this.selected))) {
                await this.refresh();
                this.busy = false;
            }
        },

        async seatTapped(el) {
            this.showSeat(el);

            if (assigning) {
                this.assignTapped(el);

                return;
            }

            const canTakePerson = el.dataset.state === 'free' || el.dataset.reason === 'wheelchair';

            if (this.moving) {
                if (canTakePerson) {
                    await this.finishMove(el.dataset.seat);
                }

                return;
            }

            if (el.dataset.state === 'taken') {
                this.selected = [];
                this.paint();
                this.detail = await this.$wire.seatDetails(el.dataset.seat);

                return;
            }

            const id = el.dataset.seat;

            this.detail = null;
            this.selected = this.selected.includes(id) ? this.selected.filter((seat) => seat !== id) : [...this.selected, id];
            this.paint();
        },

        startMove() {
            this.moving = this.detail;
            this.detail = null;
        },

        async finishMove(to) {
            const moved = await this.$wire.moveSeat(this.moving.id, to);

            if (moved) {
                this.moving = null;
                await this.refresh();
            }
        },

        async block() {
            this.busy = true;
            // Null keeps each seat's own note when they differ and nobody typed one.
            const note = this.mixedNote && !this.noteTouched ? null : this.note;

            await this.$wire.blockSeats(this.selected, this.reason, note);
            this.afterChange();
        },

        async unblock() {
            this.busy = true;
            await this.$wire.unblockSeats(this.selected);
            this.afterChange();
        },

        async afterChange() {
            this.clearSelection();
            await this.refresh();
            this.busy = false;
        },

        clearSelection() {
            this.selected = [];
            this.paint();
        },

        toggleBox() {
            this.box = !this.box;
            this.$refs.viewport.querySelector('svg').classList.toggle('is-boxing', this.box);
        },

        /** Screen position to SVG coordinates. */
        svgPoint(event) {
            const svg = this.$refs.viewport.querySelector('svg');

            return new DOMPoint(event.clientX, event.clientY).matrixTransform(svg.getScreenCTM().inverse());
        },

        pointerDown(event) {
            if (!this.box || event.button !== 0) {
                map.pointerDown.call(this, event);

                return;
            }

            const svg = this.$refs.viewport.querySelector('svg');
            const start = this.svgPoint(event);
            const rect = document.createElementNS(SVG_NS, 'rect');

            rect.classList.add('seat-map__box');
            svg.append(rect);
            svg.setPointerCapture(event.pointerId);
            drawn = { start, rect };
        },

        pointerMove(event) {
            if (!drawn) {
                map.pointerMove.call(this, event);

                return;
            }

            const point = this.svgPoint(event);

            drawn.rect.setAttribute('x', Math.min(drawn.start.x, point.x));
            drawn.rect.setAttribute('y', Math.min(drawn.start.y, point.y));
            drawn.rect.setAttribute('width', Math.abs(drawn.start.x - point.x));
            drawn.rect.setAttribute('height', Math.abs(drawn.start.y - point.y));
        },

        pointerUp(event) {
            if (!drawn) {
                map.pointerUp.call(this, event);

                return;
            }

            const point = this.svgPoint(event);
            const [x0, x1] = [drawn.start.x, point.x].sort((a, b) => a - b);
            const [y0, y1] = [drawn.start.y, point.y].sort((a, b) => a - b);
            const inside = [...this.$refs.viewport.querySelectorAll('[data-seat]')].filter((el) => {
                const circle = el.querySelector('circle');
                const cx = circle.cx.baseVal.value;
                const cy = circle.cy.baseVal.value;

                return el.dataset.state !== 'taken' && cx >= x0 && cx <= x1 && cy >= y0 && cy <= y1;
            });

            drawn.rect.remove();
            drawn = null;
            this.detail = null;
            this.selected = [...new Set([...this.selected, ...inside.map((el) => el.dataset.seat)])];
            this.paint();
        },

        pointerCancel(event) {
            drawn?.rect.remove();
            drawn = null;
            map.pointerCancel.call(this, event);
        },
    };
};
