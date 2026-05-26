<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Outil de planification de repas hebdomadaires avec génération automatique, calcul des coûts et apports nutritionnels">
    <title>Planificateur de Repas Hebdomadaires</title>
    <link rel="icon" href="public/assets/img/PR.png">
    <link rel="stylesheet" href="public/assets/css/style.css">
</head>
<body>
    <!-- en-tête -->
    <header>
        <h1>Planificateur de Repas</h1>
        <p class="subtitle">Générez des menus équilibrés et économiques</p>

        <!-- ══ BOUTON UTILISATEUR ══ -->
        <div class="user-menu-wrapper" id="user-menu-wrapper">
            <button
                class="user-menu-btn"
                id="user-menu-btn"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="user-dropdown"
                aria-label="Menu utilisateur"
            >
                <span class="user-avatar" aria-hidden="true" id="user-avatar">M</span>
                <span class="user-name" id="user-display-name">Marie Dupont</span>
                <span class="user-chevron" aria-hidden="true" id="user-chevron">▾</span>
            </button>

            <!-- Dropdown -->
            <div
                class="user-dropdown"
                id="user-dropdown"
                role="menu"
                aria-label="Options utilisateur"
                hidden
            >
                <!-- En-tête du dropdown -->
                <div class="dropdown-header" aria-hidden="true">
                    <span class="dropdown-avatar" id="dropdown-avatar">M</span>
                    <div class="dropdown-user-info">
                        <span class="dropdown-name" id="dropdown-name">Marie Dupont</span>
                        <span class="dropdown-email" id="dropdown-email">marie.dupont@exemple.fr</span>
                    </div>
                </div>

                <div class="dropdown-divider" role="separator"></div>

                <!-- Actions -->
                <button class="dropdown-item" role="menuitem" onclick="goToProfile()">
                    <span class="dropdown-icon" aria-hidden="true">👤</span>
                    Mon profil
                </button>

                <button class="dropdown-item" role="menuitem" onclick="goToSettings()">
                    <span class="dropdown-icon" aria-hidden="true">⚙️</span>
                    Paramètres
                </button>

                <button class="dropdown-item" role="menuitem" onclick="goToMyMenus()">
                    <span class="dropdown-icon" aria-hidden="true">📅</span>
                    Mes menus sauvegardés
                </button>

                <button class="dropdown-item" role="menuitem" onclick="goToFavorites()">
                    <span class="dropdown-icon" aria-hidden="true">⭐</span>
                    Mes favoris
                </button>

                <div class="dropdown-divider" role="separator"></div>

                <button class="dropdown-item dropdown-item--danger" role="menuitem" onclick="logout()">
                    <span class="dropdown-icon" aria-hidden="true">🚪</span>
                    Se déconnecter
                </button>
            </div>
        </div>
        <!-- ══ FIN BOUTON UTILISATEUR ══ -->
         <a href="login.php">se connecter</a>
    </header>

    <!-- navigation principale -->
    <nav aria-label="Navigation principale" class="main-nav">
        <button class="nav-btn active" onclick="showTab('generate')" aria-label="Aller à l'onglet génération de menu">
            <span aria-hidden="true"></span>Générer un menu
        </button>

        <button class="nav-btn" onclick="showTab('ingredients')" aria-label="Aller à l'onglet gestion des ingrédients">
            <span aria-hidden="true"></span>Mes ingrédients
        </button>

        <button class="nav-btn" onclick="showTab('recipes')" aria-label="Aller à l'onglet gestion des recettes">
            <span aria-hidden="true"></span>Mes recettes
        </button>

        <button class="nav-btn" onclick="showTab('menu')" aria-label="Aller à l'onglet visualisation du menu">
            <span aria-hidden="true"></span>Mon menu
        </button>
    </nav>

    <main id="main-content" class="container">

        <!-- section : génération de menu -->
        <section id="tab-generate" class="tab-content active" role="tabpanel" aria-labelledby="generate">
            <div class="card">
                <h2>Générer un menu hebdomadaire</h2>

                <div class="form-group">
                    <label for="budget">Budget hebdomadaire (€)</label>
                    <input type="number" id="budget" name="budget" min="0" step="0.5" value="50"
                        aria-describedby="budget-help">
                    <small id="budget-help">Définissez votre budget pour la semaine</small>
                </div>

                <div class="form-group">
                    <label for="persons">Nombre de personnes</label>
                    <input type="number" id="persons" name="persons" min="1" max="12" value="2"
                        aria-describedby="persons-help">
                    <small id="persons-help">Pour combien de personnes planifiez-vous</small>
                </div>

                <div class="form-group">
                    <label for="dietary">Préférence alimentaire</label>
                    <select id="dietary" name="dietary" aria-describedby="dietary-help">
                        <option value="all">Tout</option>
                        <option value="vegetarian">Végétarien</option>
                        <option value="vegan">Vegan</option>
                        <option value="no-pork">Sans Porc</option>
                    </select>
                    <small id="dietary-help">Choisissez vos préférences alimentaires</small>
                </div>

                <button class="btn btn-primary" onclick="generateMenu()"
                    aria-label="Générer un nouveau menu aléatoire">
                    Générer un menu aléatoire
                </button>

                <div id="generation-result" role="region" aria-live="polite" aria-atomic="true"></div>
            </div>
        </section>

        <!-- section : gestion des ingrédients -->
        <section id="tab-ingredients" class="tab-content" role="tabpanel" aria-labelledby="ingredients">
            <div class="card">
                <h2>Gérer mes ingrédients disponibles</h2>

                <form onsubmit="addIngredient(event)" class="ingredient-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ingredient-name">Nom de l'ingrédient</label>
                            <input type="text" id="ingredient-name" name="ingredient-name" required
                                placeholder="Ex: Tomates">
                        </div>

                        <div class="form-group">
                            <label for="ingredient-price">Prix unitaire (€)</label>
                            <input type="number" id="ingredient-price" name="ingredient-price"
                                min="0" step="0.01" required placeholder="2.50">
                        </div>

                        <div class="form-group">
                            <label for="ingredient-unit">Unité</label>
                            <select id="ingredient-unit" name="ingredient-unit">
                                <option value="kg">Kg</option>
                                <option value="g">Grammes</option>
                                <option value="L">Litres</option>
                                <option value="piece">Pièce</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ingredient-calories">Calories (pour 100g)</label>
                            <input type="number" id="ingredient-calories" name="ingredient-calories"
                                min="0" placeholder="45">
                        </div>

                        <div class="form-group">
                            <label for="ingredient-protein">Protéines (g)</label>
                            <input type="number" id="ingredient-protein" name="ingredient-protein"
                                min="0" step="0.1" placeholder="3.5">
                        </div>

                        <div class="form-group">
                            <label for="ingredient-category">Catégorie</label>
                            <select id="ingredient-category" name="ingredient-category">
                                <option value="vegetables">Légumes</option>
                                <option value="fruits">Fruits</option>
                                <option value="meat">Viande</option>
                                <option value="fish">Poisson</option>
                                <option value="dairy">Produits laitiers</option>
                                <option value="grains">Céréales</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Ajouter l'ingrédient
                    </button>
                </form>

                <div id="ingredients-list" class="items-list" role="list"></div>
            </div>
        </section>

        <!-- section : gestion des recettes -->
        <section id="tab-recipes" class="tab-content" role="tabpanel" aria-labelledby="recipes">
            <div class="card">
                <h2>Gérer mes recettes</h2>

                <form onsubmit="addRecipe(event)" class="recipe-form">
                    <div class="form-group">
                        <label for="recipe-name">Nom de la recette</label>
                        <input type="text" id="recipe-name" required
                            placeholder="Ex: Poulet rôti aux légumes">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="recipe-type">Type de repas</label>
                            <select id="recipe-type" name="recipe-type">
                                <option value="breakfast">Petit-déjeuner</option>
                                <option value="lunch">Déjeuner</option>
                                <option value="dinner">Dîner</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="recipe-time">Temps de préparation (min)</label>
                            <input type="number" id="recipe-time" name="recipe-time"
                                min="5" placeholder="30">
                        </div>

                        <div class="form-group">
                            <label for="recipe-dietary">Type alimentaire</label>
                            <select id="recipe-dietary" name="recipe-dietary">
                                <option value="all">Tout</option>
                                <option value="vegetarian">Végétarien</option>
                                <option value="vegan">Végan</option>
                                <option value="no-pork">Sans porc</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="recipe-ingredients">Ingrédients (séparés par des virgules)</label>
                        <textarea id="recipe-ingredients" name="recipe-ingredients" rows="3"
                            placeholder="Poulet, Pommes de terre, Carottes, Oignons"
                            aria-describedby="recipe-ingredients-help"></textarea>
                        <small id="recipe-ingredients-help">Listez les ingrédients séparés par des virgules</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Ajouter la recette</button>
                </form>

                <div id="recipes-list" class="items-list" role="list"></div>
            </div>
        </section>

        <!-- section : menu hebdomadaire -->
        <section id="tab-menu" class="tab-content" role="tabpanel" aria-labelledby="menu">
            <div class="card">
                <h2>Mon menu de la semaine</h2>

                <div class="btn-action">
                    <button class="btn btn-secondary" onclick="exportToPDF()"
                        aria-label="Exporter le menu au format PDF">
                        Exporter PDF
                    </button>

                    <button class="btn btn-secondary" onclick="exportToICS()"
                        aria-label="Exporter le menu au format calendrier">
                        Exporter ICS
                    </button>

                    <button class="btn btn-danger" onclick="clearMenu()"
                        aria-label="Effacer tout le menu">
                        Effacer
                    </button>
                </div>

                <div id="weekly-menu" class="weekly-grid"></div>
            </div>
        </section>

    </main>

    <!-- footer avec résumés -->
    <footer>
        <div class="summary-section">
            <div class="summary-card">
                <h3>Résumé financier</h3>
                <div class="summary-content">
                    <div class="summary-item">
                        <span>Coût total :</span>
                        <strong id="total-cost">0,00 €</strong>
                    </div>
                    <div class="summary-item">
                        <span>Coût par repas :</span>
                        <strong id="cost-per-meal">0,00 €</strong>
                    </div>
                    <div class="summary-item">
                        <span>Coût par personne :</span>
                        <strong id="cost-per-person">0,00 €</strong>
                    </div>
                </div>
            </div>
            <div class="summary-card">
                <h3>Apports nutritionnels (hebdomadaire)</h3>
                <div class="summary-content">
                    <div class="summary-item">
                        <span>Calories totales :</span>
                        <strong id="total-calories">0 kcal</strong>
                    </div>
                    <div class="summary-item">
                        <span>Protéines :</span>
                        <strong id="total-protein">0 g</strong>
                    </div>
                    <div class="summary-item">
                        <span>Calories/jour :</span>
                        <strong id="calories-per-day">0 kcal</strong>
                    </div>
                </div>
            </div>
        </div>
        <p>&copy; 2026 Application de Gestion de Menu réalisé par Dibaj NASRULLAH & JJ Tous droits réservés.</p>
    </footer>

    <script src="public/assets/js/app.js"></script>
</body>
</html>