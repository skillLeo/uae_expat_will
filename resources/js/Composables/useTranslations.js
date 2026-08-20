import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Interface string lookup.
 *
 *   const { t } = useTranslations();
 *   t('actions.continue')
 *   t('assessment.declarations_remaining', { accepted: 3, total: 7 })
 *
 * Returns the key itself when a string is missing, so a gap is visible in the
 * interface rather than rendering as an empty element that nobody notices.
 *
 * Page content does not come through here — that is served from the database
 * already resolved for the active locale.
 */
export function useTranslations() {
    const page = usePage();

    const strings = computed(() => page.props.translations ?? {});
    const locale = computed(() => page.props.locale ?? 'en');
    const supported = computed(() => page.props.supportedLocales ?? ['en']);

    function t(key, replacements = {}) {
        const value = key.split('.').reduce((acc, part) => acc?.[part], strings.value);

        if (typeof value !== 'string') return key;

        return Object.entries(replacements).reduce(
            (out, [k, v]) => out.replaceAll(`:${k}`, String(v)),
            value,
        );
    }

    return { t, locale, supported };
}
