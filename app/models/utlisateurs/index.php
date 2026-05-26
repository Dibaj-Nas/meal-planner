<?php
require_once('class.utilisateur.php');
$auth = new Utilisateur($pdo);
$auth->connexionAutomatiqueParCookie();

if (isset($_SESSION['user_id'])) {
    // L'utilisateur est connecté (soit par sa session active, soit par son cookie)
}
?>