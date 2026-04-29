// État de l'application

let ingredients = [];
let recipes = [];
let weeklyMenu = {
    monday: { breakfast: null, lunch: null, dinner: null },
    tuesday: { breakfast: null, lunch: null, dinner: null },
    wednesday: { breakfast: null, lunch: null, dinner: null },
    thursday: { breakfast: null, lunch: null, dinner: null },
    friday: { breakfast: null, lunch: null, dinner: null },
    saturday: { breakfast: null, lunch: null, dinner: null },
    sunday: { breakfast: null, lunch: null, dinner: null }
};

const days = {
    monday: { name: 'Lundi', emoji: '' },
    tuesday: { name: 'Mardi', emoji: '' },
    wednesday: { name: 'Mercredi', emoji: '' },
    thursday: { name: 'Jeudi', emoji: '' },
    friday: { name: 'Vendredi', emoji: '' },
    saturday: { name: 'Samedi', emoji: '' },
    sunday: { name: 'Dimanche', emoji: '' }
};

const mealTypes = {
    breakfast: 'Petit-déjeuner ',
    lunch: 'Déjeuner ',
    dinner: 'Dîner '
};

