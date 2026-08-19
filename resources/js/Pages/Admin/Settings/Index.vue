<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/FormField.vue';
import Sheet from '@/Components/Sheet.vue';

const props = defineProps({
    group: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    settings: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const values = reactive(Object.fromEntries(props.settings.map((s) => [
    s.key,
    s.type === 'json' && s.value !== null ? JSON.stringify(s.value, null, 2) : s.value,
])));

const saving = ref(false);
const jsonError = ref(null);
const testSheet = ref(null);
const testTarget = ref('');

function save() {
    const payload = {};
    jsonError.value = null;

    for (const setting of props.settings) {
        const raw = values[setting.key];

        // A blank secret means "leave it alone", never "erase it".
        if (setting.is_secret && (raw === null || raw === '')) continue;

        if (setting.type === 'json') {
            if (raw === null || raw === '') { payload[setting.key] = null; continue; }
            try {
                payload[setting.key] = JSON.parse(raw);
            } catch {
                jsonError.value = `${setting.label} is not valid JSON. Nothing was saved.`;
                return;
            }
        } else {
            payload[setting.key] = raw;
        }
    }

    saving.value = true;
    router.patch(`/admin/settings/${props.group.value}`, { settings: payload }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}

function runTest(kind) {
    const routes = { mail: '/admin/settings/test/mail', whatsapp: '/admin/settings/test/whatsapp' };
    const field = kind === 'mail' ? 'email' : 'number';
    router.post(routes[kind], { [field]: testTarget.value }, {
        preserveScroll: true,
        onFinish: () => { testSheet.value = null; testTarget.value = ''; },
    });
}

const testGateway = () => router.post('/admin/settings/test/gateway', {}, { preserveScroll: true });
</script>

<template>
    <AdminLayout title="Settings">
        <div class="grid grid-cols-[220px_minmax(0,1fr)] gap-8 max-[1080px]:grid-cols-1">
            <nav class="grid content-start gap-1 max-[1080px]:flex max-[1080px]:flex-wrap" aria-label="Setting groups">
                <Link
                    v-for="g in groups" :key="g.value" :href="`/admin/settings/${g.value}`"
                    class="tap flex items-center rounded-sm px-3 text-body-s"
                    :class="group.value === g.value ? 'bg-ink text-paper' : 'text-ink-70 hover:bg-paper'"
                >{{ g.label }}</Link>
            </nav>

            <div>
                <h2 class="mb-1 text-h3 font-semibold text-ink">{{ group.label }}</h2>
                <p class="help mb-5">
                    Stored in the database, not in <code class="font-mono">.env</code>. Every change is recorded
                    with who made it.
                </p>

                <!-- The test buttons for the integration groups -->
                <div v-if="['mail','whatsapp','payment'].includes(group.value)" class="card-paper mb-5 flex flex-wrap items-center gap-3 border border-rule-warm p-4">
                    <span class="text-body-s text-ink">Check these credentials actually work:</span>
                    <button v-if="group.value === 'mail'" type="button" class="btn btn-sm btn-secondary" @click="testSheet = 'mail'">Send test email</button>
                    <button v-if="group.value === 'whatsapp'" type="button" class="btn btn-sm btn-secondary" @click="testSheet = 'whatsapp'">Send test WhatsApp</button>
                    <button v-if="group.value === 'payment'" type="button" class="btn btn-sm btn-secondary" @click="testGateway">Test gateway connection</button>
                    <span class="help">The real provider error is shown if it fails.</span>
                </div>

                <div class="card p-6 max-[719px]:p-4">
                    <div v-for="s in settings" :key="s.key">
                        <FormField :id="s.key" :label="s.label" :help="s.help_text">
                            <!-- boolean -->
                            <label v-if="s.type === 'boolean'" class="flex items-center gap-2.5">
                                <input :id="s.key" v-model="values[s.key]" type="checkbox" class="tap accent-gold">
                                <span class="text-body-s">{{ values[s.key] ? 'On' : 'Off' }}</span>
                            </label>

                            <!-- secret -->
                            <div v-else-if="s.is_secret">
                                <input
                                    :id="s.key" v-model="values[s.key]" type="password" class="field"
                                    autocomplete="new-password"
                                    :placeholder="s.has_value ? '•••••••• — leave blank to keep' : 'Not set'"
                                >
                                <p class="help mt-1">Encrypted at rest. It is never sent back to this screen.</p>
                            </div>

                            <textarea v-else-if="s.type === 'json'" :id="s.key" v-model="values[s.key]" class="field min-h-[140px] font-mono text-body-s"></textarea>
                            <textarea v-else-if="s.type === 'text'" :id="s.key" v-model="values[s.key]" class="field min-h-[80px]"></textarea>
                            <input v-else-if="s.type === 'integer'" :id="s.key" v-model.number="values[s.key]" type="number" class="field" inputmode="numeric">
                            <input v-else :id="s.key" v-model="values[s.key]" class="field">
                        </FormField>
                    </div>

                    <p v-if="jsonError" class="error mb-3" role="alert">{{ jsonError }}</p>

                    <button v-if="can('settings.edit')" type="button" class="btn btn-primary" :disabled="saving" @click="save">
                        {{ saving ? 'Saving…' : 'Save changes' }}
                    </button>
                </div>

                <section v-if="history.length" class="mt-8">
                    <h3 class="mb-3 text-h4 font-semibold text-ink">Recent changes</h3>
                    <div class="grid gap-1.5">
                        <div v-for="(h, i) in history" :key="i" class="card flex flex-wrap items-baseline justify-between gap-3 p-3">
                            <div class="min-w-0">
                                <span class="text-body-s font-medium text-ink">{{ h.label }}</span>
                                <span class="ml-2 font-mono text-caption text-slate">{{ h.old_value ?? '—' }} → {{ h.new_value ?? '—' }}</span>
                            </div>
                            <span class="tabular flex-none font-mono text-caption text-slate">
                                {{ h.by ?? 'System' }} · {{ new Date(h.at).toLocaleString('en-GB') }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <Sheet :open="testSheet !== null" :title="testSheet === 'mail' ? 'Send a test email' : 'Send a test WhatsApp message'" size="sm" @close="testSheet = null">
            <FormField id="test-target" :label="testSheet === 'mail' ? 'Send to' : 'WhatsApp number'" required>
                <input
                    id="test-target" v-model="testTarget" class="field"
                    :type="testSheet === 'mail' ? 'email' : 'tel'"
                    :inputmode="testSheet === 'mail' ? 'email' : 'tel'"
                    :placeholder="testSheet === 'mail' ? 'you@example.com' : '+9715XXXXXXXX'"
                >
            </FormField>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="runTest(testSheet)">Send</button>
                <button type="button" class="btn btn-tertiary" @click="testSheet = null">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
