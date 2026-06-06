<?php 
namespace app\services;

/**
 * NutritionService.php - calculs nutritionnels
 * 
 * les calculs de : 
 *  - calories. et protéines totales / journalières 
 *  - côut total, par repas, par personne
 *  - estimation des apports à partir des ingrédients bruts
 */

class NutritionService
{
    // nombre de jours dans un menu hebdomadire
    private const DAYS_IN_WEEK = 7;

    // types de repas à analyser
    private const MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];

    /** repport hebdomadaire 
     * 
     * calcule un repport nutritionnel et financier complet pour un menu de 7 jours.
    */
    public static function weeklyReport(array $days, int $persons = 2, float $budget = 0): array
    {
        $totalCost     = 0.0;
        $totalCalories = 0.0;
        $totalProtein  = 0.0;
        $mealsCount    = 0;

        foreach ($days as $day) {
            $meals = $day['meals'] ?? [];

            foreach (self::MEAL_TYPES as $type) {
                $recipe = $meals[$type] ?? null;
                if (!$recipe) {
                    continue;
                }

                $totalCost     += (float) ($recipe['estimated_cost'] ?? 0);
                $totalCalories += (float) ($recipe['calories']       ?? 0);
                $totalProtein  += (float) ($recipe['protein']        ?? 0);
                $mealsCount++;
            }
        }

        $savings    = $budget - $totalCost;
        $overBudget = $savings < 0;

        return [
            /* finances */
            'total_cost'               => round($totalCost, 2),
            'cost_per_meal'            => $mealsCount > 0  ? round($totalCost / $mealsCount, 2) : 0,
            'cost_per_person'          => $persons > 0     ? round($totalCost / $persons, 2) : $totalCost,
            'cost_per_person_per_day'  => ($persons > 0 && self::DAYS_IN_WEEK > 0)
                                            ? round($totalCost / $persons / self::DAYS_IN_WEEK, 2)
                                            : 0,
            'savings'                  => round(abs($savings), 2),
            'over_budget'              => $overBudget,
            'budget'                   => $budget,

            /* nutrition */
            'total_calories'           => (int) round($totalCalories),
            'calories_per_day'         => (int) round($totalCalories / self::DAYS_IN_WEEK),
            'total_protein'            => round($totalProtein, 1),
            'protein_per_day'          => round($totalProtein / self::DAYS_IN_WEEK, 1),

            /* méta */
            'meals_count'              => $mealsCount,
            'days_count'               => count($days),
            'persons'                  => $persons,
        ];
    }

    /**
     * estimation depuis les ingrédients 
     * 
     * estime les apports nutritionnels et le côut d'une recette à partir de la liste de ses ingrédients.
     */
    public static function estimateFromIngredients(
        array $ingredientObjects,
        float $weightPerIngGrams = 200.0
    ): array {
        $cost = 0.0;
        $calories = 0.0;
        $protein = 0.0;
        $factor = $weightPerIngGrams / 100.0; // pour convertir "per 100g" -> quantity réelle

        foreach ($ingredientObjects as $ing) {
            /* coût : on suppose ~weightPerIngGrams / 1000 kg ou unité */
            $cost     += (float) ($ing['price_per_unit'] ?? 0) * ($weightPerIngGrams / 1000);
            $calories += (float) ($ing['calories_per_100g'] ?? 0) * $factor;
            $protein  += (float) ($ing['protein_per_100g']  ?? 0) * $factor;
        }

        return [
            'estimated_cost' => max(round($cost, 2), 1.50),   // minimum 1,50 €
            'calories'       => max((int) round($calories), 300), // minimum 300 kcal
            'protein'        => max(round($protein, 1), 10.0),   // minimum 10g
        ];
    }
    
    /**
     * apports journaliers recommandés (références)
     * 
     * retourne les apports journaliers recommandés pour une perssone adulte.
     * 
     * source : ANSES (agence nationale de sécurité sanitaire)
     */
    public static function dailyRecommendations(): array
    {
        return [
            'calories' => [
                'man'   => 2500, // kcal/jour homme actif
                'woman' => 2000, // kcal/jour femme active
                'label' => 'kcal/jour',
            ],
            'protein' => [
                'value' => 0.83, // g/kg de poids corporel
                'label' => 'g/kg/jour',
            ],
        ];
    }
}