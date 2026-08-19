<script setup>
/**
 * The questionnaire and routing editor.
 *
 * The rule builder is the point of this screen. It has to express a
 * cross-question rule — "IF religion is Muslim AND distribution is any of […]
 * THEN hold for review" — to somebody who does not write code, so every rule
 * renders as a sentence and the condition editor is built from the same words.
 */
import { ref, computed, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';
import SortableList from '@/Components/SortableList.vue';

const props = defineProps({
    version: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    rules: { type: Array, default: () => [] },
    declarations: { type: Array, default: () => [] },
    resultScreens: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);
const editable = computed(() => props.version.editable && can('questionnaire.edit'));

const tab = ref('questions');
const TABS = [
    ['questions', 'Questions'],
    ['rules', 'Routing rules'],
    ['preview', 'Preview'],
    ['declarations', 'Declarations'],
];

// ---------------------------------------------------------------- questions

const questionSheet = ref(false);
const editingQuestion = ref(null);
const questionForm = reactive({
    key: '', type: 'single_select', prompt: '', help_text: '', privacy_note: '',
    security_note: '', section_key: '', is_required: true, is_sensitive: false,
});

function openQuestion(q = null) {
    editingQuestion.value = q;
    Object.assign(questionForm, q
        ? { ...q, help_text: q.help_text ?? '', privacy_note: q.privacy_note ?? '', security_note: q.security_note ?? '', section_key: q.section_key ?? '' }
        : { key: '', type: 'single_select', prompt: '', help_text: '', privacy_note: '', security_note: '', section_key: '', is_required: true, is_sensitive: false });
    questionSheet.value = true;
}

function saveQuestion() {
    const done = { onSuccess: () => { questionSheet.value = false; } };
    editingQuestion.value
        ? router.patch(`/admin/questionnaire/questions/${editingQuestion.value.id}`, { ...questionForm }, done)
        : router.post(`/admin/questionnaire/${props.version.id}/questions`, { ...questionForm }, done);
}

const deleteQuestion = (q) => confirm(`Delete "${q.key}"? Any rule using it must be updated before publishing.`)
    && router.delete(`/admin/questionnaire/questions/${q.id}`);

const reorderQuestions = (order) => router.post(`/admin/questionnaire/${props.version.id}/questions/reorder`, { order }, { preserveScroll: true });

// ------------------------------------------------------------------ options

const optionSheet = ref(false);
const optionParent = ref(null);
const optionForm = reactive({ key: '', label: '', description: '', is_exclusive: false });

function openOption(question) {
    optionParent.value = question;
    Object.assign(optionForm, { key: '', label: '', description: '', is_exclusive: false });
    optionSheet.value = true;
}

const saveOption = () => router.post(
    `/admin/questionnaire/questions/${optionParent.value.id}/options`,
    { ...optionForm },
    { onSuccess: () => { optionSheet.value = false; } },
);

const toggleExclusive = (option) => router.patch(
    `/admin/questionnaire/options/${option.id}`,
    { is_exclusive: !option.is_exclusive },
    { preserveScroll: true },
);

const deleteOption = (o) => confirm(`Delete option "${o.label}"?`) && router.delete(`/admin/questionnaire/options/${o.id}`);

// --------------------------------------------------------------- conditions

const conditionSheet = ref(false);
const conditionParent = ref(null);
const conditionForm = reactive({ depends_on_question_id: '', operator: 'equals', value: '', action: 'show', target_section_key: '' });

function openCondition(question) {
    conditionParent.value = question;
    Object.assign(conditionForm, { depends_on_question_id: '', operator: 'equals', value: '', action: 'show', target_section_key: '' });
    conditionSheet.value = true;
}

const conditionDependsOptions = computed(() =>
    props.questions.find((q) => q.id === Number(conditionForm.depends_on_question_id))?.options ?? [],
);

function saveCondition() {
    const payload = { ...conditionForm };
    // `in` and `not_in` take a list; the others take one value.
    if (['in', 'not_in'].includes(payload.operator) && !Array.isArray(payload.value)) {
        payload.value = payload.value ? [payload.value] : [];
    }
    router.post(`/admin/questionnaire/questions/${conditionParent.value.id}/conditions`, payload, {
        onSuccess: () => { conditionSheet.value = false; },
    });
}

const deleteCondition = (c) => router.delete(`/admin/questionnaire/conditions/${c.id}`);

// --------------------------------------------------------------- rule builder

const ruleSheet = ref(false);
const editingRule = ref(null);
const ruleForm = reactive({
    name: '', priority: 100, outcome: 'review', outcome_detail: '',
    flag_key: '', reminder_key: '', route_mark_key: '',
    is_terminal: false, is_active: true,
    conditions: [],
});

function openRule(rule = null) {
    editingRule.value = rule;
    Object.assign(ruleForm, rule
        ? {
            ...rule,
            outcome_detail: rule.outcome_detail ?? '',
            flag_key: rule.flag_key ?? '', reminder_key: rule.reminder_key ?? '', route_mark_key: rule.route_mark_key ?? '',
            conditions: rule.conditions.map((c) => ({
                question_id: c.question_id,
                operator: c.operator,
                value: c.value,
                group_index: c.group_index,
            })),
        }
        : {
            name: '', priority: 100, outcome: 'review', outcome_detail: '',
            flag_key: '', reminder_key: '', route_mark_key: '',
            is_terminal: false, is_active: true,
            conditions: [{ question_id: '', operator: 'in', value: [], group_index: 0 }],
        });
    ruleSheet.value = true;
}

const addCondition = (groupIndex) => ruleForm.conditions.push({ question_id: '', operator: 'in', value: [], group_index: groupIndex });
const addGroup = () => ruleForm.conditions.push({ question_id: '', operator: 'in', value: [], group_index: Math.max(...ruleForm.conditions.map((c) => c.group_index), -1) + 1 });
const removeCondition = (i) => ruleForm.conditions.splice(i, 1);

const groups = computed(() => {
    const map = new Map();
    ruleForm.conditions.forEach((c, i) => {
        if (!map.has(c.group_index)) map.set(c.group_index, []);
        map.get(c.group_index).push({ ...c, index: i });
    });
    return [...map.entries()].sort((a, b) => a[0] - b[0]);
});

const optionsFor = (questionId) => props.questions.find((q) => q.id === Number(questionId))?.options ?? [];
const keyFor = (questionId) => props.questions.find((q) => q.id === Number(questionId))?.key ?? '?';

/** The live sentence, rebuilt as the administrator edits. */
const ruleSentence = computed(() => {
    if (!ruleForm.conditions.length) return 'Add at least one condition.';

    const clauses = groups.value.map(([, conds]) =>
        conds.map((c) => {
            const op = props.meta.operators.find((o) => o.value === c.operator)?.label ?? c.operator;
            const value = Array.isArray(c.value) ? c.value.join(' / ') : (c.value ?? '');
            return `${keyFor(c.question_id)} ${op} ${value}`.trim();
        }).join(' AND '),
    );

    const outcome = props.meta.outcomes.find((o) => o.value === ruleForm.outcome);
    const extras = [
        ruleForm.flag_key && `flag ${ruleForm.flag_key}`,
        ruleForm.reminder_key && `reminder ${ruleForm.reminder_key}`,
        ruleForm.route_mark_key && `route mark ${ruleForm.route_mark_key}`,
    ].filter(Boolean);

    return `IF ${clauses.join(' OR ')} THEN ${outcome?.label ?? ruleForm.outcome}`
        + (extras.length ? ` (${extras.join(', ')})` : '');
});

function saveRule() {
    const payload = {
        ...ruleForm,
        conditions: ruleForm.conditions.filter((c) => c.question_id),
    };
    const done = { onSuccess: () => { ruleSheet.value = false; } };
    editingRule.value
        ? router.patch(`/admin/questionnaire/rules/${editingRule.value.id}`, payload, done)
        : router.post(`/admin/questionnaire/${props.version.id}/rules`, payload, done);
}

const deleteRule = (r) => confirm(`Delete rule "${r.name}"?`) && router.delete(`/admin/questionnaire/rules/${r.id}`);

// -------------------------------------------------------------------- preview

const previewAnswers = reactive({});
const previewResult = ref(null);
const previewing = ref(false);

async function runPreview() {
    previewing.value = true;
    try {
        const response = await fetch(`/admin/questionnaire/${props.version.id}/preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ answers: { ...previewAnswers } }),
        });
        previewResult.value = await response.json();
    } finally {
        previewing.value = false;
    }
}

function setPreviewAnswer(question, value) {
    if (question.type === 'multi_select') {
        const current = new Set(previewAnswers[question.key] ?? []);
        current.has(value) ? current.delete(value) : current.add(value);
        previewAnswers[question.key] = [...current];
    } else {
        previewAnswers[question.key] = value;
    }
}

const isPicked = (question, value) => question.type === 'multi_select'
    ? (previewAnswers[question.key] ?? []).includes(value)
    : previewAnswers[question.key] === value;

const clearPreview = () => {
    Object.keys(previewAnswers).forEach((k) => delete previewAnswers[k]);
    previewResult.value = null;
};
</script>

<template>
    <AdminLayout :title="`Version ${version.version_number}`" back-href="/admin/questionnaire">
        <template #action>
            <StatusPill
                :tone="version.status === 'published' ? 'positive' : version.status === 'draft' ? 'attention' : 'neutral'"
                :label="version.status"
            />
        </template>

        <div v-if="!editable" class="mb-6 rounded-md border border-attention-border bg-attention-bg p-4">
            <p class="text-body-s text-ink">
                This version is read only.
                <template v-if="version.status === 'published'">A published version cannot be edited — create a draft to make changes.</template>
                <template v-else>Archived versions are kept for rollback and cannot be edited.</template>
            </p>
        </div>

        <!-- Tabs -->
        <div class="mb-6 flex flex-wrap gap-2 border-b border-rule-cool">
            <button
                v-for="[key, label] in TABS" :key="key" type="button"
                class="tap border-b-2 px-3 text-body-s font-medium"
                :class="tab === key ? 'border-gold text-ink' : 'border-transparent text-slate hover:text-ink'"
                @click="tab = key"
            >{{ label }}</button>
        </div>

        <!-- ==================================================== QUESTIONS -->
        <section v-show="tab === 'questions'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="help">{{ questions.length }} questions. Order here is the order a customer meets them.</p>
                <button v-if="editable" type="button" class="btn btn-sm btn-primary" @click="openQuestion()">Add question</button>
            </div>

            <SortableList :items="questions" :disabled="!editable" @reorder="reorderQuestions">
                <template #default="{ item: q }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2">
                                <code class="tabular font-mono text-caption text-gold-strong">{{ q.key }}</code>
                                <span class="pill pill-neutral">{{ q.type.replace('_', ' ') }}</span>
                                <span v-if="q.is_sensitive" class="pill pill-held">sensitive</span>
                                <span v-if="!q.is_required" class="pill pill-neutral">optional</span>
                                <span v-if="q.section_key" class="font-mono text-micro text-slate">{{ q.section_key }}</span>
                            </div>
                            <p class="text-body-s font-medium text-ink">{{ q.prompt }}</p>

                            <div v-if="q.conditions.length" class="mt-2 grid gap-1">
                                <div v-for="c in q.conditions" :key="c.id" class="flex items-center gap-2">
                                    <span class="pill pill-progress">{{ c.sentence }}</span>
                                    <button v-if="editable" type="button" class="text-caption text-critical hover:underline" @click="deleteCondition(c)">remove</button>
                                </div>
                            </div>

                            <div v-if="q.options.length" class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-for="o in q.options" :key="o.id"
                                    class="inline-flex items-center gap-1.5 rounded-xs border px-2 py-1 text-caption"
                                    :class="o.is_exclusive ? 'border-attention-border bg-attention-bg text-attention' : 'border-rule-cool text-ink-70'"
                                >
                                    {{ o.label }}
                                    <template v-if="editable">
                                        <button type="button" :title="o.is_exclusive ? 'Exclusive — clears all others' : 'Make exclusive'" @click="toggleExclusive(o)">
                                            {{ o.is_exclusive ? '★' : '☆' }}
                                        </button>
                                        <button type="button" class="text-critical" @click="deleteOption(o)">×</button>
                                    </template>
                                </span>
                            </div>
                        </div>

                        <div v-if="editable" class="flex flex-none flex-wrap gap-1.5">
                            <button type="button" class="btn btn-sm btn-tertiary" @click="openQuestion(q)">Edit</button>
                            <button v-if="['single_select','multi_select'].includes(q.type)" type="button" class="btn btn-sm btn-tertiary" @click="openOption(q)">+ Option</button>
                            <button type="button" class="btn btn-sm btn-tertiary" @click="openCondition(q)">+ Condition</button>
                            <button type="button" class="btn btn-sm btn-tertiary text-critical" @click="deleteQuestion(q)">Delete</button>
                        </div>
                    </div>
                </template>
            </SortableList>
        </section>

        <!-- ======================================================== RULES -->
        <section v-show="tab === 'rules'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="help max-w-[70ch]">
                    Rules are evaluated in priority order and every match is collected — the most severe outcome
                    governs. A terminal rule ends the assessment immediately.
                </p>
                <button v-if="editable" type="button" class="btn btn-sm btn-primary" @click="openRule()">Add rule</button>
            </div>

            <div class="grid gap-2">
                <article v-for="r in rules" :key="r.id" class="card p-4" :class="{ 'opacity-60': !r.is_active }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="mb-1.5 flex flex-wrap items-center gap-2">
                                <span class="tabular font-mono text-caption text-slate">{{ r.priority }}</span>
                                <span class="text-body-s font-semibold text-ink">{{ r.name }}</span>
                                <StatusPill :tone="r.tone" :label="r.outcome_label" />
                                <span v-if="r.is_terminal" class="pill pill-critical">terminal</span>
                                <span v-if="!r.is_active" class="pill pill-neutral">inactive</span>
                            </div>
                            <!-- The plain-English rendering. -->
                            <p class="prose-measure font-mono text-caption leading-[1.6] text-ink-70">{{ r.sentence }}</p>
                            <p v-if="r.outcome_detail" class="help mt-1.5">{{ r.outcome_detail }}</p>
                        </div>
                        <div v-if="editable" class="flex flex-none gap-1.5">
                            <button type="button" class="btn btn-sm btn-tertiary" @click="openRule(r)">Edit</button>
                            <button type="button" class="btn btn-sm btn-tertiary text-critical" @click="deleteRule(r)">Delete</button>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <!-- ====================================================== PREVIEW -->
        <section v-show="tab === 'preview'">
            <div class="mb-4 rounded-md border border-progress-border bg-progress-bg p-4">
                <p class="text-body-s text-ink">
                    Answer as a test customer and see exactly where this version would route them.
                    Nothing is saved: no assessment, no case, no notification.
                </p>
            </div>

            <div class="grid grid-cols-[minmax(0,1fr)_380px] gap-6 max-[1080px]:grid-cols-1">
                <div>
                    <div class="mb-3 flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" :disabled="previewing" @click="runPreview">
                            {{ previewing ? 'Running…' : 'Run preview' }}
                        </button>
                        <button type="button" class="btn btn-sm btn-tertiary" @click="clearPreview">Clear</button>
                    </div>

                    <div class="grid gap-2">
                        <div v-for="q in questions" :key="q.id" class="card p-4">
                            <div class="mb-2 flex flex-wrap items-baseline gap-2">
                                <code class="font-mono text-caption text-gold-strong">{{ q.key }}</code>
                                <span class="text-body-s font-medium text-ink">{{ q.prompt }}</span>
                            </div>
                            <div v-if="q.options.length" class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="o in q.options" :key="o.id" type="button"
                                    class="rounded-xs border px-2.5 py-1.5 text-caption"
                                    :class="isPicked(q, o.key) ? 'border-gold-strong bg-paper font-medium text-ink' : 'border-rule-cool text-ink-70'"
                                    @click="setPreviewAnswer(q, o.key)"
                                >{{ o.label }}</button>
                            </div>
                            <input
                                v-else v-model="previewAnswers[q.key]" type="text" class="field"
                                :placeholder="q.type === 'country_select' ? 'ISO code, e.g. GB or AE' : 'Test value'"
                            >
                        </div>
                    </div>
                </div>

                <aside class="sticky top-24 self-start max-[1080px]:static">
                    <div v-if="!previewResult" class="card p-5">
                        <p class="help">Run the preview to see the outcome.</p>
                    </div>
                    <div v-else class="card p-5">
                        <div class="eyebrow mb-2">Outcome</div>
                        <StatusPill :tone="previewResult.tone" :label="previewResult.outcome_label" class="mb-3" />

                        <dl class="mb-4 grid gap-1.5 text-body-s">
                            <div class="flex justify-between gap-3"><dt class="text-slate">Terminal</dt><dd>{{ previewResult.is_terminal ? 'yes' : 'no' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate">Payment offered</dt><dd>{{ previewResult.allows_payment ? 'yes' : 'no' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate">Restricted</dt><dd>{{ previewResult.is_restricted ? 'yes' : 'no' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate">Complete</dt><dd>{{ previewResult.is_complete ? 'yes' : 'no' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate">Next question</dt><dd class="font-mono">{{ previewResult.next_question ?? '—' }}</dd></div>
                        </dl>

                        <div v-if="previewResult.matched_rules.length" class="mb-4">
                            <div class="eyebrow mb-1.5">Rules that fired</div>
                            <div class="grid gap-1">
                                <code v-for="n in previewResult.matched_rules" :key="n" class="font-mono text-caption text-ink-70">{{ n }}</code>
                            </div>
                        </div>

                        <div v-for="[label, list] in [['Flags', previewResult.flags], ['Reminders', previewResult.reminders], ['Route marks', previewResult.route_marks]]" :key="label">
                            <div v-if="list.length" class="mb-3">
                                <div class="eyebrow mb-1.5">{{ label }}</div>
                                <div class="flex flex-wrap gap-1"><span v-for="x in list" :key="x" class="pill pill-neutral">{{ x }}</span></div>
                            </div>
                        </div>

                        <div v-if="previewResult.hidden_questions.length">
                            <div class="eyebrow mb-1.5">Hidden by conditions</div>
                            <div class="flex flex-wrap gap-1">
                                <code v-for="k in previewResult.hidden_questions" :key="k" class="font-mono text-micro text-slate">{{ k }}</code>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <!-- ================================================= DECLARATIONS -->
        <section v-show="tab === 'declarations'">
            <p class="help mb-4">
                All {{ declarations.length }} must be actively ticked before a customer can submit.
                None is ever pre-ticked.
            </p>
            <ol class="grid gap-2">
                <li v-for="(d, i) in declarations" :key="d.id" class="card flex gap-3 p-4">
                    <span class="tabular font-mono text-caption text-gold-strong">{{ String(i + 1).padStart(2, '0') }}</span>
                    <p class="legal-measure text-ink">{{ d.text }}</p>
                </li>
            </ol>

            <h2 class="mb-3 mt-8 text-h4 font-semibold text-ink">Result screens</h2>
            <div class="grid gap-2">
                <div v-for="s in resultScreens" :key="s.id" class="card p-4">
                    <code class="font-mono text-caption text-gold-strong">{{ s.outcome }}</code>
                    <p class="mt-1.5 text-body-s font-semibold text-ink">{{ s.heading }}</p>
                    <p class="prose-measure mt-1 text-body-s text-ink-70">{{ s.body }}</p>
                </div>
            </div>
        </section>

        <!-- ======================================================= SHEETS -->
        <Sheet :open="questionSheet" :title="editingQuestion ? 'Edit question' : 'Add question'" @close="questionSheet = false">
            <FormField v-if="!editingQuestion" id="q-key" label="Key" required help="Lowercase letters, numbers and underscores. Rules refer to this.">
                <input id="q-key" v-model="questionForm.key" class="field" placeholder="q17">
            </FormField>
            <FormField id="q-type" label="Type" required>
                <select id="q-type" v-model="questionForm.type" class="field">
                    <option v-for="t in meta.question_types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </FormField>
            <FormField id="q-prompt" label="Prompt" required>
                <textarea id="q-prompt" v-model="questionForm.prompt" class="field min-h-[80px]"></textarea>
            </FormField>
            <FormField id="q-help" label="Help text">
                <textarea id="q-help" v-model="questionForm.help_text" class="field min-h-[60px]"></textarea>
            </FormField>
            <FormField id="q-privacy" label="Privacy note" help="Shown on the same screen as the question, explaining why it is asked.">
                <textarea id="q-privacy" v-model="questionForm.privacy_note" class="field min-h-[60px]"></textarea>
            </FormField>
            <FormField id="q-security" label="Security note" help="Use for warnings such as never entering a seed phrase.">
                <textarea id="q-security" v-model="questionForm.security_note" class="field min-h-[60px]"></textarea>
            </FormField>
            <FormField id="q-section" label="Section" help="Groups questions into the named progress stages.">
                <select id="q-section" v-model="questionForm.section_key" class="field">
                    <option value="">None</option>
                    <option v-for="s in meta.sections" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </FormField>
            <label class="mb-2 flex items-center gap-2.5"><input v-model="questionForm.is_required" type="checkbox" class="tap accent-gold"><span class="text-body-s">Required</span></label>
            <label class="flex items-start gap-2.5">
                <input v-model="questionForm.is_sensitive" type="checkbox" class="tap mt-0.5 accent-gold">
                <span class="text-body-s">Sensitive
                    <span class="help block">Encrypted at rest and excluded from analytics and exports.</span>
                </span>
            </label>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="saveQuestion">Save</button>
                <button type="button" class="btn btn-tertiary" @click="questionSheet = false">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="optionSheet" title="Add option" size="sm" @close="optionSheet = false">
            <FormField id="o-key" label="Key" required><input id="o-key" v-model="optionForm.key" class="field" placeholder="none"></FormField>
            <FormField id="o-label" label="Label" required><textarea id="o-label" v-model="optionForm.label" class="field min-h-[60px]"></textarea></FormField>
            <label class="flex items-start gap-2.5">
                <input v-model="optionForm.is_exclusive" type="checkbox" class="tap mt-0.5 accent-gold">
                <span class="text-body-s">Exclusive
                    <span class="help block">Selecting this clears every other option, and vice versa. Only one per question.</span>
                </span>
            </label>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="saveOption">Add</button>
                <button type="button" class="btn btn-tertiary" @click="optionSheet = false">Cancel</button>
            </template>
        </Sheet>

        <Sheet :open="conditionSheet" title="Add condition" @close="conditionSheet = false">
            <p class="help mb-4">Controls whether this question is shown, based on an earlier answer.</p>
            <FormField id="c-action" label="Action" required>
                <select id="c-action" v-model="conditionForm.action" class="field">
                    <option v-for="a in meta.actions" :key="a.value" :value="a.value">{{ a.label }}</option>
                </select>
            </FormField>
            <FormField v-if="conditionForm.action === 'skip_section'" id="c-target" label="Section to skip" required>
                <select id="c-target" v-model="conditionForm.target_section_key" class="field">
                    <option v-for="s in meta.sections" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </FormField>
            <FormField id="c-depends" label="Depends on" required>
                <select id="c-depends" v-model="conditionForm.depends_on_question_id" class="field">
                    <option value="">Choose a question</option>
                    <option v-for="q in questions" :key="q.id" :value="q.id">{{ q.key }} — {{ q.prompt.slice(0, 60) }}</option>
                </select>
            </FormField>
            <FormField id="c-op" label="Operator" required>
                <select id="c-op" v-model="conditionForm.operator" class="field">
                    <option v-for="o in meta.operators" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </FormField>
            <FormField v-if="!['answered','not_answered'].includes(conditionForm.operator)" id="c-value" label="Value" required>
                <select v-if="conditionDependsOptions.length" id="c-value" v-model="conditionForm.value" class="field" :multiple="['in','not_in'].includes(conditionForm.operator)">
                    <option v-for="o in conditionDependsOptions" :key="o.id" :value="o.key">{{ o.label }}</option>
                </select>
                <input v-else id="c-value" v-model="conditionForm.value" class="field">
            </FormField>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="saveCondition">Add</button>
                <button type="button" class="btn btn-tertiary" @click="conditionSheet = false">Cancel</button>
            </template>
        </Sheet>

        <!-- The rule builder -->
        <Sheet :open="ruleSheet" :title="editingRule ? 'Edit rule' : 'Add rule'" size="lg" @close="ruleSheet = false">
            <!-- The sentence, live. This is what makes the builder readable. -->
            <div class="mb-5 rounded-md border border-gold bg-paper p-4">
                <div class="eyebrow mb-1.5">This rule reads</div>
                <p class="font-mono text-body-s leading-[1.6] text-ink">{{ ruleSentence }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4 max-[719px]:grid-cols-1">
                <FormField id="r-name" label="Name" required help="Shown in the case detail as the trigger reason.">
                    <input id="r-name" v-model="ruleForm.name" class="field" placeholder="R-14 · Executor needs review">
                </FormField>
                <FormField id="r-priority" label="Priority" required help="Lower runs first.">
                    <input id="r-priority" v-model.number="ruleForm.priority" type="number" class="field" inputmode="numeric">
                </FormField>
            </div>

            <FormField id="r-outcome" label="Outcome" required>
                <select id="r-outcome" v-model="ruleForm.outcome" class="field">
                    <option v-for="o in meta.outcomes" :key="o.value" :value="o.value">
                        {{ o.label }}{{ o.allows_payment ? '' : ' — no payment offered' }}
                    </option>
                </select>
            </FormField>

            <FormField id="r-detail" label="Detail shown to the customer">
                <textarea id="r-detail" v-model="ruleForm.outcome_detail" class="field min-h-[60px]"></textarea>
            </FormField>

            <div class="grid grid-cols-3 gap-4 max-[719px]:grid-cols-1">
                <FormField id="r-flag" label="Flag key" help="Seen by the reviewer after payment."><input id="r-flag" v-model="ruleForm.flag_key" class="field"></FormField>
                <FormField id="r-reminder" label="Reminder key" help="Owed in the detailed questionnaire."><input id="r-reminder" v-model="ruleForm.reminder_key" class="field"></FormField>
                <FormField id="r-mark" label="Route mark key" help="e.g. wider_dubai_route"><input id="r-mark" v-model="ruleForm.route_mark_key" class="field"></FormField>
            </div>

            <label class="mb-2 flex items-start gap-2.5">
                <input v-model="ruleForm.is_terminal" type="checkbox" class="tap mt-0.5 accent-gold">
                <span class="text-body-s">Terminal
                    <span class="help block">Ends the assessment where it stands, skipping the remaining questions and the declarations.</span>
                </span>
            </label>
            <label class="mb-5 flex items-center gap-2.5"><input v-model="ruleForm.is_active" type="checkbox" class="tap accent-gold"><span class="text-body-s">Active</span></label>

            <!-- Conditions, grouped -->
            <div class="border-t border-rule-cool pt-5">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-body-s font-semibold text-ink">Conditions</h3>
                    <button type="button" class="btn btn-sm btn-tertiary" @click="addGroup">+ OR group</button>
                </div>
                <p class="help mb-4">
                    Conditions in the same group must ALL be true. Any one group being true fires the rule.
                    This is how a rule can depend on an answer given many screens earlier.
                </p>

                <div v-for="[groupIndex, conds] in groups" :key="groupIndex" class="mb-3 rounded-md border border-rule-cool p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="eyebrow">Group {{ groupIndex + 1 }} — all must match</span>
                        <button type="button" class="text-caption font-medium text-gold-strong" @click="addCondition(groupIndex)">+ AND condition</button>
                    </div>

                    <div v-for="c in conds" :key="c.index" class="mb-2 grid grid-cols-[minmax(0,1fr)_140px_minmax(0,1fr)_auto] gap-2 max-[719px]:grid-cols-1">
                        <select v-model="ruleForm.conditions[c.index].question_id" class="field">
                            <option value="">Question</option>
                            <option v-for="q in questions" :key="q.id" :value="q.id">{{ q.key }}</option>
                        </select>
                        <select v-model="ruleForm.conditions[c.index].operator" class="field">
                            <option v-for="o in meta.operators" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <select
                            v-if="optionsFor(ruleForm.conditions[c.index].question_id).length"
                            v-model="ruleForm.conditions[c.index].value" class="field"
                            :multiple="['in','not_in'].includes(ruleForm.conditions[c.index].operator)"
                        >
                            <option v-for="o in optionsFor(ruleForm.conditions[c.index].question_id)" :key="o.id" :value="o.key">{{ o.label }}</option>
                        </select>
                        <input v-else v-model="ruleForm.conditions[c.index].value" class="field" placeholder="Value">
                        <button type="button" class="tap px-2 text-critical" @click="removeCondition(c.index)" aria-label="Remove condition">×</button>
                    </div>
                </div>
            </div>

            <template #actions>
                <button type="button" class="btn btn-primary" @click="saveRule">Save rule</button>
                <button type="button" class="btn btn-tertiary" @click="ruleSheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
