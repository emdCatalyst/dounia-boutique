<?php
session_start();
require 'config/database.php';

// 1. FILTRAGE PAR PÉRIODE - ACTUELLE (AUJOURD'HUI PAR DÉFAUT)
$start_date = $_GET['start_date'] ?? date('Y-m-d'); 
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// --- A. CALCUL DU CHIFFRE D'AFFAIRES ET BÉNÉFICE BRUT (CORRIGÉ) ---

// Online Sales : On exclut les retours, les supprimés et on retire la livraison du CA
$stmt = $pdo->prepare("SELECT SUM(total_amount - delivery_price) as total, 
                               SUM((product_price - CAST(p.description AS DECIMAL(10,2))) * o.quantity) as profit 
                       FROM orders_online o 
                       LEFT JOIN products p ON o.product_id = p.id 
                       WHERE DATE(o.created_at) BETWEEN ? AND ?
                       AND (o.status != 'RETOUR' OR o.status IS NULL)
                       AND o.is_deleted = 0");
$stmt->execute([$start_date, $end_date]);
$online = $stmt->fetch();

// Boutique Sales : On exclut les supprimés
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total, 
                               SUM((sell_price - CAST(p.description AS DECIMAL(10,2))) * s.quantity) as profit 
                       FROM sales_boutique s 
                       LEFT JOIN products p ON s.product_id = p.id 
                       WHERE DATE(s.created_at) BETWEEN ? AND ?
                       AND s.is_deleted = 0");
$stmt->execute([$start_date, $end_date]);
$boutique = $stmt->fetch();

$brut_total = ($online['profit'] ?? 0) + ($boutique['profit'] ?? 0);
$ca_total = ($online['total'] ?? 0) + ($boutique['total'] ?? 0);

// --- B. CALCUL DES DÉCHARGES PERSONNELLES ---
$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE admin_name = 'AMINE' AND DATE(expense_date) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$exp_amine = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE admin_name = 'YASSER' AND DATE(expense_date) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$exp_yasser = $stmt->fetchColumn() ?: 0;

// --- C. RÉPARTITION FINALE ---
$part_theorique = $brut_total / 2;
$net_amine = $part_theorique - $exp_amine;
$net_yasser = $part_theorique - $exp_yasser;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques & Partage de Profit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;900&display=swap');
        body { background: #020617; color: #ffffff; font-family: 'Lexend', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 25px; padding: 30px; }
        .stat-val { font-size: 2rem; font-weight: 900; }
        .text-neon-blue { color: #00f2ff; text-shadow: 0 0 10px rgba(0, 242, 255, 0.5); }
        .text-neon-green { color: #39ff14; text-shadow: 0 0 10px rgba(57, 255, 20, 0.5); }
        .text-neon-red { color: #ff3131; text-shadow: 0 0 10px rgba(255, 49, 49, 0.5); }
        .hr-custom { border-top: 2px dashed rgba(255,255,255,0.1); margin: 2rem 0; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h1 class="fw-900">ANALYSE DU <span class="text-neon-green">PROFIT</span></h1>
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $start_date ?>">
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $end_date ?>">
            <button type="submit" class="btn btn-info btn-sm">Calculer</button>
        </form>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="glass-card text-center">
                <h6 class="text-white-50">CA BRUT (HORS LIVRAISON)</h6>
                <div class="stat-val text-white"><?= number_format($ca_total, 2) ?> DA</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="glass-card text-center border-success">
                <h6 class="text-white-50">BÉNÉFICE RÉEL À PARTAGER</h6>
                <div class="stat-val text-neon-green"><?= number_format($brut_total, 2) ?> DA</div>
            </div>
        </div>
    </div>

    <div class="hr-custom"></div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="glass-card">
                <h3 class="fw-900 text-neon-blue mb-4">AMINE</h3>
                <div class="d-flex justify-content-between mb-2">
                    <span>Part Théorique (50%)</span>
                    <span class="fw-bold">+ <?= number_format($part_theorique, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-4 text-neon-red">
                    <span>Ses Décharges</span>
                    <span class="fw-bold">- <?= number_format($exp_amine, 2) ?></span>
                </div>
                <div class="p-3 bg-dark rounded-4 d-flex justify-content-between align-items-center">
                    <span class="fw-bold">RESTE NET :</span>
                    <span class="stat-val text-neon-blue" style="font-size: 1.5rem;"><?= number_format($net_amine, 2) ?> DA</span>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="glass-card">
                <h3 class="fw-900 text-neon-blue mb-4">YASSER</h3>
                <div class="d-flex justify-content-between mb-2">
                    <span>Part Théorique (50%)</span>
                    <span class="fw-bold">+ <?= number_format($part_theorique, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-4 text-neon-red">
                    <span>Ses Décharges</span>
                    <span class="fw-bold">- <?= number_format($exp_yasser, 2) ?></span>
                </div>
                <div class="p-3 bg-dark rounded-4 d-flex justify-content-between align-items-center">
                    <span class="fw-bold">RESTE NET :</span>
                    <span class="stat-val text-neon-blue" style="font-size: 1.5rem;"><?= number_format($net_yasser, 2) ?> DA</span>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="dashboard.php" class="btn btn-outline-light rounded-pill px-5">RETOUR AU MENU</a>
    </div>
</div>

</body>
</html>