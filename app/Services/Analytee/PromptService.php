<?php

namespace App\Services\Analytee;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class PromptService
{
    private const SUPPORTED_VARS = [
        'review_text',
        'business_name',
        'language',
    ];

    public function getActivePrompt(string $key): string
    {
        $row = DB::table('analytee_prompts')
            ->select(['prompt', 'is_active'])
            ->where('key', $key)
            ->where('is_active', 1)
            ->where('is_published', 1)
            ->orderByDesc('version')
            ->first();

        if (! $row) {
            throw new RuntimeException("No existe un prompt publicado y activo para key '{$key}'");
        }

        return (string) ($row->prompt ?? '');
    }

    public function build(string $key, array $vars): string
    {
        $template = $this->getActivePrompt($key);

        $replacements = [];
        foreach (self::SUPPORTED_VARS as $var) {
            $replacements['{{'.$var.'}}'] = (string) ($vars[$var] ?? '');
        }

        return strtr($template, $replacements);
    }
}
