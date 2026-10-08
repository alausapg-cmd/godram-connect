<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use App\Services\Access;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgUnitController extends Controller
{
    public function __construct(protected Access $access, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'org.manage'), 403);

        return view('admin.units', [
            'root' => OrgUnit::root()?->load('children.children.children'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'org.manage'), 403);
        $data = $request->validate([
            'parent_id' => ['required', 'exists:org_units,id'],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
        ]);
        $parent = OrgUnit::findOrFail($data['parent_id']);
        abort_unless($parent->childType(), 422, 'Assemblies cannot contain other units.');

        $unit = OrgUnit::create($data + ['type' => $parent->childType()]);
        $this->audit->log('org.unit_created', $unit, 'Added '.$unit->fullName().' under '.$parent->fullName(), [], $unit);

        return back()->with('status', $unit->fullName().' added.');
    }

    public function update(Request $request, OrgUnit $unit)
    {
        abort_unless($this->access->can($request->user(), 'org.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $old = $unit->name;
        $unit->fill($data + ['is_active' => $request->boolean('is_active', true)])->save();
        $this->audit->log('org.unit_updated', $unit, 'Updated '.$old.' ('.$unit->typeLabel().')', ['name' => $unit->name, 'active' => $unit->is_active], $unit);

        return back()->with('status', 'Saved.');
    }
}
