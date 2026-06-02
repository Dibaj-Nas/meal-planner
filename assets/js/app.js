// app.js — Planificateur de Repas
// Ce fichier gère toute la logique de l'application :
//   - Onglets de navigation
//   - Ingrédients (ajout, suppression, affichage)
//   - Recettes (ajout, suppression, affichage)
//   - Génération du menu hebdomadaire
//   - Résumé du coût et des calories
//   - Export en PDF et en calendrier (ICS)
//   - Menu utilisateur (dropdown)

'use strict';

// ═══════════════════════════════════════════════════════════════
// DONNÉES DE BASE — listes et libellés utilisés partout
// ═══════════════════════════════════════════════════════════════

// Les 7 jours de la semaine avec leurs emojis
const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
const EMOJI_JOURS = ['🌅', '☀️', '🌤️', '⛅', '🎎', '🎉', '😴'];

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
  all:        'Tout',
  vegetarian: 'Végétarien',
  vegan:      'Vegan',
  'no-pork':  'Sans Porc',
};


// ═══════════════════════════════════════════════════════════════
// STOCKAGE — sauvegarder et charger les données
// ═══════════════════════════════════════════════════════════════

// Données en mémoire (chargées au démarrage)
let donneesApp = {
  ingredients: [],
  recettes: [],
  menuActuel: null,
};

// Sauvegarde dans localStorage
function sauvegarder() {
  try {
    localStorage.setItem('mealplanner_data', JSON.stringify(donneesApp));
  } catch (err) {
    console.warn('Impossible de sauvegarder :', err);
  }
}

// Chargement depuis localStorage (au démarrage)
function charger() {
  try {
    const sauvegarde = localStorage.getItem('mealplanner_data');
    if (sauvegarde) {
      const parsed = JSON.parse(sauvegarde);
      donneesApp = { ...donneesApp, ...parsed };
    }
  } catch (err) {
    console.warn('Impossible de charger les données :', err);
  }
}


// ═══════════════════════════════════════════════════════════════
// PETITS UTILITAIRES
// ═══════════════════════════════════════════════════════════════

// Formate un nombre en euros (ex: 12.5 → "12,50 €")
function formaterEuro(valeur) {
  const nombre = Number(valeur || 0);
  return nombre.toLocaleString('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ' €';
}

// Formate une valeur nutritionnelle (ex: 150 → "150 kcal")
function formaterNutrition(valeur, unite) {
  const u = unite || 'kcal';
  return Math.round(valeur || 0).toLocaleString('fr-FR') + ' ' + u;
}

// Génère un identifiant unique
function genererID() {
  return '_' + Math.random().toString(36).slice(2, 9);
}

// Protège contre les attaques XSS
function securiserHTML(texte) {
  return String(texte ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// Petite animation d'apparition
function animerApparition(element) {
  if (!element) return;
  element.style.opacity   = '0';
  element.style.transform = 'translateY(12px)';
  element.style.transition = 'opacity 0.35s ease, transform 0.35s ease';

  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      element.style.opacity   = '1';
      element.style.transform = 'translateY(0)';
    });
  });
}


// ═══════════════════════════════════════════════════════════════
// NAVIGATION — changer d'onglet
// ═══════════════════════════════════════════════════════════════

let ongletActif = 'generate';

function showTab(nomOnglet) {
  if (ongletActif === nomOnglet) return;

  document.querySelectorAll('.tab-content').forEach(function(onglet) {
    onglet.classList.remove('active');
  });

  document.querySelectorAll('.nav-btn').forEach(function(btn) {
    btn.classList.remove('active');
  });

  const panneau = document.getElementById('tab-' + nomOnglet);
  if (panneau) {
    panneau.classList.add('active');

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

  document.querySelectorAll('.nav-btn').forEach(function(btn) {
    const onclick = btn.getAttribute('onclick') || '';
    if (onclick.includes("'" + nomOnglet + "'")) {
      btn.classList.add('active');
    }
  });

  ongletActif = nomOnglet;

  if (nomOnglet === 'menu') {
    afficherMenu();
    mettreAJourResume();
  }
}


// ═══════════════════════════════════════════════════════════════
// TOASTS — notifications en bas de page
// ═══════════════════════════════════════════════════════════════

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

// Affiche une notification (type = 'success', 'error', 'warning', 'info')
function afficherNotification(message, type, duree) {
  const dureeMs = duree || 3000;

  const couleurs = {
    success: { fond: '#d8f3dc', texte: '#1b4332', bord: '#4a9060' },
    error:   { fond: '#f8d7da', texte: '#721c24', bord: '#CE2A2A' },
    warning: { fond: '#ffe5b4', texte: '#7f3900', bord: '#e07c28' },
    info:    { fond: '#d1ecf1', texte: '#0c5460', bord: '#17a2b8' },
  };
  const style = couleurs[type] || couleurs.info;

  const toast = document.createElement('div');
  toast.style.cssText = [
    'background:' + style.fond,
    'color:' + style.texte,
    'border-left:4px solid ' + style.bord,
    'padding:.75rem 1.2rem',
    'border-radius:8px',
    'font-size:.9rem',
    'font-weight:500',
    'pointer-events:auto',
    'opacity:0',
    'transform:translateX(20px)',
    'transition:opacity .3s ease, transform .3s ease',
    'max-width:320px',
    'box-shadow:0 2px 12px rgba(0,0,0,.12)',
  ].join(';');
  toast.textContent = message;

  obtenirConteneurToasts().appendChild(toast);

  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      toast.style.opacity   = '1';
      toast.style.transform = 'translateX(0)';
    });
  });

  setTimeout(function() {
    toast.style.opacity   = '0';
    toast.style.transform = 'translateX(20px)';
    setTimeout(function() { toast.remove(); }, 350);
  }, dureeMs);
}

function notifSucces(msg, duree) { afficherNotification(msg, 'success', duree); }
function notifErreur(msg, duree) { afficherNotification(msg, 'error',   duree); }
function notifAvert(msg, duree)  { afficherNotification(msg, 'warning', duree); }
function notifInfo(msg, duree)   { afficherNotification(msg, 'info',    duree); }


// ═══════════════════════════════════════════════════════════════
// INGRÉDIENTS — ajouter, supprimer, afficher
// ═══════════════════════════════════════════════════════════════

function addIngredient(event) {
  if (event) event.preventDefault();

  const nom      = document.getElementById('ingredient-name')?.value.trim();
  const prix     = parseFloat(document.getElementById('ingredient-price')?.value  || 0);
  const unite    = document.getElementById('ingredient-unit')?.value     || 'piece';
  const calories = parseFloat(document.getElementById('ingredient-calories')?.value || 0);
  const proteines= parseFloat(document.getElementById('ingredient-protein')?.value  || 0);
  const categorie= document.getElementById('ingredient-category')?.value || 'other';

  if (!nom) {
    notifErreur("Veuillez saisir un nom d'ingrédient.");
    return;
  }
  if (isNaN(prix) || prix < 0) {
    notifErreur('Le prix saisi est invalide.');
    return;
  }

  const nouvelIngredient = {
    id:       genererID(),
    name:     nom,
    price:    prix,
    unit:     unite,
    calories: calories,
    protein:  proteines,
    category: categorie,
  };

  donneesApp.ingredients.push(nouvelIngredient);
  sauvegarder();

  notifSucces('"' + nom + '" ajouté avec succès.');
  document.querySelector('.ingredient-form')?.reset();
  afficherIngredients();
}

function supprimerIngredient(id) {
  const ingredient = donneesApp.ingredients.find(function(i) {
    return String(i.id) === String(id);
  });
  if (!ingredient) return;

  if (!confirm('Supprimer "' + ingredient.name + '" ?')) return;

  donneesApp.ingredients = donneesApp.ingredients.filter(function(i) {
    return String(i.id) !== String(id);
  });
  sauvegarder();

  notifSucces('"' + ingredient.name + '" supprimé.');
  afficherIngredients();
}

function afficherIngredients() {
  const liste = document.getElementById('ingredients-list');
  if (!liste) return;

  const recherche = document.getElementById('ing-search')?.value.toLowerCase().trim() || '';

  let ingredientsFiltres = donneesApp.ingredients;
  if (recherche) {
    ingredientsFiltres = donneesApp.ingredients.filter(function(i) {
      return i.name.toLowerCase().includes(recherche);
    });
  }

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

  // Compteur
  let compteur = liste.parentElement.querySelector('.list-counter');
  if (!compteur) {
    compteur = document.createElement('p');
    compteur.className = 'list-counter';
    liste.before(compteur);
  }
  const pluriel = ingredientsFiltres.length > 1 ? 's' : '';
  compteur.textContent = ingredientsFiltres.length + ' ingrédient' + pluriel;

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

function initialiserRechercheIngredients() {
  const liste = document.getElementById('ingredients-list');
  if (!liste || document.getElementById('ing-search')) return;

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

  let timerRecherche;
  document.getElementById('ing-search').addEventListener('input', function() {
    clearTimeout(timerRecherche);
    timerRecherche = setTimeout(afficherIngredients, 200);
  });
}


// ═══════════════════════════════════════════════════════════════
// RECETTES — ajouter, supprimer, afficher
// ═══════════════════════════════════════════════════════════════

let filtreRecettes = 'all';

function addRecipe(event) {
  if (event) event.preventDefault();

  const nom             = document.getElementById('recipe-name')?.value.trim();
  const typeRepas       = document.getElementById('recipe-type')?.value     || 'dinner';
  const tempsPrep       = parseInt(document.getElementById('recipe-time')?.value || 30, 10);
  const regime          = document.getElementById('recipe-dietary')?.value  || 'all';
  const ingredientsBrut = document.getElementById('recipe-ingredients')?.value.trim() || '';

  if (!nom) {
    notifErreur('Veuillez saisir un nom de recette.');
    return;
  }

  const listeIngredients = ingredientsBrut
    .split(',')
    .map(function(s) { return s.trim(); })
    .filter(function(s) { return s !== ''; });

  let coutEstime    = 0;
  let caloriesTotal = 0;
  let proteinesTotal= 0;

  listeIngredients.forEach(function(nomIng) {
    const trouve = donneesApp.ingredients.find(function(i) {
      return i.name.toLowerCase().includes(nomIng.toLowerCase());
    });
    if (trouve) {
      coutEstime     += Number(trouve.price)    * 0.2;
      caloriesTotal  += Number(trouve.calories) * 2;
      proteinesTotal += Number(trouve.protein)  * 2;
    }
  });

  const nouvelleRecette = {
    id:               genererID(),
    name:             nom,
    meal_type:        typeRepas,
    prep_time:        tempsPrep,
    dietary:          regime,
    ingredients:      listeIngredients,
    ingredients_list: listeIngredients.join(', '),
    estimated_cost:   Math.max(coutEstime, 1.50),
    calories:         Math.max(caloriesTotal, 300),
    protein:          Math.max(proteinesTotal, 10),
  };

  donneesApp.recettes.push(nouvelleRecette);
  sauvegarder();

  notifSucces('"' + nom + '" ajoutée avec succès.');
  document.querySelector('.recipe-form')?.reset();
  afficherRecettes();
}

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

function afficherRecettes() {
  const liste = document.getElementById('recipes-list');
  if (!liste) return;

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

function initialiserFiltresRecettes() {
  const liste = document.getElementById('recipes-list');
  if (!liste || document.querySelector('.filter-bar')) return;

  const barreFiltre = document.createElement('div');
  barreFiltre.className = 'filter-bar';
  barreFiltre.setAttribute('role', 'group');
  barreFiltre.setAttribute('aria-label', 'Filtrer les recettes');
  barreFiltre.style.cssText = 'display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem;';

  const filtres = [
    { valeur: 'all',       label: '🍽️ Tout'     },
    { valeur: 'breakfast', label: '🥐 Petit-déj' },
    { valeur: 'lunch',     label: '☀️ Déjeuner'  },
    { valeur: 'dinner',    label: '🌙 Dîner'     },
  ];

  filtres.forEach(function(filtre) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = filtre.label;
    btn.dataset.filtre = filtre.valeur;
    btn.style.cssText = 'padding:.4rem 1rem; font-size:.85rem; border-radius:20px; cursor:pointer;'
      + 'border:2px solid var(--border); transition:all .25s ease;';

    if (filtre.valeur === 'all') {
      btn.style.background = 'var(--primary)';
      btn.style.color      = 'white';
    } else {
      btn.style.background = 'var(--bg-hover)';
      btn.style.color      = 'var(--text-color)';
    }

    btn.addEventListener('click', function() {
      filtreRecettes = filtre.valeur;

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


// ═══════════════════════════════════════════════════════════════
// GÉNÉRATION DU MENU
// ═══════════════════════════════════════════════════════════════

function generateMenu() {
  const budget    = parseFloat(document.getElementById('budget')?.value  || 50);
  const personnes = parseInt(document.getElementById('persons')?.value   || 2, 10);
  const regime    = document.getElementById('dietary')?.value            || 'all';

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

  afficherLoader();

  setTimeout(function() {
    try {
      const menu = genererMenuLocalement(budget, personnes, regime);

      if (!menu.jours || menu.jours.length === 0) {
        throw new Error('Aucune recette correspondante trouvée pour ce régime.');
      }

      donneesApp.menuActuel = menu;
      sauvegarder();

      mettreAJourResume();
      afficherResultatGeneration(menu, budget);

      notifSucces('Menu généré avec succès !', 2500);

    } catch (err) {
      notifErreur('Impossible de générer le menu : ' + err.message);
      cacherLoader();
    }
  }, 600);
}

function genererMenuLocalement(budget, personnes, regime) {
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

  function choisirAuHasard(tableau) {
    if (tableau.length === 0) return null;
    return tableau[Math.floor(Math.random() * tableau.length)];
  }

  let coutTotal = 0;

  const jours = JOURS.map(function(nomJour, index) {
    const petitDej = choisirAuHasard(petitsDej);
    const dejeuner = choisirAuHasard(dejeuners);
    const diner    = choisirAuHasard(diners);

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

  return {
    id:         genererID(),
    budget:     budget,
    personnes:  personnes,
    regime:     regime,
    cout_total: coutTotal,
    jours:      jours,
    days:       jours.map(function(j) {
      return { name: j.nom, index: j.index, meals: j.repas };
    }),
  };
}

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

function afficherResultatGeneration(menu, budget) {
  const zone = document.getElementById('generation-result');
  if (!zone) return;

  const difference   = budget - Number(menu.cout_total || 0);
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


// ═══════════════════════════════════════════════════════════════
// AFFICHAGE DU MENU — grille des 7 jours
// ═══════════════════════════════════════════════════════════════

function afficherMenu() {
  const grille = document.getElementById('weekly-menu');
  if (!grille) return;

  const menu = donneesApp.menuActuel;

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

  menu.jours.forEach(function(jour, index) {
    const carte = creerCarteJour(jour, index);

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

function creerSlotRepas(type, recette) {
  const infosType = TYPES_REPAS[type] || TYPES_REPAS.dinner;
  const styleFond = 'background:' + infosType.couleur + '; border-radius:var(--radius-sm);';

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

function clearMenu() {
  if (!confirm('Effacer le menu de la semaine ?')) return;

  donneesApp.menuActuel = null;
  sauvegarder();

  afficherMenu();
  mettreAJourResume();

  notifInfo('Menu effacé.');
}


// ═══════════════════════════════════════════════════════════════
// RÉSUMÉ FINANCIER ET NUTRITIONNEL
// ═══════════════════════════════════════════════════════════════

function mettreAJourResume() {
  const menu = donneesApp.menuActuel;

  if (!menu || !menu.jours) {
    reinitialiserResume();
    return;
  }

  const toutesLesRecettes = [];
  menu.jours.forEach(function(jour) {
    const repas = jour.repas || {};
    if (repas.breakfast) toutesLesRecettes.push(repas.breakfast);
    if (repas.lunch)     toutesLesRecettes.push(repas.lunch);
    if (repas.dinner)    toutesLesRecettes.push(repas.dinner);
  });

  let coutTotal      = 0;
  let caloriesTotal  = 0;
  let proteinesTotal = 0;

  toutesLesRecettes.forEach(function(recette) {
    coutTotal      += Number(recette.estimated_cost || 0);
    caloriesTotal  += Number(recette.calories       || 0);
    proteinesTotal += Number(recette.protein        || 0);
  });

  const nbRepas     = toutesLesRecettes.length || 1;
  const nbPersonnes = Number(menu.personnes || 2);

  animerCompteur(document.getElementById('total-cost'),      coutTotal,               formaterEuro);
  animerCompteur(document.getElementById('cost-per-meal'),   coutTotal / nbRepas,     formaterEuro);
  animerCompteur(document.getElementById('cost-per-person'), coutTotal / nbPersonnes, formaterEuro);
  animerCompteur(document.getElementById('total-calories'),  caloriesTotal,           function(v) { return formaterNutrition(v); });
  animerCompteur(document.getElementById('total-protein'),   proteinesTotal,          function(v) { return formaterNutrition(v, 'g'); });
  animerCompteur(document.getElementById('calories-per-day'),caloriesTotal / 7,       function(v) { return formaterNutrition(v); });

  afficherBadgeBudget(coutTotal, Number(menu.budget || 0));
}

function reinitialiserResume() {
  const el = function(id) { return document.getElementById(id); };
  if (el('total-cost'))       el('total-cost').textContent       = '0,00 €';
  if (el('cost-per-meal'))    el('cost-per-meal').textContent    = '0,00 €';
  if (el('cost-per-person'))  el('cost-per-person').textContent  = '0,00 €';
  if (el('total-calories'))   el('total-calories').textContent   = '0 kcal';
  if (el('total-protein'))    el('total-protein').textContent    = '0 g';
  if (el('calories-per-day')) el('calories-per-day').textContent = '0 kcal';
}

function animerCompteur(element, valeurCible, formateur, dureeMs) {
  if (!element) return;
  const duree = dureeMs || 800;
  const debut = performance.now();

  function actualiser(maintenant) {
    const progression = Math.min((maintenant - debut) / duree, 1);
    const facteur = 1 - Math.pow(1 - progression, 3);
    element.textContent = formateur(valeurCible * facteur);
    if (progression < 1) requestAnimationFrame(actualiser);
  }
  requestAnimationFrame(actualiser);
}

/**
 * Affiche le badge budget via des classes CSS (plus de style inline).
 * Les classes .badge-ok et .badge-warn sont définies dans style.css.
 */
function afficherBadgeBudget(cout, budget) {
  let badge = document.getElementById('budget-badge');

  if (!badge) {
    badge = document.createElement('div');
    badge.id = 'budget-badge';
    const premiereSummaryCard = document.querySelector('.summary-card');
    if (premiereSummaryCard) premiereSummaryCard.appendChild(badge);
  }

  if (budget <= 0) {
    badge.hidden = true;
    return;
  }

  badge.hidden = false;
  const difference = budget - cout;

  badge.classList.remove('badge-ok', 'badge-warn');

  if (difference >= 0) {
    badge.textContent = '✅ Économie : ' + formaterEuro(difference);
    badge.classList.add('badge-ok');
  } else {
    badge.textContent = '⚠️ Dépassement : ' + formaterEuro(Math.abs(difference));
    badge.classList.add('badge-warn');
  }
}


// ═══════════════════════════════════════════════════════════════
// EXPORT PDF
// ═══════════════════════════════════════════════════════════════

async function exportToPDF() {
  const menu = donneesApp.menuActuel;

  if (!menu || !menu.jours || menu.jours.length === 0) {
    notifAvert("Générez d'abord un menu avant d'exporter.");
    return;
  }

  const jsPDF = await chargerJsPDF();

  if (!jsPDF) {
    notifInfo('Ouverture de la fenêtre impression du navigateur…');
    window.print();
    return;
  }

  notifInfo('Génération du PDF en cours…');

  const doc     = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
  const marge   = 15;
  const largCol = (210 - marge * 2) / 7;
  let y         = marge;

  // En-tête
  doc.setFillColor(87, 98, 56);
  doc.rect(0, 0, 210, 28, 'F');
  doc.setTextColor(243, 231, 217);
  doc.setFontSize(18);
  doc.setFont('helvetica', 'bold');
  doc.text('Planificateur de Repas — Menu de la Semaine', 105, 12, { align: 'center' });

  const dateAujourdhui = new Date().toLocaleDateString('fr-FR', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  });
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.text('Généré le ' + dateAujourdhui, 105, 22, { align: 'center' });

  y = 36;

  // Résumé rapide
  let coutTotal     = 0;
  let caloriesTotal = 0;
  menu.jours.forEach(function(jour) {
    const repas = jour.repas || {};
    [repas.breakfast, repas.lunch, repas.dinner].forEach(function(r) {
      if (r) {
        coutTotal     += Number(r.estimated_cost || 0);
        caloriesTotal += Number(r.calories       || 0);
      }
    });
  });
  const difference = Number(menu.budget || 0) - coutTotal;

  doc.setFillColor(240, 234, 220);
  doc.rect(marge, y, 210 - marge * 2, 14, 'F');
  doc.setTextColor(42, 25, 31);
  doc.setFontSize(9);
  doc.text('Budget : '      + formaterEuro(menu.budget),                                                  marge + 4,   y + 5);
  doc.text('Coût estimé : ' + formaterEuro(coutTotal),                                                    marge + 50,  y + 5);
  doc.text((difference >= 0 ? 'Économie' : 'Dépassement') + ' : ' + formaterEuro(Math.abs(difference)), marge + 110, y + 5);
  doc.text('Personnes : '   + (menu.personnes || 2),                                                      marge + 4,   y + 11);
  doc.text('Calories totales : ' + formaterNutrition(caloriesTotal),                                      marge + 50,  y + 11);
  doc.text('Préférence : '  + (LABELS_REGIME[menu.regime] || menu.regime),                                marge + 110, y + 11);

  y += 20;

  // En-têtes colonnes jours
  doc.setFillColor(84, 67, 73);
  doc.rect(marge, y, 210 - marge * 2, 7, 'F');
  doc.setTextColor(243, 231, 217);
  doc.setFontSize(7.5);
  doc.setFont('helvetica', 'bold');

  menu.jours.forEach(function(jour, i) {
    const x   = marge + i * largCol;
    const nom = jour.nom || JOURS[i] || '';
    doc.text(nom.substring(0, 3).toUpperCase(), x + largCol / 2, y + 4.5, { align: 'center' });
  });

  y += 9;

  // Grille repas
  const couleursRepas = {
    breakfast: [234, 211, 184],
    lunch:     [239, 198, 150],
    dinner:    [231, 179, 119],
  };

  ['breakfast', 'lunch', 'dinner'].forEach(function(type) {
    const infoType  = TYPES_REPAS[type];
    const hautLigne = 22;
    const couleur   = couleursRepas[type] || [240, 234, 220];

    menu.jours.forEach(function(jour, i) {
      const x       = marge + i * largCol;
      const recette = (jour.repas || {})[type];

      doc.setFillColor(couleur[0], couleur[1], couleur[2]);
      doc.rect(x, y, largCol, hautLigne, 'F');
      doc.setDrawColor(200, 190, 190);
      doc.rect(x, y, largCol, hautLigne, 'S');

      doc.setTextColor(42, 25, 31);
      doc.setFontSize(6);
      doc.setFont('helvetica', 'bold');
      doc.text(infoType.label.toUpperCase(), x + 1.5, y + 4);

      doc.setFont('helvetica', 'normal');
      doc.setFontSize(6.5);

      if (recette) {
        const lignes = doc.splitTextToSize(recette.name, largCol - 3);
        doc.text(lignes.slice(0, 2), x + 1.5, y + 9);
        doc.setFontSize(5.5);
        doc.setTextColor(100, 80, 85);
        doc.text(formaterEuro(recette.estimated_cost) + ' | ' + (recette.prep_time || '?') + 'min', x + 1.5, y + 18);
      } else {
        doc.setTextColor(160, 140, 145);
        doc.text('—', x + largCol / 2, y + 12, { align: 'center' });
      }
    });

    y += hautLigne;
  });

  // Pied de page
  doc.setFillColor(87, 98, 56);
  doc.rect(0, 280, 210, 17, 'F');
  doc.setTextColor(243, 231, 217);
  doc.setFontSize(8);
  doc.text('© 2026 Planificateur de Repas', 105, 289, { align: 'center' });

  const dateStr = new Date().toISOString().slice(0, 10);
  doc.save('menu-semaine-' + dateStr + '.pdf');

  notifSucces('PDF téléchargé avec succès !');
}

function chargerJsPDF() {
  if (window.jspdf && window.jspdf.jsPDF) {
    return Promise.resolve(window.jspdf.jsPDF);
  }

  return new Promise(function(resolve) {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    script.onload  = function() { resolve(window.jspdf?.jsPDF || null); };
    script.onerror = function() { resolve(null); };
    document.head.appendChild(script);
  });
}


// ═══════════════════════════════════════════════════════════════
// EXPORT ICS
// ═══════════════════════════════════════════════════════════════

function exportToICS() {
  const menu = donneesApp.menuActuel;

  if (!menu || !menu.jours || menu.jours.length === 0) {
    notifAvert("Générez d'abord un menu avant d'exporter.");
    return;
  }

  const lignes = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Planificateur de Repas//FR',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:Menu de la Semaine',
    'X-WR-TIMEZONE:Europe/Paris',
  ];

  const lundi = new Date();
  lundi.setDate(lundi.getDate() - ((lundi.getDay() + 6) % 7));

  function formaterDateICS(date) {
    return date.toISOString().replace(/[-:]/g, '').slice(0, 8);
  }

  const heuresDebut = { breakfast: '08', lunch: '12', dinner: '19' };
  const heuresFin   = { breakfast: '09', lunch: '13', dinner: '20' };

  menu.jours.forEach(function(jour, indexJour) {
    const dateJour = new Date(lundi);
    dateJour.setDate(lundi.getDate() + indexJour);
    const dateStr = formaterDateICS(dateJour);

    const repas = jour.repas || {};

    ['breakfast', 'lunch', 'dinner'].forEach(function(type) {
      const recette = repas[type];
      if (!recette) return;

      const infoType = TYPES_REPAS[type];
      const uid_val  = Date.now() + '-' + indexJour + '-' + type + '@mealplanner';
      const debut    = dateStr + 'T' + heuresDebut[type] + '0000';
      const fin      = dateStr + 'T' + heuresFin[type]   + '0000';
      const titre    = infoType.emoji + ' ' + recette.name;

      const description = [
        'Type : ' + infoType.label,
        'Temps de préparation : ' + (recette.prep_time || '?') + ' min',
        'Coût estimé : ' + formaterEuro(recette.estimated_cost),
        'Calories : ' + formaterNutrition(recette.calories),
      ].join('\\n');

      lignes.push(
        'BEGIN:VEVENT',
        'UID:' + uid_val,
        'DTSTART:' + debut,
        'DTEND:' + fin,
        'SUMMARY:' + titre,
        'DESCRIPTION:' + description,
        'CATEGORIES:Repas,' + infoType.label,
        'STATUS:CONFIRMED',
        'END:VEVENT'
      );
    });
  });

  lignes.push('END:VCALENDAR');

  const contenu = lignes.join('\r\n');
  const blob    = new Blob([contenu], { type: 'text/calendar;charset=utf-8' });
  const url     = URL.createObjectURL(blob);

  const lien    = document.createElement('a');
  lien.href     = url;
  lien.download = 'menu-semaine-' + new Date().toISOString().slice(0, 10) + '.ics';
  document.body.appendChild(lien);
  lien.click();

  setTimeout(function() {
    URL.revokeObjectURL(url);
    lien.remove();
  }, 1000);

  notifSucces('Fichier calendrier (.ics) téléchargé !');
}



// MODULE MENU UTILISATEUR (DROPDOWN)


/**
 * Récupère les infos de l'utilisateur connecté.
 * Priorité : sessionStorage → localStorage → données mock.
 */
function getCurrentUser() {
  const raw = sessionStorage.getItem('currentUser') || localStorage.getItem('currentUser');

  if (raw) {
    try { return JSON.parse(raw); } catch (_) { /* continue */ }
  }

  // Données mock — retirer quand le back-end est prêt
  return {
    firstname: 'John',
    lastname:  'Dupont',
    email:     'John.dupont@exemple.fr',
  };
}

function getUserInitials(user) {
  const f = (user.firstname || '').trim().charAt(0).toUpperCase();
  const l = (user.lastname  || '').trim().charAt(0).toUpperCase();
  return (f + l) || '?';
}

function getUserDisplayName(user) {
  return `${user.firstname || ''} ${user.lastname || ''}`.trim() || 'Utilisateur';
}

function renderUserInfo() {
  const user     = getCurrentUser();
  const initials = getUserInitials(user);
  const fullName = getUserDisplayName(user);

  const avatarEl     = document.getElementById('user-avatar');
  const nameEl       = document.getElementById('user-display-name');
  const dropAvatarEl = document.getElementById('dropdown-avatar');
  const dropNameEl   = document.getElementById('dropdown-name');
  const dropEmailEl  = document.getElementById('dropdown-email');

  if (avatarEl)     avatarEl.textContent    = initials;
  if (nameEl)       nameEl.textContent      = fullName;
  if (dropAvatarEl) dropAvatarEl.textContent = initials;
  if (dropNameEl)   dropNameEl.textContent  = fullName;
  if (dropEmailEl)  dropEmailEl.textContent = user.email || '';
}

function toggleUserDropdown() {
  const btn      = document.getElementById('user-menu-btn');
  const dropdown = document.getElementById('user-dropdown');
  if (!btn || !dropdown) return;

  const isOpen = btn.getAttribute('aria-expanded') === 'true';
  isOpen ? closeUserDropdown() : openUserDropdown();
}

function openUserDropdown() {
  const btn      = document.getElementById('user-menu-btn');
  const dropdown = document.getElementById('user-dropdown');
  if (!btn || !dropdown) return;

  btn.setAttribute('aria-expanded', 'true');
  dropdown.removeAttribute('hidden');

  const firstItem = dropdown.querySelector('.dropdown-item');
  if (firstItem) firstItem.focus();
}

function closeUserDropdown() {
  const btn      = document.getElementById('user-menu-btn');
  const dropdown = document.getElementById('user-dropdown');
  if (!btn || !dropdown) return;

  btn.setAttribute('aria-expanded', 'false');
  dropdown.setAttribute('hidden', '');
  btn.focus();
}

function initUserMenu() {
  renderUserInfo();

  const btn     = document.getElementById('user-menu-btn');
  const wrapper = document.getElementById('user-menu-wrapper');
  if (!btn || !wrapper) return;

  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    toggleUserDropdown();
  });

  wrapper.addEventListener('keydown', function(e) {
    const dropdown = document.getElementById('user-dropdown');
    const isOpen   = btn.getAttribute('aria-expanded') === 'true';

    switch (e.key) {
      case 'Escape':
        if (isOpen) closeUserDropdown();
        break;

      case 'ArrowDown': {
        if (!isOpen) { openUserDropdown(); break; }
        e.preventDefault();
        const itemsDown = [...dropdown.querySelectorAll('.dropdown-item')];
        const idxDown   = itemsDown.indexOf(document.activeElement);
        const next      = itemsDown[idxDown + 1] || itemsDown[0];
        next.focus();
        break;
      }

      case 'ArrowUp': {
        if (!isOpen) break;
        e.preventDefault();
        const itemsUp = [...dropdown.querySelectorAll('.dropdown-item')];
        const idxUp   = itemsUp.indexOf(document.activeElement);
        const prev    = itemsUp[idxUp - 1] || itemsUp[itemsUp.length - 1];
        prev.focus();
        break;
      }

      case 'Tab':
        if (isOpen) {
          setTimeout(function() {
            if (!wrapper.contains(document.activeElement)) closeUserDropdown();
          }, 0);
        }
        break;
    }
  });

  document.addEventListener('click', function(e) {
    if (!wrapper.contains(e.target)) closeUserDropdown();
  });
}

// Actions du dropdown
function goToProfile() {
  closeUserDropdown();
  console.info('[UserMenu] → Profil utilisateur (page à créer)');
  showUserMenuFeedback('Profil (page en cours de développement)');
}

function goToSettings() {
  closeUserDropdown();
  console.info('[UserMenu] → Paramètres (page à créer)');
  showUserMenuFeedback('Paramètres (page en cours de développement)');
}

function goToMyMenus() {
  closeUserDropdown();
  if (typeof showTab === 'function') showTab('menu');
}

function goToFavorites() {
  closeUserDropdown();
  console.info('[UserMenu] → Favoris (page à créer)');
  showUserMenuFeedback('Favoris (page en cours de développement)');
}

function logout() {
  closeUserDropdown();
  sessionStorage.removeItem('currentUser');
  sessionStorage.removeItem('authToken');
  localStorage.removeItem('currentUser');
  window.location.href = 'login.php';
}

function showUserMenuFeedback(message) {
  const region = document.getElementById('generation-result');
  if (!region) return;

  region.innerHTML = `
    <div class="alert alert-info" role="status">
      ℹ️ ${message}
    </div>
  `;

  setTimeout(function() { region.innerHTML = ''; }, 3000);
}



// DÉMARRAGE — point d'entrée unique


document.addEventListener('DOMContentLoaded', function() {

  // 1. Charger les données sauvegardées
  charger();

  // 2. Initialiser les composants de l'interface
  initialiserRechercheIngredients();
  initialiserFiltresRecettes();

  // 3. Afficher les données existantes
  afficherIngredients();
  afficherRecettes();

  if (donneesApp.menuActuel) {
    afficherMenu();
    mettreAJourResume();
  }

  // 4. Initialiser le menu utilisateur
  initUserMenu();

  // 5. Navigation clavier Alt+1 → Alt+4
  const onglets = ['generate', 'ingredients', 'recipes', 'menu'];
  document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key >= '1' && e.key <= '4') {
      e.preventDefault();
      const index = parseInt(e.key, 10) - 1;
      if (onglets[index]) showTab(onglets[index]);
    }
  });

  // 6. Navigation flèches dans la barre de navigation
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

  // 7. Lien "passer au contenu" pour l'accessibilité
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