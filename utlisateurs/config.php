<?php
require_once('class.utilisateur.php');
$pdo = new PDO('mysql:host=localhost;dbname=votre_bdd', 'user', 'password');
$auth = new Utilisateur($pdo);
?>