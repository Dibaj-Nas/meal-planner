

### 5.4 Accessibilité

#### Attributs ARIA

```html
<!-- Navigation -->
<nav role="navigation" aria-label="Navigation principale">
    <button aria-label="Aller à l'onglet génération">...</button>
    <span aria-hidden="true"></span> <!--on utilise aria-hidden="true" uniquement pour du contenu purement décoratif ou redondant. -->
</nav>

<!-- Formulaires -->
<input aria-describedby="budget-help" aria-required="true">
<small id="budget-help">Texte d'aide</small>

<!-- Live regions -->
<div role="region" aria-live="polite" aria-atomic="true">
    Résultat de la génération
</div>
```
