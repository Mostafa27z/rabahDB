<?php

namespace App\Services;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Fast property search on top of MySQL (no extra infrastructure).
 *
 * WHY IT'S FAST
 * The expensive part (Arabic normalisation: alef / ya / ta-marbuta variants, "ال", digits,
 * tashkeel...) is done ONCE when a property is saved and stored in three columns:
 *
 *   search_title     headline text: reference, title, type, area, city, seo keywords
 *   search_text      headline + address + description + details
 *   search_bedrooms  integer (0 = studio) or NULL
 *
 * At query time the user's text goes through the very same normaliser, so a search is a few
 * plain LIKEs on ONE column (instead of REGEXP on 9 columns) and the bedrooms filter is `=`.
 *
 * USAGE (unchanged):  PropertySearchEngineService::apply($query, $request->q);
 */
class PropertySearchEngineService
{
    /** Hard limits so a pasted paragraph can't generate a monster query */
    protected const MAX_QUERY_LENGTH = 120;
    protected const MAX_TOKENS = 6;

    /**
     * Stop words that can be ignored if other meaningful keywords exist
     */
    protected static array $stopWords = [
        'في', 'من', 'إلى', 'الي', 'على', 'عن', 'مع', 'و', 'أو', 'او', 'ثم',
        'هل', 'ما', 'هو', 'هي', 'هذا', 'هذه', 'ذات', 'ذو', 'عبر', 'كل', 'جميع',
        'in', 'at', 'on', 'of', 'for', 'with', 'and', 'or', 'the', 'a', 'an'
    ];

    /**
     * Groups of interchangeable words. Spelling variants (أ/إ/ا، ة/ه، ي/ى، ڤ/ف) and "ال" are
     * handled by the normaliser, so list only real alternatives. Grow this list from the
     * zero-results log.
     */
    protected static array $synonymGroups = [
        ['فيلا', 'فلل', 'فلة', 'فيلات', 'ڤيلا', 'villa', 'villas'],
        ['شقة', 'شقق', 'شقه', 'apartment', 'apartments', 'flat', 'flats'],
        ['دوبلكس', 'دبلكس', 'بوبلكس', 'duplex'],
        ['استوديو', 'استديو', 'studio'],
        ['تاونهاوس', 'تاون هاوس', 'townhouse', 'town house'],
        ['بنتهاوس', 'بنت هاوس', 'penthouse'],
        ['مكتب', 'مكاتب', 'office', 'offices'],
        ['محل', 'محلات', 'متجر', 'متاجر', 'دكان', 'دكاكين', 'shop', 'shops', 'retail', 'store'],
        ['مستودع', 'مستودعات', 'شبرة', 'شبرات', 'مخزن', 'مخازن', 'warehouse', 'storage'],
        ['ارض', 'اراضي', 'اراضى', 'قطعة', 'قطعه', 'plot', 'land'],
        ['بناية', 'بنايات', 'عمارة', 'عمائر', 'عماير', 'مبنى', 'مباني', 'building'],
        ['مزرعة', 'مزارع', 'farm'],
        ['بيت', 'بيوت', 'منزل', 'منازل', 'دار', 'house', 'home'],
    ];

    /** Purpose words (already in normalised spelling) */
    protected static array $saleWords = [
        'للبيع', 'البيع', 'بيع', 'شراء', 'للشراء', 'الشراء', 'تمليك', 'للتمليك', 'التمليك', 'sale', 'sell', 'buy'
    ];
    protected static array $rentWords = [
        'للايجار', 'الايجار', 'ايجار', 'التاجير', 'تاجير', 'للتاجير', 'مؤجر', 'rent', 'rental', 'lease'
    ];

    /** Written numbers for bedrooms (normalised spelling) */
    protected static array $numberWords = [
        'ثلاث' => 3, 'ثلاثه' => 3, 'تلات' => 3, 'تلاته' => 3,
        'اربع' => 4, 'اربعه' => 4,
        'خمس' => 5, 'خمسه' => 5,
        'ست' => 6, 'سته' => 6,
        'سبع' => 7, 'سبعه' => 7,
    ];

    protected static ?array $synonymMap = null;
    protected static ?array $phraseMap = null;
    protected static ?array $stopSet = null;

    // ------------------------------------------------------------------------------------------
    // SEARCH
    // ------------------------------------------------------------------------------------------

    /**
     * Apply the search to an Eloquent builder (mutates and returns the same builder).
     */
    public static function apply(Builder $query, ?string $rawQuery): Builder
    {
        $rawQuery = mb_substr(trim((string) $rawQuery), 0, self::MAX_QUERY_LENGTH);
        if ($rawQuery === '') {
            return $query;
        }

        // 1) Direct reference (Shj{ym}-{id} / RBH{ym}-{id} / {id}) or 4+ digit ID
        $digits = self::westernDigits($rawQuery);
        $escDigits = addcslashes($digits, '%_\\');
        if (preg_match('/^(?:Shj|RBH)(?:\d{4})?-(\d+)$/i', $digits, $m)) {
            $id = (int) $m[1];

            return $query->where(fn ($q) => $q
                ->where('id', $id)
                ->orWhere('reference', 'like', '%' . $escDigits . '%'));
        }
        if (ctype_digit($digits) && strlen($digits) >= 4 && strlen($digits) <= 12) {
            return $query->where(fn ($q) => $q
                ->where('id', (int) $digits)
                ->orWhere('reference', 'like', '%' . $escDigits . '%'));
        }

        // 2) Intents become real filters (and are removed from the search words)
        $norm = self::normalize($rawQuery, false);
        if ($norm === '') {
            return $query;
        }
        $intent = self::extractIntents($norm);

        if ($intent['purpose'] !== null) {
            $p = $intent['purpose'];
            if ($p === 'rent') {
                $query->where(function ($q) {
                    $q->where('purpose', 'rent')
                      ->orWhere('search_title', 'like', '%ايجار%')
                      ->orWhere('search_title', 'like', '% rent %')
                      ->orWhere('search_text', 'like', '%ايجار%');
                })->where(function ($q) {
                    $q->where('purpose', '!=', 'sale')
                      ->orWhere('search_title', 'like', '%ايجار%')
                      ->orWhere('search_title', 'like', '% rent %');
                });
            } elseif ($p === 'sale') {
                $query->where(function ($q) {
                    $q->where('purpose', 'sale')
                      ->orWhere('search_title', 'like', '%بيع%')
                      ->orWhere('search_title', 'like', '% sale %');
                })->where(function ($q) {
                    $q->where('purpose', '!=', 'rent')
                      ->orWhere('search_title', 'like', '%بيع%');
                });
            } else {
                $query->where('purpose', $p);
            }
        }
        if ($intent['bedrooms'] !== null) {
            $query->where('search_bedrooms', $intent['bedrooms']);
        }

        // 3) Search words ("شقق للبيع" -> intents only, no words left, filters are enough)
        $hasIntent = $intent['purpose'] !== null || $intent['bedrooms'] !== null;
        $tokens = self::splitTokens($intent['remaining'], ! $hasIntent);
        if (! $tokens) {
            return $query;
        }
        $groups = array_map(fn ($t) => self::expand($t), $tokens);

        // 4) All words must match; if nothing matches at all, fall back to "any word".
        $matchAll = fn (Builder $q) => self::matchAll($q, $groups);
        $testStrict = (clone $query)->where($matchAll);
        $testStrict->getQuery()->orders = null;
        $testStrict->getQuery()->limit = null;
        $testStrict->getQuery()->offset = null;
        $useAny = count($groups) > 1 && ! $testStrict->exists();

        $query->where($useAny ? fn (Builder $q) => self::matchAny($q, $groups) : $matchAll);

        // 5) Ranking: Google-style scoring
        $score = [];
        $bind = [];

        // Exact query / phrase match in headline (HUGE bonus)
        $score[] = '(search_title LIKE ?) * 150';
        $bind[] = '%' . addcslashes($norm, '%_\\') . '%';

        // Remaining tokens phrase in title
        if (count($tokens) > 1) {
            $score[] = '(search_title LIKE ?) * 80';
            $bind[] = '%' . addcslashes(implode(' ', $tokens), '%_\\') . '%';
        }

        // Each token matching in title (30 pts each) or text (10 pts each)
        foreach ($groups as $g) {
            $terms = array_merge([$g['main']], $g['syn']);
            $score[] = '((' . implode(' OR ', array_fill(0, count($terms), 'search_title LIKE ?')) . ') * 30)';
            foreach ($terms as $t) {
                $bind[] = self::likePattern($t);
            }
            $score[] = '((' . implode(' OR ', array_fill(0, count($terms), 'search_text LIKE ?')) . ') * 10)';
            foreach ($terms as $t) {
                $bind[] = self::likePattern($t);
            }
        }

        // Intent purpose boost in title
        if ($intent['purpose'] === 'rent') {
            $score[] = '(search_title LIKE ?) * 50';
            $bind[] = '%ايجار%';
        } elseif ($intent['purpose'] === 'sale') {
            $score[] = '(search_title LIKE ?) * 50';
            $bind[] = '%بيع%';
        }

        if (!empty($score)) {
            $query->orderByRaw('COALESCE(' . implode(' + ', $score) . ', 0) DESC', $bind);
        }

        return $query;
    }

    protected static function matchAll(Builder $q, array $groups): void
    {
        foreach ($groups as $g) {
            $q->where(fn (Builder $t) => self::matchToken($t, $g));
        }
    }

    protected static function matchAny(Builder $q, array $groups): void
    {
        foreach ($groups as $g) {
            $q->orWhere(fn (Builder $t) => self::matchToken($t, $g));
        }
    }

    /** The word itself anywhere in the text, or one of its synonyms in the headline */
    protected static function matchToken(Builder $t, array $g): void
    {
        $t->where('search_text', 'like', self::likePattern($g['main']));
        foreach ($g['syn'] as $s) {
            $t->orWhere('search_title', 'like', self::likePattern($s));
        }
    }

    /**
     * Short words (<= 3 chars, or <= 4 for non-Arabic) must match as a WHOLE word, so "باي"
     * no longer matches inside "شبايك". Longer words match as substrings.
     * Columns are stored padded with a space on both sides, which makes this a plain LIKE.
     */
    protected static function likePattern(string $term): string
    {
        $term = addcslashes($term, '%_\\');
        return self::isShort($term) ? "% {$term} %" : "%{$term}%";
    }

    protected static function isShort(string $term): bool
    {
        $len = mb_strlen($term);

        return $len <= 3 || ($len <= 4 && ! preg_match('/\p{Arabic}/u', $term));
    }

    // ------------------------------------------------------------------------------------------
    // NORMALISATION (used both when indexing and when searching, they MUST stay identical)
    // ------------------------------------------------------------------------------------------

    /**
     * lower-case, Western digits, no tashkeel/tatweel, unified letter variants
     * (ا أ إ آ / ة ه / ي ى / ڤ ف), punctuation -> space, optional "ال"-style prefix stripping.
     */
    public static function normalize(?string $s, bool $stripPrefixes = true): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        $s = mb_scrub($s, 'UTF-8');
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = self::westernDigits(mb_strtolower($s, 'UTF-8'));
        $s = preg_replace('/[\p{Mn}\p{Cf}\x{0640}]+/u', '', $s);
        $s = strtr($s, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ی' => 'ي',
            'ة' => 'ه',
            'ڤ' => 'ف',
            'ک' => 'ك',
        ]);
        $s = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));

        if ($s === '' || ! $stripPrefixes) {
            return $s;
        }

        return implode(' ', array_map(fn ($w) => self::stripPrefix($w), explode(' ', $s)));
    }

    /** "الخان" / "بالخان" / "للخان" -> "خان" (only if 3+ letters remain) */
    protected static function stripPrefix(string $w): string
    {
        if (preg_match('/^(?:بال|وال|فال|كال|ولل|لل|ال)(.{3,})$/u', $w, $m)) {
            return $m[1];
        }

        return $w;
    }

    protected static function westernDigits(string $s): string
    {
        return strtr($s, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    // ------------------------------------------------------------------------------------------
    // INTENTS + TOKENS + SYNONYMS
    // ------------------------------------------------------------------------------------------

    /**
     * Pull purpose (sale / rent) and bedrooms out of a normalised string and return what is left.
     * Works on normalised, NOT prefix-stripped text (so "للبيع" is still recognisable).
     */
    protected static function extractIntents(string $n, bool $allowStudio = true): array
    {
        $purpose = null;
        $beds = null;

        // purpose: whole words only. Both sale and rent present -> ambiguous -> no filter.
        $words = explode(' ', $n);
        $sale = array_intersect($words, self::$saleWords) !== [];
        $rent = array_intersect($words, self::$rentWords) !== [];
        if ($sale xor $rent) {
            $purpose = $sale ? 'sale' : 'rent';
        }
        if ($sale || $rent) {
            $n = implode(' ', array_diff($words, self::$saleWords, self::$rentWords));
        }

        // bedrooms
        $b = '(?<![\p{L}\p{N}])';   // not glued to a previous letter/digit ("12" is not "2")
        $e = '(?![\p{L}\p{N}])';
        $nums = implode('|', array_keys(self::$numberWords));

        $rules = [];
        if ($allowStudio) {
            $rules[] = ['/' . $b . '(?:استوديو|استديو|studio)' . $e . '/u', fn () => 0];
        }
        $rules[] = ['/' . $b . '(?:غرفتين|غرفتان)(?:\s+نوم)?' . $e . '/u', fn () => 2];
        $rules[] = ['/' . $b . 'غرفه\s+(?:واحده|وصاله)' . $e . '/u', fn () => 1];
        $rules[] = [
            '/' . $b . '(\d{1,2}|' . $nums . ')\s*(?:غرف|غرفه|نوم|bedrooms?|beds?|br)(?:\s+نوم)?' . $e . '/u',
            fn (array $m) => ctype_digit($m[1]) ? (int) $m[1] : self::$numberWords[$m[1]],
        ];

        foreach ($rules as [$re, $resolve]) {
            if (preg_match($re, $n, $m)) {
                $beds ??= $resolve($m);
                $n = preg_replace($re, ' ', $n);
            }
        }

        return [
            'purpose'   => $purpose,
            'bedrooms'  => $beds,
            'remaining' => trim(preg_replace('/\s+/u', ' ', $n)),
        ];
    }

    /**
     * Normalised text -> search words (stop words dropped, prefixes stripped, de-duplicated, capped).
     */
    protected static function splitTokens(string $text, bool $keepIfAllStopWords = true): array
    {
        $text = self::mergePhrases($text);
        $words = array_values(array_filter(explode(' ', $text), fn ($w) => mb_strlen($w) >= 2));
        if (! $words) {
            return [];
        }

        $stop = self::stopSet();
        $kept = array_values(array_filter($words, fn ($w) => ! isset($stop[$w])));
        if (! $kept) {
            if (! $keepIfAllStopWords) {
                return [];
            }
            $kept = $words;
        }

        $tokens = [];
        foreach ($kept as $w) {
            $w = self::stripPrefix($w);
            if (mb_strlen($w) >= 2) {
                $tokens[] = $w;
            }
        }

        return array_slice(array_values(array_unique($tokens)), 0, self::MAX_TOKENS);
    }

    protected static function expand(string $token): array
    {
        return ['main' => $token, 'syn' => self::synonymMap()[$token] ?? []];
    }

    /** word (normalised) => the other members of its group (normalised) */
    protected static function synonymMap(): array
    {
        if (self::$synonymMap === null) {
            $map = [];
            foreach (self::$synonymGroups as $group) {
                $members = array_values(array_unique(array_map(fn ($w) => self::normalize($w, false), $group)));
                foreach ($members as $m) {
                    $map[$m] = array_values(array_diff($members, [$m]));
                }
            }
            self::$synonymMap = $map;
        }

        return self::$synonymMap;
    }

    /** "تاون هاوس" -> "تاونهاوس", "town house" -> "townhouse" so multi-word types act as one word */
    protected static function phraseMap(): array
    {
        if (self::$phraseMap === null) {
            $map = [];
            foreach (self::$synonymGroups as $group) {
                $members = array_map(fn ($w) => self::normalize($w, false), $group);
                foreach ($members as $m) {
                    if (str_contains($m, ' ')) {
                        $joined = str_replace(' ', '', $m);
                        if (in_array($joined, $members, true)) {
                            $map[$m] = $joined;
                        }
                    }
                }
            }
            self::$phraseMap = $map;
        }

        return self::$phraseMap;
    }

    protected static function mergePhrases(string $text): string
    {
        foreach (self::phraseMap() as $phrase => $joined) {
            if (str_contains($text, $phrase)) {
                $text = preg_replace(
                    '/(?<![\p{L}\p{N}])' . preg_quote($phrase, '/') . '(?![\p{L}\p{N}])/u',
                    $joined,
                    $text
                );
            }
        }

        return $text;
    }

    protected static function stopSet(): array
    {
        return self::$stopSet ??= array_flip(array_map(fn ($w) => self::normalize($w, false), self::$stopWords));
    }

    // ------------------------------------------------------------------------------------------
    // INDEXING
    // ------------------------------------------------------------------------------------------

    /**
     * Values for the 3 search columns of one property.
     * Never throws: a search-index problem must not block saving a property.
     */
    public static function buildIndex(Model $p): array
    {
        try {
            $details = $p->getAttribute('property_details');
            if ($details instanceof Arrayable) {
                $details = $details->toArray();
            }
            $detailsArr = is_array($details)
                ? $details
                : (is_string($details) ? json_decode($details, true) : null);
            if (! is_array($detailsArr)) {
                $detailsArr = [];
            }

            $head = self::normalize(self::fields($p, [
                'reference' => 100, 'title' => 500, 'property_type' => 100,
                'area_name' => 200, 'city' => 200, 'seo_keywords' => 1000,
            ]));
            $purposeTag = match($p->getAttribute('purpose')) {
                'rent' => 'ايجار للايجار rent rental',
                'sale' => 'بيع للبيع sale',
                'investment', 'invest' => 'استثمار للاستثمار investment invest',
                default => ''
            };
            if ($purposeTag !== '') {
                $head = trim($head . ' ' . $purposeTag);
            }
            $rest = self::normalize(self::fields($p, [
                'address' => 500, 'description' => 5000, 'property_details' => 2000,
            ]));
            $text = mb_substr(trim($head . ' ' . $rest), 0, 20000);

            return [
                'search_title'    => ' ' . $head . ' ',
                'search_text'     => ' ' . $text . ' ',
                'search_bedrooms' => self::detectBedrooms($p, $detailsArr),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Rebuild the search columns for every property. Uses plain UPDATEs, so it does NOT touch
     * updated_at and does NOT fire model events / observers (no Bitrix / portal syncs).
     */
    public static function reindexAll(string $modelClass = \App\Models\Property::class, int $chunk = 200): int
    {
        $table = (new $modelClass)->getTable();
        $count = 0;

        $modelClass::query()->withoutGlobalScopes()->chunkById($chunk, function ($rows) use ($table, &$count) {
            foreach ($rows as $p) {
                $row = self::buildIndex($p);
                if ($row) {
                    DB::table($table)->where($p->getKeyName(), $p->getKey())->update($row);
                    $count++;
                }
            }
        });

        return $count;
    }

    protected static function fields(Model $p, array $limits): string
    {
        $out = [];
        foreach ($limits as $field => $max) {
            $out[] = mb_substr(self::textOf($p->getAttribute($field)), 0, $max);
        }

        return implode(' ', $out);
    }

    protected static function textOf(mixed $v): string
    {
        if ($v instanceof Arrayable) {
            $v = $v->toArray();
        }
        if (is_array($v)) {
            return (string) json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return is_scalar($v) ? (string) $v : '';
    }

    /** 0 = studio, null = unknown / not applicable (land, office...) */
    protected static function detectBedrooms(Model $p, array $details): ?int
    {
        // 1) structured data: a real `bedrooms` column, or any JSON key containing "bed" / "غرف"
        $candidates = [$p->getAttribute('bedrooms')];
        foreach ($details as $k => $v) {
            if (is_string($k) && (stripos($k, 'bed') !== false || mb_strpos($k, 'غرف') !== false)) {
                $candidates[] = $v;
            }
        }
        foreach ($candidates as $v) {
            if (is_numeric($v)) {
                return max(0, min(20, (int) $v));
            }
            if (is_string($v) && preg_match('/studio|استوديو|استديو|أستوديو/ui', $v)) {
                return 0;
            }
        }

        // 2) free text: title, then details, then description ("studio" is ignored in the
        //    description, it often just means a maid's studio in a bigger unit)
        foreach (['title' => true, 'property_details' => true, 'description' => false] as $field => $studio) {
            $n = self::normalize(self::textOf($p->getAttribute($field)), false);
            if ($n === '') {
                continue;
            }
            $b = self::extractIntents($n, $studio)['bedrooms'];
            if ($b !== null) {
                return $b;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------------------------------
    // LEGACY HELPERS (not used by apply() anymore). Kept only in case you call them from
    // somewhere else in the project. If nothing calls them, delete this whole section.
    // ------------------------------------------------------------------------------------------

    /**
     * Clean and normalize raw query string & convert Eastern Arabic numerals
     */
    public static function cleanSearchString(string $str): string
    {
        // 1. Strip HTML tags
        $str = strip_tags($str);

        // 2. Convert Eastern Arabic numerals (٠-٩) to standard (0-9)
        $easternNumerals = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $westernNumerals = ['0','1','2','3','4','5','6','7','8','9'];
        $str = str_replace($easternNumerals, $westernNumerals, $str);

        // 3. Remove Arabic diacritics (Tashkeel)
        $str = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $str);

        // 4. Remove Tatweel (Kashida)
        $str = preg_replace('/\x{0640}/u', '', $str);

        // 5. Replace punctuation & special symbols with spaces
        $str = preg_replace('/[,\.\-_\|\/\(\)\[\]\{\}\+\*\?\#\$\%\^\&\!\@\:\;\"\'`~]/u', ' ', $str);

        // 6. Collapse duplicate whitespaces
        $str = preg_replace('/\s+/u', ' ', $str);

        return trim($str);
    }

    /**
     * Split search query into meaningful tokens
     */
    public static function tokenize(string $cleanStr): array
    {
        $words = explode(' ', $cleanStr);
        $tokens = [];

        foreach ($words as $w) {
            $w = trim($w);
            if (mb_strlen($w) < 2) {
                continue;
            }
            // Skip stop words if we have other tokens
            if (in_array(mb_strtolower($w), self::$stopWords, true) && count($words) > 1) {
                continue;
            }
            $tokens[] = $w;
        }

        // If all tokens were stripped as stop words, keep the original words
        if (empty($tokens) && !empty($words)) {
            foreach ($words as $w) {
                if (mb_strlen(trim($w)) > 0) {
                    $tokens[] = trim($w);
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Build MySQL REGEXP pattern (old approach, slow: no index can be used)
     */
    public static function buildFlexibleRegexPattern(string $word): string
    {
        $w = trim($word);

        // Strip leading "ال" (Definite article) if present to match both forms
        if (str_starts_with($w, 'ال') && mb_strlen($w) > 3) {
            $wWithoutAl = mb_substr($w, 2);
        } else {
            $wWithoutAl = $w;
        }

        // Character normalization map for Arabic
        $chars = preg_split('//u', $wWithoutAl, -1, PREG_SPLIT_NO_EMPTY);
        $patternParts = [];

        foreach ($chars as $ch) {
            if (in_array($ch, ['ا', 'أ', 'إ', 'آ'], true)) {
                $patternParts[] = '[اأإآ]';
            } elseif (in_array($ch, ['ة', 'ه'], true)) {
                $patternParts[] = '[ةه]';
            } elseif (in_array($ch, ['ي', 'ى'], true)) {
                $patternParts[] = '[يى]';
            } elseif (in_array($ch, ['ف', 'ڤ'], true)) {
                $patternParts[] = '[فڤ]';
            } elseif (in_array($ch, ['ك', 'ک'], true)) {
                $patternParts[] = '[كک]';
            } else {
                $patternParts[] = preg_quote($ch, '/');
            }
        }

        $corePattern = implode('', $patternParts);

        // Allow optional "ال" prefix
        return '(ال)?' . $corePattern;
    }
}
