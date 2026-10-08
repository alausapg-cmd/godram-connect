<?php

namespace App\Services;

use App\Models\Member;
use App\Models\OrgUnit;
use Illuminate\Support\Collection;

/**
 * Looks for members who may be the same person. Exact phone or email
 * matches are certain; similar names in the same District are only a
 * warning, because the spec forbids relying on names alone.
 */
class DuplicateFinder
{
    public function exactMatches(?string $phone, ?string $email, ?int $ignoreId = null): Collection
    {
        if (! $phone && ! $email) {
            return collect();
        }

        return Member::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($phone, $email) {
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->get();
    }

    public function similarNames(string $first, string $last, ?OrgUnit $assembly, ?int $ignoreId = null): Collection
    {
        $district = $assembly?->ancestorOfType(OrgUnit::DISTRICT) ?? $assembly;
        $firstKey = soundex($first);
        $lastKey = soundex($last);

        return Member::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->when($district, fn ($q) => $q->placedWithin($district))
            ->where(function ($q) use ($first, $last) {
                $q->where('last_name', 'like', mb_substr($last, 0, 3).'%')
                    ->orWhere('first_name', 'like', mb_substr($first, 0, 3).'%');
            })
            ->limit(200)
            ->get()
            ->filter(function (Member $m) use ($first, $last, $firstKey, $lastKey) {
                $sameLast = soundex($m->last_name) === $lastKey || levenshtein(mb_strtolower($m->last_name), mb_strtolower($last)) <= 2;
                $sameFirst = soundex($m->first_name) === $firstKey || levenshtein(mb_strtolower($m->first_name), mb_strtolower($first)) <= 2;
                // Also catch first and last names entered the wrong way round.
                $swapped = mb_strtolower($m->first_name) === mb_strtolower($last) && mb_strtolower($m->last_name) === mb_strtolower($first);

                return ($sameLast && $sameFirst) || $swapped;
            })
            ->values();
    }
}
