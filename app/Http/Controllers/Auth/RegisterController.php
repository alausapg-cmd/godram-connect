<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\MembershipService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Self sign-up. The member waits for their Assembly Coordinator to confirm them. */
class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register', ['assemblies' => $this->assemblyOptions()]);
    }

    public function store(Request $request, MembershipService $membership)
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->input('email'))) : null,
        ]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('members', 'phone'), Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('members', 'email'), Rule::unique('users', 'email')],
            'org_unit_id' => ['required', Rule::exists('org_units', 'id')->where('type', OrgUnit::ASSEMBLY)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'phone.unique' => 'This phone number is already registered. Try signing in, or ask your Assembly Coordinator for help.',
            'email.unique' => 'This email is already registered. Try signing in instead.',
        ]);

        $assembly = OrgUnit::findOrFail($data['org_unit_id']);
        $member = $membership->register(
            collect($data)->only(['first_name', 'last_name', 'phone', 'email'])->all(),
            $assembly, null, false, $data['password'],
        );

        Auth::login(User::where('member_id', $member->id)->first());
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome to GODRAM CONNECT. Your Assembly Coordinator will confirm your membership shortly.');
    }

    protected function assemblyOptions()
    {
        return OrgUnit::ofType(OrgUnit::DISTRICT)->where('is_active', true)->orderBy('name')
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->get();
    }
}
