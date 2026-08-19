<script setup>
/**
 * Cookie consent.
 *
 * The rules here are compliance requirements, not design preferences:
 *   - Accept All, Reject Non-Essential and Manage Preferences are EQUALLY
 *     prominent. Reject is not a quiet link next to a loud button.
 *   - Nothing is pre-ticked. Strictly necessary is locked on and labelled.
 *   - No non-essential tag renders before consent. The analytics loader is
 *     invoked from here and nowhere else.
 *   - The choice is recorded server-side with the wording version and a
 *     timestamp, so what was agreed can be evidenced later.
 */
import { ref, reactive, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'uew.cookie-consent';
const WORDING_VERSION = '2026-08-19';

const page = usePage();
const visible = ref(false);
const panelOpen = ref(false);

// Nothing pre-ticked.
const prefs = reactive({ analytics: false, functional: false });

const analyticsConfigured = computed(
    () => !!(page.props.settings?.['analytics.ga4_measurement_id'] || page.props.settings?.['analytics.gtm_container_id']),
);

// Everything touching window/localStorage lives inside onMounted, because this
// component is also rendered on the server, where neither exists.
function openFromFooter() {
    visible.value = true;
    panelOpen.value = true;
}

onMounted(() => {
    window.addEventListener('cookie-settings:open', openFromFooter);

    const stored = localStorage.getItem(STORAGE_KEY);

    if (!stored) {
        visible.value = true;
        return;
    }

    try {
        const saved = JSON.parse(stored);

        // A stored decision against older wording is not consent to the new
        // wording. Ask again rather than assuming.
        if (saved.version !== WORDING_VERSION) {
            visible.value = true;
            return;
        }

        Object.assign(prefs, saved.prefs ?? {});
        applyTags();
    } catch {
        visible.value = true;
    }
});

function persist(choice) {
    localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({ version: WORDING_VERSION, prefs: { ...prefs }, at: new Date().toISOString() }),
    );

    // Recorded server-side too, with IP, language and the wording version, so
    // the consent is evidential rather than merely local.
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

/** Loads analytics ONLY when analytics consent is present. */
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
    // Analytics must never receive questionnaire answers, religion, family or
    // beneficiary detail, or document names. Page paths only.
    window.gtag('config', id, { anonymize_ip: true, allow_google_signals: false });
}

onUnmounted(() => window.removeEventListener('cookie-settings:open', openFromFooter));

const acceptAll = () => { prefs.analytics = true; prefs.functional = true; persist('accept_all'); };
const rejectAll = () => { prefs.analytics = false; prefs.functional = false; persist('reject_non_essential'); };
const saveChoices = () => persist('manage_preferences');
</script>

<template>
    <div
        v-if="visible"
        class="z-cookiebar on-ink safe-bottom fixed inset-x-0 bottom-0 border-t border-ink-line bg-ink"
        role="dialog" aria-modal="false" aria-labelledby="cookie-heading"
    >
        <div class="mx-auto max-w-[1280px] px-8 py-4 max-[719px]:px-4">
            <div v-if="!panelOpen" class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-[240px] flex-1">
                    <h2 id="cookie-heading" class="mb-1 text-body-s font-semibold text-paper">
                        We use cookies to run this site
                    </h2>
                    <p class="max-w-[74ch] text-caption leading-[1.6] text-steel">
                        Strictly necessary cookies keep the site working and cannot be switched off.
                        Everything else is off until you turn it on.
                    </p>
                </div>
                <!-- Three actions, equal weight. -->
                <div class="flex flex-none flex-wrap gap-2">
                    <button type="button" class="btn btn-sm border-steel text-paper" @click="panelOpen = true">Manage preferences</button>
                    <button type="button" class="btn btn-sm border-steel text-paper" @click="rejectAll">Reject non-essential</button>
                    <button type="button" class="btn btn-sm border-paper bg-paper text-ink" @click="acceptAll">Accept all</button>
                </div>
            </div>

            <div v-else>
                <h2 class="mb-3 text-body font-semibold text-paper">Cookie preferences</h2>
                <div class="grid gap-2">
                    <div class="flex items-start justify-between gap-4 border-b border-ink-line pb-3">
                        <div>
                            <div class="text-body-s font-medium text-paper">Strictly necessary</div>
                            <p class="max-w-[70ch] text-caption text-steel">
                                Session, security and consent storage. Required for the site to function.
                            </p>
                        </div>
                        <span class="pill pill-neutral flex-none">Always on</span>
                    </div>

                    <label class="flex cursor-pointer items-start justify-between gap-4 border-b border-ink-line pb-3">
                        <div>
                            <div class="text-body-s font-medium text-paper">Analytics</div>
                            <p class="max-w-[70ch] text-caption text-steel">
                                Anonymous page-level statistics. Never receives your answers.
                            </p>
                        </div>
                        <input v-model="prefs.analytics" type="checkbox" class="tap mt-1 flex-none accent-gold">
                    </label>

                    <label class="flex cursor-pointer items-start justify-between gap-4 pb-3">
                        <div>
                            <div class="text-body-s font-medium text-paper">Functional</div>
                            <p class="max-w-[70ch] text-caption text-steel">
                                Remembers preferences such as your language choice.
                            </p>
                        </div>
                        <input v-model="prefs.functional" type="checkbox" class="tap mt-1 flex-none accent-gold">
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm border-paper bg-paper text-ink" @click="saveChoices">Save my choices</button>
                    <button type="button" class="btn btn-sm border-steel text-paper" @click="rejectAll">Reject non-essential</button>
                    <button type="button" class="btn btn-sm border-steel text-paper" @click="acceptAll">Accept all</button>
                </div>
            </div>
        </div>
    </div>
</template>
