<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $member = $request->user()->member;
        abort_unless($member, 404);

        return redirect()->route('members.show', $member);
    }

    public function edit(Request $request)
    {
        $member = $request->user()->member;
        abort_unless($member, 404);

        return view('profile.edit', ['member' => $member, 'skills' => Skill::orderBy('sort')->get()]);
    }

    /** Members can update their own bio and skills; contact details go through their coordinator. */
    public function update(Request $request, AuditLogger $audit)
    {
        $member = $request->user()->member;
        abort_unless($member, 404);
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:1000'],
            'skills' => ['array'],
            'skills.*' => ['integer', 'exists:skills,id'],
        ]);
        $member->fill(['bio' => $data['bio'] ?? null])->save();
        $member->skills()->sync($data['skills'] ?? []);
        $audit->log('member.self_updated', $member, $member->full_name.' updated their profile', [], $member->assembly());

        return redirect()->route('members.show', $member)->with('status', 'Your profile has been updated.');
    }
}
