<?php
require_once('config.php');
$cle = $_GET['cle'] ?? '';
if ($auth->verifierEmail($cle)) {
    echo "Votre compte a été validé avec succès ! Vous pouvez vous connecter.";
} else {
    echo "Clé de vérification invalide ou déjà utilisée.";
}
?>