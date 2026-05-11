/**
app.js logique applicative du Planificateur de Repas Hebdomadaires 

Voir Cahier des charges:
- génération de menus aléatoires en fonction des ingrédients disponibles 
- calcul du coût total des apports nutritionnels
- export en PDF via jsPDF et ICS (RFC 5545)
- interface accessible (WCAG 2.1)

Architecture: appels REST vers api.php (backend PHP + base de données en MySQL)
*/

'use strict';

// CONSTANTES ET ÉTAT GLOBAL

const API = '/api.php'; // TO DO 

// noms des jours pour l'affichage 
const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

// libellés des types de repas
const REPAS_LABELS = {
    breakfast: 'Petit-déjeuner',
    lunch: 'Déjeuner',
    dinner: 'Dîner'
};

// état global de l'application 
// currentMenu : tableau de 7 jours, chacun avec {breakfast, lunch, dinner}
let state = {
    ingredients: [],
    recipes:     [],
    currentMenu: null,   // { id?, meals: [{day, meal_type, recipe}] }
    persons:     2,
    budget:      50,
};

// initialisation 
// point d'entrée - appelé au chargement de la page 
document.addEventListener('DOMContentLoaded', () => {
    showTab('generate');
    loadIngredients();
    loadRecipes();
});


// Navigation par onglets 
// affiche l'onglet demandé et masque les autres 
// met à jour la classe 'active' sur les boutons de navigation.
// @param {string} tabName idnetifiant de l'onglet (generate, ingredients, recipes, menu)

function showTab(tabName) {
    // Masquer tous les contenus
    document.querySelectorAll('.tab-content').forEach(section => {
        section.classList.remove('active');
    });

    // Désactiver tous les boutons
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.removeAttribute('aria-current');
    });

    // Activer la cible
    const targetSection = document.getElementById(`tab-${tabName}`);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // Marquer le bouton correspondant
    const activeBtn = document.querySelector(`[onclick="showTab('${tabName}')"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.setAttribute('aria-current', 'page');
    }
}