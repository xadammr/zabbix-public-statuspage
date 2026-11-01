<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageMacroParser
{
    public function string(Collection $macros, string $macro): ?string
    {
        $value = $macros->firstWhere('macro', $macro)['value'] ?? null;

        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    public function integer(Collection $macros, string $macro, int $default): int
    {
        $value = $macros->firstWhere('macro', $macro)['value'] ?? null;

        if ($value === null || ! is_numeric($value)) {
            return $default;
        }

        return max(1, (int) round((float) $value));
    }

    public function publicUrl(Collection $macros): ?string
    {
        $url = $this->string($macros, '{$PUBLIC_URL_OVERRIDE}')
            ?: $this->string($macros, '{$PUBLIC_URL}');

        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }

    public function metricKeys(string $value): array
    {
        $keys = [];
        $current = '';
        $bracketDepth = 0;
        $quote = null;

        foreach (str_split($value) as $character) {
            if ($quote !== null) {
                $current .= $character;

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $current .= $character;

                continue;
            }

            if ($character === '[') {
                $bracketDepth++;
                $current .= $character;

                continue;
            }

            if ($character === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                $current .= $character;

                continue;
            }

            if (($character === ',' || $character === ';') && $bracketDepth === 0) {
                $keys[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $keys[] = trim($current);

        return collect($keys)->filter()->unique()->values()->all();
    }
}
