import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Draws a vertical hairline as the page scrolls, lighting each marker as it
 * passes the read line.
 *
 * This is the one orchestrated motion moment in the product, so it earns a
 * little care:
 *   - It is rAF-throttled, so scrolling stays cheap.
 *   - It reads geometry only, never writes layout, so it cannot thrash.
 *   - Under prefers-reduced-motion it does not animate at all: the rail is
 *     drawn complete and every marker is lit on mount, so a reader who has
 *     asked for stillness still sees the finished state rather than nothing.
 */
export function useDrawOnScroll() {
    const rail = ref(null);
    const fill = ref(null);
    const lit = ref(new Set());

    let frame = 0;
    let markers = [];

    function tick() {
        if (!rail.value || !fill.value) return;

        const box = rail.value.getBoundingClientRect();
        const readLine = (window.innerHeight || 800) * 0.62;
        const progress = Math.max(0, Math.min(1, (readLine - box.top) / Math.max(1, box.height)));

        fill.value.style.transform = `scaleY(${progress})`;

        const next = new Set(lit.value);
        markers.forEach((m, i) => {
            const r = m.getBoundingClientRect();
            if (r.top + r.height / 2 <= readLine) next.add(i);
        });

        if (next.size !== lit.value.size) lit.value = next;
    }

    function onScroll() {
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            tick();
        });
    }

    onMounted(() => {
        markers = Array.from(rail.value?.querySelectorAll('[data-marker]') ?? []);

        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

        if (reduced) {
            if (fill.value) fill.value.style.transform = 'none';
            lit.value = new Set(markers.map((_, i) => i));
            return;
        }

        tick();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
    });

    onUnmounted(() => {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
        if (frame) cancelAnimationFrame(frame);
    });

    return { rail, fill, lit };
}
