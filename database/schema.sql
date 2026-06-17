-- ============================================================
-- schema.sql — Planificateur de Repas Hebdomadaires
-- Encoding : utf8mb4 | Engine : InnoDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS meal_planner
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE meal_planner;

-- ────────────────────────────────────────────────────────────
-- TABLES
-- ────────────────────────────────────────────────────────────

-- Utilisateurs
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  firstname   VARCHAR(100)  NOT NULL,
  lastname    VARCHAR(100)  NOT NULL,
  email       VARCHAR(255)  NOT NULL,
  password    VARCHAR(255)  NOT NULL,          -- bcrypt hash
  email_verified TINYINT(1) NOT NULL DEFAULT 0, -- 0 = adresse non confirmée, 1 = confirmée
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons de vérification d'e-mail (lien de confirmation envoyé à l'inscription)
CREATE TABLE IF NOT EXISTS email_verifications (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED  NOT NULL,
  token      VARCHAR(255)  NOT NULL,             -- hash SHA-256 du token (jamais en clair)
  expires_at TIMESTAMP     NOT NULL,             -- validité (24 h)
  used       TINYINT(1)    NOT NULL DEFAULT 0,   -- 1 une fois le lien cliqué
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_ev_token (token),
  FOREIGN KEY fk_ev_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_ev_token (token),
  INDEX idx_ev_user  (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paramètres utilisateur
CREATE TABLE IF NOT EXISTS user_settings (
  user_id           INT UNSIGNED  NOT NULL,
  default_persons   TINYINT UNSIGNED NOT NULL DEFAULT 2,
  default_dietary   ENUM('all','vegetarian','vegan','no-pork') NOT NULL DEFAULT 'all',
  default_budget    DECIMAL(8,2)  NOT NULL DEFAULT 100.00,
  PRIMARY KEY (user_id),
  FOREIGN KEY fk_us_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingrédients
CREATE TABLE IF NOT EXISTS ingredients (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED    NOT NULL,
  name        VARCHAR(150)    NOT NULL,
  price       DECIMAL(8,2)    NOT NULL DEFAULT 0.00,   -- prix unitaire
  unit        ENUM('kg','g','L','piece') NOT NULL DEFAULT 'piece',
  calories    DECIMAL(8,2)    NOT NULL DEFAULT 0.00,   -- kcal / 100 g
  protein     DECIMAL(8,2)    NOT NULL DEFAULT 0.00,   -- g    / 100 g
  category    ENUM('vegetables','fruits','meat','fish','dairy','grains','other')
              NOT NULL DEFAULT 'other',
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_ing_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_ing_user     (user_id),
  INDEX idx_ing_category (user_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recettes
CREATE TABLE IF NOT EXISTS recipes (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED    NOT NULL,
  name            VARCHAR(200)    NOT NULL,
  meal_type       ENUM('breakfast','lunch','dinner') NOT NULL DEFAULT 'dinner',
  prep_time       SMALLINT UNSIGNED NOT NULL DEFAULT 30,   -- minutes
  dietary         ENUM('all','vegetarian','vegan','no-pork') NOT NULL DEFAULT 'all',
  estimated_cost  DECIMAL(8,2)    NOT NULL DEFAULT 0.00,   -- € / portion
  calories        DECIMAL(8,2)    NOT NULL DEFAULT 0.00,   -- kcal / portion
  protein         DECIMAL(8,2)    NOT NULL DEFAULT 0.00,   -- g   / portion
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_rec_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_rec_user      (user_id),
  INDEX idx_rec_meal_type (user_id, meal_type),
  INDEX idx_rec_dietary   (user_id, dietary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingrédients d'une recette
CREATE TABLE IF NOT EXISTS recipe_ingredients (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  recipe_id       INT UNSIGNED  NOT NULL,
  ingredient_id   INT UNSIGNED  NULL,          -- NULL si ingrédient libre (texte)
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

-- Recettes favorites
CREATE TABLE IF NOT EXISTS favorites (
  user_id    INT UNSIGNED NOT NULL,
  recipe_id  INT UNSIGNED NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, recipe_id),
  FOREIGN KEY fk_fav_user   (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY fk_fav_recipe (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réinitialisation de mot de passe
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED  NOT NULL,
  token      VARCHAR(255)  NOT NULL,
  expires_at TIMESTAMP     NOT NULL,
  used       TINYINT(1)    NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_token (token),
  FOREIGN KEY fk_pr_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_pr_token   (token),
  INDEX idx_pr_user    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menus hebdomadaires
CREATE TABLE IF NOT EXISTS weekly_menus (
  id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED     NOT NULL,
  week_start  DATE             NOT NULL,              -- lundi de la semaine
  budget      DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
  persons     TINYINT UNSIGNED NOT NULL DEFAULT 2,
  dietary     ENUM('all','vegetarian','vegan','no-pork') NOT NULL DEFAULT 'all',
  total_cost  DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
  created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_wm_user (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_wm_user_week (user_id, week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repas dans un menu (7 jours × 3 créneaux = 21 slots)
CREATE TABLE IF NOT EXISTS menu_meals (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_id     INT UNSIGNED NOT NULL,
  day_index   TINYINT UNSIGNED NOT NULL,              -- 0 = Lundi … 6 = Dimanche
  meal_type   ENUM('breakfast','lunch','dinner') NOT NULL,
  recipe_id   INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_mm_slot (menu_id, day_index, meal_type),
  FOREIGN KEY fk_mm_menu   (menu_id)    REFERENCES weekly_menus(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY fk_mm_recipe (recipe_id)  REFERENCES recipes(id)      ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_mm_menu (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listes de courses
CREATE TABLE IF NOT EXISTS shopping_lists (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  menu_id         INT UNSIGNED  NOT NULL,
  user_id         INT UNSIGNED  NOT NULL,
  item_name       VARCHAR(150)  NOT NULL,
  quantity        DECIMAL(8,2)  NOT NULL DEFAULT 1.00,
  unit            VARCHAR(30)   NOT NULL DEFAULT 'piece',
  estimated_price DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  is_bought       TINYINT(1)    NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  FOREIGN KEY fk_sl_menu (menu_id) REFERENCES weekly_menus(id) ON DELETE CASCADE,
  FOREIGN KEY fk_sl_user (user_id) REFERENCES users(id)        ON DELETE CASCADE,
  INDEX idx_sl_menu (menu_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────
-- VUES
-- ────────────────────────────────────────────────────────────

-- Recette enrichie avec liste d'ingrédients
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
  ) AS ingredients_list,
  r.created_at
FROM   recipes r
LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
GROUP  BY r.id;

-- Statistiques par utilisateur
CREATE OR REPLACE VIEW user_statistics AS
SELECT
  u.id        AS user_id,
  u.firstname,
  u.lastname,
  u.email,
  COUNT(DISTINCT i.id) AS ingredient_count,
  COUNT(DISTINCT r.id) AS recipe_count,
  COUNT(DISTINCT m.id) AS menu_count
FROM users          u
LEFT JOIN ingredients   i ON u.id = i.user_id
LEFT JOIN recipes       r ON u.id = r.user_id
LEFT JOIN weekly_menus  m ON u.id = m.user_id
GROUP  BY u.id;


-- ────────────────────────────────────────────────────────────
-- DONNÉES DE SEED
-- Compte système "défaut" (user_id = 1) qui porte les recettes
-- et ingrédients pré-chargés accessibles à tous les nouveaux
-- utilisateurs via le MenuController (génération par défaut).
-- ────────────────────────────────────────────────────────────

-- Compte système (mot de passe inutilisé — hash invalide volontaire)
INSERT IGNORE INTO users (id, firstname, lastname, email, password)
VALUES (1, 'Système', 'Défaut', 'system@meal-planner.internal',
        '$2y$12$INVALID_SYSTEM_ACCOUNT_DO_NOT_USE');

SET @uid = 1;

-- ── Ingrédients par défaut ──────────────────────────────────
INSERT IGNORE INTO ingredients
  (user_id, name, price, unit, calories, protein, category)
VALUES
-- Légumes
(@uid, 'Tomates',           2.00, 'kg',    18.0,  0.9, 'vegetables'),
(@uid, 'Carottes',          1.20, 'kg',    41.0,  0.9, 'vegetables'),
(@uid, 'Pommes de terre',   1.50, 'kg',    77.0,  2.0, 'vegetables'),
(@uid, 'Oignons',           1.00, 'kg',    40.0,  1.1, 'vegetables'),
(@uid, 'Champignons',       3.00, 'kg',    22.0,  3.1, 'vegetables'),
(@uid, 'Courgettes',        2.50, 'kg',    17.0,  1.2, 'vegetables'),
(@uid, 'Épinards',          3.50, 'kg',    23.0,  2.9, 'vegetables'),
(@uid, 'Ail',               2.00, 'piece', 149.0,  6.4, 'vegetables'),
-- Viandes
(@uid, 'Poulet (filet)',    8.50, 'kg',   165.0, 31.0, 'meat'),
(@uid, 'Bœuf haché',        9.00, 'kg',   254.0, 17.0, 'meat'),
(@uid, 'Lardons',           5.00, 'piece',337.0, 20.0, 'meat'),
-- Poissons
(@uid, 'Saumon (pavé)',    16.00, 'kg',   208.0, 20.0, 'fish'),
(@uid, 'Thon (boîte)',      2.00, 'piece',132.0, 29.0, 'fish'),
-- Produits laitiers
(@uid, 'Œufs (×6)',         2.50, 'piece',155.0, 13.0, 'dairy'),
(@uid, 'Lait (1L)',         1.10, 'piece', 42.0,  3.4, 'dairy'),
(@uid, 'Beurre (250g)',     3.00, 'piece',737.0,  0.6, 'dairy'),
(@uid, 'Yaourt nature',     0.60, 'piece', 59.0,  3.5, 'dairy'),
(@uid, 'Crème fraîche',     1.80, 'piece',292.0,  2.0, 'dairy'),
(@uid, 'Gruyère râpé',      3.50, 'piece',413.0, 29.0, 'dairy'),
-- Féculents / Céréales
(@uid, 'Pain de mie',       2.20, 'piece',265.0,  9.0, 'grains'),
(@uid, 'Riz (500g)',        1.90, 'piece',130.0,  2.7, 'grains'),
(@uid, 'Pâtes (500g)',      1.50, 'piece',158.0,  5.5, 'grains'),
(@uid, 'Flocons d''avoine', 2.00, 'piece',379.0, 13.0, 'grains'),
-- Fruits
(@uid, 'Pommes',            2.50, 'kg',   52.0,  0.3, 'fruits'),
(@uid, 'Bananes',           1.80, 'kg',   89.0,  1.1, 'fruits'),
(@uid, 'Oranges',           2.00, 'kg',   47.0,  0.9, 'fruits');

-- ── Recettes par défaut ─────────────────────────────────────
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
(@uid, 'Wrap thon-crudités',        'lunch',     10, 'no-pork',    3.60,  410.0, 24.0),
(@uid, 'Taboulé maison',            'lunch',     20, 'vegan',      2.90,  350.0,  8.0),
-- Dîners
(@uid, 'Poulet rôti aux légumes',   'dinner',    60, 'all',        6.50,  520.0, 38.0),
(@uid, 'Pâtes tomate-basilic',      'dinner',    20, 'vegan',      2.80,  460.0, 13.0),
(@uid, 'Saumon en papillote',       'dinner',    30, 'no-pork',    8.00,  480.0, 34.0),
(@uid, 'Omelette champignons',      'dinner',    15, 'vegetarian', 3.40,  320.0, 20.0),
(@uid, 'Gratin dauphinois',         'dinner',    70, 'vegetarian', 4.20,  520.0, 15.0),
(@uid, 'Bœuf haché sauce tomate',   'dinner',    30, 'all',        5.20,  480.0, 32.0),
(@uid, 'Curry de légumes',          'dinner',    35, 'vegan',      3.50,  380.0, 10.0);

-- Liaison ingrédients ↔ recettes (principales recettes)
SET @r1 = (SELECT id FROM recipes WHERE user_id = @uid AND name = 'Poulet rôti aux légumes');
INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(@r1, 'Poulet (filet)',  500, 'g'),
(@r1, 'Pommes de terre', 600, 'g'),
(@r1, 'Carottes',        300, 'g'),
(@r1, 'Oignons',         150, 'g');

SET @r2 = (SELECT id FROM recipes WHERE user_id = @uid AND name = 'Pâtes tomate-basilic');
INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(@r2, 'Pâtes (500g)',    250, 'g'),
(@r2, 'Tomates',         400, 'g'),
(@r2, 'Ail',               2, 'piece');

SET @r3 = (SELECT id FROM recipes WHERE user_id = @uid AND name = 'Saumon en papillote');
INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(@r3, 'Saumon (pavé)',   300, 'g'),
(@r3, 'Courgettes',      200, 'g'),
(@r3, 'Citron',            1, 'piece');

SET @r4 = (SELECT id FROM recipes WHERE user_id = @uid AND name = 'Omelette champignons');
INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(@r4, 'Œufs (×6)',         3, 'piece'),
(@r4, 'Champignons',     150, 'g'),
(@r4, 'Beurre (250g)',    20, 'g');

SET @r5 = (SELECT id FROM recipes WHERE user_id = @uid AND name = 'Soupe de légumes');
INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(@r5, 'Carottes',        200, 'g'),
(@r5, 'Pommes de terre', 200, 'g'),
(@r5, 'Oignons',         100, 'g'),
(@r5, 'Courgettes',      200, 'g');




-- procédure stockées
-- ────────────────────────────────────────────────────────────

DELIMITER $$

-- Génère un menu aléatoire pour un utilisateur
-- Pioche d'abord dans les recettes propres à l'utilisateur,
-- puis dans les recettes par défaut (user_id = 1) en complément.
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

  -- Boucle 7 jours × 3 créneaux
  WHILE v_day < 7 DO

    -- Petit-déjeuner
    SET v_recipe_id = NULL;
    SELECT id, estimated_cost INTO v_recipe_id, v_cost
    FROM   recipes
    WHERE  user_id IN (p_user_id, 1)
      AND  meal_type = 'breakfast'
      AND  (p_dietary = 'all' OR dietary = p_dietary OR dietary = 'all')
    ORDER  BY (user_id = p_user_id) DESC, RAND()
    LIMIT  1;

    IF v_recipe_id IS NOT NULL THEN
      INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
      VALUES (p_menu_id, v_day, 'breakfast', v_recipe_id);
      SET v_total_cost = v_total_cost + IFNULL(v_cost, 0);
    END IF;

    -- Déjeuner
    SET v_recipe_id = NULL;
    SELECT id, estimated_cost INTO v_recipe_id, v_cost
    FROM   recipes
    WHERE  user_id IN (p_user_id, 1)
      AND  meal_type = 'lunch'
      AND  (p_dietary = 'all' OR dietary = p_dietary OR dietary = 'all')
    ORDER  BY (user_id = p_user_id) DESC, RAND()
    LIMIT  1;

    IF v_recipe_id IS NOT NULL THEN
      INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
      VALUES (p_menu_id, v_day, 'lunch', v_recipe_id);
      SET v_total_cost = v_total_cost + IFNULL(v_cost, 0);
    END IF;

    -- Dîner
    SET v_recipe_id = NULL;
    SELECT id, estimated_cost INTO v_recipe_id, v_cost
    FROM   recipes
    WHERE  user_id IN (p_user_id, 1)
      AND  meal_type = 'dinner'
      AND  (p_dietary = 'all' OR dietary = p_dietary OR dietary = 'all')
    ORDER  BY (user_id = p_user_id) DESC, RAND()
    LIMIT  1;

    IF v_recipe_id IS NOT NULL THEN
      INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
      VALUES (p_menu_id, v_day, 'dinner', v_recipe_id);
      SET v_total_cost = v_total_cost + IFNULL(v_cost, 0);
    END IF;

    SET v_day = v_day + 1;
  END WHILE;

  -- Mise à jour du coût total
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

  -- Insère les ingrédients de toutes les recettes du menu (agrégés)
  INSERT INTO shopping_lists (menu_id, user_id, item_name, quantity, unit, estimated_price)
  SELECT
    p_menu_id,
    v_user_id,
    ri.ingredient_name,
    SUM(ri.quantity)                    AS quantity,
    ri.unit,
    IFNULL(SUM(i.price * ri.quantity / 1000), 0) AS estimated_price
  FROM   menu_meals mm
  JOIN   recipes             r  ON mm.recipe_id       = r.id
  JOIN   recipe_ingredients  ri ON r.id               = ri.recipe_id
  LEFT JOIN ingredients      i  ON ri.ingredient_id   = i.id
  WHERE  mm.menu_id = p_menu_id
  GROUP  BY ri.ingredient_name, ri.unit;
END$$

DELIMITER ;