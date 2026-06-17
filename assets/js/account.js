/**
 * account.js — Espace utilisateur (profil, paramètres, menus, favoris) via API Ajax
 */
'use strict';

const MEAL_LABELS = {
  breakfast: 'Petit-déjeuner',
  lunch: 'Déjeuner',
  dinner: 'Dîner',
};

const DIET_LABELS = {
  all: 'Tout',
  vegetarian: 'Végétarien',
  vegan: 'Vegan',
  'no-pork': 'Sans Porc',
};

/** Affiche un message d'alerte (succès/erreur) en haut de l'espace compte. */
function showAccountAlert(message, type) {
  const box = document.getElementById('account-alerts');
  if (!box) return;
  const cls = type === 'success' ? 'alert-success' : 'alert-danger';
  const icon = type === 'success' ? '✅' : '⚠️';
  box.innerHTML = '<div class="alert ' + cls + '" role="alert">' + icon + ' ' + message + '</div>';
  setTimeout(function () {
    const el = box.querySelector('.alert');
    if (el) {
      el.style.transition = 'opacity .5s ease';
      el.style.opacity = '0';
      setTimeout(function () { box.innerHTML = ''; }, 500);
    }
  }, 4500);
}

/** Échappe le HTML d'une chaîne pour éviter les injections (XSS). */
function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** Formate une date en français (JJ/MM/AAAA). */
function formatDateFr(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('fr-FR');
}

/** Formate une date + heure en français (JJ/MM/AAAA à HHhMM). */
function formatDateTimeFr(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('fr-FR') + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

/** Formate un nombre en euros (ex : 12.5 → "12,50 €"). */
function formatEuro(n) {
  return Number(n || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ── Formulaire profil ── */
/** Branche le formulaire de profil : envoi Ajax (PUT) + retour visuel. */
function initProfileForm() {
  const form = document.getElementById('profile-form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;

    API.account.updateProfile({
      firstname: document.getElementById('firstname')?.value.trim(),
      lastname: document.getElementById('lastname')?.value.trim(),
      email: document.getElementById('email')?.value.trim(),
    })
    .then(function (result) {
      const data = result.data;
      if (!data.success) throw new Error(data.error || data.message || 'Erreur');
      showAccountAlert(data.message || 'Profil mis à jour.', 'success');
      const heroName = document.querySelector('.account-name');
      const heroMeta = document.querySelector('.account-meta');
      const fn = document.getElementById('firstname')?.value.trim();
      const ln = document.getElementById('lastname')?.value.trim();
      const em = document.getElementById('email')?.value.trim();
      if (heroName) heroName.textContent = fn + ' ' + ln;
      if (heroMeta) heroMeta.innerHTML = '✉️ ' + escHtml(em) + ' &nbsp;·&nbsp; ' + (heroMeta.textContent.split('·').pop() || '');
      sessionStorage.setItem('currentUser', JSON.stringify({ firstname: fn, lastname: ln, email: em }));
    })
    .catch(function (err) {
      showAccountAlert(err.message || 'Erreur lors de la mise à jour.', 'error');
    })
    .finally(function () {
      if (btn) btn.disabled = false;
    });
  });
}

/* ── Formulaire mot de passe ── */
/** Branche le formulaire de changement de mot de passe (validation + Ajax). */
function initPasswordForm() {
  const form = document.getElementById('password-form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;

    API.account.changePassword({
      current_password: document.getElementById('current_password')?.value,
      new_password: document.getElementById('new_password')?.value,
      confirm_password: document.getElementById('confirm_password')?.value,
    })
    .then(function (result) {
      const data = result.data;
      if (!data.success) throw new Error(data.error || data.message || 'Erreur');
      showAccountAlert(data.message || 'Mot de passe modifié.', 'success');
      form.reset();
    })
    .catch(function (err) {
      showAccountAlert(err.message || 'Erreur.', 'error');
    })
    .finally(function () {
      if (btn) btn.disabled = false;
    });
  });
}

/* ── Formulaire paramètres ── */
/** Branche le formulaire des paramètres (régime, budget, nb de personnes). */
function initSettingsForm() {
  const form = document.getElementById('settings-form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;

    API.account.saveSettings({
      dietary_pref: document.getElementById('dietary_pref')?.value,
      default_budget: parseFloat(document.getElementById('default_budget')?.value || 0),
      default_persons: parseInt(document.getElementById('default_persons')?.value || 2, 10),
    })
    .then(function (result) {
      const data = result.data;
      if (!data.success) throw new Error(data.error || data.message || 'Erreur');
      showAccountAlert(data.message || 'Paramètres enregistrés.', 'success');
    })
    .catch(function (err) {
      showAccountAlert(err.message || 'Erreur.', 'error');
    })
    .finally(function () {
      if (btn) btn.disabled = false;
    });
  });
}

/* ── Menus sauvegardés ── */
/** Construit le HTML de la liste des menus sauvegardés. */
function renderSavedMenus(menus) {
  const container = document.getElementById('saved-menus-list');
  if (!container) return;

  if (!menus || menus.length === 0) {
    container.outerHTML = ''
      + '<div class="account-empty" id="saved-menus-empty">'
      + '<div class="account-empty-icon">📋</div>'
      + '<h2>Aucun menu sauvegardé</h2>'
      + '<p>Générez un menu pour le retrouver ici.</p>'
      + '<a href="/index.php" class="btn btn-secondary">Générer un menu</a>'
      + '</div>';
    return;
  }

  let html = '<div class="account-menus" id="saved-menus-list">';
  menus.forEach(function (menu) {
    const mid = menu.id;
    const label = menu.week_start
      ? 'Semaine du ' + formatDateFr(menu.week_start)
      : 'Menu #' + mid;
    html += ''
      + '<article class="account-menu-card">'
      + '<div class="account-menu-header">'
      + '<div><h3>' + escHtml(label) + '</h3>'
      + '<p class="account-menu-meta">'
      + 'Sauvegardé le ' + formatDateTimeFr(menu.created_at)
      + ' &nbsp;·&nbsp; ' + (menu.persons || 1) + ' pers.'
      + ' &nbsp;·&nbsp; Budget : ' + formatEuro(menu.budget) + ' €'
      + ' &nbsp;·&nbsp; Coût : ' + formatEuro(menu.total_cost) + ' €'
      + '</p></div>'
      + '<div class="account-menu-actions">'
      + '<a href="/index.php?load_menu=' + mid + '" class="btn btn-success btn-sm">📂 Charger</a>'
      + '<button type="button" class="btn btn-danger btn-sm btn-delete-menu" data-menu-id="' + mid + '">🗑 Supprimer</button>'
      + '</div></div></article>';
  });
  html += '</div>';
  container.outerHTML = html;
  bindDeleteMenuButtons();
}

/** Attache l'action "supprimer" à chaque bouton de menu sauvegardé. */
function bindDeleteMenuButtons() {
  document.querySelectorAll('.btn-delete-menu').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const menuId = btn.dataset.menuId;
      if (!menuId || !confirm('Supprimer ce menu ?')) return;

      API.menus.delete(menuId)
      .then(function (result) {
        const data = result.data;
        if (!data.success) throw new Error(data.error || data.message || 'Erreur');
        showAccountAlert(data.message || 'Menu supprimé.', 'success');
        return API.menus.list();
      })
      .then(function (result) {
        if (result && result.data.success) {
          renderSavedMenus(result.data.data);
        } else {
          const card = btn.closest('.account-menu-card');
          if (card) card.remove();
        }
      })
      .catch(function (err) {
        showAccountAlert(err.message || 'Erreur.', 'error');
      });
    });
  });
}

/** Recharge la liste des menus sauvegardés depuis l'API. */
function refreshSavedMenus() {
  const list = document.getElementById('saved-menus-list');
  const empty = document.getElementById('saved-menus-empty');
  if (!list && !empty) return;

  API.menus.list()
  .then(function (result) {
    const data = result.data;
    if (data.success) renderSavedMenus(data.data);
  })
  .catch(function () {});
}

/* ── Favoris ── */
/** Construit le HTML de la grille des recettes favorites. */
function renderFavorites(favorites) {
  const container = document.getElementById('favorites-list');
  if (!container) return;

  if (!favorites || favorites.length === 0) {
    container.innerHTML = ''
      + '<div class="account-empty">'
      + '<div class="account-empty-icon">⭐</div>'
      + '<h2>Aucun favori</h2>'
      + '<p>Ajoutez des recettes en favori depuis l\'onglet Mes recettes.</p>'
      + '<a href="/index.php" class="btn btn-secondary">Voir mes recettes</a>'
      + '</div>';
    return;
  }

  let html = '';
  favorites.forEach(function (recipe) {
    const mt = MEAL_LABELS[recipe.meal_type] || recipe.meal_type || '';
    const diet = DIET_LABELS[recipe.dietary] || recipe.dietary || '';
    html += ''
      + '<article class="account-fav-card">'
      + '<div class="account-fav-header">'
      + '<h3>' + escHtml(recipe.name) + '</h3>'
      + '<span class="account-star">⭐</span></div>'
      + '<div class="account-fav-badges">'
      + (mt ? '<span class="item-detail">🍽️ ' + escHtml(mt) + '</span>' : '')
      + (diet ? '<span class="item-detail">🌿 ' + escHtml(diet) + '</span>' : '')
      + (recipe.prep_time ? '<span class="item-detail">⏱️ ' + recipe.prep_time + ' min</span>' : '')
      + (recipe.estimated_cost ? '<span class="item-detail">💰 ' + formatEuro(recipe.estimated_cost) + ' €</span>' : '')
      + (recipe.calories ? '<span class="item-detail">🔥 ' + recipe.calories + ' kcal</span>' : '')
      + (recipe.protein ? '<span class="item-detail">💪 ' + recipe.protein + 'g prot.</span>' : '')
      + '</div>'
      + '<div class="account-fav-footer">'
      + '<span class="account-fav-date">Ajouté le ' + formatDateFr(recipe.created_at) + '</span>'
      + '<button type="button" class="btn btn-danger btn-sm btn-remove-favorite" data-recipe-id="' + recipe.id + '">✕ Retirer</button>'
      + '</div></article>';
  });
  container.innerHTML = html;
  bindRemoveFavoriteButtons();
}

/** Attache l'action "retirer" à chaque bouton de recette favorite. */
function bindRemoveFavoriteButtons() {
  document.querySelectorAll('.btn-remove-favorite').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const recipeId = btn.dataset.recipeId;
      if (!recipeId || !confirm('Retirer des favoris ?')) return;

      API.account.removeFavorite(recipeId)
      .then(function (result) {
        const data = result.data;
        if (!data.success) throw new Error(data.error || data.message || 'Erreur');
        showAccountAlert(data.message || 'Favori retiré.', 'success');
        const params = new URLSearchParams(window.location.search);
        const mealType = params.get('meal_type') || '';
        return API.account.getFavorites(mealType);
      })
      .then(function (result) {
        if (result && result.data.success) {
          renderFavorites(result.data.data);
        }
      })
      .catch(function (err) {
        showAccountAlert(err.message || 'Erreur.', 'error');
      });
    });
  });
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function () {

  document.querySelectorAll('.pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      btn.textContent = visible ? '👁' : '🙈';
      btn.setAttribute('aria-pressed', String(!visible));
    });
  });

  const newPw = document.getElementById('new_password');
  const strengthBar = document.getElementById('strength-bar');
  const strengthLbl = document.getElementById('strength-label');

  if (newPw && strengthBar) {
    newPw.addEventListener('input', function () {
      const score = calcStrength(newPw.value);
      const colors = ['', '#c0392b', '#e89c30', '#27ae60', '#27ae60'];
      const labels = ['', 'Faible', 'Moyen', 'Bon', 'Fort'];
      strengthBar.style.width = (score * 25) + '%';
      strengthBar.style.background = colors[score] || 'transparent';
      if (strengthLbl) strengthLbl.textContent = newPw.value ? (labels[score] || '') : '';
    });
  }

  const confirmPw = document.getElementById('confirm_password');
  const confirmHint = document.getElementById('confirm-hint');
  if (newPw && confirmPw) {
    function checkMatch() {
      if (!confirmPw.value) return;
      if (newPw.value === confirmPw.value) {
        confirmPw.setCustomValidity('');
        if (confirmHint) {
          confirmHint.textContent = '✅ Les mots de passe correspondent.';
          confirmHint.style.color = 'var(--success, #27ae60)';
        }
      } else {
        confirmPw.setCustomValidity('Ne correspond pas.');
        if (confirmHint) {
          confirmHint.textContent = '❌ Ne correspond pas.';
          confirmHint.style.color = 'var(--danger, #c0392b)';
        }
      }
    }
    newPw.addEventListener('input', checkMatch);
    confirmPw.addEventListener('input', checkMatch);
  }

  initProfileForm();
  initPasswordForm();
  initSettingsForm();
  bindDeleteMenuButtons();
  bindRemoveFavoriteButtons();

  const tab = window.__ACCOUNT_TAB__ || '';
  if (tab === 'saved-menus') refreshSavedMenus();
});

/** Évalue la robustesse d'un mot de passe (score 0 à 4) pour la barre de force. */
function calcStrength(pw) {
  let score = 0;
  if (pw.length >= 8) score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  return Math.min(4, score);
}
