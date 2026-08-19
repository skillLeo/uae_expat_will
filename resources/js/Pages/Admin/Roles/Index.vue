<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({ roles: { type: Array, default: () => [] }, modules: { type: Array, default: () => [] } });

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const sheet = ref(false);
const editing = ref(null);
const form = reactive({ name: '', description: '', permissions: [] });

function open(role = null) {
    editing.value = role;
    Object.assign(form, role
        ? { name: role.name, description: role.description ?? '', permissions: [...role.permissions] }
        : { name: '', description: '', permissions: [] });
    sheet.value = true;
}

function save() {
    const done = { onSuccess: () => { sheet.value = false; }, preserveScroll: true };
    editing.value
        ? router.patch(`/admin/roles/${editing.value.id}`, { description: form.description, permissions: form.permissions }, done)
        : router.post('/admin/roles', { ...form }, done);
}

const remove = (r) => confirm(`Delete the role "${r.name}"?`) && router.delete(`/admin/roles/${r.id}`, { preserveScroll: true });

function toggleModule(module, on) {
    const names = module.permissions.map((p) => p.name);
    form.permissions = on
        ? [...new Set([...form.permissions, ...names])]
        : form.permissions.filter((p) => !names.includes(p));
}

const moduleState = (module) => {
    const names = module.permissions.map((p) => p.name);
    const held = names.filter((n) => form.permissions.includes(n)).length;
    return held === 0 ? 'none' : held === names.length ? 'all' : 'some';
};
</script>

<template>
    <AdminLayout title="Roles and permissions">
        <template #action>
            <button v-if="can('roles.create')" type="button" class="btn btn-sm btn-primary" @click="open()">Create role</button>
        </template>

        <p class="help mb-5 max-w-[80ch]">
            Permissions are enforced on the server for every route. What a role can see in the interface is
            only a reflection of that, never the control itself.
        </p>

        <div class="grid gap-3">
            <article v-for="r in roles" :key="r.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <h2 class="text-body font-semibold text-ink">{{ r.name }}</h2>
                            <span v-if="r.is_system" class="pill pill-neutral">system</span>
                            <span class="tabular pill pill-progress">{{ r.users_count }} {{ r.users_count === 1 ? 'holder' : 'holders' }}</span>
                            <span class="tabular pill pill-neutral">{{ r.permissions.length }} permissions</span>
                        </div>
                        <p v-if="r.description" class="prose-measure text-body-s text-ink-70">{{ r.description }}</p>
                    </div>
                    <div class="flex flex-none flex-wrap gap-2">
                        <Link :href="`/admin/roles/${r.id}/preview`" class="btn btn-sm btn-secondary">What it can see</Link>
                        <button v-if="can('roles.update')" type="button" class="btn btn-sm btn-tertiary" @click="open(r)">Edit</button>
                        <button v-if="can('roles.update') && !r.is_system" type="button" class="btn btn-sm btn-tertiary text-critical" @click="remove(r)">Delete</button>
                    </div>
                </div>
            </article>
        </div>

        <Sheet :open="sheet" :title="editing ? `Edit ${editing.name}` : 'Create a role'" size="lg" @close="sheet = false">
            <FormField v-if="!editing" id="r-name" label="Name" required><input id="r-name" v-model="form.name" class="field"></FormField>
            <FormField id="r-desc" label="Description"><textarea id="r-desc" v-model="form.description" class="field min-h-[60px]"></textarea></FormField>

            <div class="grid gap-4">
                <fieldset v-for="m in modules" :key="m.module" class="rounded-md border border-rule-cool p-4">
                    <legend class="flex items-center gap-2 px-1">
                        <span class="text-body-s font-semibold text-ink">{{ m.label }}</span>
                        <button
                            type="button" class="text-caption font-medium text-gold-strong"
                            @click="toggleModule(m, moduleState(m) !== 'all')"
                        >{{ moduleState(m) === 'all' ? 'clear' : 'select all' }}</button>
                    </legend>
                    <label v-for="p in m.permissions" :key="p.name" class="mb-1.5 flex items-start gap-2.5">
                        <input v-model="form.permissions" type="checkbox" :value="p.name" class="tap mt-0.5 accent-gold">
                        <span class="text-body-s">
                            {{ p.description ?? p.name }}
                            <code class="block font-mono text-micro text-slate">{{ p.name }}</code>
                        </span>
                    </label>
                </fieldset>
            </div>

            <template #actions>
                <button type="button" class="btn btn-primary" @click="save">Save</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
