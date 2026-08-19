<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

defineProps({ secret: { type: String, required: true }, qr: { type: String, required: true } });

const form = useForm({ code: '' });
</script>

<template>
    <AuthLayout title="Set up two-factor authentication">
        <h1 class="mb-2 font-display text-display-s text-ink">Set up two-factor authentication</h1>
        <p class="help mb-6">
            This is required for every Summit account and cannot be skipped. Scan the code with your
            authenticator app, then enter the six digits it shows.
        </p>

        <div class="card mb-4 grid place-items-center p-6">
            <img :src="qr" alt="Two-factor QR code" width="220" height="220">
        </div>

        <div class="card-paper mb-6 border border-rule-warm p-4">
            <div class="eyebrow mb-1.5">Or enter this key by hand</div>
            <code class="tabular block break-all font-mono text-body-s text-ink">{{ secret }}</code>
        </div>

        <form @submit.prevent="form.post('/admin/two-factor/confirm')">
            <label class="label" for="code">Six-digit code</label>
            <!-- One input, not six boxes: paste distributes correctly, and the
                 22px size stops iOS zooming on focus. -->
            <input
                id="code" v-model="form.code" type="text" class="field tabular text-center font-mono"
                style="font-size: 22px; letter-spacing: 0.4em;"
                inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus required
                :aria-invalid="!!form.errors.code"
            >
            <div class="field-slot pt-1.5">
                <p v-if="form.errors.code" class="error" role="alert">{{ form.errors.code }}</p>
            </div>
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? 'Verifying…' : 'Confirm and continue' }}
            </button>
        </form>
    </AuthLayout>
</template>
