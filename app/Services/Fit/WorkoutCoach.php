<?php

namespace App\Services\Fit;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Services\Anthropic\Models;

class WorkoutCoach
{
    private const SYSTEM_PROMPT = <<<'TXT'
Ești un antrenor personal prietenos, care răspunde în limba română, în aplicația de fitness Kalo Mind.
Utilizatorul îți spune ce vrea să facă azi la sală (grupe musculare, timp disponibil, echipament,
nivel). Dacă informația e insuficientă, pune 1-2 întrebări scurte de clarificare, fără să incluzi
exerciții încă. Când ai destule informații, propune un antrenament complet: încălzire scurtă, apoi
4-7 exerciții concrete, cu serii, repetări și o notă scurtă (tehnică sau greutate orientativă) pentru
fiecare. Ține cont de antrenamentele recente primite ca să eviți aceleași grupe musculare zile la rând.
Răspunde scurt (maxim 3-4 propoziții în afara listei de exerciții), fără emoji. Nu da sfaturi medicale;
pentru dureri articulare sau probleme de sănătate, îndrumă spre un medic sau un kinetoterapeut.
TXT;

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context  recent workout history, given to the model as grounding
     * @param  list<array{role: string, content: string}>  $history  earlier turns of this conversation, oldest first
     * @return array{
     *   reply: string,
     *   plan: array{title: string, exercises: list<array{name: string, sets: int, reps: string, notes: string}>}
     * }
     */
    public function ask(array $context, array $history, string $message): array
    {
        $system = self::SYSTEM_PROMPT."\n\nAntrenamentele recente ale utilizatorului (JSON): ".json_encode($context, JSON_UNESCAPED_UNICODE);

        $messages = [
            ...array_map(fn (array $turn) => ['role' => $turn['role'], 'content' => $turn['content']], $history),
            ['role' => 'user', 'content' => $message],
        ];

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: 1200,
                system: $system,
                messages: $messages,
                outputConfig: Models::outputConfig($this->model, 'low', $this->schema()),
            );
        } catch (APIException $e) {
            report($e);

            throw new AssistantException('Antrenorul nu este disponibil momentan. Încearcă din nou.', 0, $e);
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
            throw new AssistantException('Răspunsul antrenorului a fost invalid. Încearcă din nou.');
        }

        $data['plan'] = [
            'title' => $data['plan']['title'] ?? '',
            'exercises' => array_slice($data['plan']['exercises'] ?? [], 0, 10),
        ];

        return $data;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string'],
                'plan' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'exercises' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'sets' => ['type' => 'integer'],
                                    'reps' => ['type' => 'string'],
                                    'notes' => ['type' => 'string'],
                                ],
                                'required' => ['name', 'sets', 'reps', 'notes'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['title', 'exercises'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['reply', 'plan'],
            'additionalProperties' => false,
        ];
    }
}
