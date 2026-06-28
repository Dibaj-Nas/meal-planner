#!/usr/bin/env bash
#
# start.sh — Lance le Planificateur de Repas
# ==========================================
# Démarre le serveur PHP en utilisant le front controller (public/index.php)
# comme routeur. C'est INDISPENSABLE : sans lui, le serveur ouvrirait
# l'application (index.php) qui redirige vers la connexion, au lieu d'ouvrir
# la page d'accueil (index.html).
#
# Utilisation :
#   ./start.sh           → démarre sur http://localhost:3000
#   ./start.sh 8000      → démarre sur le port indiqué
#
# (Si besoin la première fois : chmod +x start.sh)

# On se place dans le dossier du projet (là où se trouve ce script).
cd "$(dirname "$0")" || exit 1

PORT="${1:-3000}"

echo "🍽️  Planificateur de Repas"
echo "→ Ouvre http://localhost:${PORT} dans ton navigateur"
echo "→ (Ctrl+C pour arrêter)"
echo ""

# -t .            : la racine du projet est le dossier des fichiers (assets, etc.)
# public/index.php : script routeur (front controller) qui gère TOUS les chemins
php -S "localhost:${PORT}" -t . public/index.php
