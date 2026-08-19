<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\SessionDevice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::admins()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name'),
                    'is_active' => $u->is_active,
                    'two_factor' => $u->hasTwoFactorEnabled(),
                    'last_login_at' => $u->last_login_at?->toIso8601String(),
                    'disabled_reason' => $u->disabled_reason,
                    'locked' => $u->isLockedOut(),
                ]),
            'roles' => Role::where('guard_name', 'admin')->orderBy('name')->pluck('name'),
        ]);
    }

    public function show(User $user): Response
    {
        abort_unless($user->isAdmin(), 404);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'is_active' => $user->is_active,
                'two_factor' => $user->hasTwoFactorEnabled(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_login_ip' => $user->last_login_ip,
                'disabled_reason' => $user->disabled_reason,
            ],
            'roles' => Role::where('guard_name', 'admin')->orderBy('name')->pluck('name'),
            'devices' => $user->devices()->latest('last_active_at')->limit(20)->get()
                ->map(fn (SessionDevice $d) => [
                    'id' => $d->id,
                    'label' => $d->device_label,
                    'ip' => $d->ip,
                    'last_active_at' => $d->last_active_at?->toIso8601String(),
                    'revoked' => ! $d->isActive(),
                ]),
            // Per-user activity, read-only.
            'activity' => Activity::where('causer_id', $user->id)
                ->latest('id')->limit(50)->get()
                ->map(fn ($a) => [
                    'description' => $a->description,
                    'log_name' => $a->log_name,
                    'subject' => $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : null,
                    'at' => $a->created_at->toIso8601String(),
                    'ip' => $a->ip_address,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'email' => 'required|email|max:190|unique:users,email',
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        // A random password the invitee never learns. They set their own via
        // reset, and 2FA enrolment is compulsory on first sign-in regardless.
        $user = User::create([
            'user_type' => User::TYPE_ADMIN,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::password(32)),
            'is_active' => true,
            'timezone' => 'Asia/Dubai',
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        $this->audit->log('users', 'User invited', $user, ['roles' => $validated['roles'] ?? []]);

        return back()->with('success', "{$user->name} has been invited. They must complete two-factor enrolment at first sign-in.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:160',
            'phone' => 'nullable|string|max:32',
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user->update(collect($validated)->only(['name', 'phone'])->all());

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
            $this->audit->log('users', 'Roles changed', $user, ['roles' => $validated['roles']]);
        }

        return back()->with('success', 'User updated.');
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        $validated = $request->validate(['reason' => 'required|string|max:500']);

        // Disabling revokes every session too. An account that can no longer
        // sign in but stays signed in somewhere is not disabled.
        $user->forceFill([
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_by' => $request->user('admin')->id,
            'disabled_reason' => $validated['reason'],
        ])->save();

        $user->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $this->audit->log('users', 'User disabled', $user, ['reason' => $validated['reason']]);

        return back()->with('success', "{$user->name} has been disabled and their sessions revoked.");
    }

    public function enable(User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        $user->forceFill([
            'is_active' => true,
            'disabled_at' => null,
            'disabled_by' => null,
            'disabled_reason' => null,
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $this->audit->log('users', 'User re-enabled', $user);

        return back()->with('success', "{$user->name} can sign in again.");
    }

    public function revokeSessions(User $user): RedirectResponse
    {
        $user->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->audit->log('users', 'All sessions revoked', $user);

        return back()->with('success', 'All sessions revoked. They will be signed out on their next request.');
    }

    public function revokeDevice(SessionDevice $device): RedirectResponse
    {
        $device->update(['revoked_at' => now()]);
        $this->audit->log('users', 'Session revoked', $device->user);

        return back()->with('success', 'Session revoked.');
    }

    public function resetTwoFactor(User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log('users', 'Two-factor reset — re-enrolment required', $user);

        return back()->with('success', 'Two-factor reset. They must enrol again at next sign-in.');
    }
}
