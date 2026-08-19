<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles' => Role::where('guard_name', 'admin')
                ->with('permissions:id,name')
                ->withCount('users')
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'description' => $r->description,
                    'is_system' => $r->is_system,
                    'users_count' => $r->users_count,
                    'permissions' => $r->permissions->pluck('name'),
                ]),
            // Grouped by module, which is how an administrator thinks about them.
            'modules' => Permission::where('guard_name', 'admin')
                ->orderBy('module')->orderBy('name')->get()
                ->groupBy('module')
                ->map(fn ($perms, $module) => [
                    'module' => $module,
                    'label' => str($module)->replace('_', ' ')->ucfirst()->toString(),
                    'permissions' => $perms->map(fn (Permission $p) => [
                        'name' => $p->name,
                        'description' => $p->description,
                    ])->values(),
                ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'admin',
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        $this->audit->log('roles', 'Role created', $role, ['permissions' => $validated['permissions'] ?? []]);

        return back()->with('success', "Role \"{$role->name}\" created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // A system role's permissions may be tuned, but it can never be renamed
        // or deleted — Super Administrator must always exist or the platform
        // can lock everybody out.
        $role->update(['description' => $validated['description'] ?? $role->description]);

        if (isset($validated['permissions'])) {
            $before = $role->permissions->pluck('name')->all();
            $role->syncPermissions($validated['permissions']);

            $this->audit->log('roles', 'Role permissions changed', $role, [
                'added' => array_values(array_diff($validated['permissions'], $before)),
                'removed' => array_values(array_diff($before, $validated['permissions'])),
            ]);
        }

        return back()->with('success', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'A system role cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Reassign the users holding this role before deleting it.');
        }

        $name = $role->name;
        $this->audit->log('roles', 'Role deleted', $role);
        $role->delete();

        return back()->with('success', "Role \"{$name}\" deleted.");
    }

    /**
     * The "what this role can and cannot see" preview.
     *
     * Renders the consequences in plain language rather than permission names,
     * because the person assigning a role is deciding what someone can do, not
     * which strings are ticked.
     */
    public function preview(Request $request, Role $role): Response
    {
        $has = fn (string $p) => $role->hasPermissionTo($p, 'admin');

        return Inertia::render('Admin/Roles/Preview', [
            'role' => ['name' => $role->name, 'description' => $role->description],
            // The guard must be named: roles live on `admin`, and Spatie's scope
            // defaults to the `web` guard, which has no such role.
            'users' => User::role($role->name, 'admin')->pluck('name'),
            'summary' => [
                [
                    'area' => 'Cases',
                    'can' => array_values(array_filter([
                        $has('cases.view.all') ? 'See every case in the system' : null,
                        $has('cases.view.assigned') && ! $has('cases.view.all') ? 'See only cases assigned to them' : null,
                        $has('cases.update') ? 'Change case details and status' : null,
                        $has('cases.assign') ? 'Assign and reassign staff' : null,
                        $has('cases.close') ? 'Close a case' : null,
                    ])),
                    'cannot' => array_values(array_filter([
                        ! $has('cases.view_restricted') ? 'Read the capacity or undue-influence flag, or its reason' : null,
                        ! $has('cases.view.all') && ! $has('cases.view.assigned') ? 'See any case at all' : null,
                        ! $has('cases.assign') ? 'Assign staff to a case' : null,
                    ])),
                ],
                [
                    'area' => 'Money',
                    'can' => array_values(array_filter([
                        $has('payments.view') ? 'See payments and history' : null,
                        $has('payments.create_link') ? 'Generate a payment link' : null,
                        $has('payments.record_manual') ? 'Record a bank transfer or cash payment' : null,
                        $has('payments.refund') ? 'Issue a refund' : null,
                    ])),
                    'cannot' => array_values(array_filter([
                        ! $has('payments.refund') ? 'Issue a refund' : null,
                        ! $has('payments.record_manual') ? 'Record a manual payment' : null,
                    ])),
                ],
                [
                    'area' => 'Configuration',
                    'can' => array_values(array_filter([
                        $has('questionnaire.edit') ? 'Edit the questionnaire and routing rules' : null,
                        $has('questionnaire.publish') ? 'Publish a questionnaire version' : null,
                        $has('content.edit') ? 'Edit page content and FAQs' : null,
                        $has('settings.edit') ? 'Change settings' : null,
                        $has('settings.integrations') ? 'Change mail, WhatsApp and payment credentials' : null,
                    ])),
                    'cannot' => array_values(array_filter([
                        ! $has('settings.integrations') ? 'See or change integration credentials' : null,
                        ! $has('roles.update') ? 'Change what any role can do' : null,
                        ! $has('questionnaire.publish') ? 'Publish a questionnaire version' : null,
                    ])),
                ],
                [
                    'area' => 'Oversight',
                    'can' => array_values(array_filter([
                        $has('audit.view') ? 'Read the audit log' : null,
                        $has('audit.export') ? 'Export the audit log' : null,
                        $has('consents.export') ? 'Export consent records' : null,
                        $has('analytics.view') ? 'View operational analytics' : null,
                    ])),
                    'cannot' => array_values(array_filter([
                        ! $has('audit.view') ? 'Read the audit log' : null,
                        ! $has('reports.export') ? 'Export case data' : null,
                    ])),
                ],
            ],
        ]);
    }
}
