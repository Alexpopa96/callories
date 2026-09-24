<?php

namespace App\Services\Calories;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Services\Anthropic\Models;

class FoodPhotoAnalyzer
{
    use ReadsFoodAnalysis;

    private const SYSTEM_PROMPT = <<<'TXT'
Ești un nutriționist care estimează valorile nutriționale ale mâncării dintr-o fotografie.
Identifică fiecare aliment sau preparat distinct din imagine, estimează porția în grame după aspectul
vizual (farfurie, tacâmuri, ambalaje și alte obiecte de referință) și oferă valori nutriționale
realiste pentru porția respectivă. Numele alimentelor se scriu în limba română.
Pentru fiecare aliment, completează și pieces cu numărul de bucăți individuale vizibile în imagine
(ex: 2 pentru două ouă, 3 pentru trei felii de pâine). Dacă alimentul nu se numără firesc în bucăți
(ex: o porție de orez, o farfurie de supă), setează pieces=1. portion_grams rămâne greutatea totală
pentru toate bucățile la un loc, nu greutatea unei singure bucăți.
Dacă în imagine nu există mâncare sau băutură, setează is_food=false și lasă items gol.
Setează confidence=low când porția sau ingredientele sunt greu de stabilit (preparate compuse,
unghi prost, obiecte de referință lipsă). În notes menționează pe scurt ipotezele importante
(ex: „am presupus că paste sunt fierte, cu sos de roșii”), maxim două propoziții.
TXT;

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {
    }

    /**
     * @return array{
     *   is_food: bool,
     *   items: list<array{name: string, portion_grams: float, pieces: float, calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float}>,
     *   totals: array{calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float},
     *   confidence: string,
     *   notes: string
     * }
     */
    public function analyze(string $imageBytes, string $mediaType): array
    {
        try {
            $message = $this->client->messages->create(
                model: $this->model,
                maxTokens: 4000,
                system: self::SYSTEM_PROMPT,
                messages: [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'mediaType' => $mediaType,
                                'data' => base64_encode($imageBytes),
                            ],
                        ],
                        ['type' => 'text', 'text' => 'Estimează valorile nutriționale pentru mâncarea din poză.'],
                    ],
                ]],
                outputConfig: Models::outputConfig($this->model, 'medium', $this->schema()),
            );
        } catch (APIException $e) {
            report($e);

            throw new FoodAnalysisException('Serviciul de analiză nu este disponibil momentan. Încearcă din nou.', 0, $e);
        }

        return $this->read($message, 'Analiza nu a putut fi finalizată. Încearcă cu altă poză.');
    }
}
