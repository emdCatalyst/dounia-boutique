<?php
session_start();
require 'config/database.php';

// 1. RÉCUPÉRATION DU PRODUIT
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Produit introuvable.");
}

// 2. TRAITEMENT DE LA MISE À JOUR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    $name       = $_POST['name'];
    $price_buy  = $_POST['price_wholesale']; 
    $price_sale = $_POST['price_sale']; 
    $stock      = $_POST['quantity'];
    $description = $price_buy; // On garde l'astuce du prix d'achat dans description

    // Gestion de l'image (si on en télécharge une nouvelle)
    $image_name = $product['image']; 
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        $image_name = time() . '_' . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $image_name);
    }

    $sql = "UPDATE products SET name=?, price=?, stock=?, image=?, description=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $price_sale, $stock, $image_name, $description, $id]);

    header("Location: products.php?success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier le produit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a1d; color: white; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-edit { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border-radius: 15px; padding: 30px; width: 100%; max-width: 600px; border: 1px solid rgba(255,255,255,0.2); }
        .form-control { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; }
        .form-control:focus { background: rgba(0,0,0,0.3); color: white; border-color: #0d6efd; }
    </style>
</head>
<body>

<div class="card-edit shadow">
    <h3 class="mb-4 text-center">Modifier le produit</h3>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Désignation</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Prix d'achat (DZD)</label>
                <input type="number" step="0.01" name="price_wholesale" class="form-control" value="<?= $product['description'] ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Prix Vente (DZD)</label>
                <input type="number" step="0.01" name="price_sale" class="form-control" value="<?= $product['price'] ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Stock / Quantité</label>
            <input type="number" name="quantity" class="form-control" value="<?= $product['stock'] ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Changer l'image (optionnel)</label>
            <input type="file" name="image" class="form-control" accept="image/*">
            <small class="text-white-50">Actuelle : <?= $product['image'] ?></small>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="update_product" class="btn btn-primary w-100">Enregistrer les modifications</button>
            <a href="products.php" class="btn btn-outline-light w-50">Annuler</a>
        </div>
    </form>
</div>

</body>
</html>