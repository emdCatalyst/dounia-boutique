<?php
session_start();
require 'config/database.php';

if(!isset($_SESSION['admin_logged'])){
    header("Location: index.php");
    exit;
}

try {
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_deleted = 0")->fetchColumn() ?: 0;
    $totalStock = $pdo->query("SELECT SUM(stock) FROM products WHERE is_deleted = 0")->fetchColumn() ?: 0;
    
    // --- CALCUL DU CHIFFRE D'AFFAIRES NET (SANS LIVRAISON) ---
    $revB = $pdo->query("SELECT SUM(total_amount) FROM sales_boutique WHERE is_deleted = 0")->fetchColumn() ?: 0;
    
    // Pour l'online, on fait : SOMME(total) - SOMME(frais de livraison)
    $revO_query = $pdo->query("SELECT SUM(total_amount - delivery_price) FROM orders_online WHERE (status != 'RETOUR' OR status IS NULL) AND is_deleted = 0")->fetchColumn() ?: 0;
    
    $totalRevenue = $revB + $revO_query;

    $countB = $pdo->query("SELECT COUNT(*) FROM sales_boutique WHERE is_deleted = 0")->fetchColumn() ?: 0;
    $countO = $pdo->query("SELECT COUNT(*) FROM orders_online WHERE (status != 'RETOUR' OR status IS NULL) AND is_deleted = 0")->fetchColumn() ?: 0;
    $totalOrders = $countB + $countO;

    $recentSales = $pdo->query("
        (SELECT 'Boutique' as type, sale_date as date, total_amount as total FROM sales_boutique WHERE is_deleted = 0)
        UNION ALL
        (SELECT 'Online' as type, created_at as date, (total_amount - delivery_price) as total FROM orders_online WHERE (status != 'RETOUR' OR status IS NULL) AND is_deleted = 0)
        ORDER BY date DESC LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $totalProducts = $totalStock = $totalOrders = $totalRevenue = 0;
    $recentSales = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>DINA PREMIUM - COMMAND CENTER</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;600;900&family=Orbitron:wght@400;900&display=swap');
        
        :root {
            --neon-blue: #00f2ff;
            --neon-pink: #ff00e5;
            --neon-purple: #9d00ff;
            --glass-bg: rgba(15, 23, 42, 0.8);
        }

        body {
            font-family: 'Lexend', sans-serif;
            background: radial-gradient(circle at top right, #1e1b4b, #020617);
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            background: var(--glass-bg);
            backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            height: 100vh;
            position: sticky;
            top: 0;
            padding: 40px 20px;
        }

        .brand-logo {
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
            background: linear-gradient(to right, var(--neon-blue), var(--neon-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.8rem;
            letter-spacing: 2px;
            margin-bottom: 50px;
            text-align: center;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            padding: 14px 20px;
            margin-bottom: 8px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            border: 1px solid transparent;
        }

        .nav-link i { font-size: 1.2rem; margin-right: 15px; }

        .nav-link:hover, .nav-link.active {
            background: rgba(56, 189, 248, 0.1);
            color: var(--neon-blue);
            border-color: rgba(0, 242, 255, 0.3);
            transform: translateX(10px);
            box-shadow: -5px 0 20px rgba(0, 242, 255, 0.1);
        }

        .nav-link.premium {
            background: linear-gradient(45deg, #059669, #10b981);
            color: white !important;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .nav-link.corbeille {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171 !important;
            border-color: rgba(239, 68, 68, 0.2);
            margin-top: 10px;
        }
        .nav-link.corbeille:hover {
            background: #ef4444;
            color: white !important;
        }

        .nav-link.settings {
            background: rgba(255, 255, 255, 0.05);
            color: #94a3b8;
            margin-top: 10px;
        }
        .nav-link.settings:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .card-stat {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 30px;
            padding: 30px;
            position: relative;
            overflow: hidden;
            transition: 0.4s;
        }

        .card-stat:hover {
            background: rgba(30, 41, 59, 0.8);
            border-color: var(--neon-blue);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 900;
            margin-top: 10px;
            background: linear-gradient(to bottom, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .glow-icon {
            width: 55px; height: 55px;
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .glass-panel {
            background: var(--glass-bg);
            border-radius: 35px;
            padding: 35px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .badge-neon {
            padding: 8px 15px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 1px;
        }

        .btn-exit {
            margin-top: 20px;
            border-radius: 18px;
            padding: 15px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            font-weight: bold;
            transition: 0.3s;
        }

        .btn-exit:hover { background: #ef4444; color: white; }

        @media (max-width: 768px) {
            .sidebar { height: auto; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-xl-2 sidebar d-flex flex-column">
            <div class="brand-logo">BOUTIQUE DINA</div>
            
            <nav class="nav flex-column flex-grow-1">
                <a href="dashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="products.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventaire</a>
                <a href="sales_choice.php" class="nav-link"><i class="bi bi-bag-heart"></i> Ventes</a>
                <a href="caisse_fournisseur.php" class="nav-link"><i class="bi bi-truck-flatbed"></i> Fournisseurs</a>
                <a href="expenses.php" class="nav-link"><i class="bi bi-wallet2"></i> Décharges</a>
                <a href="historique.php" class="nav-link"><i class="bi bi-clock-history"></i> Historique</a>
                
                <a href="profit_analytics.php" class="nav-link premium">
                    <i class="bi bi-stars"></i> ANALYTICS PRO
                </a>

                <a href="corbeille.php" class="nav-link corbeille">
                    <i class="bi bi-trash3"></i> CORBEILLE
                </a>

                <a href="settings.php" class="nav-link settings">
                    <i class="bi bi-gear-wide-connected"></i> PARAMÈTRES
                </a>
            </nav>

            <a href="logout.php" class="btn btn-exit text-decoration-none text-center">
                <i class="bi bi-power"></i> DÉCONNEXION
            </a>
        </div>

        <div class="col-lg-9 col-xl-10 p-4 p-md-5">
            <header class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h1 class="fw-900 display-5">Bonjour, <span style="color:var(--neon-blue)">Yasser</span> <span class="badge bg-info text-dark fs-6 rounded-pill align-middle ms-2" style="font-family:'Lexend';">ADMIN</span></h1>
                    <p class="text-white-50 fs-5">Le centre de contrôle de votre empire est prêt.</p>
                </div>
                <div class="glass-panel py-2 px-4 border-0" style="background: rgba(255,255,255,0.05);">
                    <i class="bi bi-clock-history text-info me-2"></i>
                    <span class="fw-bold small"><?= date('l, d F Y') ?></span>
                </div>
            </header>

            <div class="row g-4 mb-5">
                <div class="col-md-6 col-xl-3">
                    <div class="card-stat">
                        <div class="glow-icon" style="color: #38bdf8;"><i class="bi bi-boxes"></i></div>
                        <p class="text-white-50 small mt-3 mb-0">Total Produits</p>
                        <div class="stat-value"><?= $totalProducts ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card-stat">
                        <div class="glow-icon" style="color: #fbbf24;"><i class="bi bi-lightning-charge"></i></div>
                        <p class="text-white-50 small mt-3 mb-0">Pièces en Stock</p>
                        <div class="stat-value"><?= $totalStock ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card-stat">
                        <div class="glow-icon" style="color: #10b981;"><i class="bi bi-cart-check"></i></div>
                        <p class="text-white-50 small mt-3 mb-0">Volume Ventes</p>
                        <div class="stat-value"><?= $totalOrders ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card-stat">
                        <div class="glow-icon" style="color: var(--neon-pink);"><i class="bi bi-currency-exchange"></i></div>
                        <p class="text-white-50 small mt-3 mb-0">CA (HORS Livr.)</p>
                        <div class="stat-value"><?= number_format($totalRevenue, 0, '.', ' ') ?> <span style="font-size:1rem">DA</span></div>
                    </div>
                </div>
            </div>

            <div class="glass-panel shadow-lg">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-900 mb-0"><i class="bi bi-activity text-info me-2"></i> Flux d'activités récentes</h4>
                    <a href="profit_analytics.php" class="btn btn-sm btn-outline-info rounded-pill px-4">Voir tout</a>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-white-50 small" style="border-bottom: 2px solid rgba(255,255,255,0.05);">
                                <th>SOURCE</th>
                                <th>DATE & HEURE</th>
                                <th class="text-end">MONTANT NET</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recentSales)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-white-50">Aucun signal détecté sur les radars.</td></tr>
                            <?php else: ?>
                                <?php foreach($recentSales as $sale): ?>
                                <tr>
                                    <td>
                                        <span class="badge-neon <?= $sale['type'] == 'Boutique' ? 'bg-primary' : 'bg-info' ?> text-white">
                                            <i class="bi <?= $sale['type'] == 'Boutique' ? 'bi-shop' : 'bi-globe' ?> me-1"></i>
                                            <?= $sale['type'] ?>
                                        </span>
                                    </td>
                                    <td class="text-white-50"><?= date('d M, H:i', strtotime($sale['date'])) ?></td>
                                    <td class="text-end fw-900 text-info fs-5"><?= number_format($sale['total'], 2) ?> <small>DA</small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer class="mt-5 text-center text-white-50 small">
                <div class="mb-2">DINA CONTROL PANEL v3.5 // ENCRYPTED CONNECTION</div>
                <div class="badge bg-dark text-secondary border border-secondary px-3">InfinityFree Cloud Database</div>
            </footer>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>