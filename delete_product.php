<?php
session_start();
require 'config/database.php';

// Vérifier si admin est connecté
if(!isset($_SESSION['admin_logged'])){
    header("Location: login.php");
    exit;
}

// Vérifier si un ID est passé en paramètre
if(isset($_GET['id']) && !empty($_GET['id'])){
    $id = $_GET['id'];

    // 1. (Optionnel) Supprimer le fichier image du dossier uploads
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if($product && $product['image'] != 'default.png'){
        $filePath = 'uploads/' . $product['image'];
        if(file_exists($filePath)){
            unlink($filePath); // Supprime le fichier physique
        }
    }

    // 2. Supprimer la ligne dans la base de données
    $delete = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $delete->execute([$id]);

    // Rediriger vers la liste des produits avec un message
    header("Location: products.php?deleted=1");
    exit;
} else {
    // Si pas d'ID, retour direct
    header("Location: products.php");
    exit;
}