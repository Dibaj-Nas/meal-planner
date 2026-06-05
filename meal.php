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
