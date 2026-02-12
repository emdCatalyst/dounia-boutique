<?php
session_start();
require 'config/database.php';

if(!isset($_SESSION['admin_logged'])){ header("Location: index.php"); exit; }

// --- LOGIQUE DE RESTAURATION ---
if (isset($_GET['restore_type']) && isset($_GET['id'])) {
    $table = $_GET['restore_type']; 
    $id = (int)$_GET['id'];
    
    // Liste blanche des tables autorisées pour la sécurité
    $allowed_tables = ['orders_online', 'products', 'expenses'];
    if (in_array($table, $allowed_tables)) {
        $pdo->prepare("UPDATE $table SET is_deleted = 0 WHERE id = ?")->execute([$id]);
    }
    header("Location: corbeille.php?restored=1");
    exit;
}

// --- LOGIQUE DE SUPPRESSION DÉFINITIVE ---
if (isset($_GET['force_delete_type']) && isset($_GET['id'])) {
    $table = $_GET['force_delete_type'];
    $id = (int)$_GET['id'];
    
    $allowed_tables = ['orders_online', 'products', 'expenses'];
    if (in_array($table, $allowed_tables)) {
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    }
    header("Location: corbeille.php?purged=1");
    exit;
}

// Récupération des éléments supprimés
$deleted_orders = $pdo->query("SELECT * FROM orders_online WHERE is_deleted = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$deleted_products = $pdo->query("SELECT * FROM products WHERE is_deleted = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$deleted_expenses = $pdo->query("SELECT * FROM expenses WHERE is_deleted = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>CORBEILLE - DINA CONTROL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@400;900&display=swap');
        body { background: #020617; color: white; font-family: 'Lexend', sans-serif; padding: 40px; }
        .glass-card { background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 25px; padding: 25px; margin-bottom: 30px; }
        .table { color: white !important; }
        .btn-restore { color: #4ade80; border: 1px solid #4ade80; border-radius: 10px; padding: 5px 15px; text-decoration: none; font-size: 0.8rem; transition: 0.3s; }
        .btn-restore:hover { background: #4ade80; color: #000; }
        .btn-purge { color: #ff4d4d; border: 1px solid #ff4d4d; border-radius: 10px; padding: 5px 15px; text-decoration: none; font-size: 0.8rem; transition: 0.3s; }
        .btn-purge:hover { background: #ff4d4d; color: #fff; }
        .alert-custom { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; border-radius: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h1 class="fw-900 text-danger"><i class="bi bi-trash3-fill"></i> ARCHIVES CORBEILLE</h1>
        <a href="dashboard.php" class="btn btn-outline-light rounded-pill px-4">Dashboard</a>
    </div>

    <?php if(isset($_GET['restored'])): ?>
        <div class="alert alert-custom mb-4"><i class="bi bi-check-circle-fill me-2"></i> Élément restauré avec succès !</div>
    <?php endif; ?>

    <div class="glass-card">
        <h4 class="text-info mb-4"><i class="bi bi-globe2 me-2"></i> Commandes Online supprimées</h4>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-white-50 small">
                        <th>Client</th>
                        <th>Montant</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($deleted_orders as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['customer_name']) ?></strong><br><small><?= $o['phone1'] ?></small></td>
                        <td><?= number_format($o['total_amount'], 2) ?> DA</td>
                        <td class="text-end">
                            <a href="?restore_type=orders_online&id=<?= $o['id'] ?>" class="btn-restore me-2"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</a>
                            <a href="?force_delete_type=orders_online&id=<?= $o['id'] ?>" class="btn-purge" onclick="return confirm('Supprimer définitivement cette commande ?')"><i class="bi bi-x-circle"></i> Purger</a>
                        </td>
                    </tr>
                    <?php endforeach; if(empty($deleted_orders)) echo "<tr><td colspan='3' class='text-center opacity-50 py-4'>Aucune commande ici</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h4 class="text-warning mb-4"><i class="bi bi-box-seam me-2"></i> Produits supprimés</h4>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-white-50 small">
                        <th>Produit</th>
                        <th>Stock rest.</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($deleted_products as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><?= $p['stock'] ?> pcs</td>
                        <td class="text-end">
                            <a href="?restore_type=products&id=<?= $p['id'] ?>" class="btn-restore me-2"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</a>
                            <a href="?force_delete_type=products&id=<?= $p['id'] ?>" class="btn-purge" onclick="return confirm('Supprimer définitivement ce produit ?')"><i class="bi bi-x-circle"></i> Purger</a>
                        </td>
                    </tr>
                    <?php endforeach; if(empty($deleted_products)) echo "<tr><td colspan='3' class='text-center opacity-50 py-4'>Aucun produit ici</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h4 class="text-danger mb-4"><i class="bi bi-wallet2 me-2"></i> Décharges (Expenses) supprimées</h4>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-white-50 small">
                        <th>Motif / Description</th>
                        <th>Montant</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($deleted_expenses as $e): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($e['description']) ?></strong></td>
                        <td class="text-danger fw-bold"><?= number_format($e['amount'], 2) ?> DA</td>
                        <td class="text-end">
                            <a href="?restore_type=expenses&id=<?= $e['id'] ?>" class="btn-restore me-2"><i class="bi bi-arrow-counterclockwise"></i> Restaurer</a>
                            <a href="?force_delete_type=expenses&id=<?= $e['id'] ?>" class="btn-purge" onclick="return confirm('Définitif ? Cette décharge sera perdue.')"><i class="bi bi-x-circle"></i> Purger</a>
                        </td>
                    </tr>
                    <?php endforeach; if(empty($deleted_expenses)) echo "<tr><td colspan='3' class='text-center opacity-50 py-4'>Aucune décharge ici</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>