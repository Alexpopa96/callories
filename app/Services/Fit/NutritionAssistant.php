<?php

namespace App\Services\Fit;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Services\Anthropic\Models;

class NutritionAssistant
{
    private const SYSTEM_PROMPT = <<<'TXT'
Ești un asistent nutrițional prietenos, care răspunde în limba română, în aplicația de calorii Kalo.
Primești câte a mâncat deja azi utilizatorul, obiectivele lui și cât îi mai rămâne, plus alimentele lui
favorite. Răspunde scurt (maxim 3-4 propoziții, fără liste marcate în text, fără emoji).
Dacă întrebarea cere idei de mâncare, propune 2-4 alimente concrete și realiste, cu porții și valori
nutriționale estimate, care se încadrează în ce-i mai rămâne azi din calorii și, dacă are, din
macronutrientul întrebat. Preferă alimentele lui favorite când se potrivesc.
Nu presupune ce a mâncat în afara datelor primite și nu da sfaturi medicale; pentru probleme de sănătate,
îndrumă spre un medic sau un nutriționist.
TXT;

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context  today's totals, goals and favorites, given to the model as grounding
     * @param  list<array{role: string, content: string}>  $history  earlier turns of this conversation, oldest first
     * @return array{
     *   reply: string,
     *   suggestions: list<array{name: string, portion_grams: float, calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float}>
     * }
     */
    public function ask(array $context, array $history, string $message): array
    {
        $system = self::SYSTEM_PROMPT."\n\nDatele utilizatorului acum (JSON): ".json_encode($context, JSON_UNESCAPED_UNICODE);

        $messages = [
            ...array_map(fn (array $turn) => ['role' => $turn['role'], 'content' => $turn['content']], $history),
            ['role' => 'user', 'content' => $message],
        ];

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 1000,
                system: $system,
                messages: $messages,
                outputConfig: Models::outputConfig($this->model, 'low', $this->schema()),
            );
        } catch (APIException $e) {
            report($e);

            throw new AssistantException('Asistentul nu este disponibil momentan. Încearcă din nou.', 0, $e);
        }

        if ($response->stopReason !== 'end_turn') {
            throw new AssistantException('Răspunsul nu a putut fi generat. Încearcă din nou.');
        }

        $data = null;
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                break;
            }
        }

        if (! is_array($data) || ! isset($data['reply'])) {
            throw new AssistantException('Răspunsul asistentului a fost invalid. Încearcă din nou.');
        }

        $data['suggestions'] = array_slice($data['suggestions'] ?? [], 0, 4);

        return $data;
    }

    private function schema(): array
    {
        $number = ['type' => 'number'];

        return [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string'],
                'suggestions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'portion_grams' => $number,
                            'calories' => $number,
                            'protein_g' => $number,
                            'carbs_g' => $number,
                            'fat_g' => $number,
                            'fiber_g' => $number,
                        ],
                        'required' => ['name', 'portion_grams', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['reply', 'suggestions'],
            'additionalProperties' => false,
        ];
    }
}
