<script setup>
/**
 * Specialist request confirmation. Copy is Summit's, transcribed exactly.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    reference: { type: String, required: true },
    copy: { type: Object, required: true },
});

const page = usePage();
const whatsapp = computed(() => {
    const n = String(page.props.settings?.['contact.whatsapp_number'] ?? '').replace(/[^0-9]/g, '');
    return n ? `https://wa.me/${n}` : '/contact';
});
</script>

<template>
    <PublicLayout :title="copy.heading" :description="copy.body">
        <section class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="max-w-[64ch]">
                    <div class="eyebrow mb-4">{{ copy.eyebrow }}</div>
                    <h1 class="mb-5 font-display text-display-l leading-[1.1] text-ink">{{ copy.heading }}</h1>
                    <p class="prose-measure mb-8 text-body-l leading-[1.65] text-ink-70">{{ copy.body }}</p>

                    <div class="mb-8">
                        <div class="eyebrow mb-1.5">Request reference</div>
                        <div class="tabular font-mono text-h3 text-ink">{{ reference }}</div>
                        <p class="help mt-1.5">Quote this if you contact us about your request.</p>
                    </div>

                    <div class="mb-8 flex flex-wrap items-center gap-4">
                        <Link href="/" class="btn btn-primary btn-lg">{{ copy.primary }}</Link>
                        <a :href="whatsapp" class="text-legal font-medium text-ink underline decoration-gold underline-offset-4">
                            {{ copy.secondary }}
                        </a>
                    </div>

                    <p class="mb-6 text-legal leading-[1.72] text-ink-70">{{ copy.payment }}</p>

                    <aside class="card-paper border-l-2 border-gold p-5">
                        <p class="mb-2 text-body-s font-semibold leading-[1.6] text-ink">{{ copy.notice_heading }}</p>
                        <p class="text-legal leading-[1.72] text-ink-70">{{ copy.notice_body }}</p>
                    </aside>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
