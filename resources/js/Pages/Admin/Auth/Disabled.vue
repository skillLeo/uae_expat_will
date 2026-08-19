<script setup>
import { Link } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps({
    disabledBy: { type: String, default: null },
    disabledAt: { type: String, default: null },
    reason: { type: String, default: null },
});
</script>

<template>
    <AuthLayout title="Account disabled">
        <h1 class="mb-2 font-display text-display-s text-ink">This account has been disabled</h1>
        <p class="help mb-6">
            You cannot sign in. The details below tell you who to speak to — please do not ask a
            colleague to share their credentials.
        </p>

        <div class="card-paper mb-6 border border-rule-warm p-6">
            <dl class="grid gap-3 text-body-s">
                <div v-if="disabledBy">
                    <dt class="text-caption text-slate">Disabled by</dt>
                    <dd class="font-medium text-ink">{{ disabledBy }}</dd>
                </div>
                <div v-if="disabledAt">
                    <dt class="text-caption text-slate">When</dt>
                    <dd class="tabular font-mono text-ink">{{ disabledAt }}</dd>
                </div>
                <div v-if="reason">
                    <dt class="text-caption text-slate">Reason given</dt>
                    <dd class="text-ink">{{ reason }}</dd>
                </div>
            </dl>
        </div>

        <Link href="/" class="btn btn-secondary w-full">Back to the website</Link>
    </AuthLayout>
</template>
