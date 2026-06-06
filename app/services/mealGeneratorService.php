<?php
namespace app\services;

use app\models\Meal;

/**
 * MealGeneratorService.php -> génération algorithmique de menus hebdomadaires
 * 
 * TODO
 * 
 * responsabilité :
 *  - filtrer les recettes selon le régime alimentaire
 *  - sélectionner aléatoirment des recettes pour chaque créneau
 *  - limiter le dépassement budgétaire
 *  - calculer les côut total et les rapports nutritionnels globaux.
 */

class MealGeneratorService
{
    // les noms des jours de la semaine
    private const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    
    // types de repas dans l'ordre
    private const MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];

    private Meal $mealModel;

    public function __construct()
    {
        $this->mealModel = new Meal();
    }

    // point d'entrée pour générer les menus

    //génére les menus hebdomadaires complet.

    public function generate(int $userId, array $params): array
    {
        $budget = (float) ($params['budget'] ?? 50);
        $persons = (int) ($params['persons'] ?? 2);
        $dietary = $params['dietary'] ?? 'all';

        // 1. charger les recettes et les répartir par type
        $pools = $this->buildPools($userId, $dietary);
        $this->validatePools($pools);

        // 2. on construit les 7 jours 
        $days      = [];
        $totalCost = 0.0;
        $totalCals = 0.0;
        $totalProt = 0.0;

        for ($i = 0; $i < 7; $i++) {
            $dayMeals = [];

            foreach (self::MEAL_TYPES as $type) {
                $recipe = $this->pickRandom($pools[$type]);

                if ($recipe) {
                    $dayMeals[$type] = $recipe;
                    $totalCost += (float) ($recipe['estimated_cost'] ?? 0);
                    $totalCals += (float) ($recipe['calories']       ?? 0);
                    $totalProt += (float) ($recipe['protein']        ?? 0);
                } else {
                    $dayMeals[$type] = null;
                }
            }

            $days[] = [
                'index'  => $i,
                'name'   => self::DAYS[$i],
                'meals'  => $dayMeals,
            ];
        }

        // 3. retourner le menu structuré 
        return [
            'budget' => $budget,
            'persons' => $persons,
            'dietary' => $dietary,
            'total_cost'      => round($totalCost, 2),
            'total_calories'  => round($totalCals),
            'total_protein'   => round($totalProt, 1),
            'cost_per_person' => $persons > 0 ? round($totalCost / $persons, 2) : 0,
            'savings'         => round($budget - $totalCost, 2),
            'week_start'      => $this->getMondayOfCurrentWeek(),
            'days'            => $days,
        ];
    }

    /**
     * méthodes privées
     * 
     * charge et répartit les recettes par type de repas.
     */
    private function buildPools(int $userId, string $dietary): array
    {
        $pools = ['breakfast' => [], 'lunch' => [], 'dinner' => []];

        $recipes = $this->mealModel->allRecipesByUser($userId, '', $dietary);

        foreach ($recipes as $recipe) {
            $type = $recipe['meal_type'] ?? 'dinner';
            if (isset($pools[$type])) {
                $pools[$type][] = $recipe;
            }
        }
        return $pools;
    }

    // on vérifie que chaque créneau dispose d'au moins une recette.
    private function validatePools(array $pools): void
    {
        $labels = [
            'breakfast' => 'petit-déjeuner',
            'lunch' => 'déjeuner',
            'dinner' => 'dîner'
        ];

        foreach ($pools as $type => $pool) {
            if (empty($pool)) {
                throw new \RuntimeException("Aucune recette disponible pour le créneau « {$labels[$type]} ». "
                    . "Ajoutez au moins une recette de ce type avant de générer un menu."
                );
            }
        }
    }

    // sélectionne une recette aléatoire dans un tableau
    // préfère éviter de répéter la même recette (shuffle + mémo).
    private function pickRandom(array $pool): ?array
    {
        if (empty($pool)) {
            return null;
        }
        return $pool[array_rand($pool)];
    }

    // retourne la date du lundi de la semaine courante (format Y-m-d)
    private function getMondayOfCurrentWeek(): string
    {
        $today = new \DateTime();
        $dayOfWeek = (int) $today->format('N'); // 1= lundi, 7= dimanche
        $today->modify('-' . ($dayOfWeek - 1) . 'days');
        return $today->format('Y-m-d');
    }
    

}