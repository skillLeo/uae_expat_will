<script setup>
/**
 * System health.
 *
 * This exists because the platform's worst failure mode is a silent one: the
 * cron entries live in the hosting panel, and if they stop, the queue stops,
 * notifications never send, and a case is created that nobody at Summit ever
 * hears about. Nothing else in the interface would say a word.
 *
 * So the panel is loud when something is wrong and quiet when it is not, and
 * every failing check explains the business consequence rather than the
 * technical symptom.
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import StatusPill from '@/Components/StatusPill.vue';

const props = defineProps({ health: { type: Object, required: true } });

const expanded = ref(null);
const refreshing = ref(false);

const checks = computed(() => props.health.checks ?? []);
const critical = computed(() => checks.value.filter((c) => c.state === 'critical'));
const warnings = computed(() => checks.value.filter((c) => c.state === 'warning'));
const unknown = computed(() => checks.value.filter((c) => c.state === 'unknown'));

const allWell = computed(() => props.health.worst === 'healthy');

const headline = computed(() => {
    if (allWell.value) return 'Everything is running';
    if (critical.value.length) {
        return critical.value.length === 1
            ? '1 thing is not working'
            : `${critical.value.length} things are not working`;
    }
    if (warnings.value.length) return `${warnings.value.length} need attention`;
    return 'Some checks could not run';
});

function refresh() {
    refreshing.value = true;
    router.post('/admin/health/refresh', {}, {
        preserveScroll: true,
        onFinish: () => { refreshing.value = false; },
    });
}

const toggle = (key) => { expanded.value = expanded.value === key ? null : key; };
</script>

<template>
    <section class="mb-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-h3 font-semibold text-ink">System health</h2>
                <StatusPill
                    :tone="allWell ? 'positive' : critical.length ? 'critical' : warnings.length ? 'attention' : 'neutral'"
                    :label="headline"
                />
            </div>
            <button type="button" class="btn btn-sm btn-tertiary" :disabled="refreshing" @click="refresh">
                {{ refreshing ? 'Checking…' : 'Check again' }}
            </button>
        </div>

        <!-- Anything critical gets its own block above the grid. It is the
             thing somebody needs to read, so it does not sit in a row of tiles. -->
        <div v-if="critical.length" class="mb-4 grid gap-2">
            <article
                v-for="c in critical" :key="c.key"
                class="rounded-md border border-critical-border bg-critical-bg p-4"
            >
                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                    <svg width="17" height="17" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" class="flex-none text-critical" aria-hidden="true">
                        <circle cx="8" cy="8" r="6.2" stroke-width="1.3" /><path d="M8 4.6v4M8 10.8v.2" stroke-linecap="round" />
                    </svg>
                    <span class="text-body-s font-semibold text-critical">{{ c.label }}</span>
                    <span class="text-body-s text-ink">{{ c.summary }}</span>
                </div>
                <p v-if="c.consequence" class="prose-measure mb-2 text-body-s leading-[1.6] text-ink">{{ c.consequence }}</p>
                <p v-if="c.fix" class="text-caption leading-[1.6] text-ink-70">
                    <strong class="font-medium">What to do:</strong> {{ c.fix }}
                    <span v-if="c.fix_is_host_panel" class="pill pill-neutral ml-1.5">hosting panel, not here</span>
                </p>
            </article>
        </div>

        <!-- Everything, compact. -->
        <div class="grid grid-cols-4 gap-3 max-[900px]:grid-cols-2 max-[600px]:grid-cols-1">
            <button
                v-for="c in checks" :key="c.key"
                type="button"
                class="card p-4 text-left transition-colors hover:border-gold"
                :class="{
                    'border-critical-border bg-critical-bg': c.state === 'critical',
                    'border-attention-border bg-attention-bg': c.state === 'warning',
                }"
                :aria-expanded="expanded === c.key"
                @click="toggle(c.key)"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <span class="text-caption font-medium uppercase tracking-[0.06em] text-slate">{{ c.label }}</span>
                    <span
                        class="mt-0.5 h-2 w-2 flex-none rounded-pill"
                        :class="{
                            'bg-positive': c.state === 'healthy',
                            'bg-attention': c.state === 'warning',
                            'bg-critical': c.state === 'critical',
                            'bg-steel': c.state === 'unknown',
                        }"
                        :aria-label="c.state_label"
                    ></span>
                </div>
                <p class="text-body-s leading-[1.45] text-ink">{{ c.summary }}</p>

                <dl v-if="expanded === c.key" class="mt-3 grid gap-1 border-t border-rule-cool pt-3">
                    <template v-for="(value, key) in c.detail" :key="key">
                        <div v-if="key !== 'recent'" class="flex justify-between gap-3 text-caption">
                            <dt class="text-slate">{{ key }}</dt>
                            <dd class="tabular text-right font-mono text-ink">{{ value }}</dd>
                        </div>
                    </template>

                    <!-- Failed jobs carry their errors inline, so nobody has to
                         go digging through a log to see what broke. -->
                    <div v-if="c.detail.recent?.length" class="mt-1.5 grid gap-1.5">
                        <div v-for="(job, i) in c.detail.recent" :key="i" class="border-t border-rule-cool pt-1.5">
                            <p class="break-words font-mono text-micro leading-[1.5] text-critical">{{ job.error }}</p>
                            <p class="tabular font-mono text-micro text-slate">{{ job.when }}</p>
                        </div>
                    </div>
                </dl>
            </button>
        </div>

        <p v-if="warnings.length || unknown.length" class="help mt-3">
            <template v-if="warnings.length">
                {{ warnings.map((c) => c.label).join(', ') }} {{ warnings.length === 1 ? 'needs' : 'need' }} attention.
            </template>
            <template v-if="unknown.length">
                {{ unknown.map((c) => c.label).join(', ') }} could not be checked.
            </template>
            Select any tile for detail.
        </p>
    </section>
</template>
