<?php
require_once('config.php');

if ($auth->inscrire('jean.dupont@example.com', 'SuperMotDePasse123!')) {
    echo "Inscription réussie ! Un email de validation vous a été envoyé.";
} else {
    echo "Une erreur est survenue (email peut-être déjà utilisé).";
}
?>