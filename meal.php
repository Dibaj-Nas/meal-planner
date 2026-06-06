<?php

namespace 

use PDO;

class Meal
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Récupère toutes les recettes
    public function getAllRecettes(): array
    {
        $sql = "SELECT * FROM recette ORDER BY nom ASC";
        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupère une recette par son id
    public function getRecetteById(int $id): ?array
    {
        $sql = "SELECT * FROM recette WHERE id_recette = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id
        ]);

        $recette = $stmt->fetch(PDO::FETCH_ASSOC);

        return $recette ?: null;
    }

    // Récupère les ingrédients d'une recette
    public function getIngredientsByRecette(int $idRecette): array
    {
        $sql = "
            SELECT 
                ingredients.nom,
                ingredients.prix_unitaire,
                ingredients.unite_mesure,
                ingredients.calories_par_unite,
                ingredients.proteines_par_unite,
                ingredients.glucides_par_unite,
                ingredients.lipides_par_unite,
                recette_ingredient.quantite,
                recette_ingredient.unite
            FROM recette_ingredient
            INNER JOIN ingredients 
                ON ingredients.id_ingredient = recette_ingredient.id_ingredient
            WHERE recette_ingredient.id_recette = :id_recette
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_recette' => $idRecette
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crée un menu hebdomadaire
    public function createMenu(int $idUtilisateur, string $semaine, float $budget): int
    {
        $sql = "
            INSERT INTO menu_hebdomadaire (id_utilisateur, semaine, budget)
            VALUES (:id_utilisateur, :semaine, :budget)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_utilisateur' => $idUtilisateur,
            'semaine' => $semaine,
            'budget' => $budget
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Ajoute un repas dans un menu
    public function addRepas(int $idMenu, string $jour, string $typeRepas): int
    {
        $sql = "
            INSERT INTO repas (id_menu, jour, type_repas)
            VALUES (:id_menu, :jour, :type_repas)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_menu' => $idMenu,
            'jour' => $jour,
            'type_repas' => $typeRepas
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Associe une recette à un repas
    public function addRecetteToRepas(int $idRepas, int $idRecette): bool
    {
        $sql = "
            INSERT INTO repas_recette (id_repas, id_recette)
            VALUES (:id_repas, :id_recette)
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id_repas' => $idRepas,
            'id_recette' => $idRecette
        ]);
    }

     // Récupère les repas d'un menu
    public function getRepasByMenu(int $idMenu): array
    {
        $sql = "
            SELECT *
            FROM repas
            WHERE id_menu = :id_menu
            ORDER BY jour ASC, type_repas ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_menu' => $idMenu
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupère un menu complet avec ses repas et recettes
    public function getMenuComplet(int $idMenu): array
    {
        $sql = "
            SELECT 
                repas.jour,
                repas.type_repas,
                recette.id_recette,
                recette.nom,
                recette.description,
                recette.temps_preparation,
                recette.temps_cuisson,
                recette.nombre_personnes,
                recette.difficulte,
                recette.saison
            FROM repas
            INNER JOIN repas_recette 
                ON repas.id_repas = repas_recette.id_repas
            INNER JOIN recette 
                ON recette.id_recette = repas_recette.id_recette
            WHERE repas.id_menu = :id_menu
            ORDER BY repas.jour ASC, repas.type_repas ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_menu' => $idMenu
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}