# meal-planner
Application web pour générer, planifier et gérer vos menus hebdomadaires de manière économique et équilibrée.

## Fonctionnalités principales

### Génération automatique de menus
- Création de menus aléatoires pour la semaine
- Respect du budget défini
- Adaptation aux préférences alimentaires (végétarien, végan, sans porc)
- Personnalisation selon le nombre de personnes

### Calcul des coûts
- Estimation précise du coût total
- Coût par repas et par personne
- Comparaison budget vs coût réel
- Détection des économies ou surplus

### Apports nutritionnels
- Calcul des calories totales
- Suivi des protéines, glucides et lipides
- Moyenne journalière
- Résumé hebdomadaire

### Export multi-formats
- **PDF** : Menu complet avec résumés
- **ICS** : Import dans Google Calendar, Outlook, etc.
- Format d'impression optimisé

### Accessibilité
- Conforme WCAG 2.1 niveau AA
- Navigation complète au clavier
- Support des lecteurs d'écran
- Contrastes optimisés

---

## Installation rapide

### Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache ou Nginx)
- Navigateur moderne


## Style

### Variables CSS (à définir)
```css
:root {
    /* les couleurs de base */
    --bg-color: #FDF6EF;
    --primary-color: #F3E7D9;
    --secondary-color: #544349;
    
    /* le reste des couleurs */
    --btn-color: #CE2A2A;
    --title-color: #000000;
    --text-color: #2A191F;
    --text-activ: #F3E7D9;
    --breakfast: #EAD3B8;
    --lunch: #EFC696;
    --diner: #E7B377;

    /* Espacements */
    --spacing-xs: 0.25rem;
    --spacing-sm: 0.5rem;
    --spacing-md: 1rem;
    --spacing-lg: 2rem;
    
    /* Typographie */
    --font-family: 'Gloock';
    --font-base: 'glory';
    --font-size-base: 16px;
    
    /* Bordures */
    --border-radius: 8px;

}
```