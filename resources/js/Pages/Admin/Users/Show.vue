<script setup>
import { ref, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Sheet from '@/Components/Sheet.vue';
import FormField from '@/Components/FormField.vue';

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    devices: { type: Array, default: () => [] },
    activity: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.permissions ?? []).includes(p);

const selectedRoles = ref([...props.user.roles]);
const disableSheet = ref(false);
const reason = ref('');

const saveRoles = () => router.patch(`/admin/users/${props.user.id}`, { roles: selectedRoles.value }, { preserveScroll: true });
const disable = () => router.post(`/admin/users/${props.user.id}/disable`, { reason: reason.value }, {
    preserveScroll: true, onSuccess: () => { disableSheet.value = false; reason.value = ''; },
});
const enable = () => router.post(`/admin/users/${props.user.id}/enable`, {}, { preserveScroll: true });
const revokeAll = () => confirm('Sign this user out everywhere?') && router.post(`/admin/users/${props.user.id}/revoke-sessions`, {}, { preserveScroll: true });
const revokeOne = (d) => router.post(`/admin/devices/${d.id}/revoke`, {}, { preserveScroll: true });
const reset2fa = () => confirm('Reset two-factor? They must enrol again at next sign-in.') && router.post(`/admin/users/${props.user.id}/reset-2fa`, {}, { preserveScroll: true });
</script>

<template>
    <AdminLayout :title="user.name" back-href="/admin/users">
        <div class="grid grid-cols-[minmax(0,1fr)_340px] gap-6 max-[1080px]:grid-cols-1">
            <div class="min-w-0">
                <div v-if="!user.is_active" class="mb-6 rounded-md border border-critical-border bg-critical-bg p-4">
                    <p class="text-body-s font-semibold text-critical">This account is disabled</p>
                    <p class="mt-1 text-body-s text-ink">{{ user.disabled_reason }}</p>
                </div>

                <section class="card mb-6 p-5">
                    <h2 class="mb-4 text-h4 font-semibold text-ink">Roles</h2>
                    <label v-for="r in roles" :key="r" class="mb-1.5 flex items-center gap-2.5">
                        <input v-model="selectedRoles" type="checkbox" :value="r" class="tap accent-gold" :disabled="!can('users.update')">
                        <span class="text-body-s">{{ r }}</span>
                    </label>
                    <button v-if="can('users.update')" type="button" class="btn btn-sm btn-primary mt-3" @click="saveRoles">Save roles</button>
                </section>

                <section class="card mb-6 p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-h4 font-semibold text-ink">Active sessions</h2>
                        <button v-if="can('users.disable')" type="button" class="btn btn-sm btn-tertiary text-critical" @click="revokeAll">Revoke all</button>
                    </div>
                    <div class="grid gap-2">
                        <div v-for="d in devices" :key="d.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-rule-cool pb-2 last:border-0">
                            <div class="min-w-0">
                                <div class="text-body-s text-ink">{{ d.label }}</div>
                                <div class="tabular font-mono text-caption text-slate">
                                    {{ d.ip }} · {{ d.last_active_at ? new Date(d.last_active_at).toLocaleString('en-GB') : '—' }}
                                </div>
                            </div>
                            <StatusPill v-if="d.revoked" tone="neutral" label="Revoked" />
                            <button v-else-if="can('users.disable')" type="button" class="btn btn-sm btn-tertiary" @click="revokeOne(d)">Revoke</button>
                        </div>
                        <p v-if="!devices.length" class="help">No sessions recorded.</p>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="mb-4 text-h4 font-semibold text-ink">Activity</h2>
                    <div class="grid gap-2">
                        <div v-for="(a, i) in activity" :key="i" class="flex flex-wrap items-baseline justify-between gap-3 border-b border-rule-cool pb-2 last:border-0">
                            <div class="min-w-0">
                                <span class="text-body-s text-ink">{{ a.description }}</span>
                                <span v-if="a.subject" class="ml-2 font-mono text-caption text-slate">{{ a.subject }}</span>
                            </div>
                            <span class="tabular flex-none font-mono text-caption text-slate">{{ new Date(a.at).toLocaleString('en-GB') }}</span>
                        </div>
                        <p v-if="!activity.length" class="help">No activity recorded.</p>
                    </div>
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <div class="card p-5">
                    <div class="eyebrow mb-3">Account</div>
                    <dl class="grid gap-2 text-body-s">
                        <div class="flex justify-between gap-3"><dt class="text-slate">Email</dt><dd class="truncate">{{ user.email }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate">2FA</dt><dd><StatusPill :tone="user.two_factor ? 'positive' : 'critical'" :label="user.two_factor ? 'Enrolled' : 'Not enrolled'" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate">Last sign-in</dt><dd class="tabular text-right font-mono text-caption">{{ user.last_login_at ? new Date(user.last_login_at).toLocaleString('en-GB') : 'Never' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate">From</dt><dd class="tabular font-mono text-caption">{{ user.last_login_ip ?? '—' }}</dd></div>
                    </dl>

                    <div class="mt-4 grid gap-2 border-t border-rule-cool pt-4">
                        <button v-if="can('users.update') && user.two_factor" type="button" class="btn btn-sm btn-secondary" @click="reset2fa">Reset two-factor</button>
                        <button v-if="can('users.disable') && user.is_active" type="button" class="btn btn-sm btn-destructive" @click="disableSheet = true">Disable account</button>
                        <button v-if="can('users.disable') && !user.is_active" type="button" class="btn btn-sm btn-secondary" @click="enable">Re-enable account</button>
                    </div>
                </div>

                <div class="card p-5">
                    <div class="eyebrow mb-3">Effective permissions</div>
                    <div class="flex flex-wrap gap-1">
                        <code v-for="p in user.permissions" :key="p" class="font-mono text-micro text-ink-70">{{ p }}</code>
                    </div>
                    <p v-if="!user.permissions.length" class="help">None. This account can sign in but do nothing.</p>
                </div>
            </aside>
        </div>

        <Sheet :open="disableSheet" title="Disable this account" size="sm" @close="disableSheet = false">
            <p class="help mb-4">
                They will be signed out everywhere immediately, and the reason below is shown to them
                when they try to sign in.
            </p>
            <FormField id="d-reason" label="Reason" required>
                <textarea id="d-reason" v-model="reason" class="field min-h-[80px]" placeholder="Left the firm"></textarea>
            </FormField>
            <template #actions>
                <button type="button" class="btn btn-destructive" :disabled="!reason.trim()" @click="disable">Disable and revoke sessions</button>
                <button type="button" class="btn btn-tertiary" @click="disableSheet = false">Cancel</button>
            </template>
        </Sheet>
    </AdminLayout>
</template>
