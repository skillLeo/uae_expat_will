import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Pull-to-refresh for list views.
 *
 * Only engages when the list is already scrolled to the very top, so it cannot
 * fight an ordinary upward scroll. Disabled entirely under reduced motion,
 * where a springy gesture is not what the reader asked for.
 */
export function usePullToRefresh(threshold = 72) {
    const distance = ref(0);
    const refreshing = ref(false);

    let startY = null;
    let enabled = true;

    function onTouchStart(e) {
        if (!enabled || window.scrollY > 0 || refreshing.value) {
            startY = null;
            return;
        }
        startY = e.touches[0].clientY;
    }

    function onTouchMove(e) {
        if (startY === null) return;
        const delta = e.touches[0].clientY - startY;
        distance.value = delta > 0 ? Math.min(delta * 0.5, threshold * 1.5) : 0;
    }

    function onTouchEnd() {
        if (distance.value >= threshold && !refreshing.value) {
            refreshing.value = true;
            router.reload({
                onFinish: () => {
                    refreshing.value = false;
                    distance.value = 0;
                },
            });
        } else {
            distance.value = 0;
        }
        startY = null;
    }

    onMounted(() => {
        enabled = !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        if (!enabled) return;
        window.addEventListener('touchstart', onTouchStart, { passive: true });
        window.addEventListener('touchmove', onTouchMove, { passive: true });
        window.addEventListener('touchend', onTouchEnd);
    });

    onUnmounted(() => {
        window.removeEventListener('touchstart', onTouchStart);
        window.removeEventListener('touchmove', onTouchMove);
        window.removeEventListener('touchend', onTouchEnd);
    });

    return { distance, refreshing };
}
