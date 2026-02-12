<?php
// Identifiants de connexion extraits de ton panel InfinityFree
$host     = 'localhost'; // MySQL Hostname
$dbname   = 'boutique';  // Ton MySQL Database Name réel
$username = 'root';           // Ton MySQL Username
$password = '';           // Ton MySQL Password


/*
Production
$host     = 'sql306.infinityfree.com'; // MySQL Hostname
$dbname   = 'if0_41047218_boutique';  // Ton MySQL Database Name réel
$username = 'if0_41047218';           // Ton MySQL Username
$password = 'YRGObOu2Xc3';    
*/
try {
    // Connexion via PDO avec configuration pour le serveur distant
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

    // Configuration des erreurs et du mode de récupération
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si la connexion échoue, on affiche l'erreur (utile pour le débugage)
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
