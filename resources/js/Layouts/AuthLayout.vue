<script setup>
/**
 * The auth split: a manifest on ink at columns 1–5, the form on paper at 6–12,
 * with a gold seam between them. The card is vertically centred.
 */
import { Head } from '@inertiajs/vue3';
import Wordmark from '@/Components/Wordmark.vue';

defineProps({ title: { type: String, default: 'Sign in' } });
</script>

<template>
    <Head :title="title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="grid min-h-dvh grid-cols-[minmax(0,5fr)_1px_minmax(0,7fr)] max-[900px]:grid-cols-1">
        <!-- Manifest -->
        <div class="on-ink flex flex-col justify-between bg-ink-deep p-12 max-[900px]:p-6">
            <Wordmark context="lockup" ground="ink" />
            <div class="max-[900px]:hidden">
                <p class="prose-measure mb-4 text-body-l leading-[1.65] text-steel">
                    This is the internal tool for Summit Legal Consultancy UAE. Every action taken here
                    is recorded in an audit log that cannot be edited or deleted.
                </p>
                <p class="text-legal leading-[1.72] text-steel">
                    Two-factor authentication is required for every account. If you cannot sign in,
                    contact an administrator rather than sharing credentials.
                </p>
            </div>
            <div class="text-caption text-steel">
                {{ $page.props.settings?.['branding.ownership_line'] }}
            </div>
        </div>

        <div class="bg-gold max-[900px]:hidden"></div>

        <!-- Form -->
        <div class="flex items-center justify-center bg-page p-12 max-[900px]:p-6">
            <div class="w-full max-w-[420px]">
                <slot />
            </div>
        </div>
    </div>
</template>
