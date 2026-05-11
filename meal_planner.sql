-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : lun. 11 mai 2026 à 14:00
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `meal_planner`
--

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id_categorie` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id_categorie`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id_categorie`, `nom`, `description`) VALUES
(1, 'Légumes', 'Légumes frais et surgelés'),
(2, 'Viandes', 'Viandes et volailles'),
(3, 'Poissons', 'Poissons et fruits de mer'),
(4, 'Féculents', 'Pâtes, riz, pommes de terre'),
(5, 'Produits laitiers', 'Lait, fromage, yaourts'),
(6, 'Fruits', 'Fruits frais et secs'),
(7, 'Épices et condiments', 'Herbes, épices, sauces'),
(8, 'Céréales', 'Pain, céréales, farines');

-- --------------------------------------------------------

--
-- Structure de la table `ingredients`
--

DROP TABLE IF EXISTS `ingredients`;
CREATE TABLE IF NOT EXISTS `ingredients` (
  `id_ingredient` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `unite_mesure` varchar(50) DEFAULT NULL,
  `calories_par_unite` int DEFAULT NULL,
  `proteines_par_unite` decimal(10,2) DEFAULT NULL,
  `glucides_par_unite` decimal(10,2) DEFAULT NULL,
  `lipides_par_unite` decimal(10,2) DEFAULT NULL,
  `id_categorie` int DEFAULT NULL,
  PRIMARY KEY (`id_ingredient`),
  KEY `id_categorie` (`id_categorie`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `ingredients`
--

INSERT INTO `ingredients` (`id_ingredient`, `nom`, `prix_unitaire`, `unite_mesure`, `calories_par_unite`, `proteines_par_unite`, `glucides_par_unite`, `lipides_par_unite`, `id_categorie`) VALUES
(1, 'Tomate', 2.50, 'kg', 180, 8.80, 38.80, 2.00, 1),
(2, 'Poulet', 8.90, 'kg', 2390, 310.00, 0.00, 140.00, 2),
(3, 'Saumon', 15.50, 'kg', 2080, 200.00, 0.00, 132.00, 3),
(4, 'Pâtes', 1.20, 'kg', 3710, 130.00, 750.00, 15.00, 4),
(5, 'Riz', 1.50, 'kg', 3650, 66.00, 800.00, 6.60, 4),
(6, 'Œufs', 3.20, 'douzaine', 1560, 126.00, 7.20, 105.00, 5),
(7, 'Lait', 1.10, 'L', 640, 33.00, 47.00, 36.00, 5),
(8, 'Fromage emmental', 12.00, 'kg', 4030, 288.00, 15.00, 313.00, 5),
(9, 'Carotte', 1.80, 'kg', 410, 9.30, 96.00, 2.40, 1),
(10, 'Oignon', 1.50, 'kg', 400, 11.00, 92.00, 1.00, 1),
(11, 'Pomme de terre', 1.20, 'kg', 770, 20.00, 170.00, 0.90, 1),
(12, 'Huile olive', 8.50, 'L', 8840, 0.00, 0.00, 1000.00, 7),
(13, 'Sel', 0.80, 'kg', 0, 0.00, 0.00, 0.00, 7),
(14, 'Poivre', 15.00, 'kg', 2510, 104.00, 640.00, 33.00, 7),
(15, 'Baguette', 1.00, 'unité', 2650, 88.00, 550.00, 32.00, 8),
(16, 'Courgette', 2.20, 'kg', 170, 12.00, 33.00, 3.20, 1),
(17, 'Poivron', 3.50, 'kg', 200, 10.00, 46.00, 3.00, 1),
(18, 'Ail', 8.00, 'kg', 1490, 63.00, 331.00, 5.00, 7),
(19, 'Bœuf haché', 12.00, 'kg', 2500, 260.00, 0.00, 200.00, 2),
(20, 'Jambon', 15.00, 'kg', 1450, 210.00, 10.00, 65.00, 2);

-- --------------------------------------------------------

--
-- Structure de la table `menu_hebdomadaire`
--

DROP TABLE IF EXISTS `menu_hebdomadaire`;
CREATE TABLE IF NOT EXISTS `menu_hebdomadaire` (
  `id_menu` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int NOT NULL,
  `semaine` int NOT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_menu`),
  KEY `id_utilisateur` (`id_utilisateur`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recette`
--

DROP TABLE IF EXISTS `recette`;
CREATE TABLE IF NOT EXISTS `recette` (
  `id_recette` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `description` text,
  `instructions` text,
  `temps_preparation` int DEFAULT NULL,
  `temps_cuisson` int DEFAULT NULL,
  `nombre_personnes` int DEFAULT NULL,
  `difficulte` varchar(50) DEFAULT NULL,
  `type_repas` varchar(50) DEFAULT NULL,
  `saison` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_recette`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `recette`
--

INSERT INTO `recette` (`id_recette`, `nom`, `description`, `instructions`, `temps_preparation`, `temps_cuisson`, `nombre_personnes`, `difficulte`, `type_repas`, `saison`) VALUES
(1, 'Pâtes à la tomate', 'Plat simple et rapide de pâtes avec sauce tomate maison', 'Faire cuire les pâtes...', 10, 15, 4, 'facile', 'dejeuner', 'toutes'),
(2, 'Poulet rôti aux légumes', 'Poulet tendre avec légumes de saison', 'Préchauffer le four...', 15, 60, 4, 'moyen', 'diner', 'toutes'),
(3, 'Saumon grillé', 'Filet de saumon grillé avec un filet de citron', 'Assaisonner les filets...', 5, 12, 4, 'facile', 'diner', 'toutes'),
(4, 'Omelette nature', 'Omelette simple et rapide aux œufs frais', 'Battre les œufs...', 5, 5, 2, 'facile', 'petit-dejeuner', 'toutes'),
(5, 'Riz aux légumes', 'Riz sauté avec légumes variés et colorés', 'Cuire le riz...', 10, 20, 4, 'facile', 'dejeuner', 'toutes'),
(6, 'Gratin de courgettes', 'Gratin fondant aux courgettes et fromage', 'Préchauffer le four...', 10, 30, 4, 'facile', 'diner', 'ete'),
(7, 'Salade composée', 'Salade fraîche et équilibrée', 'Laver et couper...', 15, 10, 4, 'facile', 'dejeuner', 'ete');

-- --------------------------------------------------------

--
-- Structure de la table `recette_ingredient`
--

DROP TABLE IF EXISTS `recette_ingredient`;
CREATE TABLE IF NOT EXISTS `recette_ingredient` (
  `id_recette` int NOT NULL,
  `id_ingredient` int NOT NULL,
  `quantite` decimal(10,2) DEFAULT NULL,
  `unite` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_recette`,`id_ingredient`),
  KEY `id_ingredient` (`id_ingredient`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `recette_ingredient`
--

INSERT INTO `recette_ingredient` (`id_recette`, `id_ingredient`, `quantite`, `unite`) VALUES
(1, 4, 400.00, 'g'),
(1, 1, 500.00, 'g'),
(1, 10, 100.00, 'g'),
(1, 12, 30.00, 'ml'),
(1, 13, 5.00, 'g'),
(1, 14, 2.00, 'g'),
(2, 2, 1200.00, 'g'),
(2, 9, 400.00, 'g'),
(2, 11, 600.00, 'g'),
(2, 12, 40.00, 'ml'),
(2, 13, 8.00, 'g'),
(2, 14, 3.00, 'g'),
(3, 3, 600.00, 'g'),
(3, 12, 20.00, 'ml'),
(3, 13, 5.00, 'g'),
(3, 14, 2.00, 'g'),
(4, 6, 4.00, 'unité'),
(4, 12, 10.00, 'ml'),
(4, 13, 3.00, 'g'),
(4, 14, 1.00, 'g'),
(5, 5, 300.00, 'g'),
(5, 9, 200.00, 'g'),
(5, 10, 100.00, 'g'),
(5, 16, 150.00, 'g'),
(5, 12, 30.00, 'ml'),
(5, 13, 5.00, 'g'),
(6, 16, 800.00, 'g'),
(6, 8, 200.00, 'g'),
(6, 12, 20.00, 'ml'),
(6, 13, 5.00, 'g'),
(6, 14, 2.00, 'g'),
(7, 1, 300.00, 'g'),
(7, 17, 200.00, 'g'),
(7, 6, 2.00, 'unité'),
(7, 12, 40.00, 'ml'),
(7, 13, 3.00, 'g'),
(7, 14, 2.00, 'g');

-- --------------------------------------------------------

--
-- Structure de la table `repas`
--

DROP TABLE IF EXISTS `repas`;
CREATE TABLE IF NOT EXISTS `repas` (
  `id_repas` int NOT NULL AUTO_INCREMENT,
  `id_menu` int NOT NULL,
  `jour` varchar(50) NOT NULL,
  `type_repas` varchar(50) NOT NULL,
  PRIMARY KEY (`id_repas`),
  KEY `id_menu` (`id_menu`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `repas_recette`
--

DROP TABLE IF EXISTS `repas_recette`;
CREATE TABLE IF NOT EXISTS `repas_recette` (
  `id_repas` int NOT NULL,
  `id_recette` int NOT NULL,
  PRIMARY KEY (`id_repas`,`id_recette`),
  KEY `id_recette` (`id_recette`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock`
--

DROP TABLE IF EXISTS `stock`;
CREATE TABLE IF NOT EXISTS `stock` (
  `id_stock` int NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int NOT NULL,
  `id_ingredient` int NOT NULL,
  `quantite` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id_stock`),
  KEY `id_utilisateur` (`id_utilisateur`),
  KEY `id_ingredient` (`id_ingredient`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id_utilisateur` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
