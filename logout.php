<?php
session_start();
// 1. On vide toutes les variables de session
$_SESSION = array();

// 2. On détruit la session sur le serveur
session_destroy();

// 3. On redirige vers la page de connexion
header("Location: index.php");
exit;
?>