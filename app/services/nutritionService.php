<?php
declare(strict_types=1);

namespace App\Services;

class NutritionService
{
    private const DAYS_IN_WEEK = 7;
    private const MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];

    public static function weeklyReport(array $days, int $persons = 2, float $budget = 0): array
    {
        $totalCost = 0.0;
        $totalCalories = 0.0;
        $totalProtein = 0.0;
        $mealsCount = 0;

        foreach ($days as $day) {
            $meals = $day['meals'] ?? $day['repas'] ?? [];
            foreach (self::MEAL_TYPES as $type) {
                $recipe = $meals[$type] ?? null;
                if (!$recipe) continue;
                $totalCost     += (float) ($recipe['estimated_cost'] ?? 0);
                $totalCalories += (float) ($recipe['calories'] ?? 0);
                $totalProtein  += (float) ($recipe['protein'] ?? 0);
                $mealsCount++;
            }
        }

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

    public static function estimateFromIngredients(array $ingredientObjects, float $weightPerIngGrams = 200.0): array
    {
        $cost = 0.0;
        $calories = 0.0;
        $protein = 0.0;
        $factor = $weightPerIngGrams / 100.0;

        foreach ($ingredientObjects as $ing) {
            $cost     += (float) ($ing['price_per_unit'] ?? 0) * ($weightPerIngGrams / 1000);
            $calories += (float) ($ing['calories_per_100g'] ?? 0) * $factor;
            $protein  += (float) ($ing['protein_per_100g'] ?? 0) * $factor;
        }

        return [
            'estimated_cost' => max(round($cost, 2), 1.50),
            'calories'       => max((int) round($calories), 300),
            'protein'        => max(round($protein, 1), 10.0),
        ];
    }
}
