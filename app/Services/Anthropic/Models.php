<?php

namespace App\Services\Anthropic;

/**
 * The Claude models a user may pick for the AI features, and what each one accepts.
 */
class Models
{
    /** @return array<string, string> model id => label */
    public static function options(): array
    {
        return config('services.anthropic.models');
    }

    public static function allowed(?string $model): bool
    {
        return $model !== null && array_key_exists($model, self::options());
    }

    /** Haiku 4.5 rejects output_config.effort, so it runs at its default depth. */
    public static function outputConfig(string $model, string $effort, array $schema): array
    {
        $format = ['type' => 'json_schema', 'schema' => $schema];

        return str_starts_with($model, 'claude-haiku-4-5')
            ? ['format' => $format]
            : ['effort' => $effort, 'format' => $format];
    }
}
