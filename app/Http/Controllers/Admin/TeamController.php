<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Who works in the hotel's admin, and what each person may do there.
 */
class TeamController extends Controller
{
    use ResolvesCurrentHotel;

    private const ROLES = [HotelUser::ROLE_OWNER, HotelUser::ROLE_FRONT_DESK, HotelUser::ROLE_STAFF];

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        return view('admin.team.index', [
            'hotel' => $hotel,
            'members' => $hotel->users()->orderBy('name')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = $this->currentHotel($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        $user = User::where('email', $data['email'])->first();
        $password = null;

        if ($user && $hotel->users()->whereKey($user->id)->exists()) {
            return back()->withErrors(['email' => 'That person is already on the team.'])->withInput();
        }

        if (! $user) {
            $password = Str::password(14, symbols: false);
            $user = User::create(['name' => $data['name'] ?: Str::headline(Str::before($data['email'], '@')), 'email' => $data['email'], 'password' => $password]);
        }

        $hotel->users()->attach($user->id, ['role' => $data['role'], 'status' => 'active']);
        AuditLog::record($hotel->id, 'team.added', "Added {$user->email} as {$data['role']}", $hotel, $request->user(), ['user' => $user->email]);

        $message = "{$user->email} joined the team as {$data['role']}.";

        if ($password) {
            $message .= " A new account was created — temporary password: {$password} (shown only once; ask them to change it under Account).";
        }

        return back()->with('status', $message);
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_unless($hotel->users()->whereKey($member->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        if (! $this->keepsAnOwner($hotel, $member, $data['role'], $data['status'])) {
            return back()->withErrors(['role' => 'The hotel must keep at least one active owner.']);
        }

        $hotel->users()->updateExistingPivot($member->id, ['role' => $data['role'], 'status' => $data['status']]);
        AuditLog::record($hotel->id, 'team.updated', "Set {$member->email} to {$data['role']} ({$data['status']})", $hotel, $request->user(), ['user' => $member->email]);

        return back()->with('status', "{$member->name} is now {$data['role']} ({$data['status']}).");
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_unless($hotel->users()->whereKey($member->id)->exists(), 404);

        if (! $this->keepsAnOwner($hotel, $member, null, null)) {
            return back()->withErrors(['role' => 'The hotel must keep at least one active owner.']);
        }

        $hotel->users()->detach($member->id);
        AuditLog::record($hotel->id, 'team.removed', "Removed {$member->email} from the team", $hotel, $request->user(), ['user' => $member->email]);

        return back()->with('status', "{$member->name} was removed from the team.");
    }

    /**
     * Whether an active owner would still remain after this member takes the
     * given role and status (null role means removal).
     */
    private function keepsAnOwner(Hotel $hotel, User $member, ?string $role, ?string $status): bool
    {
        $stillOwner = $role === HotelUser::ROLE_OWNER && $status === 'active';

        return $stillOwner || $hotel->users()
            ->wherePivot('role', HotelUser::ROLE_OWNER)
            ->wherePivot('status', 'active')
            ->where('users.id', '!=', $member->id)
            ->exists();
    }
}
