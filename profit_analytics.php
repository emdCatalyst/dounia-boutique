<?php
session_start();
require 'config/database.php';

// Définir le fuseau horaire pour l'Algérie (GMT+1)
date_default_timezone_set('Africa/Algiers');

// 1. GESTION DES DATES - PAR DÉFAUT AUJOURD'HUI (S'ACTUALISE AUTOMATIQUEMENT CHAQUE JOUR)
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-d'); 
$date_end   = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-d');    

// Détection si on affiche aujourd'hui
$is_today = ($date_start === date('Y-m-d') && $date_end === date('Y-m-d'));

// 2. FONCTION POUR CALCULER LE CHIFFRE D'AFFAIRES (CA) - SANS LES FRAIS DE LIVRAISON
function getRevenue($pdo, $start, $end) {
    // CA Boutique
    $sqlB = "SELECT SUM(total_amount) FROM sales_boutique WHERE DATE(sale_date) BETWEEN ? AND ?";
    $stmtB = $pdo->prepare($sqlB);
    $stmtB->execute([$start, $end]);
    $ca_boutique = $stmtB->fetchColumn() ?: 0;
    
    // CA Online (SANS livraison et SANS les retours)
    $sqlO = "SELECT SUM(total_amount - delivery_price) FROM orders_online 
             WHERE DATE(created_at) BETWEEN ? AND ? 
             AND (status != 'RETOUR' OR status IS NULL) 
             AND is_deleted = 0";
    $stmtO = $pdo->prepare($sqlO);
    $stmtO->execute([$start, $end]);
    $ca_online = $stmtO->fetchColumn() ?: 0;
    
    return [
        'boutique' => $ca_boutique,
        'online' => $ca_online,
        'total' => $ca_boutique + $ca_online
    ];
}

// 3. FONCTION POUR CALCULER LE PROFIT BRUT (Marge)
function getGrossProfit($pdo, $start, $end) {
    // Profit Boutique
    $sqlB = "SELECT SUM(s.total_amount - (CAST(p.description AS DECIMAL(10,2)) * s.quantity)) 
             FROM sales_boutique s JOIN products p ON s.product_id = p.id 
             WHERE DATE(s.sale_date) BETWEEN ? AND ?";
    $stmtB = $pdo->prepare($sqlB);
    $stmtB->execute([$start, $end]);
    $profit_boutique = $stmtB->fetchColumn() ?: 0;
    
    // Profit Online (SANS livraison, SANS retours, AVEC déduction de la remise)
    $sqlO = "SELECT SUM(s.total_amount - s.delivery_price - (CAST(p.description AS DECIMAL(10,2)) * s.quantity)) 
             FROM orders_online s JOIN products p ON s.product_id = p.id 
             WHERE DATE(s.created_at) BETWEEN ? AND ? 
             AND (s.status != 'RETOUR' OR s.status IS NULL) 
             AND s.is_deleted = 0";
    $stmtO = $pdo->prepare($sqlO);
    $stmtO->execute([$start, $end]);
    $profit_online = $stmtO->fetchColumn() ?: 0;

    return [
        'boutique' => $profit_boutique,
        'online' => $profit_online,
        'total' => $profit_boutique + $profit_online
    ];
}

// 4. RÉCUPÉRATION DES DONNÉES
$revenue = getRevenue($pdo, $date_start, $date_end);
$profit_data = getGrossProfit($pdo, $date_start, $date_end);

// 5. RÉCUPÉRATION DÉTAILLÉE DES DÉPENSES (DÉCHARGES)
$sqlExp = "SELECT * FROM expenses WHERE DATE(expense_date) BETWEEN ? AND ? ORDER BY expense_date DESC";
$stmtExp = $pdo->prepare($sqlExp);
$stmtExp->execute([$date_start, $date_end]);
$all_expenses = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

$total_magasin = 0;
$total_amine = 0;
$total_yasser = 0;

foreach($all_expenses as $ex) {
    if($ex['admin_name'] == 'Magasin') $total_magasin += $ex['amount'];
    if($ex['admin_name'] == 'Amine')   $total_amine += $ex['amount'];
    if($ex['admin_name'] == 'Yasser')  $total_yasser += $ex['amount'];
}

// 6. CALCULS FINAUX
$gross_profit = $profit_data['total'];

// Le bénéfice à partager est le profit brut moins les charges du magasin
$profit_a_partager = $gross_profit - $total_magasin;

// Part brute par personne (50/50)
$base_share = $profit_a_partager / 2;

// Part nette finale (on retire les dettes personnelles de chacun)
$final_yasser = $base_share - $total_yasser;
$final_amine = $base_share - $total_amine;

$net_total_boutique = $gross_profit - ($total_magasin + $total_amine + $total_yasser);

// 7. TOP PRODUITS (Exclut les retours et inclut la remise)
$top_sql = "
    SELECT name, SUM(profit_net) as total_profit, SUM(qte) as total_qty FROM (
        SELECT p.name, SUM(s.total_amount - (CAST(p.description AS DECIMAL(10,2)) * s.quantity)) as profit_net, SUM(s.quantity) as qte
        FROM sales_boutique s JOIN products p ON s.product_id = p.id 
        WHERE DATE(s.sale_date) BETWEEN ? AND ? GROUP BY p.id
        UNION ALL
        SELECT p.name, SUM(s.total_amount - s.delivery_price - (CAST(p.description AS DECIMAL(10,2)) * s.quantity)) as profit_net, SUM(s.quantity) as qte
        FROM orders_online s JOIN products p ON s.product_id = p.id 
        WHERE DATE(s.created_at) BETWEEN ? AND ? 
        AND (s.status != 'RETOUR' OR s.status IS NULL) 
        AND s.is_deleted = 0
        GROUP BY p.id
    ) AS combined_stats GROUP BY name ORDER BY total_profit DESC LIMIT 10";

$stmtTop = $pdo->prepare($top_sql);
$stmtTop->execute([$date_start, $date_end, $date_start, $date_end]);
$top_products = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

// 8. COMPTAGE DES VENTES (Exclut les retours)
$stmtCountB = $pdo->prepare("SELECT COUNT(*) FROM sales_boutique WHERE DATE(sale_date) BETWEEN ? AND ?");
$stmtCountB->execute([$date_start, $date_end]);
$count_boutique = $stmtCountB->fetchColumn();

$stmtCountO = $pdo->prepare("SELECT COUNT(DISTINCT CONCAT(customer_name, phone1, created_at)) FROM orders_online 
                             WHERE DATE(created_at) BETWEEN ? AND ? 
                             AND (status != 'RETOUR' OR status IS NULL) 
                             AND is_deleted = 0");
$stmtCountO->execute([$date_start, $date_end]);
$count_online = $stmtCountO->fetchColumn();

$total_v_period = $count_boutique + $count_online;

// 9. FRAIS DE LIVRAISON TOTAUX
$stmtDelivery = $pdo->prepare("SELECT SUM(delivery_price) FROM orders_online 
                                WHERE DATE(created_at) BETWEEN ? AND ? 
                                AND (status != 'RETOUR' OR status IS NULL) 
                                AND is_deleted = 0");
$stmtDelivery->execute([$date_start, $date_end]);
$total_delivery = $stmtDelivery->fetchColumn() ?: 0;

// 10. TAUX DE MARGE
$margin_rate = $revenue['total'] > 0 ? ($gross_profit / $revenue['total']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💎 PROFIT ANALYTICS - BOUTIQUE DINA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;600;700;900&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            color: #ffffff; 
            font-family: 'Lexend', sans-serif;
            overflow-x: hidden;
        }
        
        /* Animation de particules flottantes */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(74, 222, 128, 0.5);
            border-radius: 50%;
            animation: float 6s infinite ease-in-out;
        }
        
        .container { position: relative; z-index: 1; }
        
        /* Header avec effet glassmorphism */
        .header-section { 
            background: rgba(30, 41, 59, 0.3);
            backdrop-filter: blur(20px);
            border-bottom: 2px solid rgba(74, 222, 128, 0.2);
            padding: 30px 0;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        /* Carte principale avec effet néon */
        .main-card { 
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%);
            border: 3px solid;
            border-image: linear-gradient(135deg, #4ade80 0%, #38bdf8 100%) 1;
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 0 60px rgba(74, 222, 128, 0.3), 0 0 100px rgba(56, 189, 248, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .main-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(74, 222, 128, 0.1) 0%, transparent 70%);
            animation: pulse 4s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .share-box { 
            background: rgba(15, 23, 42, 0.8);
            border-radius: 20px;
            padding: 25px;
            border: 2px solid rgba(74, 222, 128, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .share-box:hover {
            transform: translateY(-5px);
            border-color: #4ade80;
            box-shadow: 0 10px 40px rgba(74, 222, 128, 0.3);
        }
        
        .share-box::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(74, 222, 128, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .share-box:hover::after {
            left: 100%;
        }
        
        .stat-card { 
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(56, 189, 248, 0.3);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 50px rgba(56, 189, 248, 0.4);
            border-color: #38bdf8;
        }
        
        .stat-card.revenue-card {
            border-color: rgba(139, 92, 246, 0.5);
        }
        
        .stat-card.revenue-card:hover {
            border-color: #8b5cf6;
            box-shadow: 0 15px 50px rgba(139, 92, 246, 0.4);
        }
        
        .stat-card.danger-card {
            border-color: rgba(239, 68, 68, 0.5);
        }
        
        .stat-card.danger-card:hover {
            border-color: #ef4444;
            box-shadow: 0 15px 50px rgba(239, 68, 68, 0.4);
        }
        
        .text-neon-blue { 
            color: #38bdf8;
            text-shadow: 0 0 20px rgba(56, 189, 248, 0.6);
        }
        
        .text-neon-green { 
            color: #4ade80;
            text-shadow: 0 0 30px rgba(74, 222, 128, 0.8), 0 0 60px rgba(74, 222, 128, 0.4);
            animation: glow 2s infinite alternate;
        }
        
        @keyframes glow {
            from { text-shadow: 0 0 20px rgba(74, 222, 128, 0.6); }
            to { text-shadow: 0 0 40px rgba(74, 222, 128, 1), 0 0 80px rgba(74, 222, 128, 0.6); }
        }
        
        .text-neon-red { 
            color: #ef4444;
            text-shadow: 0 0 20px rgba(239, 68, 68, 0.6);
        }
        
        .text-neon-purple {
            color: #a78bfa;
            text-shadow: 0 0 20px rgba(167, 139, 250, 0.6);
        }
        
        .search-bar { 
            background: rgba(15, 23, 42, 0.8) !important;
            border: 2px solid rgba(56, 189, 248, 0.3) !important;
            color: white !important;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .search-bar:focus {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 30px rgba(56, 189, 248, 0.5) !important;
            background: rgba(15, 23, 42, 0.95) !important;
        }
        
        .product-item { 
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.8) 100%);
            border-radius: 15px;
            margin-bottom: 12px;
            padding: 20px;
            border-left: 5px solid #38bdf8;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .product-item:hover {
            transform: translateX(10px);
            border-left-color: #4ade80;
            box-shadow: 0 10px 30px rgba(56, 189, 248, 0.3);
        }
        
        .badge-live {
            background: linear-gradient(135deg, #ef4444 0%, #f97316 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            animation: blink 1.5s infinite;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.6);
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: rgba(56, 189, 248, 0.2);
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ade80 0%, #38bdf8 100%);
            transition: width 1s ease;
            box-shadow: 0 0 20px rgba(74, 222, 128, 0.6);
        }
        
        .metric-icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            top: 10px;
            right: 15px;
        }
        
        .table-dark {
            background: rgba(15, 23, 42, 0.8) !important;
        }
        
        .table-dark tbody tr {
            border-bottom: 1px solid rgba(56, 189, 248, 0.1);
            transition: all 0.3s ease;
        }
        
        .table-dark tbody tr:hover {
            background: rgba(56, 189, 248, 0.1) !important;
            transform: scale(1.01);
        }
        
        .btn-filter {
            background: linear-gradient(135deg, #38bdf8 0%, #4ade80 100%);
            border: none;
            color: white;
            font-weight: 700;
            box-shadow: 0 5px 20px rgba(56, 189, 248, 0.4);
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 40px rgba(56, 189, 248, 0.6);
        }
        
        .auto-refresh-indicator {
            background: rgba(74, 222, 128, 0.2);
            border: 2px solid #4ade80;
            border-radius: 10px;
            padding: 8px 15px;
            font-size: 0.8rem;
            display: inline-block;
        }
    </style>
</head>
<body>

<!-- Particules flottantes -->
<div class="particles" id="particles"></div>

<div class="header-section mb-4">
    <div class="container">
        <div class="text-center mb-4">
            <h1 class="fw-900 mb-2">
                <i class="bi bi-gem"></i> PROFIT ANALYTICS
            </h1>
            <?php if($is_today): ?>
                <span class="badge-live">
                    <i class="bi bi-circle-fill"></i> LIVE - Aujourd'hui
                </span>
                <div class="mt-2">
                    <span class="auto-refresh-indicator">
                        <i class="bi bi-arrow-repeat"></i> Actualisation automatique quotidienne
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="row g-2 justify-content-center">
            <div class="col-md-3">
                <label class="small text-white-50 mb-1">Date début</label>
                <input type="date" name="date_start" class="form-control search-bar" value="<?= $date_start ?>">
            </div>
            <div class="col-md-3">
                <label class="small text-white-50 mb-1">Date fin</label>
                <input type="date" name="date_end" class="form-control search-bar" value="<?= $date_end ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-filter w-100 rounded-pill py-2">
                    <i class="bi bi-funnel"></i> FILTRER
                </button>
            </div>
            <?php if(!$is_today): ?>
            <div class="col-md-2 d-flex align-items-end">
                <a href="?" class="btn btn-outline-info w-100 rounded-pill py-2">
                    <i class="bi bi-calendar-day"></i> Aujourd'hui
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="container pb-5">
    
    <!-- CARTE PRINCIPALE - BÉNÉFICE NET -->
    <div class="main-card text-center mb-4">
        <div style="position: relative; z-index: 2;">
            <h5 class="text-uppercase fw-bold text-white-50 mb-3">
                <i class="bi bi-trophy"></i> Bénéfice Net à Partager
            </h5>
            <h1 class="display-1 fw-900 text-neon-green mb-4"><?= number_format($net_total_boutique, 2) ?> DA</h1>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="share-box">
                        <div style="position: relative; z-index: 2;">
                            <i class="bi bi-person-fill metric-icon text-info"></i>
                            <p class="small fw-bold text-info mb-2 text-uppercase">
                                <i class="bi bi-wallet2"></i> Part Finale YASSER
                            </p>
                            <h2 class="fw-900 mb-2 text-white"><?= number_format($final_yasser, 2) ?> DA</h2>
                            <div class="d-flex justify-content-between small text-white-50">
                                <span>Base: <?= number_format($base_share, 2) ?> DA</span>
                                <span class="text-danger">- <?= number_format($total_yasser, 2) ?> DA</span>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?= $base_share > 0 ? ($final_yasser / $base_share * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="share-box">
                        <div style="position: relative; z-index: 2;">
                            <i class="bi bi-person-fill metric-icon text-warning"></i>
                            <p class="small fw-bold text-warning mb-2 text-uppercase">
                                <i class="bi bi-wallet2"></i> Part Finale AMINE
                            </p>
                            <h2 class="fw-900 mb-2 text-white"><?= number_format($final_amine, 2) ?> DA</h2>
                            <div class="d-flex justify-content-between small text-white-50">
                                <span>Base: <?= number_format($base_share, 2) ?> DA</span>
                                <span class="text-danger">- <?= number_format($total_amine, 2) ?> DA</span>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?= $base_share > 0 ? ($final_amine / $base_share * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STATISTIQUES PRINCIPALES -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card revenue-card">
                <i class="bi bi-cash-stack metric-icon text-neon-purple"></i>
                <small class="text-neon-purple fw-bold d-block">CHIFFRE D'AFFAIRES</small>
                <h3 class="fw-900 text-white mb-1"><?= number_format($revenue['total'], 2) ?> DA</h3>
                <small class="text-white-50">
                    <i class="bi bi-shop"></i> <?= number_format($revenue['boutique'], 0) ?> | 
                    <i class="bi bi-globe"></i> <?= number_format($revenue['online'], 0) ?>
                </small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="bi bi-graph-up-arrow metric-icon text-neon-blue"></i>
                <small class="text-neon-blue fw-bold d-block">PROFIT BRUT</small>
                <h3 class="fw-900 text-white mb-1"><?= number_format($gross_profit, 2) ?> DA</h3>
                <small class="text-white-50">
                    Marge: <?= number_format($margin_rate, 1) ?>%
                </small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card">
                <i class="bi bi-receipt metric-icon text-info"></i>
                <small class="text-info fw-bold d-block">CHARGES MAGASIN</small>
                <h3 class="fw-900 text-info mb-1"><?= number_format($total_magasin, 2) ?> DA</h3>
                <small class="text-white-50"><?= count(array_filter($all_expenses, fn($e) => $e['admin_name'] == 'Magasin')) ?> décharges</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card danger-card">
                <i class="bi bi-person-x metric-icon text-neon-red"></i>
                <small class="text-neon-red fw-bold d-block">DÉCH. AMINE</small>
                <h3 class="fw-900 text-neon-red mb-1"><?= number_format($total_amine, 2) ?> DA</h3>
                <small class="text-white-50"><?= count(array_filter($all_expenses, fn($e) => $e['admin_name'] == 'Amine')) ?> décharges</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card danger-card">
                <i class="bi bi-person-x metric-icon text-neon-red"></i>
                <small class="text-neon-red fw-bold d-block">DÉCH. YASSER</small>
                <h3 class="fw-900 text-neon-red mb-1"><?= number_format($total_yasser, 2) ?> DA</h3>
                <small class="text-white-50"><?= count(array_filter($all_expenses, fn($e) => $e['admin_name'] == 'Yasser')) ?> décharges</small>
            </div>
        </div>
    </div>

    <!-- STATISTIQUES SECONDAIRES -->
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-basket3 metric-icon text-success"></i>
                <small class="text-success fw-bold d-block">VENTES TOTALES</small>
                <h2 class="fw-900 text-white"><?= $total_v_period ?></h2>
                <small class="text-white-50">
                    <i class="bi bi-shop"></i> <?= $count_boutique ?> | 
                    <i class="bi bi-globe"></i> <?= $count_online ?>
                </small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-truck metric-icon text-warning"></i>
                <small class="text-warning fw-bold d-block">FRAIS LIVRAISON</small>
                <h2 class="fw-900 text-white"><?= number_format($total_delivery, 2) ?> DA</h2>
                <small class="text-white-50"><?= $count_online ?> livraisons</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-percent metric-icon text-info"></i>
                <small class="text-info fw-bold d-block">TAUX DE MARGE</small>
                <h2 class="fw-900 text-white"><?= number_format($margin_rate, 1) ?>%</h2>
                <small class="text-white-50">Profit / CA</small>
            </div>
        </div>
    </div>

    <!-- DÉTAIL DES DÉCHARGES -->
    <div class="mb-5">
        <h3 class="fw-900 mb-3">
            <i class="bi bi-receipt-cutoff"></i> Détail des Décharges
            <span class="badge bg-info text-dark ms-2"><?= count($all_expenses) ?></span>
        </h3>
        <div class="stat-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead style="background: rgba(15, 23, 42, 0.95);">
                        <tr class="small">
                            <th><i class="bi bi-calendar3"></i> Date</th>
                            <th><i class="bi bi-person"></i> Propriétaire</th>
                            <th><i class="bi bi-tag"></i> Désignation</th>
                            <th class="text-end"><i class="bi bi-currency-dollar"></i> Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($all_expenses)): ?>
                            <tr>
                                <td colspan="4" class="text-center p-5">
                                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-3 text-white-50">Aucune décharge sur cette période</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($all_expenses as $ex): ?>
                            <tr>
                                <td class="small">
                                    <i class="bi bi-calendar-check text-info"></i>
                                    <?= date('d/m/Y', strtotime($ex['expense_date'])) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $ex['admin_name']=='Magasin'?'bg-info':($ex['admin_name']=='Amine'?'bg-warning':'bg-primary') ?> text-dark">
                                        <i class="bi bi-person-fill"></i> <?= $ex['admin_name'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($ex['category']) ?></td>
                                <td class="text-end">
                                    <span class="fw-bold text-white"><?= number_format($ex['amount'], 2) ?> DA</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TOP PRODUITS -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-900 mb-0">
                <i class="bi bi-star-fill text-warning"></i> Top 10 Produits
            </h3>
            <input type="text" id="search" class="form-control search-bar" placeholder="🔍 Rechercher..." style="width: 300px;">
        </div>
    </div>

    <div id="product-list">
        <?php if(empty($top_products)): ?>
            <div class="text-center p-5">
                <i class="bi bi-inbox" style="font-size: 4rem; opacity: 0.3;"></i>
                <p class="mt-3 text-white-50">Aucun produit vendu sur cette période</p>
            </div>
        <?php else: ?>
            <?php $rank = 1; foreach($top_products as $tp): ?>
            <div class="product-item d-flex justify-content-between align-items-center" data-name="<?= strtolower($tp['name']) ?>">
                <div class="d-flex align-items-center gap-3">
                    <div class="text-center" style="min-width: 50px;">
                        <h3 class="fw-900 mb-0 text-neon-blue">#<?= $rank ?></h3>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 text-white"><?= htmlspecialchars($tp['name']) ?></h5>
                        <small class="text-neon-blue">
                            <i class="bi bi-box-seam"></i> <?= $tp['total_qty'] ?> unités vendues
                        </small>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-neon-green fw-900" style="font-size: 1.5rem;">
                        + <?= number_format($tp['total_profit'], 2) ?> DA
                    </div>
                </div>
            </div>
            <?php $rank++; endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- BOUTONS DE NAVIGATION -->
    <div class="text-center mt-5 d-flex gap-3 justify-content-center">
        <a href="boutique_sale.php" class="btn btn-outline-info px-5 rounded-pill">
            <i class="bi bi-shop"></i> Ventes Boutique
        </a>
        <a href="online_sale.php" class="btn btn-outline-success px-5 rounded-pill">
            <i class="bi bi-globe"></i> Ventes Online
        </a>
        <a href="dashboard.php" class="btn btn-outline-light px-5 rounded-pill">
            <i class="bi bi-speedometer2"></i> Menu Principal
        </a>
    </div>
</div>

<script>
    // Génération de particules flottantes
    function createParticles() {
        const container = document.getElementById('particles');
        for(let i = 0; i < 50; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 6 + 's';
            particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
            container.appendChild(particle);
        }
    }
    
    createParticles();
    
    // Recherche de produits
    document.getElementById('search').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.product-item').forEach(item => {
            let name = item.getAttribute('data-name');
            item.style.display = name.includes(val) ? 'flex' : 'none';
        });
    });
    
    // Animation au chargement
    window.addEventListener('load', function() {
        document.querySelectorAll('.stat-card, .product-item, .share-box').forEach((el, index) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            setTimeout(() => {
                el.style.transition = 'all 0.5s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });
</script>

</body>
</html>
