<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions and roles.
 *
 * Everything is on the `admin` guard except the Customer role, which is on
 * `web`. Keeping them on separate guards means a customer account can never
 * satisfy a staff permission check, whatever happens to its roles.
 */
class PermissionSeeder extends Seeder
{
    /** @var array<string, array<string, string>> module => [permission => description] */
    private array $permissions = [
        'cases' => [
            'cases.view.all' => 'See every case in the system',
            'cases.view.assigned' => 'See only cases assigned to this user',
            'cases.create' => 'Create a case manually',
            'cases.update' => 'Change case details and status',
            'cases.assign' => 'Assign and reassign staff to a case',
            'cases.reclassify' => 'Change a case pathway or service type',
            'cases.close' => 'Close a case',
            'cases.view_restricted' => 'Read the capacity or undue-influence flag and its reason',
        ],
        'notes' => [
            'notes.create' => 'Add a note to a case',
            'notes.view_internal' => 'Read internal-only notes',
        ],
        'contacts' => [
            'contacts.log' => 'Log a contact with a customer',
        ],
        'payments' => [
            'payments.view' => 'See payments and payment history',
            'payments.create_link' => 'Generate a payment link',
            'payments.record_manual' => 'Record a bank transfer or cash payment',
            'payments.refund' => 'Issue a refund',
        ],
        'questionnaire' => [
            'questionnaire.view' => 'View the questionnaire and routing rules',
            'questionnaire.edit' => 'Edit questions, options and routing rules',
            'questionnaire.publish' => 'Publish a questionnaire version',
            'questionnaire.rollback' => 'Roll back to a previous version',
        ],
        'content' => [
            'content.view' => 'View page content and FAQs',
            'content.edit' => 'Edit page content and FAQs',
            'content.publish' => 'Publish content changes',
        ],
        'users' => [
            'users.view' => 'View user accounts',
            'users.create' => 'Invite and create users',
            'users.update' => 'Edit users and assign roles',
            'users.disable' => 'Disable an account and revoke its sessions',
        ],
        'roles' => [
            'roles.view' => 'View roles and permissions',
            'roles.create' => 'Create a role',
            'roles.update' => 'Change a role\'s permissions',
        ],
        'settings' => [
            'settings.view' => 'View settings',
            'settings.edit' => 'Change settings',
            'settings.integrations' => 'Change mail, WhatsApp and payment credentials',
        ],
        'audit' => [
            'audit.view' => 'Read the audit log',
            'audit.export' => 'Export the audit log',
            'consents.export' => 'Export consent records',
        ],
        'analytics' => [
            'analytics.view' => 'View operational analytics',
            'reports.export' => 'Export reports and case data',
        ],
        'documents' => [
            'documents.view' => 'View uploaded documents',
            'documents.upload' => 'Upload documents to a case',
        ],
        'drafts' => [
            'drafts.view' => 'View drafts',
            'drafts.send' => 'Send a draft to the customer',
            'drafts.approve' => 'Record approval of a draft',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $all = [];

            foreach ($this->permissions as $module => $entries) {
                foreach ($entries as $name => $description) {
                    Permission::updateOrCreate(
                        ['name' => $name, 'guard_name' => 'admin'],
                        ['description' => $description, 'module' => $module],
                    );

                    $all[] = $name;
                }
            }

            $this->role(
                'Super Administrator',
                'Unrestricted access, including roles and integration credentials. Cannot be deleted.',
                $all,
                isSystem: true,
            );

            // Everything except role management and integration credentials —
            // the two things that let an account escalate itself or exfiltrate
            // a credential.
            $this->role(
                'Administrator',
                'Full operational access. Cannot manage roles or integration credentials.',
                array_values(array_filter(
                    $all,
                    fn (string $p) => ! str_starts_with($p, 'roles.') && $p !== 'settings.integrations',
                )),
                isSystem: true,
            );

            $this->role(
                'Legal Reviewer',
                'Reviews and drafts. Holds the restricted-case permission.',
                [
                    'cases.view.all', 'cases.update', 'cases.reclassify', 'cases.view_restricted',
                    'notes.create', 'notes.view_internal', 'contacts.log',
                    'documents.view', 'documents.upload',
                    'drafts.view', 'drafts.send', 'drafts.approve',
                    'questionnaire.view', 'payments.view',
                ],
                isSystem: true,
            );

            // Note what is absent: cases.view_restricted. A coordinator can move
            // a case forward without ever being able to read why it was held.
            $this->role(
                'Case Handler',
                'Works assigned cases only. Cannot see restricted flags.',
                [
                    'cases.view.assigned', 'cases.update',
                    'notes.create', 'notes.view_internal', 'contacts.log',
                    'documents.view', 'documents.upload',
                    'drafts.view', 'payments.view', 'payments.create_link',
                ],
                isSystem: true,
            );

            $this->role(
                'Finance',
                'Payments, refunds and revenue reporting.',
                [
                    'cases.view.all', 'payments.view', 'payments.create_link',
                    'payments.record_manual', 'payments.refund',
                    'analytics.view', 'reports.export',
                ],
                isSystem: true,
            );

            $this->role(
                'Read Only',
                'Can look, cannot touch.',
                ['cases.view.all', 'payments.view', 'questionnaire.view', 'content.view', 'analytics.view'],
                isSystem: true,
            );

            // The customer role lives on the `web` guard and holds no staff
            // permissions at all.
            Role::updateOrCreate(
                ['name' => 'Customer', 'guard_name' => 'web'],
                ['description' => 'A customer of the platform.', 'is_system' => true],
            );
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param array<int, string> $permissions */
    private function role(string $name, string $description, array $permissions, bool $isSystem = false): Role
    {
        $role = Role::updateOrCreate(
            ['name' => $name, 'guard_name' => 'admin'],
            ['description' => $description, 'is_system' => $isSystem],
        );

        $role->syncPermissions($permissions);

        return $role;
    }
}
