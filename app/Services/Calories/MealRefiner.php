<?php

namespace App\Services\Calories;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Services\Anthropic\Models;

/**
 * Recalculates a meal that was already estimated (photo, barcode, text or manual)
 * after the user points out what is wrong with it.
 */
class MealRefiner
{
    use ReadsFoodAnalysis;

    private const SYSTEM_PROMPT = <<<'TXT'
Ești un nutriționist care corectează estimarea nutrițională a unei mese. Primești lista curentă de
alimente (JSON, cu porția în grame și valorile pentru porția respectivă), eventual notițele de la
estimarea inițială, și o remarcă a utilizatorului despre ce nu e corect.
Aplică remarca și întoarce lista completă, actualizată: modifică, adaugă sau elimină alimente după
cum cere remarca (ex: „a fost cu smântână” adaugă smântână, „am mâncat doar jumătate” înjumătățește
porțiile, „nu e orez, e cuscus” înlocuiește alimentul, „pâinea era integrală” recalculează valorile).
Recalculează caloriile și macronutrienții realist pentru fiecare aliment modificat. Alimentele la care
remarca nu se referă rămân neschimbate, cu aceleași nume și valori. Remarca utilizatorului are
prioritate față de estimarea inițială. Numele alimentelor se scriu în limba română.
Pentru fiecare aliment completează pieces cu numărul de bucăți (1 dacă nu se numără firesc în bucăți);
portion_grams rămâne greutatea totală pentru toate bucățile la un loc.
Setează is_food=false și lasă items gol doar dacă remarca spune clar că nu a fost mâncare.
În notes rezumă pe scurt ce ai schimbat, maxim două propoziții.
TXT;

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{
     *   is_food: bool,
     *   items: list<array{name: string, portion_grams: float, pieces: float, calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float}>,
     *   totals: array{calories: float, protein_g: float, carbs_g: float, fat_g: float, fiber_g: float},
     *   confidence: string,
     *   notes: string
     * }
     */
    public function refine(array $items, string $remark, ?string $notes = null): array
    {
        $prompt = "Masa curentă:\n".json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($notes) {
            $prompt .= "\n\nNotițe de la estimarea inițială: {$notes}";
        }

        $prompt .= "\n\nRemarca utilizatorului: {$remark}";

        try {
            $message = $this->client->messages->create(
                model: $this->model,
                maxTokens: 4000,
                system: self::SYSTEM_PROMPT,
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
                outputConfig: Models::outputConfig($this->model, 'medium', $this->schema()),
            );
        } catch (APIException $e) {
            report($e);

            throw new FoodAnalysisException('Serviciul de analiză nu este disponibil momentan. Încearcă din nou.', 0, $e);
        }

        return $this->read($message, 'Recalcularea nu a putut fi finalizată. Încearcă să reformulezi remarca.');
    }
}
