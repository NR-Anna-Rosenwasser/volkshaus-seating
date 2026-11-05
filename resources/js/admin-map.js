import seatMap from './seat-map';

const REFRESH_MS = 15_000;
const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * The seat map for the admin: tap or drag seats to block them, tap an assigned
 * seat to see who sits there and move them.
 */
export default ({ plan, firstReason }) => {
    const map = seatMap({ url: null, refreshMs: REFRESH_MS, sections: {}, total: {} });
    let drawn = null;

    return {
        ...map,
        selected: [],
        reason: firstReason,
        note: '',
        sellable: plan.sellable,
        sold: plan.sold,
        detail: null,
        moving: null,
        box: false,
        busy: false,

        init() {
            map.init.call(this);
            this.applyPlan(plan);
        },

        status() {
            if (this.seat) {
                return this.seat;
            }

            if (this.box) {
                return 'Ziehe ein Rechteck über die Plätze.';
            }

            return this.zoomed ? 'Tippe auf Plätze, um sie auszuwählen.' : 'Tippe auf einen Bereich oder nutze «Bereich wählen».';
        },

        applyPlan(data) {
            for (const [id, state] of Object.entries(data.seats)) {
                const el = this.seatElement(id);

                if (el) {
                    el.dataset.state = state;
                    data.reasons[id] ? (el.dataset.reason = data.reasons[id]) : delete el.dataset.reason;
                }
            }

            this.sellable = data.sellable;
            this.sold = data.sold;
            this.selected = this.selected.filter((id) => this.seatElement(id)?.dataset.state !== 'taken');
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
        },

        async seatTapped(el) {
            this.showSeat(el);

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
            await this.$wire.blockSeats(this.selected, this.reason, this.note);
            this.afterChange();
        },

        async unblock() {
            this.busy = true;
            await this.$wire.unblockSeats(this.selected);
            this.afterChange();
        },

        async afterChange() {
            this.clearSelection();
            this.note = '';
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
