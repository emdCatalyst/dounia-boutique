<?php
session_start();
date_default_timezone_set('Africa/Algiers'); // GMT+1
require 'config/database.php';

// 1. TRAITEMENT DE L'AJOUT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name        = $_POST['name'];
    $price_buy   = $_POST['price_wholesale']; 
    $price_sale  = $_POST['price_sale']; 
    $stock       = $_POST['quantity'];    
    
    // L'ASTUCE : On stocke le prix d'achat dans le champ description
    $description = $price_buy; 
    
    $image_name = "default.png"; 
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $image_name = time() . '_' . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $image_name);
    }

    $sql = "INSERT INTO products (name, price, stock, image, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $price_sale, $stock, $image_name, $description]);
    
    header("Location: products.php?success=1");
    exit;
}

// 2. RÉCUPÉRATION DES PRODUITS (On filtre par is_deleted = 0 pour ne pas afficher les supprimés)
$products = $pdo->query("SELECT * FROM products WHERE is_deleted = 0 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- CALCUL DES TOTAUX DYNAMIQUES ---
$total_types_produits = count($products);
$total_stock_global = 0;
$valeur_totale_inventaire = 0;

foreach($products as $p) {
    $qty = (int)$p['stock'];
    $p_achat = (float)($p['description'] ?? 0);
    
    $total_stock_global += $qty;
    // LOGIQUE : Prix d'achat * Quantité pour chaque article
    $valeur_totale_inventaire += ($p_achat * $qty);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits & Profit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Lexend', sans-serif;
        }
        
        body { 
            background: #0a0e27;
            min-height: 100vh; 
            color: #e2e8f0;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(0, 242, 255, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(236, 72, 153, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .container {
            position: relative;
            z-index: 1;
        }
        
        .glass-card { 
            background: rgba(15, 23, 42, 0.7); 
            backdrop-filter: blur(20px); 
            border-radius: 24px; 
            border: 1px solid rgba(148, 163, 184, 0.1); 
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            border-color: rgba(0, 242, 255, 0.2);
            box-shadow: 0 12px 40px rgba(0, 242, 255, 0.1);
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            padding: 25px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(0, 242, 255, 0.03) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 242, 255, 0.3);
            box-shadow: 0 20px 60px rgba(0, 242, 255, 0.15);
        }
        
        .stat-card.type-products {
            border-left: 4px solid #00f2ff;
        }
        
        .stat-card.stock-global {
            border-left: 4px solid #fbbf24;
        }
        
        .stat-card.valeur-achat {
            border-left: 4px solid #10b981;
        }
        
        .table { 
            color: #e2e8f0 !important; 
        }
        
        .table thead tr {
            background: rgba(15, 23, 42, 0.6);
            border-bottom: 2px solid rgba(0, 242, 255, 0.2);
        }
        
        .table thead th {
            color: #00f2ff !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 18px 12px;
            border: none;
        }
        
        .table tbody tr {
            border-bottom: 1px solid rgba(148, 163, 184, 0.05);
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(0, 242, 255, 0.03);
            transform: scale(1.01);
        }
        
        .img-prod { 
            width: 60px; 
            height: 60px; 
            object-fit: cover; 
            border-radius: 12px;
            border: 2px solid rgba(0, 242, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .img-prod:hover {
            transform: scale(1.1);
            border-color: #00f2ff;
            box-shadow: 0 4px 20px rgba(0, 242, 255, 0.3);
        }
        
        .bg-glass-search { 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid rgba(148, 163, 184, 0.2); 
            color: white;
            border-radius: 12px;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .bg-glass-search:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: #00f2ff;
            box-shadow: 0 0 0 3px rgba(0, 242, 255, 0.1);
            color: white;
        }
        
        .bg-glass-search::placeholder {
            color: rgba(226, 232, 240, 0.5);
        }
        
        .text-profit { 
            color: #10b981; 
            font-weight: 700; 
            font-size: 1rem;
        }
        
        .text-date { 
            font-size: 0.85rem; 
            color: #000000; 
            font-weight: 500; 
        }
        
        .modification-info {
            font-size: 0.75rem;
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.1);
            padding: 4px 10px;
            border-radius: 8px;
            border-left: 3px solid #fbbf24;
            margin-top: 5px;
            display: inline-block;
            font-weight: 500;
        }
        
        .modification-icon {
            color: #fbbf24;
            margin-right: 4px;
        }
        
        .form-control, .form-select {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(148, 163, 184, 0.2) !important;
            color: #e2e8f0 !important;
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(15, 23, 42, 0.9) !important;
            border-color: #00f2ff !important;
            box-shadow: 0 0 0 3px rgba(0, 242, 255, 0.1) !important;
            color: #e2e8f0 !important;
        }
        
        .form-label {
            color: #cbd5e1;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #00f2ff 0%, #0ea5e9 100%);
            border: none;
            border-radius: 12px;
            padding: 14px 32px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 242, 255, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 242, 255, 0.5);
            background: linear-gradient(135deg, #0ea5e9 0%, #00f2ff 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #0a0e27;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
            color: #0a0e27;
        }
        
        .btn-outline-light {
            border: 2px solid rgba(226, 232, 240, 0.3);
            color: #e2e8f0;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-light:hover {
            background: rgba(226, 232, 240, 0.1);
            border-color: #e2e8f0;
            color: #e2e8f0;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #00f2ff 0%, #06b6d4 100%);
            border: none;
            color: #0a0e27;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-info:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 242, 255, 0.4);
            color: #0a0e27;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            border: 1px solid #10b981;
            border-radius: 16px;
            color: #d1fae5;
            font-weight: 500;
            padding: 16px 20px;
        }
        
        .badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        }
        
        .bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        }
        
        h2, h4 {
            color: #f1f5f9;
            font-weight: 700;
        }
        
        .stat-label {
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.2;
        }
        
        .table-responsive {
            border-radius: 16px;
            overflow: hidden;
        }
        
        .product-name {
            color: #f1f5f9;
            font-weight: 600;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .glass-card, .stat-card {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>

<div class="container py-5">
    
    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success border-0 shadow-lg" id="alert-box">
            <i class="bi bi-check-circle-fill me-2"></i> Opération effectuée avec succès !
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1">
                <i class="bi bi-box-seam text-info"></i> Inventaire & Profits
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Gestion complète de vos produits</p>
        </div>
        <div>
            <a href="caisse_fournisseur.php" class="btn btn-warning me-2">
                <i class="bi bi-truck"></i> Caisse Fournisseur
            </a>
            <a href="dashboard.php" class="btn btn-outline-light">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card type-products h-100">
                <div class="stat-label">
                    <i class="bi bi-tags"></i> Types de produits
                </div>
                <div class="stat-value text-info"><?= $total_types_produits ?></div>
                <small class="text-muted">modèles disponibles</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stock-global h-100">
                <div class="stat-label">
                    <i class="bi bi-stack"></i> Stock Global
                </div>
                <div class="stat-value text-warning"><?= number_format($total_stock_global, 0) ?></div>
                <small class="text-muted">pièces en stock</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card valeur-achat h-100">
                <div class="stat-label">
                    <i class="bi bi-currency-dollar"></i> Valeur d'Achat Totale
                </div>
                <div class="stat-value text-success"><?= number_format($valeur_totale_inventaire, 2) ?></div>
                <small class="text-muted">DA investis</small>
            </div>
        </div>
    </div>

    <div class="glass-card mb-5">
        <h4 class="mb-4">
            <i class="bi bi-plus-circle text-info"></i> Ajouter un nouveau produit
        </h4>
        <form method="POST" enctype="multipart/form-data" class="row g-4">
            <div class="col-md-3">
                <label class="form-label">Désignation</label>
                <input type="text" name="name" class="form-control" placeholder="Nom du produit" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Prix d'achat (DZD)</label>
                <input type="number" step="0.01" name="price_wholesale" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Prix Vente (DZD)</label>
                <input type="number" step="0.01" name="price_sale" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Quantité</label>
                <input type="number" name="quantity" class="form-control" placeholder="0" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div class="col-12">
                <button type="submit" name="add_product" class="btn btn-primary w-100">
                    <i class="bi bi-save"></i> Enregistrer le produit
                </button>
            </div>
        </form>
    </div>

    <div class="glass-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">
                <i class="bi bi-list-ul text-info"></i> Liste des produits
            </h4>
            <div class="position-relative" style="width: 300px;">
                <i class="bi bi-search position-absolute" style="left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" id="searchInput" class="form-control bg-glass-search ps-5" placeholder="Rechercher un produit...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th><i class="bi bi-calendar3"></i> Date Ajout / Modif</th>
                        <th><i class="bi bi-image"></i> Image</th>
                        <th><i class="bi bi-tag"></i> Nom</th>
                        <th><i class="bi bi-cash-coin"></i> Achat</th>
                        <th><i class="bi bi-currency-exchange"></i> Vente</th>
                        <th><i class="bi bi-graph-up-arrow"></i> Profit/Unité</th>
                        <th><i class="bi bi-box"></i> Stock</th>
                        <th class="text-end"><i class="bi bi-gear"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="productTable">
                    <?php foreach($products as $p): 
                        $p_achat = (float)($p['description'] ?? 0);
                        $p_vente = (float)($p['price'] ?? 0);
                        $profit  = $p_vente - $p_achat;
                        
                        // Date d'ajout
                        $date_ajout = isset($p['created_at']) ? date('d/m/Y', strtotime($p['created_at'])) : '-';
                        $heure_ajout = isset($p['created_at']) ? date('H:i', strtotime($p['created_at'])) : '-';
                        
                        // Date de modification
                        $date_modif = isset($p['updated_at']) && $p['updated_at'] ? date('d/m/Y', strtotime($p['updated_at'])) : null;
                        $heure_modif = isset($p['updated_at']) && $p['updated_at'] ? date('H:i', strtotime($p['updated_at'])) : null;
                        
                        // Vérifier si le produit a été modifié
                        $is_modified = ($date_modif && $p['updated_at'] != $p['created_at']);
                    ?>
                    <tr class="product-row">
                        <td>
                            <div class="text-date">
                                <i class="bi bi-plus-circle text-success me-1"></i> <?= $date_ajout ?> à <?= $heure_ajout ?>
                            </div>
                            <?php if($is_modified): ?>
                                <div class="modification-info mt-1">
                                    <i class="bi bi-pencil-fill modification-icon"></i>
                                    Modifié le <?= $date_modif ?> à <?= $heure_modif ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <img src="uploads/<?= htmlspecialchars($p['image'] ?? 'default.png') ?>" class="img-prod" alt="<?= htmlspecialchars($p['name']) ?>">
                        </td>
                        <td class="product-name"><?= htmlspecialchars($p['name']) ?></td>
                        <td><span style="color: #94a3b8;"><?= number_format($p_achat, 2) ?> DA</span></td>
                        <td><span style="color: #cbd5e1; font-weight: 600;"><?= number_format($p_vente, 2) ?> DA</span></td>
                        <td class="text-profit">+<?= number_format($profit, 2) ?> DA</td>
                        <td>
                            <span class="badge <?= ($p['stock'] <= 0) ? 'bg-danger' : 'bg-success' ?>">
                                <?= $p['stock'] ?> pcs
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group" role="group">
                                <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info" title="Modifier">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="delete_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Recherche de produits
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('.product-row');
        rows.forEach(row => {
            let name = row.querySelector('.product-name').textContent.toLowerCase();
            row.style.display = name.includes(filter) ? '' : 'none';
        });
    });

    // Auto-hide success alert after 3 seconds
    setTimeout(() => {
        const alert = document.getElementById('alert-box');
        if(alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    }, 3000);
</script>

</body>
</html>
