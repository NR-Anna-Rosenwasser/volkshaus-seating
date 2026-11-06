// Screen pixels per SVG unit.
const MAX_SCALE = 9;
const READABLE_SCALE = 1.25;
const SECTION_SCALE = 2.2;
const NUMBERS_SCALE = 1.1;

const TAP_SLOP_PX = 8;
const ANIMATION_MS = 260;

const STATE_LABELS = { free: 'frei', taken: 'belegt', blocked: 'gesperrt' };

const swiss = (number) => String(number).replace(/\B(?=(\d{3})+(?!\d))/g, "'");

/**
 * Pan and zoom work on the SVG viewBox, so the map stays sharp at any scale.
 * The view always has the viewport's aspect ratio, which keeps pixels and SVG
 * units in a fixed relation.
 */
export default ({ url, refreshMs, sections, total }) => {
    // Kept outside the Alpine state so it never becomes reactive.
    let viewportEl;
    let svg;
    let base;
    let size = { width: 0, height: 0 };
    let view;
    let animation = null;
    let activeSeat = null;
    let gesture = null;
    const seats = new Map();
    const pointers = new Map();

    const scaleOf = (box) => size.width / box.w;

    const overview = () => {
        const scale = Math.min(size.width / base.w, size.height / base.h);
        const w = size.width / scale;
        const h = size.height / scale;

        return { x: base.x + (base.w - w) / 2, y: base.y + (base.h - h) / 2, w, h };
    };

    const clamp = (box) => {
        const bounds = overview();
        const w = Math.min(Math.max(box.w, size.width / MAX_SCALE), bounds.w);
        const h = (w * size.height) / size.width;

        return {
            x: Math.min(Math.max(box.x, bounds.x), bounds.x + bounds.w - w),
            y: Math.min(Math.max(box.y, bounds.y), bounds.y + bounds.h - h),
            w,
            h,
        };
    };

    const applyView = (box) => {
        view = box;
        svg.setAttribute('viewBox', `${box.x} ${box.y} ${box.w} ${box.h}`);
    };

    const pointerPosition = (event) => {
        const rect = viewportEl.getBoundingClientRect();

        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    };

    const startPinch = () => {
        const [a, b] = [...pointers.values()];
        const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };

        gesture = {
            type: 'pinch',
            view,
            distance: Math.max(Math.hypot(a.x - b.x, a.y - b.y), 1),
            anchor: { x: view.x + mid.x / scaleOf(view), y: view.y + mid.y / scaleOf(view) },
        };
    };

    const startDrag = (position, target = null) => {
        gesture = { type: 'drag', view, start: position, target };
    };

    return {
        sections,
        total,
        section: null,
        seat: null,
        zoomed: false,
        swiss,

        hint() {
            return this.zoomed ? 'Tippt auf einen Platz für Details.' : 'Tippt auf einen Bereich, um ihn zu vergrössern.';
        },

        seatTapped(el) {
            this.showSeat(el);
        },

        seatElement(id) {
            return seats.get(id);
        },

        seatLabel(el) {
            const { row, number } = el.dataset;

            return `${el.closest('[data-section-seats]').dataset.label} · Reihe ${row} · Platz ${number}`;
        },

        afterRefresh() {},

        init() {
            viewportEl = this.$refs.viewport;
            svg = viewportEl.querySelector('svg');

            const [x, y, w, h] = svg.getAttribute('viewBox').split(' ').map(Number);
            base = { x, y, w, h };
            svg.querySelectorAll('[data-seat]').forEach((el) => seats.set(el.dataset.seat, el));

            new ResizeObserver(() => this.measure()).observe(viewportEl);
            this.measure();

            svg.addEventListener('pointerdown', (event) => this.pointerDown(event));
            svg.addEventListener('pointermove', (event) => this.pointerMove(event));
            svg.addEventListener('pointerup', (event) => this.pointerUp(event));
            svg.addEventListener('pointercancel', (event) => this.pointerCancel(event));
            svg.addEventListener('wheel', (event) => this.wheel(event), { passive: false });

            setInterval(() => this.refresh(), refreshMs);
            document.addEventListener('visibilitychange', () => this.refresh());
        },

        measure() {
            const { width, height } = viewportEl.getBoundingClientRect();

            if (!width || !height || (width === size.width && height === size.height)) {
                return;
            }

            size = { width, height };

            if (this.section) {
                this.moveTo(this.sectionView(this.section), false);
            } else {
                this.moveTo(overview(), false);
            }
        },

        moveTo(target, animate = true) {
            cancelAnimationFrame(animation);

            const end = clamp(target);
            const start = view;
            const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!animate || !start || reducedMotion) {
                applyView(end);
                this.syncZoom();

                return;
            }

            const began = performance.now();

            const step = (now) => {
                const t = Math.min((now - began) / ANIMATION_MS, 1);
                const eased = 1 - (1 - t) ** 3;

                applyView({
                    x: start.x + (end.x - start.x) * eased,
                    y: start.y + (end.y - start.y) * eased,
                    w: start.w + (end.w - start.w) * eased,
                    h: start.h + (end.h - start.h) * eased,
                });

                if (t < 1) {
                    animation = requestAnimationFrame(step);
                } else {
                    this.syncZoom();
                }
            };

            animation = requestAnimationFrame(step);
        },

        syncZoom() {
            const scale = scaleOf(view);

            this.zoomed = scale > scaleOf(overview()) * 1.02;
            svg.classList.toggle('is-zoomed', this.zoomed);
            svg.classList.toggle('shows-numbers', scale >= NUMBERS_SCALE);

            if (!this.zoomed) {
                this.section = null;
                this.clearSeat();
            }
        },

        /**
         * Fit the section if its seats stay readable that way. Otherwise (the
         * tall side galleries) start at its stage end and let people pan.
         */
        sectionView(id) {
            const hit = svg.querySelector(`[data-section="${id}"]`);
            const box = hit.getBBox();
            const fitAll = Math.min(size.width / box.width, size.height / box.height);
            const fitWidth = size.width / box.width;
            const scale = fitAll >= READABLE_SCALE ? fitAll : Math.min(fitWidth, SECTION_SCALE);
            const w = size.width / scale;
            const h = size.height / scale;

            return {
                x: box.x + (box.width - w) / 2,
                y: h < box.height ? box.y : box.y + (box.height - h) / 2,
                w,
                h,
            };
        },

        showSection(id) {
            this.clearSeat();
            this.section = id;
            this.moveTo(this.sectionView(id));
            viewportEl.parentElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },

        /** Shows the given seats with some room around them. */
        showBounds(ids) {
            const circles = ids.map((id) => seats.get(id).querySelector('circle').getBBox());
            const x0 = Math.min(...circles.map((box) => box.x));
            const y0 = Math.min(...circles.map((box) => box.y));
            const x1 = Math.max(...circles.map((box) => box.x + box.width));
            const y1 = Math.max(...circles.map((box) => box.y + box.height));
            const scale = Math.min(size.width / (x1 - x0 + 120), size.height / (y1 - y0 + 120), SECTION_SCALE);
            const w = size.width / scale;
            const h = size.height / scale;

            this.section = null;
            this.moveTo({ x: (x0 + x1 - w) / 2, y: (y0 + y1 - h) / 2, w, h });
        },

        showOverview() {
            this.section = null;
            this.moveTo(overview());
        },

        zoomBy(factor) {
            const w = view.w / factor;
            const h = view.h / factor;

            this.moveTo({ x: view.x + (view.w - w) / 2, y: view.y + (view.h - h) / 2, w, h });
        },

        pointerDown(event) {
            if (event.button !== 0) {
                return;
            }

            cancelAnimationFrame(animation);
            svg.setPointerCapture(event.pointerId);

            const position = pointerPosition(event);
            pointers.set(event.pointerId, position);

            if (pointers.size === 2) {
                startPinch();
            } else if (pointers.size === 1) {
                startDrag(position, event.target);
            }
        },

        pointerMove(event) {
            if (!pointers.has(event.pointerId)) {
                return;
            }

            const position = pointerPosition(event);
            pointers.set(event.pointerId, position);

            if (gesture?.type === 'pinch' && pointers.size === 2) {
                const [a, b] = [...pointers.values()];
                const mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
                const factor = gesture.distance / Math.max(Math.hypot(a.x - b.x, a.y - b.y), 1);
                const w = gesture.view.w * factor;
                const h = gesture.view.h * factor;

                applyView(clamp({
                    x: gesture.anchor.x - (mid.x * w) / size.width,
                    y: gesture.anchor.y - (mid.y * w) / size.width,
                    w,
                    h,
                }));
                this.syncZoom();
            } else if (gesture?.type === 'drag') {
                const dx = position.x - gesture.start.x;
                const dy = position.y - gesture.start.y;

                if (Math.hypot(dx, dy) > TAP_SLOP_PX) {
                    gesture.target = null;
                }

                if (this.zoomed && !gesture.target) {
                    const scale = scaleOf(gesture.view);

                    applyView(clamp({
                        ...gesture.view,
                        x: gesture.view.x - dx / scale,
                        y: gesture.view.y - dy / scale,
                    }));
                }
            }
        },

        pointerUp(event) {
            if (!pointers.delete(event.pointerId)) {
                return;
            }

            if (pointers.size === 1) {
                startDrag([...pointers.values()][0]);
            } else if (pointers.size === 0) {
                if (gesture?.type === 'drag' && gesture.target) {
                    this.tap(gesture.target);
                }

                gesture = null;
            }
        },

        pointerCancel(event) {
            pointers.delete(event.pointerId);
            gesture = null;
        },

        wheel(event) {
            // Trackpad pinches arrive as ctrl+wheel. Plain scrolling stays with the page.
            if (!event.ctrlKey) {
                return;
            }

            event.preventDefault();

            const position = pointerPosition(event);
            const factor = Math.exp(event.deltaY * 0.01);
            const w = view.w * factor;
            const h = view.h * factor;
            const scale = scaleOf(view);

            applyView(clamp({
                x: view.x + position.x / scale - (position.x * w) / size.width,
                y: view.y + position.y / scale - (position.y * w) / size.width,
                w,
                h,
            }));
            this.syncZoom();
        },

        tap(target) {
            const seat = target.closest('[data-seat]');
            const hit = target.closest('[data-section]');

            if (hit) {
                this.showSection(hit.dataset.section);
            } else if (seat) {
                this.seatTapped(seat);
            } else {
                this.clearSeat();
            }
        },

        showSeat(el) {
            activeSeat?.classList.remove('is-active');
            activeSeat = el;
            el.classList.add('is-active');
            this.describeSeat();
        },

        clearSeat() {
            activeSeat?.classList.remove('is-active');
            activeSeat = null;
            this.seat = null;
        },

        describeSeat() {
            const parts = [this.seatLabel(activeSeat), STATE_LABELS[activeSeat.dataset.state]];

            if (activeSeat.classList.contains('seat--obstructed')) {
                parts.push('Sichtbehinderung');
            }

            this.seat = parts.join(' · ');
        },

        async refresh() {
            if (document.visibilityState !== 'visible') {
                return;
            }

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!response.ok) {
                    return;
                }

                const plan = await response.json();

                for (const [id, state] of Object.entries(plan.seats)) {
                    const el = seats.get(id);

                    if (el && el.dataset.state !== state) {
                        el.dataset.state = state;
                    }
                }

                this.sections = plan.sections ?? this.sections;
                this.total = plan.total ?? this.total;

                if (activeSeat) {
                    this.describeSeat();
                }

                this.afterRefresh();
            } catch {
                // Offline for a moment: keep showing the last known state.
            }
        },
    };
};
