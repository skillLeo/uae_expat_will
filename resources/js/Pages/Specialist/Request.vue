<script setup>
/**
 * Specialist legal review request.
 *
 * Two steps, and the split is the point. Step one saves the contact details
 * and opens the case before anything else is asked, so somebody who types
 * their name and then closes the tab is still a lead Summit can phone rather
 * than a person who never existed.
 *
 * All copy is Summit's, transcribed from the 26 August handoff. Do not reword
 * it in passing.
 */
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    service: { type: Object, required: true },
    serviceNote: { type: String, default: null },
    step: { type: Number, default: 1 },
    reference: { type: String, default: null },
    countries: { type: Object, default: () => ({}) },
    consent: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const page = usePage();
const whatsapp = computed(() => {
    const n = String(page.props.settings?.['contact.whatsapp_number'] ?? '').replace(/[^0-9]/g, '');
    return n ? `https://wa.me/${n}` : '/contact';
});

const contactForm = useForm({
    full_name: '',
    email: '',
    phone: '',
    country_of_residence: '',
    preferred_contact_method: 'email',
});

const detailForm = useForm({ brief_description: '', consent: false });

const submitContact = () => contactForm.post(`/specialist-request/${props.service.value}/contact`, {
    preserveScroll: true,
});

const submitDetails = () => detailForm.post(`/specialist-request/${props.service.value}`, {
    preserveScroll: true,
});
</script>

<template>
    <PublicLayout :title="copy.heading" :description="copy.body">
        <section class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="max-w-[68ch]">
                    <div class="eyebrow mb-4">{{ copy.eyebrow }}</div>
                    <h1 class="mb-5 font-display text-display-l leading-[1.1] text-ink">{{ copy.heading }}</h1>
                    <p class="prose-measure mb-6 text-body-l leading-[1.65] text-ink-70">{{ copy.body }}</p>

                    <!-- Which service, and what that means. Never asked again:
                         it came from question one. -->
                    <div class="card-paper mb-8 border-l-2 border-gold p-5">
                        <div class="eyebrow mb-1.5">{{ service.label }}</div>
                        <p v-if="serviceNote" class="text-legal leading-[1.72] text-ink">{{ serviceNote }}</p>
                    </div>

                    <!-- ------------------------------------------- step one -->
                    <form v-if="step === 1" class="grid max-w-[560px] gap-4" @submit.prevent="submitContact">
                        <h2 class="text-h3 font-semibold text-ink">Your contact details</h2>

                        <FormField id="s-name" label="Full name" required :error="contactForm.errors.full_name">
                            <input id="s-name" v-model="contactForm.full_name" class="field" autocomplete="name">
                        </FormField>

                        <FormField id="s-email" label="Email address" required :error="contactForm.errors.email">
                            <input id="s-email" v-model="contactForm.email" type="email" class="field" autocomplete="email" inputmode="email">
                        </FormField>

                        <FormField
                            id="s-phone" label="Mobile or WhatsApp number" required
                            help="Please include your country code." :error="contactForm.errors.phone"
                        >
                            <input id="s-phone" v-model="contactForm.phone" type="tel" class="field" autocomplete="tel" inputmode="tel" placeholder="+971 50 123 4567">
                        </FormField>

                        <FormField id="s-country" label="Country of residence" required :error="contactForm.errors.country_of_residence">
                            <select id="s-country" v-model="contactForm.country_of_residence" class="field">
                                <option value="" disabled>Select your country</option>
                                <option v-for="(name, code) in countries" :key="code" :value="code">{{ name }}</option>
                            </select>
                        </FormField>

                        <FormField id="s-contact" label="Preferred contact method" required :error="contactForm.errors.preferred_contact_method">
                            <select id="s-contact" v-model="contactForm.preferred_contact_method" class="field">
                                <option value="email">Email</option>
                                <option value="telephone">Telephone</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </FormField>

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button type="submit" class="btn btn-primary btn-lg" :disabled="contactForm.processing">
                                {{ contactForm.processing ? 'Saving…' : 'Continue' }}
                            </button>
                            <a :href="whatsapp" class="text-legal font-medium text-ink underline decoration-gold underline-offset-4">
                                {{ copy.secondary }}
                            </a>
                        </div>
                        <p class="help">{{ copy.reassurance }}</p>
                    </form>

                    <!-- ------------------------------------------- step two -->
                    <form v-else class="grid max-w-[620px] gap-4" @submit.prevent="submitDetails">
                        <h2 class="text-h3 font-semibold text-ink">About your request</h2>
                        <p class="help -mt-2">
                            Your details are saved. Reference
                            <span class="tabular font-mono text-ink">{{ reference }}</span>.
                        </p>

                        <FormField
                            id="s-desc" label="Briefly, what do you need?" required
                            help="A few sentences is enough. Please do not include identity documents or account numbers here."
                            :error="detailForm.errors.brief_description"
                        >
                            <textarea id="s-desc" v-model="detailForm.brief_description" class="field min-h-[130px]"></textarea>
                        </FormField>

                        <label class="flex items-start gap-3">
                            <input v-model="detailForm.consent" type="checkbox" class="tap mt-1 accent-gold">
                            <span class="text-legal leading-[1.72] text-ink">{{ consent.wording }}</span>
                        </label>
                        <p v-if="detailForm.errors.consent" class="error">{{ detailForm.errors.consent }}</p>

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button type="submit" class="btn btn-primary btn-lg" :disabled="detailForm.processing">
                                {{ detailForm.processing ? 'Sending…' : copy.primary }}
                            </button>
                            <a :href="whatsapp" class="text-legal font-medium text-ink underline decoration-gold underline-offset-4">
                                {{ copy.secondary }}
                            </a>
                        </div>
                        <p class="help">{{ copy.reassurance }}</p>
                    </form>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
