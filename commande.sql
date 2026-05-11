PRAGMA foreign_keys = ON

CREATE TABLE utilisateur (
    id_utilisateur INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    prenom TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    mot_de_passe TEXT NOT NULL
);
CREATE TABLE ingredients (
    id_ingredient INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    categorie TEXT,
    unite TEXT
);
CREATE TABLE recette (
    id_recette INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    duree_preparation INTEGER,
    type_d_alimentation TEXT,
    id_utilisateur INTEGER NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);
CREATE TABLE recette_ingredient (
    id_recette INTEGER NOT NULL,
    id_ingredient INTEGER NOT NULL,
    quantite REAL,
    PRIMARY KEY (id_recette, id_ingredient),
    FOREIGN KEY (id_recette) REFERENCES recette(id_recette),
    FOREIGN KEY (id_ingredient) REFERENCES ingredients(id_ingredient)
);
CREATE TABLE menu_hebdomadaire (
    id_menu INTEGER PRIMARY KEY AUTOINCREMENT,
    id_utilisateur INTEGER NOT NULL,
    semaine INTEGER NOT NULL,
    budget REAL,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur)
);
CREATE TABLE repas (
    id_repas INTEGER PRIMARY KEY AUTOINCREMENT,
    id_menu INTEGER NOT NULL,
    jour TEXT NOT NULL,              -- Lundi, Mardi, ...
    type_repas TEXT NOT NULL,        -- Déjeuner, Dîner
    FOREIGN KEY (id_menu) REFERENCES menu_hebdomadaire(id_menu)
);
CREATE TABLE repas_recette (
    id_repas INTEGER NOT NULL,
    id_recette INTEGER NOT NULL,
    PRIMARY KEY (id_repas, id_recette),
    FOREIGN KEY (id_repas) REFERENCES repas(id_repas),
    FOREIGN KEY (id_recette) REFERENCES recette(id_recette)
);
CREATE TABLE stock (
    id_stock INTEGER PRIMARY KEY AUTOINCREMENT,
    id_utilisateur INTEGER NOT NULL,
    id_ingredient INTEGER NOT NULL,
    quantite REAL DEFAULT 0,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id_utilisateur),
    FOREIGN KEY (id_ingredient) REFERENCES ingredients(id_ingredient)
);
CREATE TABLE categories (
    id_categorie INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    description TEXT
);
DROP TABLE IF EXISTS ingredients;
CREATE TABLE ingredients (
    id_ingredient INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    prix_unitaire REAL,
    unite_mesure TEXT,
    calories_par_unite INTEGER,
    proteines_par_unite REAL,
    glucides_par_unite REAL,
    lipides_par_unite REAL,
    id_categorie INTEGER,
    FOREIGN KEY (id_categorie) REFERENCES categories(id_categorie)
);

DROP TABLE IF EXISTS recette;
CREATE TABLE recette (
    id_recette INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    description TEXT,
    instructions TEXT,
    temps_preparation INTEGER,
    temps_cuisson INTEGER,
    nombre_personnes INTEGER,
    difficulte TEXT,
    type_repas TEXT,
    saison TEXT
);
DROP TABLE IF EXISTS recette_ingredient;
CREATE TABLE recette_ingredient (
    id_recette INTEGER NOT NULL,
    id_ingredient INTEGER NOT NULL,
    quantite REAL,
    unite TEXT,
    PRIMARY KEY (id_recette, id_ingredient),
    FOREIGN KEY (id_recette) REFERENCES recette(id_recette),
    FOREIGN KEY (id_ingredient) REFERENCES ingredients(id_ingredient)
);
INSERT INTO categories (nom, description) VALUES
('Légumes', 'Légumes frais et surgelés'),
('Viandes', 'Viandes et volailles'),
('Poissons', 'Poissons et fruits de mer'),
('Féculents', 'Pâtes, riz, pommes de terre'),
('Produits laitiers', 'Lait, fromage, yaourts'),
('Fruits', 'Fruits frais et secs'),
('Épices et condiments', 'Herbes, épices, sauces'),
('Céréales', 'Pain, céréales, farines');

INSERT INTO ingredients (nom, prix_unitaire, unite_mesure, calories_par_unite, proteines_par_unite, glucides_par_unite, lipides_par_unite, id_categorie) VALUES
('Tomate', 2.50, 'kg', 180, 8.8, 38.8, 2.0, 1),
('Poulet', 8.90, 'kg', 2390, 310, 0, 140, 2),
('Saumon', 15.50, 'kg', 2080, 200, 0, 132, 3),
('Pâtes', 1.20, 'kg', 3710, 130, 750, 15, 4),
('Riz', 1.50, 'kg', 3650, 66, 800, 6.6, 4),
('Œufs', 3.20, 'douzaine', 1560, 126, 7.2, 105, 5),
('Lait', 1.10, 'L', 640, 33, 47, 36, 5),
('Fromage emmental', 12.00, 'kg', 4030, 288, 15, 313, 5),
('Carotte', 1.80, 'kg', 410, 9.3, 96, 2.4, 1),
('Oignon', 1.50, 'kg', 400, 11, 92, 1.0, 1),
('Pomme de terre', 1.20, 'kg', 770, 20, 170, 0.9, 1),
('Huile olive', 8.50, 'L', 8840, 0, 0, 1000, 7),
('Sel', 0.80, 'kg', 0, 0, 0, 0, 7),
('Poivre', 15.00, 'kg', 2510, 104, 640, 33, 7),
('Baguette', 1.00, 'unité', 2650, 88, 550, 32, 8),
('Courgette', 2.20, 'kg', 170, 12, 33, 3.2, 1),
('Poivron', 3.50, 'kg', 200, 10, 46, 3.0, 1),
('Ail', 8.00, 'kg', 1490, 63, 331, 5.0, 7),
('Bœuf haché', 12.00, 'kg', 2500, 260, 0, 200, 2),
('Jambon', 15.00, 'kg', 1450, 210, 10, 65, 2);
INSERT INTO recette (nom, description, instructions, temps_preparation, temps_cuisson, nombre_personnes, difficulte, type_repas, saison) VALUES
('Pâtes à la tomate', 'Plat simple et rapide de pâtes avec sauce tomate maison', 'Faire cuire les pâtes dans l''eau bouillante salée. Pendant ce temps, faire revenir l''oignon émincé dans l''huile d''olive. Ajouter les tomates coupées en dés. Laisser mijoter 10 min. Égoutter les pâtes et mélanger avec la sauce.', 10, 15, 4, 'facile', 'dejeuner', 'toutes'),

('Poulet rôti aux légumes', 'Poulet tendre avec légumes de saison', 'Préchauffer le four à 180°C. Assaisonner le poulet avec sel, poivre et herbes. Découper les carottes et pommes de terre en gros morceaux. Disposer dans un plat. Arroser d''huile d''olive. Enfourner 1h en arrosant régulièrement.', 15, 60, 4, 'moyen', 'diner', 'toutes'),

('Saumon grillé', 'Filet de saumon grillé avec un filet de citron', 'Assaisonner les filets de saumon avec sel et poivre. Chauffer une poêle avec un peu d''huile d''olive. Cuire le saumon 6 minutes de chaque côté à feu moyen. Servir avec du citron.', 5, 12, 4, 'facile', 'diner', 'toutes'),

('Omelette nature', 'Omelette simple et rapide aux œufs frais', 'Battre les œufs dans un bol avec une pincée de sel et de poivre. Chauffer une poêle avec un peu d''huile. Verser les œufs et laisser cuire 3-4 minutes. Plier en deux et servir.', 5, 5, 2, 'facile', 'petit-dejeuner', 'toutes'),

('Riz aux légumes', 'Riz sauté avec légumes variés et colorés', 'Cuire le riz selon les instructions. Couper les légumes en petits dés. Faire revenir les légumes dans l''huile d''olive. Ajouter le riz cuit et mélanger. Assaisonner et servir chaud.', 10, 20, 4, 'facile', 'dejeuner', 'toutes'),

('Gratin de courgettes', 'Gratin fondant aux courgettes et fromage', 'Préchauffer le four à 200°C. Couper les courgettes en rondelles. Disposer dans un plat beurré. Recouvrir de fromage râpé. Enfourner 30 minutes jusqu''à ce que le dessus soit doré.', 10, 30, 4, 'facile', 'diner', 'ete'),

('Salade composée', 'Salade fraîche et équilibrée', 'Laver et couper les tomates et poivrons. Ajouter des œufs durs coupés en quartiers. Assaisonner avec huile d''olive, sel et poivre.', 15, 10, 4, 'facile', 'dejeuner', 'ete');
INSERT INTO recette_ingredient (id_recette, id_ingredient, quantite, unite) VALUES

(1, 4, 400, 'g'),
(1, 1, 500, 'g'),
(1, 10, 100, 'g'),
(1, 12, 30, 'ml'),
(1, 13, 5, 'g'),
(1, 14, 2, 'g'),

(2, 2, 1200, 'g'),
(2, 9, 400, 'g'),
(2, 11, 600, 'g'),
(2, 12, 40, 'ml'),
(2, 13, 8, 'g'),
(2, 14, 3, 'g'),

(3, 3, 600, 'g'),
(3, 12, 20, 'ml'),
(3, 13, 5, 'g'),
(3, 14, 2, 'g'),

(4, 6, 4, 'unité'),
(4, 12, 10, 'ml'),
(4, 13, 3, 'g'),
(4, 14, 1, 'g'),

(5, 5, 300, 'g'),
(5, 9, 200, 'g'),
(5, 10, 100, 'g'),
(5, 16, 150, 'g'),
(5, 12, 30, 'ml'),
(5, 13, 5, 'g'),

(6, 16, 800, 'g'),
(6, 8, 200, 'g'),
(6, 12, 20, 'ml'),
(6, 13, 5, 'g'),
(6, 14, 2, 'g'),

(7, 1, 300, 'g'),
(7, 17, 200, 'g'),
(7, 6, 2, 'unité'),
(7, 12, 40, 'ml'),
(7, 13, 3, 'g'),
(7, 14, 2, 'g');