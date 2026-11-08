<?php

namespace App\Support;

/**
 * The award types offered for SOI 1.a Coast Guard Awards and Decorations.
 * The list lives in one place, the options partial
 * (resources/views/applicant/partials/cg-award-options.blade.php); this reads
 * the option texts from it the way a browser sends them (whitespace collapsed),
 * so the server can refuse a type that is not on the list.
 */
class CgAwardTypes
{
    private static ?array $all = null;

    /** @return list<string> */
    public static function all(): array
    {
        if (self::$all === null) {
            $html = (string) @file_get_contents(resource_path('views/applicant/partials/cg-award-options.blade.php'));
            preg_match_all('#<option(?![^>]*value="")[^>]*>(.*?)</option>#s', $html, $m);
            self::$all = array_values(array_unique(array_filter(array_map(
                fn ($t) => trim(preg_replace('/\s+/u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                $m[1]
            ))));
        }

        return self::$all;
    }
}
