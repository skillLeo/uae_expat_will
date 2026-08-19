<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Wordmark from './Wordmark.vue';

defineProps({ active: { type: String, default: '' } });

const page = usePage();
const menuOpen = ref(false);

const nav = [
    { id: 'how', label: 'How It Works', href: '/how-it-works' },
    { id: 'need', label: 'Do You Need a Will?', href: '/do-you-need-a-uae-will' },
    { id: 'options', label: 'UAE Will Options', href: '/uae-will-registration-options' },
    { id: 'pricing', label: 'Pricing', href: '/pricing' },
    { id: 'faqs', label: 'FAQs', href: '/faqs' },
    { id: 'about', label: 'About Us', href: '/about-us' },
    { id: 'contact', label: 'Contact', href: '/contact' },
];

// Open item 03: the utility slot is reserved at 96×24 so Client Login drops in
// without relayout the day Summit decides. Until then it stays empty.
const showClientLogin = computed(() => page.props.features?.client_login_in_header === true);
</script>

<template>
    <header class="on-ink sticky top-0 z-30 border-b border-ink-line bg-ink-deep">
        <div class="mx-auto flex max-w-[1280px] flex-wrap items-center gap-6 px-8 py-[18px] max-[719px]:px-4">
            <Link href="/" class="flex-none" aria-label="UAE Expat Wills — home">
                <Wordmark context="header" ground="ink" />
            </Link>

            <nav
                class="flex flex-1 justify-center gap-4 whitespace-nowrap text-body-s max-[1199px]:order-3 max-[1199px]:basis-full max-[1199px]:justify-start max-[1199px]:border-t max-[1199px]:border-ink-line max-[1199px]:pt-3 max-[719px]:hidden"
                aria-label="Main"
            >
                <Link
                    v-for="item in nav" :key="item.id" :href="item.href"
                    :class="active === item.id
                        ? 'border-b border-gold pb-[3px] font-medium text-paper'
                        : 'text-steel hover:text-gold-soft'"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <button
                type="button"
                class="tap ml-auto hidden flex-none place-items-center rounded-sm border border-slate max-[719px]:grid"
                :aria-expanded="menuOpen"
                aria-controls="mobile-nav"
                @click="menuOpen = !menuOpen"
            >
                <span class="sr-only">{{ menuOpen ? 'Close menu' : 'Open menu' }}</span>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="#F6F1E7" stroke-width="1.5" aria-hidden="true">
                    <path v-if="!menuOpen" d="M3.5 6h13M3.5 10h13M3.5 14h13" stroke-linecap="round" />
                    <path v-else d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                </svg>
            </button>

            <div v-if="showClientLogin" class="flex-none">
                <Link href="/client/login" class="text-body-s text-steel hover:text-gold-soft">Client login</Link>
            </div>
            <div v-else class="h-6 w-24 flex-none" aria-hidden="true"></div>

            <Link
                href="/assessment"
                class="tap flex-none whitespace-nowrap rounded-sm border border-paper bg-paper px-[18px] py-3 text-body-s font-medium text-ink transition-colors hover:bg-surface"
            >
                Start the assessment
            </Link>
        </div>

        <!-- Mobile navigation. A drawer, not a page swap. -->
        <nav
            v-show="menuOpen" id="mobile-nav"
            class="hidden border-t border-ink-line bg-ink-deep px-4 pb-4 max-[719px]:block"
            aria-label="Main"
        >
            <Link
                v-for="item in nav" :key="item.id" :href="item.href"
                class="tap flex items-center border-b border-ink-line text-body"
                :class="active === item.id ? 'text-gold-soft' : 'text-steel'"
                @click="menuOpen = false"
            >
                {{ item.label }}
            </Link>
        </nav>
    </header>
</template>
