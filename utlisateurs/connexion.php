<?php
require_once('config.php');
try {
    $seSouvenir = isset($_POST['remember']); // Booléen issu d'une checkbox
    if ($auth->connecter('jean.dupont@example.com', 'SuperMotDePasse123!', $seSouvenir)) {
        echo "Bienvenue " . $_SESSION['user_email'];
    } else {
        echo "Identifiants incorrects.";
    }
} catch (Exception $e) {
    echo $e->getMessage(); // Affiche le message de compte non vérifié
}
?>