/**
 * theme.js — Gestion du mode clair / sombre
 * Injecte le bouton toggle, lit/écrit localStorage,
 * supporte le mode système (prefers-color-scheme).
 */
'use strict';

(function () {

    /* ── Résolution du thème effectif ─────────────────────────── */
    function resolveTheme(stored) {
        if (stored === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return stored || 'light';
    }

    function getSavedTheme() {
        return localStorage.getItem('theme') || 'light';
    }

    /* ── Application du thème sur <html> ──────────────────────── */
    function applyTheme(stored) {
        var effective = resolveTheme(stored);
        document.documentElement.setAttribute('data-theme', effective);
        updateButton(effective);
    }

    /* ── Mise à jour de l'icône du bouton ─────────────────────── */
    function updateButton(effectiveTheme) {
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;
        if (effectiveTheme === 'dark') {
            btn.textContent = '☀️';
            btn.setAttribute('aria-label', 'Passer en mode clair');
            btn.setAttribute('title', 'Mode clair');
        } else {
            btn.textContent = '🌙';
            btn.setAttribute('aria-label', 'Passer en mode sombre');
            btn.setAttribute('title', 'Mode sombre');
        }
    }

    /* ── Toggle au clic ───────────────────────────────────────── */
    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next    = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', next);
        applyTheme(next);

        /* Synchronise le sélecteur radio dans account.php si présent */
        var radio = document.querySelector('input[name="theme"][value="' + next + '"]');
        if (radio) radio.checked = true;
    }

    /* ── Injection du bouton flottant (ou liaison sur bouton existant) ── */
    function injectButton() {
        var btn = document.getElementById('theme-toggle');
        if (!btn) {
            /* Pas de bouton dans le HTML : on en crée un flottant */
            btn = document.createElement('button');
            btn.id   = 'theme-toggle';
            btn.type = 'button';
            document.body.appendChild(btn);
        }
        btn.addEventListener('click', toggleTheme);
        updateButton(document.documentElement.getAttribute('data-theme') || 'light');
    }

    /* ── Écoute du changement de préférence système ───────────── */
    function watchSystem() {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (getSavedTheme() === 'system') applyTheme('system');
        });
    }

    /* ── Synchronisation depuis account.php ───────────────────── */
    function syncFromRadios() {
        var radios = document.querySelectorAll('input[name="theme"]');
        if (!radios.length) return;
        var saved = getSavedTheme();
        radios.forEach(function (radio) {
            // Coche le radio correspondant au thème enregistré
            radio.checked = (radio.value === saved);
            radio.addEventListener('change', function () {
                localStorage.setItem('theme', radio.value);
                applyTheme(radio.value);
            });
        });
    }

    /* ── Init ──────────────────────────────────────────────────── */
    function init() {
        applyTheme(getSavedTheme());
        injectButton();
        watchSystem();
        syncFromRadios();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
