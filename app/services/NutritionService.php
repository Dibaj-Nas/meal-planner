<?php
declare(strict_types=1);

namespace App\Services;

/**
 * NutritionService — Calculs de coûts et d'apports nutritionnels
 * ==============================================================
 *
 * Un "service" contient de la logique métier pure (des calculs), sans
 * accès direct à la base. On lui passe des données, il renvoie un résultat.
 *
 * Deux calculs ici :
 *   - weeklyReport()          : bilan d'un menu de la semaine (coût + nutrition)
 *   - estimateFromIngredients(): estime coût/calories/protéines d'une recette
 */
class NutritionService
{
    /** Une semaine = 7 jours (utilisé pour les moyennes "par jour"). */
    private const DAYS_IN_WEEK = 7;
    /** Les 3 créneaux de repas d'une journée. */
    private const MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];

    /**
     * Calcule le bilan d'un menu hebdomadaire : coût total, coût par repas/
     * personne, économies par rapport au budget, calories et protéines.
     *
     * @param array $days    Les jours du menu (chacun contient ses 'meals').
     * @param int   $persons Nombre de convives (pour les ratios par personne).
     * @param float $budget  Budget visé (pour calculer l'écart / les économies).
     * @return array Un tableau de toutes les statistiques calculées.
     */
    public static function weeklyReport(array $days, int $persons = 2, float $budget = 0): array
    {
        // On accumule les totaux en parcourant chaque repas de chaque jour.
        $totalCost = 0.0;
        $totalCalories = 0.0;
        $totalProtein = 0.0;
        $mealsCount = 0;

        foreach ($days as $day) {
            // Le format peut venir en anglais ('meals') ou français ('repas').
            $meals = $day['meals'] ?? $day['repas'] ?? [];
            foreach (self::MEAL_TYPES as $type) {
                $recipe = $meals[$type] ?? null;
                if (!$recipe) continue;   // créneau vide → on saute
                $totalCost     += (float) ($recipe['estimated_cost'] ?? 0);
                $totalCalories += (float) ($recipe['calories'] ?? 0);
                $totalProtein  += (float) ($recipe['protein'] ?? 0);
                $mealsCount++;
            }
        }

        // Écart au budget : positif = économies, négatif = dépassement.
        $savings = $budget - $totalCost;
        return [
            'total_cost'              => round($totalCost, 2),
            'cost_per_meal'           => $mealsCount > 0 ? round($totalCost / $mealsCount, 2) : 0,
            'cost_per_person'         => $persons > 0 ? round($totalCost / $persons, 2) : $totalCost,
            'cost_per_person_per_day' => ($persons > 0) ? round($totalCost / $persons / self::DAYS_IN_WEEK, 2) : 0,
            'savings'                 => round(abs($savings), 2),
            'over_budget'             => $savings < 0,
            'budget'                  => $budget,
            'total_calories'          => (int) round($totalCalories),
            'calories_per_day'        => (int) round($totalCalories / self::DAYS_IN_WEEK),
            'total_protein'           => round($totalProtein, 1),
            'protein_per_day'         => round($totalProtein / self::DAYS_IN_WEEK, 1),
            'meals_count'             => $mealsCount,
            'days_count'              => count($days),
            'persons'                 => $persons,
        ];
    }

    /**
     * Estime le coût et les apports d'une recette à partir de ses ingrédients.
     *
     * Les valeurs nutritionnelles des ingrédients sont exprimées "pour 100 g".
     * On suppose un poids par ingrédient (200 g par défaut) et on convertit.
     *
     * @param array $ingredientObjects  Ingrédients (avec prix et nutriments).
     * @param float $weightPerIngGrams  Poids supposé de chaque ingrédient (g).
     * @return array ['estimated_cost', 'calories', 'protein'] avec des planchers.
     */
    public static function estimateFromIngredients(array $ingredientObjects, float $weightPerIngGrams = 200.0): array
    {
        $cost = 0.0;
        $calories = 0.0;
        $protein = 0.0;
        // factor = ratio entre le poids utilisé et les 100 g de référence.
        $factor = $weightPerIngGrams / 100.0;

        foreach ($ingredientObjects as $ing) {
            // Prix : exprimé par kg → on divise par 1000 pour passer aux grammes.
            $cost     += (float) ($ing['price_per_unit'] ?? 0) * ($weightPerIngGrams / 1000);
            $calories += (float) ($ing['calories_per_100g'] ?? 0) * $factor;
            $protein  += (float) ($ing['protein_per_100g'] ?? 0) * $factor;
        }

        // max(..., plancher) : on évite des valeurs irréalistement basses
        // (une recette coûte au moins 1,50 €, apporte au moins 300 kcal, etc.).
        return [
            'estimated_cost' => max(round($cost, 2), 1.50),
            'calories'       => max((int) round($calories), 300),
            'protein'        => max(round($protein, 1), 10.0),
        ];
    }
}
