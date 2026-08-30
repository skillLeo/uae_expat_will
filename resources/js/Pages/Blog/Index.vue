<script setup>
/**
 * The insight index.
 *
 * Each card leads with the date the piece was last verified rather than the
 * date it was written. On a subject where the law moves, "checked in March"
 * tells a reader more than "written in January".
 */
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    posts: { type: Object, required: true },
    page: { type: Object, required: true },
    structuredData: { type: Object, default: null },
    isPreview: { type: Boolean, default: false },
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
        <section class="bg-page pb-24 pt-16 max-[719px]:py-12">
            <div class="mx-auto max-w-[1280px] px-8 max-[719px]:px-4">
                <div class="mb-12 max-w-[62ch]">
                    <div class="eyebrow mb-4">Insights</div>
                    <h1 class="mb-5 font-display text-display-l leading-[1.1] text-ink">
                        Writing on UAE Wills and inheritance
                    </h1>
                    <p class="prose-measure text-body-l leading-[1.65] text-ink-70">
                        Practical pieces from the legal team at Summit Legal Consultancy. Every article
                        names the person who wrote it and the date it was last checked, because the law
                        in this area changes.
                    </p>
                </div>

                <div v-if="isPreview" class="mb-8 rounded-md border border-attention-border bg-attention-bg p-4">
                    <p class="text-body-s font-semibold text-ink">You are signed in, so drafts are shown here too</p>
                    <p class="mt-1 text-legal leading-[1.72] text-ink-70">
                        A visitor sees only published articles, and the blog is not linked anywhere on the
                        site until the first one is published.
                    </p>
                </div>

                <div v-if="posts.data.length" class="grid grid-cols-3 gap-6 max-[1080px]:grid-cols-2 max-[719px]:grid-cols-1">
                    <article v-for="post in posts.data" :key="post.slug" class="card flex flex-col p-6">
                        <div v-if="post.category" class="eyebrow mb-3">{{ post.category }}</div>

                        <h2 class="mb-3 font-display text-h3 leading-[1.3] text-ink">
                            <Link :href="`/blog/${post.slug}`" class="hover:text-gold-strong">{{ post.title }}</Link>
                        </h2>

                        <p class="mb-5 flex-1 text-body-s leading-[1.6] text-ink-70">{{ post.excerpt }}</p>

                        <div class="mt-auto border-t border-rule-warm pt-4">
                            <div class="text-caption font-medium text-ink">{{ post.author_name }}</div>
                            <div class="tabular mt-1 font-mono text-micro text-slate">
                                {{ when(post.reviewed_at ?? post.published_at) }}
                                <span v-if="post.reviewed_at"> · reviewed</span>
                                · {{ post.reading_minutes }} min read
                            </div>
                        </div>
                    </article>
                </div>

                <template v-else>
                    <p v-if="!isPreview" class="text-body-l text-ink-70">
                        The first articles are being written. Please check back shortly.
                    </p>

                    <div v-else class="card-paper max-w-[64ch] border-l-2 border-gold p-6">
                        <p class="mb-2 text-body font-semibold text-ink">Nothing written yet</p>
                        <p class="mb-4 text-body-s leading-[1.65] text-ink-70">
                            Write your first article in the dashboard, under Content then Insights. You can
                            save it without publishing and it will appear here for you to read, marked as a
                            draft. Nobody else sees anything until you tick publish.
                        </p>
                        <a href="/admin/content/posts" class="btn btn-primary">Write an article</a>
                    </div>
                </template>

                <div v-if="posts.links?.length > 3" class="mt-10 flex flex-wrap gap-1">
                    <Link
                        v-for="link in posts.links" :key="link.label" :href="link.url ?? '#'"
                        class="tap grid place-items-center rounded-sm border px-3 text-body-s"
                        :class="link.active ? 'border-ink bg-ink text-paper' : 'border-rule-cool text-ink-70'"
                        v-html="link.label"
                    />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
