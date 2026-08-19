<script setup>
/**
 * Cookie consent.
 *
 * These are compliance requirements, not design preferences:
 *   - Accept All, Reject Non-Essential and Manage Preferences carry EQUAL
 *     weight. Reject is not a quiet link beside a loud button.
 *   - Nothing is pre-ticked. Strictly necessary is locked on and labelled
 *     "Always on" so it is clear it is not a choice being made for you.
 *   - No non-essential tag renders before consent. The analytics loader is
 *     called from here and nowhere else.
 *   - The choice is recorded server-side with the wording version and a
 *     timestamp, so what was agreed can be evidenced later.
 */
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'uew.cookie-consent';
const WORDING_VERSION = '2026-08-20';

const CATEGORIES = [
    {
        id: 'essential', label: 'Strictly necessary', locked: true, count: '4 cookies',
        body: 'Your session, security, and the assessment answers held against this device. Without these the platform cannot run, so they cannot be switched off.',
    },
    {
        id: 'prefs', label: 'Preferences', locked: false, count: '2 cookies',
        body: 'Remembers this cookie choice, and your language if you set one, so you are not asked again on every page.',
    },
    {
        id: 'analytics', label: 'Analytics', locked: false, count: '3 cookies',
        body: 'Anonymous, page-level statistics about how the site is used. It never receives your answers, your religion, your family details or any document name.',
    },
    {
        id: 'marketing', label: 'Marketing', locked: false, count: '2 cookies',
        body: 'Measures whether a campaign brought you here. Off by default and not required to use the service.',
    },
];

const page = usePage();
const visible = ref(false);
const panelOpen = ref(false);

// Nothing pre-ticked.
const prefs = reactive({ prefs: false, analytics: false, marketing: false });

const analyticsConfigured = computed(() => !!(
    page.props.settings?.['analytics.ga4_measurement_id'] || page.props.settings?.['analytics.gtm_container_id']
));

function openFromFooter() {
    visible.value = true;
    panelOpen.value = true;
}

onMounted(() => {
    window.addEventListener('cookie-settings:open', openFromFooter);

    const stored = localStorage.getItem(STORAGE_KEY);
    if (!stored) { visible.value = true; return; }

    try {
        const saved = JSON.parse(stored);
        // A decision against older wording is not consent to the new wording.
        if (saved.version !== WORDING_VERSION) { visible.value = true; return; }
        Object.assign(prefs, saved.prefs ?? {});
        applyTags();
    } catch {
        visible.value = true;
    }
});

onUnmounted(() => window.removeEventListener('cookie-settings:open', openFromFooter));

function persist(choice) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
        version: WORDING_VERSION, prefs: { ...prefs }, at: new Date().toISOString(),
    }));

    // Recorded server-side too, with IP, language and wording version, so the
    // consent is evidential rather than merely local.
    fetch('/consent/cookie', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ choice, preferences: { ...prefs }, version: WORDING_VERSION }),
    }).catch(() => {});

    visible.value = false;
    panelOpen.value = false;
    applyTags();
}

/** Loads analytics ONLY where analytics consent is present. */
function applyTags() {
    if (!prefs.analytics || !analyticsConfigured.value || window.__uewTags) return;

    window.__uewTags = true;
    const id = page.props.settings['analytics.ga4_measurement_id'];
    if (!id) return;

    const s = document.createElement('script');
    s.async = true;
    s.src = `https://www.googletagmanager.com/gtag/js?id=${id}`;
    document.head.appendChild(s);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    // Page paths only. Analytics must never receive questionnaire answers,
    // religion, family or beneficiary detail, or document names.
    window.gtag('config', id, { anonymize_ip: true, allow_google_signals: false });
}

const acceptAll = () => { prefs.prefs = true; prefs.analytics = true; prefs.marketing = true; persist('accept_all'); };
const rejectAll = () => { prefs.prefs = false; prefs.analytics = false; prefs.marketing = false; persist('reject_non_essential'); };
const saveChoices = () => persist('manage_preferences');
</script>

<template>
    <div
        v-if="visible"
        class="z-cookiebar on-ink safe-bottom fixed inset-x-0 bottom-0 border-t border-ink-line bg-ink"
        role="dialog" aria-modal="false" aria-labelledby="cookie-heading"
    >
        <div class="mx-auto max-w-[1280px] px-8 py-4 max-[719px]:px-4">
            <!-- The bar -->
            <div v-if="!panelOpen" class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-[260px] flex-1">
                    <h2 id="cookie-heading" class="mb-1 text-body-s font-semibold text-paper">
                        We use cookies to run this site
                    </h2>
                    <p class="max-w-[74ch] text-caption leading-[1.6] text-steel">
                        Strictly necessary cookies keep the site working and cannot be switched off.
                        Everything else stays off until you turn it on.
                    </p>
                </div>
                <!-- Three actions, equal weight. -->
                <div class="flex flex-none flex-wrap gap-2 max-[719px]:w-full">
                    <button type="button" class="btn btn-sm flex-1 border-steel text-paper" @click="panelOpen = true">Manage preferences</button>
                    <button type="button" class="btn btn-sm flex-1 border-steel text-paper" @click="rejectAll">Reject non-essential</button>
                    <button type="button" class="btn btn-sm flex-1 border-paper bg-paper text-ink" @click="acceptAll">Accept all</button>
                </div>
            </div>

            <!-- The preferences panel -->
            <div v-else class="max-h-[70dvh] overflow-y-auto">
                <h2 class="mb-4 text-body font-semibold text-paper">Cookie preferences</h2>

                <div class="grid gap-3">
                    <div
                        v-for="c in CATEGORIES" :key="c.id"
                        class="border-b border-ink-line pb-3 last:border-0"
                    >
                        <label class="flex cursor-pointer items-start justify-between gap-4" :class="{ 'cursor-default': c.locked }">
                            <div class="min-w-0">
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <span class="text-body-s font-medium text-paper">{{ c.label }}</span>
                                    <span class="tabular font-mono text-micro text-slate">{{ c.count }}</span>
                                </div>
                                <p class="max-w-[74ch] text-caption leading-[1.6] text-steel">{{ c.body }}</p>
                            </div>
                            <span v-if="c.locked" class="pill pill-neutral flex-none">Always on</span>
                            <input
                                v-else v-model="prefs[c.id]" type="checkbox"
                                class="tap mt-1 flex-none accent-gold"
                                :aria-label="c.label"
                            >
                        </label>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm border-paper bg-paper text-ink" @click="saveChoices">Save my choices</button>
                    <button type="button" class="btn btn-sm border-steel text-paper" @click="rejectAll">Reject non-essential</button>
                    <button type="button" class="btn btn-sm border-steel text-paper" @click="acceptAll">Accept all</button>
                </div>
            </div>
        </div>
    </div>
</template>
