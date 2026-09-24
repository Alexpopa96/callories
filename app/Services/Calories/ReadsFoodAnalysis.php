<?php

namespace App\Services\Calories;

use Anthropic\Messages\Message;

/**
 * Output schema and response parsing shared by every service that returns a list of foods.
 */
trait ReadsFoodAnalysis
{
    private function read(Message $message, string $incompleteMessage): array
    {
        if ($message->stopReason !== 'end_turn') {
            throw new FoodAnalysisException($incompleteMessage);
        }

        $data = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                break;
            }
        }

        if (! is_array($data) || ! isset($data['items'])) {
            throw new FoodAnalysisException('Răspunsul de la analiză a fost invalid. Încearcă din nou.');
        }

        return $this->withTotals($data);
    }

    private function withTotals(array $data): array
    {
        $totals = ['calories' => 0.0, 'protein_g' => 0.0, 'carbs_g' => 0.0, 'fat_g' => 0.0, 'fiber_g' => 0.0];

        foreach ($data['items'] as $item) {
            foreach ($totals as $key => $sum) {
                $totals[$key] = $sum + (float) ($item[$key] ?? 0);
            }
        }

        $data['totals'] = array_map(fn (float $value) => round($value, 1), $totals);

        return $data;
    }

    private function schema(): array
    {
        $number = ['type' => 'number'];

        return [
            'type' => 'object',
            'properties' => [
                'is_food' => ['type' => 'boolean'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'portion_grams' => $number,
                            'pieces' => $number,
                            'calories' => $number,
                            'protein_g' => $number,
                            'carbs_g' => $number,
                            'fat_g' => $number,
                            'fiber_g' => $number,
                        ],
                        'required' => ['name', 'portion_grams', 'pieces', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'],
                        'additionalProperties' => false,
                    ],
                ],
                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['is_food', 'items', 'confidence', 'notes'],
            'additionalProperties' => false,
        ];
    }
}
