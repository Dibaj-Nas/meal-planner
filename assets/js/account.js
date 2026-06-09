/**
 * account.js
 * Logique cliente pour account.php (profil, paramètres, menus, favoris)
 * + Mise à jour des fonctions dropdown de app.js (goToProfile, etc.)

 *
 * Pour app.js : remplacer le bloc "Actions du dropdown" par les
 * fonctions indiquées en bas de ce fichier (section PATCH APP.JS).
 */

'use strict';



// initialisation au chargement

document.addEventListener('DOMContentLoaded', function () {

    /* ── 1. Toggle visibilité mots de passe ── */
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) return;
            var visible = input.type === 'text';
            input.type      = visible ? 'password' : 'text';
            btn.textContent = visible ? '👁' : '🙈';
            btn.setAttribute('aria-pressed', String(!visible));
        });
    });

    /* ── 2. Indicateur de force du mot de passe ── */
    var newPw      = document.getElementById('new_password');
    var strengthBar= document.getElementById('strength-bar');
    var strengthLbl= document.getElementById('strength-label');

    if (newPw && strengthBar) {
        newPw.addEventListener('input', function () {
            var score  = calcStrength(newPw.value);
            var colors = ['', '#c0392b', '#e89c30', '#27ae60', '#27ae60'];
            var labels = ['', 'Faible', 'Moyen', 'Bon', 'Fort'];

            strengthBar.style.width      = (score * 25) + '%';
            strengthBar.style.background = colors[score] || 'transparent';
            if (strengthLbl) strengthLbl.textContent = newPw.value ? (labels[score] || '') : '';
        });
    }

    /* ── 3. Vérification concordance mots de passe ── */
    var confirmPw  = document.getElementById('confirm_password');
    var confirmHint= document.getElementById('confirm-hint');

    if (newPw && confirmPw) {
        function checkMatch() {
            if (!confirmPw.value) return;
            if (newPw.value === confirmPw.value) {
                confirmPw.setCustomValidity('');
                confirmPw.style.borderColor = 'var(--success, #27ae60)';
                if (confirmHint) { confirmHint.textContent = '✅ Les mots de passe correspondent.'; confirmHint.style.color = 'var(--success, #27ae60)'; }
            } else {
                confirmPw.setCustomValidity('Ne correspond pas.');
                confirmPw.style.borderColor = 'var(--danger, #c0392b)';
                if (confirmHint) { confirmHint.textContent = '❌ Ne correspond pas.'; confirmHint.style.color = 'var(--danger, #c0392b)'; }
            }
        }
        newPw.addEventListener('input',     checkMatch);
        confirmPw.addEventListener('input', checkMatch);
    }

    /* ── 4. Alertes auto-disparaissantes ── */
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .5s ease';
            el.style.opacity    = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4500);
    });

    /* ── 5. Prévisualisation du thème (paramètres) ── */
    document.querySelectorAll('input[name="theme"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.documentElement.setAttribute('data-theme', radio.value);
        });
    });

    /* ── 6. Chargement d'un menu sauvegardé dans index.php ──
       Appelé par index.php quand ?load_menu=<id> est présent dans l'URL. */
    var params   = new URLSearchParams(window.location.search);
    var loadMenu = params.get('load_menu');
    if (loadMenu && typeof charger === 'undefined') {
        // On est bien dans account.php, pas index.php — on ignore
    }
});



// utilitaires

/**
 * Calcule la force d'un mot de passe (score 0–4).
 * @param {string} pw
 * @returns {number}
 */
function calcStrength(pw) {
    var score = 0;
    if (pw.length >= 8)                              score++;
    if (pw.length >= 12)                             score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw))       score++;
    if (/[0-9]/.test(pw))                            score++;
    if (/[^A-Za-z0-9]/.test(pw))                    score++;
    return Math.min(4, score);
}


