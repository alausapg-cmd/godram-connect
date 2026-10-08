<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkillSeeder extends Seeder
{
    public const SKILLS = [
        'Acting', 'Directing', 'Scriptwriting', 'Stage Management', 'Film Production', 'Cinematography',
        'Editing', 'Sound', 'Lighting', 'Costume', 'Makeup', 'Dance', 'Music', 'Choreography',
        'Production Management', 'Technical Theatre', 'Set Design', 'Props', 'Photography', 'Content Creation',
    ];

    public function run(): void
    {
        foreach (self::SKILLS as $i => $name) {
            Skill::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'sort' => $i]);
        }
    }
}
