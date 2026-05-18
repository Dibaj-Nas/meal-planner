// app.js — Planificateur de Repas
// Ce fichier gère toute la logique de l'application :
//   - Onglets de navigation
//   - Ingrédients (ajout, suppression, affichage)
//   - Recettes (ajout, suppression, affichage)
//   - Génération du menu hebdomadaire
//   - Résumé du coût et des calories
//   - Export en PDF et en calendrier (ICS)




// Les 7 jours de la semaine avec leurs emojis
const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
const EMOJI_JOURS = ['🌅', '☀️', '🌤️', '⛅', '🌈', '🎉', '😴'];

// Les types de repas dans la journée
const TYPES_REPAS = {
  breakfast: { label: 'Petit-déjeuner', emoji: '🥐', couleur: 'var(--breakfast)' },
  lunch:     { label: 'Déjeuner',       emoji: '🍽️', couleur: 'var(--lunch)'     },
  dinner:    { label: 'Dîner',          emoji: '🌙', couleur: 'var(--diner)'     },
};

// Emojis par catégorie d'ingrédient
const EMOJI_CATEGORIES = {
  vegetables: '🥦',
  fruits:     '🍎',
  meat:       '🥩',
  fish:       '🐟',
  dairy:      '🧀',
  grains:     '🌾',
  other:      '📦',
};

// Labels pour les régimes alimentaires
const LABELS_REGIME = {
  all:       'Tout',
  vegetarian:'Végétarien',
  vegan:     'Vegan',
  'no-pork': 'Sans Porc',
};


// STOCKAGE — sauvegarder et charger les données


// Je stocke tout dans localStorage pour ne pas perdre les données
// quand on recharge la page. C'est une sorte de "base de données locale".

// Données en mémoire (chargées au démarrage)
let donneesApp = {
  ingredients: [],
  recettes: [],
  menuActuel: null, // le menu de la semaine en cours
};

// Sauvegarder les données dans localStorage
function sauvegarder() {
  try {
    localStorage.setItem('mealplanner_data', JSON.stringify(donneesApp));
  } catch (err) {
    console.warn('Impossible de sauvegarder :', err);
  }
}

// Charger les données depuis localStorage (au démarrage)
function charger() {
  try {
    const sauvegarde = localStorage.getItem('mealplanner_data');
    if (sauvegarde) {
      const parsed = JSON.parse(sauvegarde);
      // On fusionne avec les valeurs par défaut au cas où
      donneesApp = { ...donneesApp, ...parsed };
    }
  } catch (err) {
    console.warn('Impossible de charger les données :', err);
  }
}


// PETITS UTILITAIRES — fonctions réutilisables

// Formate un nombre en euros (ex: 12.5 → "12,50 €")
function formaterEuro(valeur) {
  const nombre = Number(valeur || 0);
  return nombre.toLocaleString('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }) + ' €';
}

// Formate une valeur nutritionnelle (ex: 150 → "150 kcal")
function formaterNutrition(valeur, unite) {
  const u = unite || 'kcal';
  return Math.round(valeur || 0).toLocaleString('fr-FR') + ' ' + u;
}

// Génère un identifiant unique (pour donner un ID à chaque ingredient/recette)
function genererID() {
  return '_' + Math.random().toString(36).slice(2, 9);
}

// Protège contre les attaques XSS en échappant le HTML
// (important pour ne pas afficher du code malveillant)
function securiserHTML(texte) {
  return String(texte ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// Petite animation quand un élément apparaît dans la page
function animerApparition(element) {
  element.style.opacity   = '0';
  element.style.transform = 'translateY(12px)';
  element.style.transition = 'opacity 0.35s ease, transform 0.35s ease';

  // On attend 2 frames pour que le navigateur "voie" le style initial
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      element.style.opacity   = '1';
      element.style.transform = 'translateY(0)';
    });
  });
}



// NAVIGATION — changer d'onglet


// Onglet actuellement visible
let ongletActif = 'generate';

// Affiche l'onglet demandé et cache les autres
function showTab(nomOnglet) {
  if (ongletActif === nomOnglet) return; // déjà dessus, rien à faire

  // Cache tous les onglets
  document.querySelectorAll('.tab-content').forEach(function(onglet) {
    onglet.classList.remove('active');
  });

  // Désactive tous les boutons de navigation
  document.querySelectorAll('.nav-btn').forEach(function(btn) {
    btn.classList.remove('active');
  });

  // Affiche l'onglet demandé
  const panneau = document.getElementById('tab-' + nomOnglet);
  if (panneau) {
    panneau.classList.add('active');

    // Petite animation d'entrée pour les éléments du panneau
    Array.from(panneau.children).forEach(function(enfant, i) {
      enfant.style.opacity   = '0';
      enfant.style.transform = 'translateY(16px)';
      enfant.style.transition = 'opacity 0.4s ease ' + (i * 0.07) + 's, transform 0.4s ease ' + (i * 0.07) + 's';
      requestAnimationFrame(function() {
        requestAnimationFrame(function() {
          enfant.style.opacity   = '1';
          enfant.style.transform = 'translateY(0)';
        });
      });
    });
  }

  // Active le bouton correspondant
  // On cherche le bouton qui a onclick contenant le nom de l'onglet
  document.querySelectorAll('.nav-btn').forEach(function(btn) {
    const onclick = btn.getAttribute('onclick') || '';
    if (onclick.includes("'" + nomOnglet + "'")) {
      btn.classList.add('active');
    }
  });

  ongletActif = nomOnglet;

  // Actions spéciales quand on revient sur l'onglet "menu"
  if (nomOnglet === 'menu') {
    afficherMenu();
    mettreAJourResume();
  }
}


// TOASTS — petits messages de notification en bas de page


// Crée un conteneur fixe en bas de page pour les notifications
let conteneurToasts = null;

function obtenirConteneurToasts() {
  if (!conteneurToasts) {
    conteneurToasts = document.createElement('div');
    conteneurToasts.style.cssText = [
      'position:fixed',
      'bottom:1.5rem',
      'right:1.5rem',
      'display:flex',
      'flex-direction:column',
      'gap:.5rem',
      'z-index:9999',
      'pointer-events:none',
    ].join(';');
    document.body.appendChild(conteneurToasts);
  }
  return conteneurToasts;
}

// Affiche une notification (type = 'success', 'error', 'warning', 'info') TODO



// Raccourcis pratiques
function notifSucces(msg, duree)  { afficherNotification(msg, 'success', duree); }
function notifErreur(msg, duree)  { afficherNotification(msg, 'error',   duree); }
function notifAvert(msg, duree)   { afficherNotification(msg, 'warning', duree); }
function notifInfo(msg, duree)    { afficherNotification(msg, 'info',    duree); }



// INGRÉDIENTS — ajouter, supprimer, afficher


// Ajoute un ingrédient depuis le formulaire
function addIngredient(event) {
  if (event) event.preventDefault();

  // On récupère les valeurs du formulaire
  const nom      = document.getElementById('ingredient-name')?.value.trim();
  const prix     = parseFloat(document.getElementById('ingredient-price')?.value  || 0);
  const unite    = document.getElementById('ingredient-unit')?.value     || 'piece';
  const calories = parseFloat(document.getElementById('ingredient-calories')?.value || 0);
  const proteines= parseFloat(document.getElementById('ingredient-protein')?.value  || 0);
  const categorie= document.getElementById('ingredient-category')?.value || 'other';

  // Vérifications de base
  if (!nom) {
    notifErreur("Veuillez saisir un nom d'ingrédient.");
    return;
  }
  if (isNaN(prix) || prix < 0) {
    notifErreur('Le prix saisi est invalide.');
    return;
  }

  // On crée l'objet ingrédient
  const nouvelIngredient = {
    id:        genererID(),
    name:      nom,
    price:     prix,
    unit:      unite,
    calories:  calories,
    protein:   proteines,
    category:  categorie,
  };

  // On l'ajoute à notre liste et on sauvegarde
  donneesApp.ingredients.push(nouvelIngredient);
  sauvegarder();

  notifSucces('"' + nom + '" ajouté avec succès.');

  // On vide le formulaire
  document.querySelector('.ingredient-form')?.reset();

  // On réaffiche la liste
  afficherIngredients();
}

// Supprime un ingrédient par son ID
function supprimerIngredient(id) {
  const ingredient = donneesApp.ingredients.find(function(i) {
    return String(i.id) === String(id);
  });
  if (!ingredient) return;

  if (!confirm('Supprimer "' + ingredient.name + '" ?')) return;

  // Filtre pour garder tous sauf celui-là
  donneesApp.ingredients = donneesApp.ingredients.filter(function(i) {
    return String(i.id) !== String(id);
  });
  sauvegarder();

  notifSucces('"' + ingredient.name + '" supprimé.');
  afficherIngredients();
}

// Affiche la liste des ingrédients dans la page
function afficherIngredients() {
  const liste = document.getElementById('ingredients-list');
  if (!liste) return;

  // Récupère le texte de recherche s'il y en a un
  const recherche = document.getElementById('ing-search')?.value.toLowerCase().trim() || '';

  // Filtre selon la recherche
  let ingredientsFiltres = donneesApp.ingredients;
  if (recherche) {
    ingredientsFiltres = donneesApp.ingredients.filter(function(i) {
      return i.name.toLowerCase().includes(recherche);
    });
  }

  // Si la liste est vide, on affiche un message sympa
  if (ingredientsFiltres.length === 0) {
    const message = recherche
      ? '🔍 Aucun ingrédient trouvé pour cette recherche.'
      : "🥕 Aucun ingrédient pour l'instant. Ajoutez-en un ci-dessus !";

    liste.innerHTML = '<div class="empty-state" role="status">'
      + '<p class="empty-state-icon">🥕</p>'
      + '<p class="empty-state-text">' + message + '</p>'
      + '</div>';
    return;
  }

  // Affiche le compteur
  let compteur = liste.parentElement.querySelector('.list-counter');
  if (!compteur) {
    compteur = document.createElement('p');
    compteur.className = 'list-counter';
    compteur.style.cssText = 'color:var(--text-light); font-size:.85rem; margin-bottom:.5rem;';
    liste.before(compteur);
  }
  const pluriel = ingredientsFiltres.length > 1 ? 's' : '';
  compteur.textContent = ingredientsFiltres.length + ' ingrédient' + pluriel;

  // Vide la liste et la recrée
  liste.innerHTML = '';

  ingredientsFiltres.forEach(function(ing, index) {
    const emoji = EMOJI_CATEGORIES[ing.category] || '📦';

    const carte = document.createElement('article');
    carte.className = 'item-card';
    carte.setAttribute('role', 'listitem');
    carte.dataset.id = ing.id;

    carte.innerHTML = ''
      + '<div class="item-info">'
      +   '<p class="item-name">' + emoji + ' ' + securiserHTML(ing.name) + '</p>'
      +   '<div class="item-details">'
      +     '<span>💰 ' + formaterEuro(ing.price) + '/' + securiserHTML(ing.unit) + '</span>'
      +     '<span>🔥 ' + formaterNutrition(ing.calories) + ' /100g</span>'
      +     '<span>💪 ' + formaterNutrition(ing.protein, 'g prot.') + '</span>'
      +   '</div>'
      + '</div>'
      + '<div class="item-actions">'
      +   '<button class="btn btn-danger" onclick="supprimerIngredient(\'' + ing.id + '\')"'
      +     ' aria-label="Supprimer ' + securiserHTML(ing.name) + '">'
      +     '🗑️ Supprimer'
      +   '</button>'
      + '</div>';

    // Petite animation décalée (chaque carte apparaît un peu après la précédente)
    carte.style.opacity   = '0';
    carte.style.transform = 'translateY(10px)';
    liste.appendChild(carte);

    setTimeout(function() {
      carte.style.transition = 'opacity .3s ease, transform .3s ease';
      carte.style.opacity    = '1';
      carte.style.transform  = 'translateY(0)';
    }, index * 50);
  });
}

// Ajoute la barre de recherche des ingrédients (appelée au chargement)
function initialiserRechercheIngredients() {
  const liste = document.getElementById('ingredients-list');
  if (!liste) return;

  // On vérifie qu'elle n'existe pas déjà
  if (document.getElementById('ing-search')) return;

  const wrapper = document.createElement('div');
  wrapper.style.cssText = 'position:relative; margin-bottom:1rem;';
  wrapper.innerHTML = ''
    + '<span style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); opacity:.4; pointer-events:none;">🔍</span>'
    + '<input type="search" id="ing-search" placeholder="Rechercher un ingrédient…"'
    + '  aria-label="Filtrer les ingrédients"'
    + '  style="width:100%; padding:.75rem 1rem .75rem 2.6rem; border:2px solid var(--border);'
    + '  border-radius:var(--border-radius); font-family:Commissioner,sans-serif;'
    + '  font-size:.95rem; background:var(--bg-light); color:var(--text-color);">';

  liste.before(wrapper);

  // On relance l'affichage à chaque frappe (avec un petit délai)
  let timerRecherche;
  document.getElementById('ing-search').addEventListener('input', function() {
    clearTimeout(timerRecherche);
    timerRecherche = setTimeout(afficherIngredients, 200);
  });
}

// RECETTES — ajouter, supprimer, afficher


// Filtre actif pour les recettes ('all', 'breakfast', 'lunch', 'dinner')
let filtreRecettes = 'all';

// Ajoute une recette depuis le formulaire
function addRecipe(event) {
  if (event) event.preventDefault();

  const nom           = document.getElementById('recipe-name')?.value.trim();
  const typeRepas     = document.getElementById('recipe-type')?.value     || 'dinner';
  const tempsPrep     = parseInt(document.getElementById('recipe-time')?.value || 30, 10);
  const regime        = document.getElementById('recipe-dietary')?.value  || 'all';
  const ingredientsBrut = document.getElementById('recipe-ingredients')?.value.trim() || '';

  if (!nom) {
    notifErreur('Veuillez saisir un nom de recette.');
    return;
  }

  // On transforme la liste d'ingrédients (séparés par virgules) en tableau
  const listeIngredients = ingredientsBrut
    .split(',')
    .map(function(s) { return s.trim(); })
    .filter(function(s) { return s !== ''; });

  // Estimation automatique du coût en cherchant les ingrédients connus
  let coutEstime  = 0;
  let caloriesTotal = 0;
  let proteinesTotal = 0;

  listeIngredients.forEach(function(nomIng) {
    // On cherche si l'ingrédient existe dans notre liste
    const trouve = donneesApp.ingredients.find(function(i) {
      return i.name.toLowerCase().includes(nomIng.toLowerCase());
    });
    if (trouve) {
      // On estime ~200g par ingrédient (valeur approximative)
      coutEstime     += Number(trouve.price)    * 0.2;
      caloriesTotal  += Number(trouve.calories) * 2;
      proteinesTotal += Number(trouve.protein)  * 2;
    }
  });

  // On s'assure que les valeurs minimales sont respectées
  const nouvelleRecette = {
    id:              genererID(),
    name:            nom,
    meal_type:       typeRepas,
    prep_time:       tempsPrep,
    dietary:         regime,
    ingredients:     listeIngredients,
    ingredients_list: listeIngredients.join(', '),
    estimated_cost:  Math.max(coutEstime, 1.50),  // au minimum 1,50 €
    calories:        Math.max(caloriesTotal, 300), // au minimum 300 kcal
    protein:         Math.max(proteinesTotal, 10), // au minimum 10g de protéines
  };

  donneesApp.recettes.push(nouvelleRecette);
  sauvegarder();

  notifSucces('"' + nom + '" ajoutée avec succès.');
  document.querySelector('.recipe-form')?.reset();
  afficherRecettes();
}

// Supprime une recette par son ID
function supprimerRecette(id) {
  const recette = donneesApp.recettes.find(function(r) {
    return String(r.id) === String(id);
  });
  if (!recette) return;

  if (!confirm('Supprimer la recette "' + recette.name + '" ?')) return;

  donneesApp.recettes = donneesApp.recettes.filter(function(r) {
    return String(r.id) !== String(id);
  });
  sauvegarder();

  notifSucces('"' + recette.name + '" supprimée.');
  afficherRecettes();
}

// Affiche la liste des recettes (avec le filtre actif)
function afficherRecettes() {
  const liste = document.getElementById('recipes-list');
  if (!liste) return;

  // Filtre selon le type de repas sélectionné
  let recettesFiltrees = donneesApp.recettes;
  if (filtreRecettes !== 'all') {
    recettesFiltrees = donneesApp.recettes.filter(function(r) {
      return r.meal_type === filtreRecettes;
    });
  }

  if (recettesFiltrees.length === 0) {
    const suffixe = filtreRecettes !== 'all' ? ' dans cette catégorie' : '';
    liste.innerHTML = '<div class="empty-state" role="status">'
      + '<p class="empty-state-icon">📖</p>'
      + '<p class="empty-state-text">Aucune recette' + suffixe + ' pour l\'instant.</p>'
      + '</div>';
    return;
  }

  liste.innerHTML = '';

  recettesFiltrees.forEach(function(recette, index) {
    const typeInfo = TYPES_REPAS[recette.meal_type] || TYPES_REPAS.dinner;
    const listeIng = recette.ingredients_list
      || (Array.isArray(recette.ingredients) ? recette.ingredients.join(', ') : '—');

    const carte = document.createElement('article');
    carte.className = 'item-card recipe-card';
    carte.dataset.id = recette.id;
    carte.setAttribute('role', 'listitem');
    carte.style.borderLeft = '4px solid ' + typeInfo.couleur;

    carte.innerHTML = ''
      + '<div class="item-info">'
      +   '<p class="item-name">' + typeInfo.emoji + ' ' + securiserHTML(recette.name) + '</p>'
      +   '<div class="item-details">'
      +     '<span>⏱️ ' + (recette.prep_time || '?') + ' min</span>'
      +     '<span>💰 ' + formaterEuro(recette.estimated_cost) + '</span>'
      +     '<span>🔥 ' + formaterNutrition(recette.calories) + '</span>'
      +     '<span>💪 ' + formaterNutrition(recette.protein, 'g prot.') + '</span>'
      +     '<span>' + typeInfo.emoji + ' ' + typeInfo.label + '</span>'
      +     '<span class="dietary-tag">' + securiserHTML(LABELS_REGIME[recette.dietary] || recette.dietary) + '</span>'
      +   '</div>'
      +   (listeIng ? '<p class="rec-ingredients">📝 ' + securiserHTML(listeIng) + '</p>' : '')
      + '</div>'
      + '<div class="item-actions">'
      +   '<button class="btn btn-danger" onclick="supprimerRecette(\'' + recette.id + '\')"'
      +     ' aria-label="Supprimer ' + securiserHTML(recette.name) + '">'
      +     '🗑️ Supprimer'
      +   '</button>'
      + '</div>';

    carte.style.opacity   = '0';
    carte.style.transform = 'translateY(10px)';
    liste.appendChild(carte);

    setTimeout(function() {
      carte.style.transition = 'opacity .3s ease, transform .3s ease';
      carte.style.opacity    = '1';
      carte.style.transform  = 'translateY(0)';
    }, index * 55);
  });
}

// Crée les boutons de filtre au-dessus des recettes
function initialiserFiltresRecettes() {
  const liste = document.getElementById('recipes-list');
  if (!liste) return;

  // On ne crée pas deux fois les filtres
  if (document.querySelector('.filter-bar')) return;

  const barreFiltre = document.createElement('div');
  barreFiltre.className = 'filter-bar';
  barreFiltre.setAttribute('role', 'group');
  barreFiltre.setAttribute('aria-label', 'Filtrer les recettes');
  barreFiltre.style.cssText = 'display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem;';

  const filtres = [
    { valeur: 'all',       label: '🍽️ Tout'       },
    { valeur: 'breakfast', label: '🥐 Petit-déj'   },
    { valeur: 'lunch',     label: '☀️ Déjeuner'    },
    { valeur: 'dinner',    label: '🌙 Dîner'       },
  ];

  filtres.forEach(function(filtre) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = filtre.label;
    btn.dataset.filtre = filtre.valeur;
    btn.style.cssText = 'padding:.4rem 1rem; font-size:.85rem; border-radius:20px; cursor:pointer;'
      + 'border:2px solid var(--border); transition:all .25s ease;';

    // Style du bouton actif
    if (filtre.valeur === 'all') {
      btn.style.background = 'var(--primary)';
      btn.style.color      = 'white';
    } else {
      btn.style.background = 'var(--bg-hover)';
      btn.style.color      = 'var(--text-color)';
    }

    btn.addEventListener('click', function() {
      filtreRecettes = filtre.valeur;

      // Met à jour le style de tous les boutons
      barreFiltre.querySelectorAll('button').forEach(function(b) {
        const estActif = b.dataset.filtre === filtre.valeur;
        b.style.background = estActif ? 'var(--primary)' : 'var(--bg-hover)';
        b.style.color      = estActif ? 'white'          : 'var(--text-color)';
      });

      afficherRecettes();
    });

    barreFiltre.appendChild(btn);
  });

  liste.before(barreFiltre);
}


// GÉNÉRATION DU MENU — la fonctionnalité principale !

function generateMenu() {
  const budget   = parseFloat(document.getElementById('budget')?.value  || 50);
  const personnes = parseInt(document.getElementById('persons')?.value  || 2, 10);
  const regime    = document.getElementById('dietary')?.value           || 'all';

  // Quelques vérifications
  if (isNaN(budget) || budget <= 0) {
    notifErreur('Le budget saisi est invalide.');
    return;
  }
  if (isNaN(personnes) || personnes < 1) {
    notifErreur('Le nombre de personnes est invalide.');
    return;
  }
  if (donneesApp.recettes.length < 3) {
    notifAvert('Ajoutez au moins 3 recettes avant de générer un menu.');
    return;
  }

  // Affiche un loader pendant la génération
  afficherLoader();

  // On génère le menu côté client (pas besoin de serveur)
  // On utilise setTimeout pour laisser le loader s'afficher
  setTimeout(function() {
    try {
      const menu = genererMenuLocalement(budget, personnes, regime);

      if (!menu.jours || menu.jours.length === 0) {
        throw new Error('Aucune recette correspondante trouvée pour ce régime.');
      }

      // On sauvegarde le menu et on met à jour l'interface
      donneesApp.menuActuel = menu;
      sauvegarder();

      mettreAJourResume();
      afficherResultatGeneration(menu, budget);

      notifSucces('Menu généré avec succès !', 2500);

    } catch (err) {
      notifErreur('Impossible de générer le menu : ' + err.message);
      cacherLoader();
    }
  }, 600); // petit délai pour l'effet visuel
}

// Génère un menu aléatoire en choisissant des recettes au hasard
function genererMenuLocalement(budget, personnes, regime) {
  // On sépare les recettes par type
  const petitsDej = donneesApp.recettes.filter(function(r) {
    return r.meal_type === 'breakfast'
      && (regime === 'all' || r.dietary === regime || r.dietary === 'all');
  });
  const dejeuners = donneesApp.recettes.filter(function(r) {
    return r.meal_type === 'lunch'
      && (regime === 'all' || r.dietary === regime || r.dietary === 'all');
  });
  const diners = donneesApp.recettes.filter(function(r) {
    return r.meal_type === 'dinner'
      && (regime === 'all' || r.dietary === regime || r.dietary === 'all');
  });

  // Choisit une recette aléatoire dans un tableau
  // (retourne null si le tableau est vide)
  function choisirAuHasard(tableau) {
    if (tableau.length === 0) return null;
    const index = Math.floor(Math.random() * tableau.length);
    return tableau[index];
  }

  let coutTotal = 0;

  // On construit les 7 jours
  const jours = JOURS.map(function(nomJour, index) {
    const petitDej = choisirAuHasard(petitsDej);
    const dejeuner = choisirAuHasard(dejeuners);
    const diner    = choisirAuHasard(diners);

    // Additionne les coûts des repas de la journée
    [petitDej, dejeuner, diner].forEach(function(repas) {
      if (repas) coutTotal += Number(repas.estimated_cost || 0);
    });

    return {
      nom:   nomJour,
      index: index,
      repas: {
        breakfast: petitDej,
        lunch:     dejeuner,
        dinner:    diner,
      },
    };
  });

  // On retourne le menu complet
  return {
    id:         genererID(),
    budget:     budget,
    personnes:  personnes,
    regime:     regime,
    cout_total: coutTotal,
    jours:      jours,
    // On garde aussi le format "days" pour la compatibilité avec l'export
    days:       jours.map(function(j) {
      return { name: j.nom, index: j.index, meals: j.repas };
    }),
  };
}

// Affiche un message de chargement pendant la génération
function afficherLoader() {
  const zone = document.getElementById('generation-result');
  if (!zone) return;
  zone.innerHTML = ''
    + '<div class="alert alert-info" role="status" style="margin-top:1.5rem;">'
    +   '<span style="display:inline-block; width:1rem; height:1rem; border:2px solid currentColor;'
    +   ' border-top-color:transparent; border-radius:50%; animation:spin 0.7s linear infinite;'
    +   ' margin-right:.5rem; vertical-align:middle;" aria-hidden="true"></span>'
    +   '⏳ Génération du menu en cours…'
    + '</div>';
}

function cacherLoader() {
  const zone = document.getElementById('generation-result');
  if (zone) zone.innerHTML = '';
}

// Affiche le résultat après la génération (budget vs coût réel)
function afficherResultatGeneration(menu, budget) {
  const zone = document.getElementById('generation-result');
  if (!zone) return;

  const difference = budget - Number(menu.cout_total || 0);
  const classeAlerte = difference >= 0 ? 'alert-success' : 'alert-warning';
  const icone        = difference >= 0 ? '✅' : '⚠️';
  const labelDiff    = difference >= 0
    ? 'Économie : ' + formaterEuro(difference)
    : 'Dépassement : ' + formaterEuro(Math.abs(difference));

  zone.innerHTML = ''
    + '<div class="alert ' + classeAlerte + '" role="status" style="margin-top:1.5rem;">'
    +   '<strong>' + icone + ' Menu généré !</strong><br>'
    +   'Budget : <strong>' + formaterEuro(budget) + '</strong>&nbsp;|&nbsp;'
    +   'Coût estimé : <strong>' + formaterEuro(menu.cout_total) + '</strong>&nbsp;|&nbsp;'
    +   labelDiff
    +   '<br><br>'
    +   '<button class="btn btn-primary" onclick="showTab(\'menu\')" style="margin-top:.5rem;">'
    +     'Voir mon menu →'
    +   '</button>'
    + '</div>';

  animerApparition(zone.firstElementChild);
}


// AFFICHAGE DU MENU — la grille des 7 jours


function afficherMenu() {
  const grille = document.getElementById('weekly-menu');
  if (!grille) return;

  const menu = donneesApp.menuActuel;

  // Si pas de menu, on affiche un message
  if (!menu || !menu.jours || menu.jours.length === 0) {
    grille.innerHTML = ''
      + '<div class="empty-state" role="status">'
      +   '<p class="empty-state-icon">📅</p>'
      +   '<p class="empty-state-text">Aucun menu généré pour l\'instant.</p>'
      +   '<p>Allez dans "Générer un menu" pour créer votre planning !</p>'
      + '</div>';
    return;
  }

  grille.innerHTML = '';

  // On crée une carte pour chaque jour
  menu.jours.forEach(function(jour, index) {
    const carte = creerCarteJour(jour, index);

    // Animation décalée par jour
    carte.style.opacity   = '0';
    carte.style.transform = 'translateY(20px)';
    grille.appendChild(carte);

    setTimeout(function() {
      carte.style.transition = 'opacity .4s ease ' + (index * 0.07) + 's, transform .4s ease ' + (index * 0.07) + 's';
      carte.style.opacity    = '1';
      carte.style.transform  = 'translateY(0)';
    }, 50);
  });
}

// Crée la carte HTML d'un jour
function creerCarteJour(jour, index) {
  const carte = document.createElement('section');
  carte.className = 'day-card';
  carte.setAttribute('aria-labelledby', 'jour-titre-' + index);

  const nomJour = jour.nom || JOURS[index] || 'Jour ' + (index + 1);
  const emoji   = EMOJI_JOURS[index] || '📅';
  const repas   = jour.repas || {};

  carte.innerHTML = ''
    + '<h3 class="day-title" id="jour-titre-' + index + '">'
    +   '<span aria-hidden="true">' + emoji + '</span> ' + securiserHTML(nomJour)
    + '</h3>'
    + creerSlotRepas('breakfast', repas.breakfast)
    + creerSlotRepas('lunch',     repas.lunch)
    + creerSlotRepas('dinner',    repas.dinner);

  return carte;
}

// Crée le HTML pour un créneau repas (petit-déjeuner, déjeuner ou dîner)
function creerSlotRepas(type, recette) {
  const infosType = TYPES_REPAS[type] || TYPES_REPAS.dinner;
  const styleFond = 'background:' + infosType.couleur + '; border-radius:var(--radius-sm);';

  // Cas où il n'y a pas de recette pour ce créneau
  if (!recette) {
    return '<div class="meal-slot" style="' + styleFond + '">'
      + '<p class="meal-type">' + infosType.emoji + ' ' + infosType.label + '</p>'
      + '<p class="empty-meal">—</p>'
      + '</div>';
  }

  return '<div class="meal-slot" style="' + styleFond + '">'
    + '<p class="meal-type">' + infosType.emoji + ' ' + infosType.label + '</p>'
    + '<p class="meal-name">' + securiserHTML(recette.name) + '</p>'
    + '<div class="meal-info">'
    +   '<span>⏱️ ' + (recette.prep_time || '?') + ' min</span>'
    +   '<span>💰 ' + formaterEuro(recette.estimated_cost) + '</span>'
    +   '<span>🔥 ' + formaterNutrition(recette.calories) + '</span>'
    + '</div>'
    + '</div>';
}

// Efface le menu actuel
function clearMenu() {
  if (!confirm('Effacer le menu de la semaine ?')) return;

  donneesApp.menuActuel = null;
  sauvegarder();

  afficherMenu();
  mettreAJourResume();

  notifInfo('Menu effacé.');
}


// RÉSUMÉ FINANCIER ET NUTRITIONNEL — en bas de page -> TODO


// EXPORT PDF — télécharger le menu en PDF -> TODO


// EXPORT ICS — exporter le menu dans un calendrier -> TODO



// STYLES CSS DYNAMIQUES — injectés au chargement


// Quelques styles supplémentaires qu'on ajoute directement en JS
// (pour éviter de modifier le fichier CSS)
(function ajouterStyles() {
  const style = document.createElement('style');
  style.textContent = `
    /* Animation rotation (utilisée pour le spinner de chargement) */
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Badge régime alimentaire sur les recettes */
    .dietary-tag {
      background: var(--bg-hover);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 0.1rem 0.6rem;
      font-size: 0.78rem;
      color: var(--text-medium);
    }

    /* Liste des ingrédients dans une recette */
    .rec-ingredients {
      font-size: 0.82rem;
      color: var(--text-light);
      margin-top: 0.4rem;
      font-style: italic;
    }

    /* Compteur d'éléments dans une liste */
    .list-counter {
      font-size: .83rem;
      color: var(--text-light);
      margin-bottom: .5rem;
    }

    /* Indicateur visuel sur le bouton de nav actif */
    .nav-btn.active::after {
      content: '';
      display: block;
      height: 3px;
      background: var(--btn-color);
      border-radius: 2px;
      margin-top: 3px;
      animation: scaleIn .3s ease;
    }

    @keyframes scaleIn {
      from { transform: scaleX(0); }
      to   { transform: scaleX(1); }
    }

    /* Espace pour que le scroll s'arrête au bon endroit */
    .tab-content { scroll-margin-top: 1rem; }

    /* Met en valeur le coût dans les cartes de jour */
    .meal-info span:nth-child(2) { font-weight: 600; color: var(--secondary); }

    /* Animation d'apparition générale */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
  `;
  document.head.appendChild(style);
})();



// DÉMARRAGE — tout commence ici quand la page est chargée

document.addEventListener('DOMContentLoaded', function() {

  // 1. On charge les données sauvegardées
  charger();

  // 2. On initialise les composants de l'interface
  initialiserRechercheIngredients();
  initialiserFiltresRecettes();

  // 3. On affiche les données existantes
  afficherIngredients();
  afficherRecettes();

  // Si un menu était déjà sauvegardé, on l'affiche
  if (donneesApp.menuActuel) {
    afficherMenu();
    mettreAJourResume();
  }

  // 4. Navigation clavier (Alt+1 à Alt+4 pour changer d'onglet)
  const onglets = ['generate', 'ingredients', 'recipes', 'menu'];
  document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key >= '1' && e.key <= '4') {
      e.preventDefault();
      const index = parseInt(e.key, 10) - 1;
      if (onglets[index]) showTab(onglets[index]);
    }
  });

  // 5. Navigation flèches dans la barre de navigation
  document.querySelectorAll('.nav-btn').forEach(function(btn) {
    btn.addEventListener('keydown', function(e) {
      const tousLesBtns = Array.from(document.querySelectorAll('.nav-btn'));
      const monIndex    = tousLesBtns.indexOf(btn);
      if (e.key === 'ArrowRight' && monIndex < tousLesBtns.length - 1) {
        tousLesBtns[monIndex + 1].focus();
      }
      if (e.key === 'ArrowLeft' && monIndex > 0) {
        tousLesBtns[monIndex - 1].focus();
      }
    });
  });

  // 6. Lien "passer au contenu" pour l'accessibilité
  const lienSkip = document.querySelector('.skip-link');
  if (lienSkip) {
    lienSkip.addEventListener('click', function(e) {
      e.preventDefault();
      const contenuPrincipal = document.getElementById('main-content');
      if (contenuPrincipal) {
        contenuPrincipal.tabIndex = -1;
        contenuPrincipal.focus();
      }
    });
  }

  console.info('%c🍽️ Planificateur de Repas — Prêt !', 'color:#576238; font-size:14px; font-weight:bold;');
});