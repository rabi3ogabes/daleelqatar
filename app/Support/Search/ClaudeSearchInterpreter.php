<?php

declare(strict_types=1);

namespace App\Support\Search;

use Anthropic\Client;

/**
 * Smart search's AI step (Claude, fast model, a few seconds at most). The model
 * may only pick slugs from the lists it is given; anything else is discarded.
 */
final class ClaudeSearchInterpreter implements SearchInterpreter
{
    public function available(): bool
    {
        return trim((string) config('services.anthropic.key')) !== '' && (bool) setting('integrations.ai_assistant', true);
    }

    public function interpret(string $query, array $taxonomy, array $places): ?array
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'), baseUrl: config('services.anthropic.base_url') ?: null);

        $message = $client->messages->create(
            model: (string) config('services.anthropic.fast_model', 'claude-haiku-5-5'),
            maxTokens: 300,
            system: <<<'TXT'
            You read searches typed into a business directory for Qatar (Arabic or English) and map them to the directory's filters.
            Pick at most one category slug and one place key, only from the lists given. Use null when unsure — never guess.
            open_now is true only when the person asks for something open now / at this moment.
            keywords: the few remaining words that describe the specific thing wanted (e.g. "kids", "italian"), or null.
            TXT,
            messages: [['role' => 'user', 'content' => 'Search: '.mb_substr($query, 0, 120)."\n\nCategories (slug: label):\n".
                implode("\n", array_map(fn ($s, $l) => "$s: $l", array_keys($taxonomy), $taxonomy)).
                "\n\nPlaces (key: name):\n".implode("\n", array_map(fn ($s, $l) => "$s: $l", array_keys($places), $places))]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object',
                'properties' => [
                    'category' => ['type' => ['string', 'null']],
                    'place' => ['type' => ['string', 'null']],
                    'open_now' => ['type' => 'boolean'],
                    'keywords' => ['type' => ['string', 'null']],
                ],
                'required' => ['category', 'place', 'open_now', 'keywords'],
                'additionalProperties' => false,
            ]]],
            requestOptions: ['timeout' => 6, 'maxRetries' => 0],
        );

        $data = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
            }
        }
        if (! is_array($data)) {
            return null;
        }

        $place = (string) ($data['place'] ?? '');
        $category = (string) ($data['category'] ?? '');

        return [
            'category' => isset($taxonomy[$category]) ? $category : null,
            'municipality' => str_starts_with($place, 'm:') && isset($places[$place]) ? substr($place, 2) : null,
            'area' => str_starts_with($place, 'a:') && isset($places[$place]) ? substr($place, 2) : null,
            'open_now' => (bool) ($data['open_now'] ?? false),
            'keywords' => is_string($data['keywords'] ?? null) ? mb_substr(strip_tags($data['keywords']), 0, 60) : null,
        ];
    }
}
