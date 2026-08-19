<script setup>
import { ref, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

defineProps({ users: { type: Array, default: () => [] }, roles: { type: Array, default: () => [] } });

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const sheet = ref(false);
const form = reactive({ name: '', email: '', roles: [] });

const invite = () => router.post('/admin/users', { ...form }, {
    onSuccess: () => { sheet.value = false; Object.assign(form, { name: '', email: '', roles: [] }); },
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'roles', label: 'Roles' },
    { key: 'two_factor', label: '2FA' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <AdminLayout title="Users">
        <template #action>
            <button v-if="can('users.create')" type="button" class="btn btn-sm btn-primary" @click="sheet = true">Invite user</button>
        </template>

        <DataTable :columns="columns" :rows="users">
            <template #cell-name="{ row }">
                <Link :href="`/admin/users/${row.id}`" class="font-medium text-ink underline decoration-gold underline-offset-4">{{ row.name }}</Link>
            </template>
            <template #cell-roles="{ row }">
                <span v-if="!row.roles.length" class="text-slate">No role</span>
                <span v-for="r in row.roles" :key="r" class="pill pill-neutral mr-1">{{ r }}</span>
            </template>
            <template #cell-two_factor="{ row }">
                <StatusPill :tone="row.two_factor ? 'positive' : 'critical'" :label="row.two_factor ? 'Enrolled' : 'Not enrolled'" />
            </template>
            <template #cell-status="{ row }">
                <StatusPill v-if="!row.is_active" tone="critical" label="Disabled" />
                <StatusPill v-else-if="row.locked" tone="attention" label="Locked out" />
                <StatusPill v-else tone="positive" label="Active" />
            </template>
        </DataTable>

        <Sheet :open="sheet" title="Invite a user" size="sm" @close="sheet = false">
            <FormField id="u-name" label="Name" required><input id="u-name" v-model="form.name" class="field" autocomplete="name"></FormField>
            <FormField id="u-email" label="Email" required><input id="u-email" v-model="form.email" type="email" class="field" inputmode="email" autocomplete="email"></FormField>
            <fieldset>
                <legend class="label">Roles</legend>
                <label v-for="r in roles" :key="r" class="mb-1.5 flex items-center gap-2.5">
                    <input v-model="form.roles" type="checkbox" :value="r" class="tap accent-gold">
                    <span class="text-body-s">{{ r }}</span>
                </label>
            </fieldset>
            <p class="help mt-3">
                They set their own password by reset, and two-factor enrolment is compulsory at first sign-in.
            </p>
            <template #actions>
                <button type="button" class="btn btn-primary" @click="invite">Send invite</button>
                <button type="button" class="btn btn-tertiary" @click="sheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
