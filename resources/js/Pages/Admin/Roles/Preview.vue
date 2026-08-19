<script setup>
/**
 * "What this role can and cannot see."
 *
 * Written in consequences rather than permission names, because the person
 * assigning a role is deciding what someone can DO, not which strings are
 * ticked.
 */
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    role: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] },
});
</script>

<template>
    <AdminLayout :title="role.name" back-href="/admin/roles">
        <div class="card-paper mb-6 border border-rule-warm p-5">
            <p v-if="role.description" class="mb-2 text-body-s text-ink">{{ role.description }}</p>
            <p class="help">
                Held by {{ users.length }} {{ users.length === 1 ? 'person' : 'people' }}<span v-if="users.length">: {{ users.join(', ') }}</span>.
            </p>
        </div>

        <div class="grid gap-4">
            <section v-for="area in summary" :key="area.area" class="card p-5">
                <h2 class="mb-4 text-h4 font-semibold text-ink">{{ area.area }}</h2>
                <div class="grid grid-cols-2 gap-6 max-[719px]:grid-cols-1">
                    <div>
                        <div class="eyebrow mb-2 text-positive">Can</div>
                        <ul v-if="area.can.length" class="grid gap-1.5">
                            <li v-for="c in area.can" :key="c" class="flex items-start gap-2 text-body-s text-ink">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="#2E6A4E" stroke-width="2" class="mt-1 flex-none" aria-hidden="true">
                                    <polyline points="3.6,8.4 6.4,11.2 12.4,4.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ c }}
                            </li>
                        </ul>
                        <p v-else class="help">Nothing in this area.</p>
                    </div>
                    <div>
                        <div class="eyebrow mb-2 text-critical">Cannot</div>
                        <ul v-if="area.cannot.length" class="grid gap-1.5">
                            <li v-for="c in area.cannot" :key="c" class="flex items-start gap-2 text-body-s text-ink-70">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="#8C2F2F" stroke-width="2" class="mt-1 flex-none" aria-hidden="true">
                                    <path d="M5 5l6 6M11 5l-6 6" stroke-linecap="round" />
                                </svg>
                                {{ c }}
                            </li>
                        </ul>
                        <p v-else class="help">No restrictions in this area.</p>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
