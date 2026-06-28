-- ============================================================
-- cleanup_duplicates.sql — Supprime les doublons et ajoute des contraintes
-- Planificateur de Repas
--
-- Usage :
--   mysql -u root meal_planner < database/cleanup_duplicates.sql
--
-- Conserve la ligne la plus ancienne (id le plus petit) pour chaque doublon.
-- ============================================================

USE meal_planner;

-- ── Ingrédients en double (même utilisateur + même nom) ──
DELETE ri FROM recipe_ingredients ri
INNER JOIN ingredients i ON ri.ingredient_id = i.id
INNER JOIN ingredients dup ON dup.user_id = i.user_id
  AND dup.name = i.name
  AND dup.id < i.id;

DELETE i FROM ingredients i
INNER JOIN ingredients dup ON dup.user_id = i.user_id
  AND dup.name = i.name
  AND dup.id < i.id;

-- ── Recettes en double (même utilisateur + nom + type de repas) ──
DELETE mm FROM menu_meals mm
INNER JOIN recipes r ON mm.recipe_id = r.id
INNER JOIN recipes dup ON dup.user_id = r.user_id
  AND dup.name = r.name
  AND dup.meal_type = r.meal_type
  AND dup.id < r.id;

DELETE f FROM favorites f
INNER JOIN recipes r ON f.recipe_id = r.id
INNER JOIN recipes dup ON dup.user_id = r.user_id
  AND dup.name = r.name
  AND dup.meal_type = r.meal_type
  AND dup.id < r.id;

DELETE ri FROM recipe_ingredients ri
INNER JOIN recipes r ON ri.recipe_id = r.id
INNER JOIN recipes dup ON dup.user_id = r.user_id
  AND dup.name = r.name
  AND dup.meal_type = r.meal_type
  AND dup.id < r.id;

DELETE r FROM recipes r
INNER JOIN recipes dup ON dup.user_id = r.user_id
  AND dup.name = r.name
  AND dup.meal_type = r.meal_type
  AND dup.id < r.id;

-- ── Contraintes UNIQUE (ignore si déjà présentes) ──
SET @db = DATABASE();

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = @db AND table_name = 'ingredients' AND index_name = 'uk_ing_user_name') = 0,
  'ALTER TABLE ingredients ADD UNIQUE KEY uk_ing_user_name (user_id, name)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.statistics
   WHERE table_schema = @db AND table_name = 'recipes' AND index_name = 'uk_rec_user_name_type') = 0,
  'ALTER TABLE recipes ADD UNIQUE KEY uk_rec_user_name_type (user_id, name, meal_type)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Nettoyage terminé.' AS status;
