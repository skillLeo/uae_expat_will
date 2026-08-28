<script setup>
/**
 * One article.
 *
 * The author and the review date sit directly under the title rather than in a
 * footer. A page about what happens to somebody's family after they die is
 * judged on who wrote it and whether it is current, and both facts should be
 * visible before the first paragraph, not after the last.
 */
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    post: { type: Object, required: true },
    page: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    structuredData: { type: Object, default: null },
});

const when = (iso) => iso
    ? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
    : null;
</script>

<template>
    <PublicLayout
        :title="page.seo_title" :description="page.meta_description"
        :canonical="page.canonical" :structured-data="structuredData"
    >
        <article class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <Link href="/blog" class="eyebrow mb-6 inline-block text-gold-strong">← All insights</Link>

                <header class="mb-10 max-w-[64ch]">
                    <div v-if="post.category" class="eyebrow mb-4">{{ post.category }}</div>

                    <h1 class="mb-6 font-display text-display-l leading-[1.1] text-ink">{{ post.title }}</h1>

                    <p class="prose-measure mb-6 text-body-l leading-[1.65] text-ink-70">{{ post.excerpt }}</p>

                    <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1 border-t border-rule-warm pt-5">
                        <div>
                            <span class="text-body-s font-medium text-ink">{{ post.author_name }}</span>
                            <span class="text-caption text-slate"> · {{ post.author_title }}</span>
                        </div>
                        <div class="tabular font-mono text-caption text-slate">
                            <template v-if="post.was_reviewed">
                                Reviewed {{ when(post.reviewed_at) }}
                            </template>
                            <template v-else>
                                Published {{ when(post.published_at) }}
                            </template>
                            · {{ post.reading_minutes }} min read
                        </div>
                    </div>
                </header>

                <!-- The body is Summit's own writing, stored as HTML and edited
                     in the admin. It is their legal content, so it renders as
                     written rather than being reformatted here. -->
                <div class="prose-article max-w-[64ch] text-body leading-[1.75] text-ink" v-html="post.body"></div>

                <aside class="mt-12 max-w-[64ch] border-t border-rule-warm pt-6">
                    <p class="text-legal leading-[1.72] text-ink-70">
                        This article is general information, not legal advice, and does not create a
                        professional engagement. UAE Expat Wills and Summit Legal Consultancy UAE are not
                        a court, registry, notary or government authority.
                    </p>
                </aside>

                <section v-if="related.length" class="mt-16">
                    <h2 class="eyebrow mb-5">Related reading</h2>
                    <div class="grid grid-cols-3 gap-6 max-[1080px]:grid-cols-2 max-[719px]:grid-cols-1">
                        <article v-for="r in related" :key="r.slug" class="card p-5">
                            <h3 class="mb-2 font-display text-h4 leading-[1.35] text-ink">
                                <Link :href="`/blog/${r.slug}`" class="hover:text-gold-strong">{{ r.title }}</Link>
                            </h3>
                            <p class="text-caption leading-[1.55] text-ink-70">{{ r.excerpt }}</p>
                        </article>
                    </div>
                </section>
            </div>
        </article>
    </PublicLayout>
</template>
