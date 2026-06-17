/**
 * api.js — Client Ajax centralisé pour api.php
 */
'use strict';

const API = (function () {
  const BASE = 'api.php';

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  async function request(method, path, body) {
    const url = path.startsWith('http') ? path : BASE + '/' + path.replace(/^\//, '');
    const opts = {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    };

    const token = csrfToken();
    if (token) {
      opts.headers['X-CSRF-TOKEN'] = token;
    }

    if (body !== undefined && body !== null) {
      opts.headers['Content-Type'] = 'application/json';
      if (typeof body === 'object' && !body.csrf_token && token) {
        body = Object.assign({}, body, { csrf_token: token });
      }
      opts.body = JSON.stringify(body);
    }

    const response = await fetch(url, opts);
    const rawText = await response.text();
    let data = {};
    try {
      data = rawText ? JSON.parse(rawText) : {};
    } catch (_) {
      const snippet = rawText.replace(/\s+/g, ' ').trim().slice(0, 120);
      data = {
        success: false,
        error: snippet
          ? 'Réponse serveur invalide : ' + snippet
          : 'Réponse vide du serveur (vérifiez la base de données et les logs PHP).',
      };
    }

    if (response.status === 401 && !path.includes('auth/login')) {
      window.location.href = 'login.php';
      throw new Error(data.error || 'Non authentifié');
    }

    return { response: response, data: data };
  }

  return {
    csrfToken: csrfToken,

    auth: {
      login: function (email, password) {
        return request('POST', 'auth/login', { email: email, password: password });
      },
      register: function (payload) {
        return request('POST', 'auth/register', payload);
      },
      logout: function () {
        return request('POST', 'auth/logout', {});
      },
      me: function () {
        return request('GET', 'auth/me');
      },
    },

    ingredients: {
      list: function (search) {
        const q = search ? '?search=' + encodeURIComponent(search) : '';
        return request('GET', 'ingredients' + q);
      },
      create: function (data) {
        return request('POST', 'ingredients', data);
      },
      update: function (id, data) {
        return request('PUT', 'ingredients/' + id, data);
      },
      delete: function (id) {
        return request('DELETE', 'ingredients/' + id);
      },
    },

    recipes: {
      list: function (params) {
        params = params || {};
        const qs = new URLSearchParams();
        if (params.meal_type) qs.set('meal_type', params.meal_type);
        if (params.dietary) qs.set('dietary', params.dietary);
        const q = qs.toString();
        return request('GET', 'recipes' + (q ? '?' + q : ''));
      },
      create: function (data) {
        return request('POST', 'recipes', data);
      },
      delete: function (id) {
        return request('DELETE', 'recipes/' + id);
      },
    },

    menus: {
      list: function () {
        return request('GET', 'menus');
      },
      get: function (id) {
        return request('GET', 'menus/' + id);
      },
      generate: function (params) {
        return request('POST', 'menus/generate', params);
      },
      save: function (data) {
        return request('POST', 'menus', data);
      },
      delete: function (id) {
        return request('DELETE', 'menus/' + id);
      },
    },

    account: {
      getProfile: function () {
        return request('GET', 'account/profile');
      },
      updateProfile: function (data) {
        return request('PUT', 'account/profile', data);
      },
      changePassword: function (data) {
        return request('PUT', 'account/password', data);
      },
      getSettings: function () {
        return request('GET', 'account/settings');
      },
      saveSettings: function (data) {
        return request('PUT', 'account/settings', data);
      },
      getFavorites: function (mealType) {
        const q = mealType ? '?meal_type=' + encodeURIComponent(mealType) : '';
        return request('GET', 'account/favorites' + q);
      },
      addFavorite: function (recipeId) {
        return request('POST', 'account/favorites', { recipe_id: recipeId });
      },
      removeFavorite: function (recipeId) {
        return request('DELETE', 'account/favorites/' + recipeId);
      },
    },
  };
})();
