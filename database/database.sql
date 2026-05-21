-- Schéma base de données — Planificateur de Repas Hebdomadaires
-- ════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS meal_planner
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE meal_planner;

-- ─── Utilisateurs ──
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  firstname   VARCHAR(100)  NOT NULL,
  lastname    VARCHAR(100)  NOT NULL,
  email       VARCHAR(255)  NOT NULL,
  password    VARCHAR(255)  NOT NULL,          -- bcrypt hash
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Ingrédients ────
CREATE TABLE IF NOT EXISTS ingredients (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED    NOT NULL,
  name        VARCHAR(150)    NOT NULL,
  price       DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
  unit        ENUM('kg','g','L','piece') NOT NULL DEFAULT 'piece',
  calories    DECIMAL(8,2)    NOT NULL DEFAULT 0.00,  -- kcal/100g
  protein     DECIMAL(8,2)    NOT NULL DEFAULT 0.00,  -- g/100g
  category    ENUM('vegetables','fruits','meat','fish','dairy','grains','other')
              NOT NULL DEFAULT 'other',
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_ing_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_ing_user (user_id),
  INDEX idx_ing_category (user_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Recettes ───
CREATE TABLE IF NOT EXISTS recipes (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED    NOT NULL,
  name            VARCHAR(200)    NOT NULL,
  meal_type       ENUM('breakfast','lunch','dinner') NOT NULL DEFAULT 'dinner',
  prep_time       SMALLINT UNSIGNED NOT NULL DEFAULT 30,     -- minutes
  dietary         ENUM('all','vegetarian','vegan','no-pork') NOT NULL DEFAULT 'all',
  estimated_cost  DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
  calories        DECIMAL(8,2)    NOT NULL DEFAULT 0.00,     -- kcal / portion
  protein         DECIMAL(8,2)    NOT NULL DEFAULT 0.00,     -- g   / portion
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_rec_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_rec_user      (user_id),
  INDEX idx_rec_meal_type (user_id, meal_type),
  INDEX idx_rec_dietary   (user_id, dietary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Ingrédients d'une recette ──────
CREATE TABLE IF NOT EXISTS recipe_ingredients (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  recipe_id       INT UNSIGNED  NOT NULL,
  ingredient_id   INT UNSIGNED  NULL,          -- NULL si ingrédient libre
  ingredient_name VARCHAR(150)  NOT NULL,
  quantity        DECIMAL(8,2)  NOT NULL DEFAULT 1.00,
  unit            VARCHAR(30)   NOT NULL DEFAULT 'piece',
  PRIMARY KEY (id),
  UNIQUE KEY uk_ri (recipe_id, ingredient_name),
  FOREIGN KEY fk_ri_recipe (recipe_id)
    REFERENCES recipes(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY fk_ri_ingredient (ingredient_id)
    REFERENCES ingredients(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Menus hebdomadaires ────
CREATE TABLE IF NOT EXISTS weekly_menus (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED    NOT NULL,
  week_start  DATE            NOT NULL,
  budget      DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
  persons     TINYINT UNSIGNED NOT NULL DEFAULT 2,
  dietary     ENUM('all','vegetarian','vegan','no-pork') NOT NULL DEFAULT 'all',
  total_cost  DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_wm_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_wm_user_week (user_id, week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Repas dans un menu ────
CREATE TABLE IF NOT EXISTS menu_meals (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  menu_id     INT UNSIGNED    NOT NULL,
  day_index   TINYINT UNSIGNED NOT NULL,       -- 0 = Lundi … 6 = Dimanche
  meal_type   ENUM('breakfast','lunch','dinner') NOT NULL,
  recipe_id   INT UNSIGNED    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_mm_slot (menu_id, day_index, meal_type),
  FOREIGN KEY fk_mm_menu (menu_id)
    REFERENCES weekly_menus(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY fk_mm_recipe (recipe_id)
    REFERENCES recipes(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_mm_menu (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Listes de courses ──────
CREATE TABLE IF NOT EXISTS shopping_lists (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  menu_id     INT UNSIGNED  NOT NULL,
  user_id     INT UNSIGNED  NOT NULL,
  item_name   VARCHAR(150)  NOT NULL,
  quantity    DECIMAL(8,2)  NOT NULL DEFAULT 1.00,
  unit        VARCHAR(30)   NOT NULL DEFAULT 'piece',
  estimated_price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  is_bought   TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  FOREIGN KEY fk_sl_menu (menu_id)
    REFERENCES weekly_menus(id) ON DELETE CASCADE,
  FOREIGN KEY fk_sl_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_sl_menu (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- VUES --

-- Vue : recette enrichie avec liste d'ingrédients
CREATE OR REPLACE VIEW recipe_summary AS
SELECT
  r.id,
  r.user_id,
  r.name,
  r.meal_type,
  r.prep_time,
  r.dietary,
  r.estimated_cost,
  r.calories,
  r.protein,
  GROUP_CONCAT(
    ri.ingredient_name
    ORDER BY ri.ingredient_name
    SEPARATOR ', '
  )                       AS ingredients_list,
  r.created_at
FROM   recipes           r
LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
GROUP BY r.id;

-- Vue : statistiques par utilisateur
CREATE OR REPLACE VIEW user_statistics AS
SELECT
  u.id          AS user_id,
  u.firstname,
  u.lastname,
  u.email,
  COUNT(DISTINCT i.id)  AS ingredient_count,
  COUNT(DISTINCT r.id)  AS recipe_count,
  COUNT(DISTINCT m.id)  AS menu_count
FROM users          u
LEFT JOIN ingredients   i ON u.id = i.user_id
LEFT JOIN recipes       r ON u.id = r.user_id
LEFT JOIN weekly_menus  m ON u.id = m.user_id
GROUP BY u.id;


-- PROCÉDURES STOCKÉES

DELIMITER $$. --La commande DELIMITER $$ dans MySQL est une instruction spécifique au client (comme le client en ligne de commande mysql) qui modifie le séparateur de requêtes par défaut.

-- Génère un menu aléatoire pour un utilisateur donné
DROP PROCEDURE IF EXISTS generate_random_menu$$
CREATE PROCEDURE generate_random_menu(
  IN  p_user_id  INT UNSIGNED,
  IN  p_budget   DECIMAL(8,2),
  IN  p_persons  TINYINT UNSIGNED,
  IN  p_dietary  VARCHAR(20),
  OUT p_menu_id  INT UNSIGNED
)
BEGIN
  DECLARE v_week_start  DATE;
  DECLARE v_total_cost  DECIMAL(8,2) DEFAULT 0;
  DECLARE v_day         TINYINT      DEFAULT 0;
  DECLARE v_recipe_id   INT UNSIGNED DEFAULT NULL;
  DECLARE v_cost        DECIMAL(8,2) DEFAULT 0;

  -- Lundi de la semaine courante
  SET v_week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);

  -- Crée l'en-tête du menu
  INSERT INTO weekly_menus (user_id, week_start, budget, persons, dietary)
  VALUES (p_user_id, v_week_start, p_budget, p_persons, p_dietary);
  SET p_menu_id = LAST_INSERT_ID();

  -- Boucle : 7 jours × 3 créneaux
  WHILE v_day < 7 DO

    -- ── Petit-déjeuner ──────────────────────────────────────────
    SET v_recipe_id = NULL;
    SELECT id, estimated_cost INTO v_recipe_id, v_cost
    FROM   recipes
    WHERE  user_id  = p_user_id
      AND  meal_type = 'breakfast'
      AND  (p_dietary = 'all' OR dietary = p_dietary OR dietary = 'all')
    ORDER BY RAND()
    LIMIT 1;

    IF v_recipe_id IS NOT NULL THEN
      INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
      VALUES (p_menu_id, v_day, 'breakfast', v_recipe_id);
      SET v_total_cost = v_total_cost + IFNULL(v_cost, 0);
    END IF;

    -- ── Déjeuner ───
    SET v_recipe_id = NULL;
    SELECT id, estimated_cost INTO v_recipe_id, v_cost
    FROM   recipes
    WHERE  user_id  = p_user_id
      AND  meal_type = 'lunch'
      AND  (p_dietary = 'all' OR dietary = p_dietary OR dietary = 'all')
    ORDER BY RAND()
    LIMIT 1;

    IF v_recipe_id IS NOT NULL THEN
      INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
      VALUES (p_menu_id, v_day, 'lunch', v_recipe_id);
      SET v_total_cost = v_total_cost + IFNULL(v_cost, 0);
    END IF;

    -- ── Dîner ───
    SET v_recipe_id = NULL;
    SELECT id, estimated_cost INTO v_recipe_id, v_cost
    FROM   recipes
    WHERE  user_id  = p_user_id
      AND  meal_type = 'dinner'
      AND  (p_dietary = 'all' OR dietary = p_dietary OR dietary = 'all')
    ORDER BY RAND()
    LIMIT 1;

    IF v_recipe_id IS NOT NULL THEN
      INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
      VALUES (p_menu_id, v_day, 'dinner', v_recipe_id);
      SET v_total_cost = v_total_cost + IFNULL(v_cost, 0);
    END IF;

    SET v_day = v_day + 1;
  END WHILE;

  -- Met à jour le coût total
  UPDATE weekly_menus SET total_cost = v_total_cost WHERE id = p_menu_id;
END$$

-- Génère la liste de courses d'un menu
DROP PROCEDURE IF EXISTS generate_shopping_list$$
CREATE PROCEDURE generate_shopping_list(IN p_menu_id INT UNSIGNED)
BEGIN
  DECLARE v_user_id INT UNSIGNED;
  SELECT user_id INTO v_user_id FROM weekly_menus WHERE id = p_menu_id;

  -- Supprime l'ancienne liste
  DELETE FROM shopping_lists WHERE menu_id = p_menu_id;

  -- Insère les ingrédients de toutes les recettes du menu
  INSERT INTO shopping_lists (menu_id, user_id, item_name, quantity, unit, estimated_price)
  SELECT
    p_menu_id,
    v_user_id,
    ri.ingredient_name,
    SUM(IFNULL(ri.quantity, 1))                          AS total_qty,
    MAX(ri.unit)                                          AS unit,
    SUM(IFNULL(ri.quantity, 1)) * IFNULL(MAX(i.price), 1) AS estimated_price
  FROM  menu_meals mm
  JOIN  recipes             r  ON mm.recipe_id  = r.id
  JOIN  recipe_ingredients  ri ON ri.recipe_id  = r.id
  LEFT JOIN ingredients     i  ON ri.ingredient_id = i.id
  WHERE mm.menu_id = p_menu_id
  GROUP BY ri.ingredient_name;
END$$

DELIMITER ;


-- ════════════════════════════════════════════════════════════════
-- TRIGGERS
-- ════════════════════════════════════════════════════════════════

DELIMITER $$

-- Recalcule le coût total du menu après chaque insertion de repas
DROP TRIGGER IF EXISTS trg_after_meal_insert$$
CREATE TRIGGER trg_after_meal_insert
AFTER INSERT ON menu_meals
FOR EACH ROW
BEGIN
  UPDATE weekly_menus wm
  SET    total_cost = (
    SELECT COALESCE(SUM(r.estimated_cost), 0)
    FROM   menu_meals mm
    JOIN   recipes    r  ON mm.recipe_id = r.id
    WHERE  mm.menu_id = NEW.menu_id
  )
  WHERE  wm.id = NEW.menu_id;
END$$

-- Recalcule après modification d'un repas
DROP TRIGGER IF EXISTS trg_after_meal_update$$
CREATE TRIGGER trg_after_meal_update
AFTER UPDATE ON menu_meals
FOR EACH ROW
BEGIN
  UPDATE weekly_menus wm
  SET    total_cost = (
    SELECT COALESCE(SUM(r.estimated_cost), 0)
    FROM   menu_meals mm
    JOIN   recipes    r  ON mm.recipe_id = r.id
    WHERE  mm.menu_id = NEW.menu_id
  )
  WHERE  wm.id = NEW.menu_id;
END$$

DELIMITER ;



-- DONNÉES DE DÉMONSTRATION


-- Utilisateur démo (mot de passe en clair : Demo1234!)
-- Hash bcrypt généré par : password_hash('Demo1234!', PASSWORD_BCRYPT)
INSERT IGNORE INTO users (firstname, lastname, email, password) VALUES
('Démo', 'Utilisateur', 'demo@mealplanner.fr',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

SET @uid = (SELECT id FROM users WHERE email = 'demo@mealplanner.fr');

-- Ingrédients de base
INSERT IGNORE INTO ingredients (user_id, name, price, unit, calories, protein, category) VALUES
(@uid, 'Poulet (filet)',    8.50, 'kg',     165.0, 31.0, 'meat'),
(@uid, 'Saumon',           14.00, 'kg',     208.0, 20.0, 'fish'),
(@uid, 'Tomates',           2.00, 'kg',      18.0,  0.9, 'vegetables'),
(@uid, 'Pommes de terre',   1.50, 'kg',      77.0,  2.0, 'vegetables'),
(@uid, 'Carottes',          1.00, 'kg',      41.0,  0.9, 'vegetables'),
(@uid, 'Oignons',           0.90, 'kg',      40.0,  1.1, 'vegetables'),
(@uid, 'Ail',               1.20, 'piece',   149.0,  6.4, 'vegetables'),
(@uid, 'Épinards',          2.50, 'kg',      23.0,  2.9, 'vegetables'),
(@uid, 'Œufs (x6)',         2.80, 'piece',   155.0, 13.0, 'dairy'),
(@uid, 'Lait (1L)',         1.20, 'L',        42.0,  3.4, 'dairy'),
(@uid, 'Fromage râpé',      3.50, 'piece',   400.0, 26.0, 'dairy'),
(@uid, 'Yaourt nature',     0.70, 'piece',    59.0,  3.8, 'dairy'),
(@uid, 'Pain de mie',       2.20, 'piece',   265.0,  9.0, 'grains'),
(@uid, 'Riz (500g)',        1.90, 'piece',   130.0,  2.7, 'grains'),
(@uid, 'Pâtes (500g)',      1.50, 'piece',   158.0,  5.5, 'grains'),
(@uid, 'Flocons d'avoine', 2.00, 'piece',   379.0, 13.0, 'grains'),
(@uid, 'Pommes',            2.50, 'kg',       52.0,  0.3, 'fruits'),
(@uid, 'Bananes',           1.80, 'kg',       89.0,  1.1, 'fruits'),
(@uid, 'Oranges',           2.00, 'kg',       47.0,  0.9, 'fruits');

-- Recettes de démonstration
INSERT IGNORE INTO recipes
  (user_id, name, meal_type, prep_time, dietary, estimated_cost, calories, protein)
VALUES
-- Petits-déjeuners
(@uid, 'Tartines beurre-confiture', 'breakfast',  5, 'vegetarian', 1.20,  310.0,  8.0),
(@uid, 'Œufs brouillés aux herbes','breakfast', 10, 'vegetarian', 2.40,  280.0, 18.0),
(@uid, 'Porridge aux fruits',       'breakfast', 10, 'vegan',      1.80,  360.0, 10.0),
(@uid, 'Yaourt granola fruits',     'breakfast',  5, 'vegetarian', 1.60,  320.0, 12.0),
(@uid, 'Pancakes maison',           'breakfast', 20, 'vegetarian', 2.20,  400.0, 11.0),
-- Déjeuners
(@uid, 'Salade niçoise',            'lunch',     15, 'no-pork',    5.50,  380.0, 22.0),
(@uid, 'Riz aux légumes sautés',    'lunch',     25, 'vegan',      3.20,  420.0, 10.0),
(@uid, 'Sandwich poulet-crudités',  'lunch',     10, 'all',        4.20,  450.0, 30.0),
(@uid, 'Soupe de légumes',          'lunch',     30, 'vegan',      2.80,  200.0,  6.0),
(@uid, 'Quiche lorraine',           'lunch',     45, 'vegetarian', 3.80,  480.0, 18.0),
-- Dîners
(@uid, 'Poulet rôti aux légumes',   'dinner',    60, 'all',        6.50,  520.0, 38.0),
(@uid, 'Pâtes tomate-basilic',      'dinner',    20, 'vegan',      2.80,  460.0, 13.0),
(@uid, 'Saumon en papillote',       'dinner',    30, 'no-pork',    8.00,  480.0, 34.0),
(@uid, 'Omelette champignons',      'dinner',    15, 'vegetarian', 3.40,  320.0, 20.0),
(@uid, 'Gratin dauphinois',         'dinner',    70, 'vegetarian', 4.20,  520.0, 15.0);

-- Lie les ingrédients aux recettes (exemples)
SET @r1 = (SELECT id FROM recipes WHERE user_id = @uid AND name = 'Poulet rôti aux légumes');
INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(@r1, 'Poulet (filet)',   500, 'g'),
(@r1, 'Pommes de terre',  600, 'g'),
(@r1, 'Carottes',         300, 'g'),
(@r1, 'Oignons',          150, 'g');

SET @r2 = (SELECT id FROM recipes WHERE user_id = @uid AND name = 'Pâtes tomate-basilic');
INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(@r2, 'Pâtes (500g)',     250, 'g'),
(@r2, 'Tomates',          400, 'g'),
(@r2, 'Ail',               2, 'piece');