/* 
   auth.js — Script unifié : login.html & register.html
   Planificateur de Repas

   Le script détecte automatiquement sur quelle page il s'exécute
   grâce à l'ID du formulaire présent dans le DOM, puis initialise
   uniquement le module correspondant.

   Structure :
     1. Utilitaires partagés
     2. Module LOGIN
     3. Module REGISTER
     4. Initialisation automatique
*/

'use strict';

/* 1. UTILITAIRES PARTAGÉS */

/**
 * Bascule la visibilité d'un champ mot de passe et met
 * à jour l'icône du bouton ainsi que son attribut ARIA.
 *
 * @param {HTMLButtonElement} btn   - Le bouton toggle
 * @param {HTMLInputElement}  input - Le champ mot de passe associé
 */
function togglePasswordVisibility(btn, input) {
    const isHidden = input.type === 'password';
    input.type                     = isHidden ? 'text' : 'password';
    btn.textContent                = isHidden ? '🙈' : '👁️';
    btn.setAttribute('aria-pressed', String(isHidden));
}

/**
 * Affiche un message d'erreur dans la zone d'alerte indiquée.
 *
 * @param {HTMLElement} errorBox  - Le conteneur d'alerte
 * @param {HTMLElement} errorText - L'élément <span> portant le texte
 * @param {string}      message   - Le message à afficher
 */
function showError(errorBox, errorText, message) {
    errorText.textContent = message;
    errorBox.classList.add('visible');
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Masque la zone d'erreur.
 *
 * @param {HTMLElement} errorBox - Le conteneur d'alerte
 */
function clearError(errorBox) {
    errorBox.classList.remove('visible');
}

/**
 * Passe un bouton submit en état "chargement".
 *
 * @param {HTMLButtonElement} btn     - Le bouton
 * @param {string}            label   - Texte affiché pendant le chargement
 */
function setLoadingState(btn, label = 'Chargement…') {
    btn.disabled = true;
    btn.querySelector('.btn-inner').innerHTML = `<span>${label}</span>`;
}

/**
 * Restaure un bouton submit en état normal.
 *
 * @param {HTMLButtonElement} btn   - Le bouton
 * @param {string}            label - Texte du libellé principal
 */
function resetButton(btn, label) {
    btn.disabled = false;
    btn.querySelector('.btn-inner').innerHTML =
        `<span>${label}</span><span aria-hidden="true">→</span>`;
}

/* 2. MODULE LOGIN */

/**
 * Initialise toute la logique de la page de connexion.
 * Appelé uniquement quand #login-form existe dans le DOM.
 */
function initLogin() {

    /* — Sélection des éléments — */
    const form      = document.getElementById('login-form');
    const emailInput = document.getElementById('email');
    const pwInput   = document.getElementById('password');
    const toggleBtn = document.getElementById('toggle-pw');
    const submitBtn = document.getElementById('submit-btn');
    const errorBox  = document.getElementById('login-error');
    const errorSpan = document.getElementById('error-text');

    /* ── Toggle visibilité mot de passe ── */
    toggleBtn.addEventListener('click', () => {
        togglePasswordVisibility(toggleBtn, pwInput);
    });

    /* ── Efface l'erreur dès qu'un champ est modifié ── */
    form.addEventListener('input', () => clearError(errorBox));

    /* ── Validation côté client ──
     *
     * @param {string} email
     * @param {string} password
     * @returns {string|null} Message d'erreur ou null si valide
     */
    function validateLogin(email, password) {
        if (!email)                               return 'Veuillez saisir votre adresse e-mail.';
        if (!email.includes('@') || !email.includes('.')) return "Format d'e-mail invalide.";
        if (!password)                            return 'Veuillez saisir votre mot de passe.';
        if (password.length < 6)                  return 'Le mot de passe doit contenir au moins 6 caractères.';
        return null;
    }

    /* ── Soumission du formulaire ── */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError(errorBox);

        const email    = emailInput.value.trim();
        const password = pwInput.value;

        /* Validation front-end */
        const validationError = validateLogin(email, password);
        if (validationError) {
            showError(errorBox, errorSpan, validationError);
            return;
        }

        /* État de chargement */
        setLoadingState(submitBtn, 'Connexion en cours…');

        try {
            const result = await API.auth.login(email, password);
            const data = result.data;

            if (!data.success) {
                showError(errorBox, errorSpan,
                    data.error || data.message || 'Email ou mot de passe incorrect.');
                resetButton(submitBtn, 'Se connecter');
                return;
            }

            if (data.data && data.data.user) {
                sessionStorage.setItem('currentUser', JSON.stringify(data.data.user));
            }

            window.location.href = 'index.php';

        } catch (error) {
            console.error('[Login] Erreur :', error);
            showError(errorBox, errorSpan, 'Une erreur est survenue. Veuillez réessayer.');
            resetButton(submitBtn, 'Se connecter');
        }
    });
}

/* 3. MODULE REGISTER */

/**
 * Initialise toute la logique de la page d'inscription.
 * Appelé uniquement quand #register-form existe dans le DOM.
 */
function initRegister() {

    /* — Sélection des éléments — */
    const form           = document.getElementById('register-form');
    const firstnameInput = document.getElementById('firstname');
    const lastnameInput  = document.getElementById('lastname');
    const emailInput     = document.getElementById('email');
    const pwInput        = document.getElementById('password');
    const confirmInput   = document.getElementById('confirm-password');
    const cguCheckbox    = document.getElementById('cgu');
    const toggleBtn1     = document.getElementById('toggle-pw');
    const toggleBtn2     = document.getElementById('toggle-pw2');
    const submitBtn      = document.getElementById('submit-btn');
    const errorBox       = document.getElementById('register-error');
    const errorSpan      = document.getElementById('error-text');
    const successBox     = document.getElementById('register-success');
    const segments       = [1, 2, 3, 4].map(i => document.getElementById(`seg${i}`));
    const strengthLabel  = document.getElementById('pw-strength-label');
    const confirmHint    = document.getElementById('confirm-hint');
    const pwRulesItems   = {
        length:  document.getElementById('rule-length'),
        upper:   document.getElementById('rule-upper'),
        lower:   document.getElementById('rule-lower'),
        digit:   document.getElementById('rule-digit'),
        special: document.getElementById('rule-special'),
    };

    /* ── Toggle visibilité des deux champs mot de passe ── */
    toggleBtn1.addEventListener('click', () => togglePasswordVisibility(toggleBtn1, pwInput));
    toggleBtn2.addEventListener('click', () => togglePasswordVisibility(toggleBtn2, confirmInput));

    /* ── Indicateur de force du mot de passe ── */

    /* Niveaux, libellés et couleurs */
    const STRENGTH_LEVELS = {
        classes: ['', 'filled-weak', 'filled-medium', 'filled-strong', 'filled-strong'],
        labels:  ['', 'Faible', 'Moyen', 'Bon', 'Fort'],
        colors:  {
            weak:   'var(--btn-color)',
            medium: '#e89c30',
            strong: 'var(--success-color)',
        },
    };

    /**
     * Calcule un score de robustesse de 0 à 4.
     * Critères : longueur ≥ 8, ≥ 12, mixte maj/min,
     *            présence de chiffres, présence de symboles.
     *
     * @param   {string} password
     * @returns {number} Score entre 0 et 4
     */
    function calcStrength(password) {
        let score = 0;
        if (password.length >= 8)                              score++;
        if (password.length >= 12)                             score++;
        if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password))                            score++;
        if (/[^A-Za-z0-9]/.test(password))                    score++;
        return Math.min(4, score);
    }

    /**
     * Met à jour visuellement les segments, le label de force,
     * et les règles de validation individuelles.
     */
    function updateStrengthIndicator() {
        const val   = pwInput.value;
        const score = val ? calcStrength(val) : 0;

        segments.forEach((seg, i) => {
            seg.className = 'strength-segment';
            if (i < score) seg.classList.add(STRENGTH_LEVELS.classes[score]);
        });

        if (!val) {
            strengthLabel.textContent = '';
        } else {
            strengthLabel.textContent = `Force : ${STRENGTH_LEVELS.labels[score]}`;
            strengthLabel.style.color =
                score <= 1 ? STRENGTH_LEVELS.colors.weak   :
                score === 2 ? STRENGTH_LEVELS.colors.medium :
                STRENGTH_LEVELS.colors.strong;
        }

        /* Mise à jour des règles individuelles */
        const rules = {
            length:  val.length >= 8,
            upper:   /[A-Z]/.test(val),
            lower:   /[a-z]/.test(val),
            digit:   /[0-9]/.test(val),
            special: /[^A-Za-z0-9]/.test(val),
        };
        Object.entries(rules).forEach(([key, ok]) => {
            const el = pwRulesItems[key];
            if (!el) return;
            el.classList.toggle('valid', ok);
            el.querySelector('.rule-icon').setAttribute(
                'aria-label', ok ? 'Critère validé' : 'Critère non validé'
            );
        });
    }

    pwInput.addEventListener('input', updateStrengthIndicator);

    /* Vérification en temps réel de la correspondance */

    /**
     * Compare le champ de confirmation avec le mot de passe principal
     * et met à jour l'indice visuel sous le champ.
     */
    function checkPasswordMatch() {
        const value = confirmInput.value;

        if (!value) {
            confirmHint.textContent = '';
            confirmInput.classList.remove('valid', 'invalid');
            return;
        }

        if (value === pwInput.value) {
            confirmHint.textContent = '✓ Les mots de passe correspondent';
            confirmHint.style.color = 'var(--success-color)';
            confirmInput.classList.remove('invalid');
            confirmInput.classList.add('valid');
        } else {
            confirmHint.textContent = '✗ Les mots de passe ne correspondent pas';
            confirmHint.style.color = 'var(--btn-color)';
            confirmInput.classList.remove('valid');
            confirmInput.classList.add('invalid');
        }
    }

    confirmInput.addEventListener('input', checkPasswordMatch);

    /* Réévalue la correspondance si le mot de passe principal change */
    pwInput.addEventListener('input', () => {
        if (confirmInput.value) checkPasswordMatch();
    });

    /* -- Messages d'alerte -- */

    /**
     * Affiche le bandeau de succès et redirige après 1,6 s.
     */
    function showSuccess() {
        successBox.classList.add('visible');
        errorBox.classList.remove('visible');
        setTimeout(() => { window.location.href = 'login.php'; }, 1600);
    }

    /* Efface l'erreur dès qu'un champ est modifié */
    form.addEventListener('input', () => clearError(errorBox));

    /* ── Validation côté client ── */

    /**
     * Valide tous les champs du formulaire d'inscription.
     *
     * @returns {string|null} Message d'erreur ou null si valide
     */
    function validateRegister() {
        const firstname = firstnameInput.value.trim();
        const lastname  = lastnameInput.value.trim();
        const email     = emailInput.value.trim();
        const password  = pwInput.value;
        const confirm   = confirmInput.value;

        if (!firstname)                                           return 'Veuillez saisir votre prénom.';
        if (!lastname)                                            return 'Veuillez saisir votre nom.';
        if (!email || !email.includes('@') || !email.includes('.')) return 'Adresse e-mail invalide.';
        if (password.length < 8)                                  return 'Le mot de passe doit contenir au moins 8 caractères.';
        if (!/[A-Z]/.test(password))                              return 'Le mot de passe doit contenir au moins une majuscule.';
        if (!/[0-9]/.test(password))                              return 'Le mot de passe doit contenir au moins un chiffre.';
        if (!/[^A-Za-z0-9]/.test(password))                       return 'Le mot de passe doit contenir au moins un caractère spécial (!@#…).';
        if (calcStrength(password) < 2)                           return 'Mot de passe trop faible. Ajoutez des majuscules, chiffres ou symboles.';
        if (password !== confirm)                                 return 'Les mots de passe ne correspondent pas.';
        if (!cguCheckbox.checked)                                 return "Vous devez accepter les conditions d'utilisation.";
        return null;
    }

    /* ── Soumission du formulaire ── */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError(errorBox);

        /* Validation front-end */
        const validationError = validateRegister();
        if (validationError) {
            showError(errorBox, errorSpan, validationError);
            return;
        }

        /* État de chargement */
        setLoadingState(submitBtn, 'Création en cours…');

        /* Données à envoyer */
        const payload = {
            firstname: firstnameInput.value.trim(),
            lastname:  lastnameInput.value.trim(),
            email:     emailInput.value.trim(),
            password:  pwInput.value,
            'confirm-password': confirmInput.value,
        };

        try {
            const result = await API.auth.register(payload);
            const data = result.data;

            if (!data.success) {
                showError(errorBox, errorSpan,
                    data.error || data.message || "Une erreur est survenue lors de l'inscription.");
                resetButton(submitBtn, 'Créer mon compte');
                return;
            }

            showSuccess();

        } catch (error) {
            console.error("[Register] Erreur :", error);
            showError(errorBox, errorSpan, 'Une erreur est survenue. Veuillez réessayer.');
            resetButton(submitBtn, 'Créer mon compte');
        }
    });
}

/*
 4. INITIALISATION AUTOMATIQUE 
   Le script détecte la page active grâce à l'ID du formulaire
   et lance uniquement le module correspondant.
*/
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('login-form'))    initLogin();
    if (document.getElementById('register-form')) initRegister();
});