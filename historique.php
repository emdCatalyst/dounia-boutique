<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['admin_logged'])) {
    header("Location: index.php");
    exit;
}

// Définir le fuseau horaire pour l'Algérie (GMT+1)
date_default_timezone_set('Africa/Algiers');

$active_tab = $_GET['active_tab'] ?? 'sales';

// --- FILTRES VENTES ---
$s_start  = $_GET['s_start'] ?? date('Y-m-01');
$s_end    = $_GET['s_end'] ?? date('Y-m-d');
$s_source = $_GET['s_source'] ?? 'Both';
$s_search = $_GET['s_search'] ?? '';

// --- FILTRES ACHATS ---
$p_start  = $_GET['p_start'] ?? date('Y-m-01');
$p_end    = $_GET['p_end'] ?? date('Y-m-d');
$p_fourn  = $_GET['p_fourn'] ?? 'All';
$p_search = $_GET['p_search'] ?? '';

// Récupérer la liste des fournisseurs pour le filtre
$liste_fournisseurs = $pdo->query("SELECT DISTINCT fournisseur_nom FROM fournisseur_achats ORDER BY fournisseur_nom ASC")->fetchAll(PDO::FETCH_COLUMN);

// --- RÉCUPÉRATION HISTORIQUE FOURNISSEURS ---
$sql_fourn = "SELECT id, fournisseur_nom, article_nom, quantite, prix_achat_unitaire, montant_total, montant_paye, statut, date_achat 
              FROM fournisseur_achats 
              WHERE DATE(date_achat) BETWEEN ? AND ?";
$params_fourn = [$p_start, $p_end];

if ($p_fourn !== 'All') {
    $sql_fourn .= " AND fournisseur_nom = ?";
    $params_fourn[] = $p_fourn;
}
if (!empty($p_search)) {
    $sql_fourn .= " AND article_nom LIKE ?";
    $params_fourn[] = "%$p_search%";
}
$sql_fourn .= " ORDER BY date_achat DESC, id DESC";
$stmt_fournisseurs = $pdo->prepare($sql_fourn);
$stmt_fournisseurs->execute($params_fourn);
$achats = $stmt_fournisseurs->fetchAll(PDO::FETCH_ASSOC);

// --- RÉCUPÉRATION HISTORIQUE VENTES ---
$parts = [];
$params_ventes = [];

if ($s_source === 'Both' || $s_source === 'Boutique') {
    $sql_b = "SELECT 'Boutique' AS source, created_at AS date_vente, 'Client Comptoir' AS client, product_name AS article, quantity AS qte, sell_price AS prix_vente, total_amount AS total 
              FROM sales_boutique 
              WHERE DATE(created_at) BETWEEN ? AND ? AND is_deleted = 0";
    $params_ventes[] = $s_start; $params_ventes[] = $s_end;
    if (!empty($s_search)) {
        $sql_b .= " AND (product_name LIKE ? OR 'Client Comptoir' LIKE ?)";
        $params_ventes[] = "%$s_search%"; $params_ventes[] = "%$s_search%";
    }
    $parts[] = "($sql_b)";
}
if ($s_source === 'Both' || $s_source === 'Online') {
    $sql_o = "SELECT 'Online' AS source, o.created_at AS date_vente, CONCAT(o.customer_name, ' (', o.phone1, ')') AS client, p.name AS article, o.quantity AS qte, o.product_price AS prix_vente, (o.product_price * o.quantity) AS total 
              FROM orders_online o 
              LEFT JOIN products p ON o.product_id = p.id 
              WHERE DATE(o.created_at) BETWEEN ? AND ? 
              AND (o.status != 'RETOUR' OR o.status IS NULL) 
              AND o.is_deleted = 0";
    $params_ventes[] = $s_start; $params_ventes[] = $s_end;
    if (!empty($s_search)) {
        $sql_o .= " AND (p.name LIKE ? OR o.customer_name LIKE ? OR o.phone1 LIKE ?)";
        $params_ventes[] = "%$s_search%"; $params_ventes[] = "%$s_search%"; $params_ventes[] = "%$s_search%";
    }
    $parts[] = "($sql_o)";
}

if (!empty($parts)) {
    $sql_ventes = implode(" UNION ALL ", $parts) . " ORDER BY date_vente DESC";
    $stmt_ventes = $pdo->prepare($sql_ventes);
    $stmt_ventes->execute($params_ventes);
    $ventes = $stmt_ventes->fetchAll(PDO::FETCH_ASSOC);
} else { $ventes = []; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique Global - DINA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;600;700;900&display=swap');
        body { background: radial-gradient(circle at top right, #1e1b4b, #020617); color: #fff; font-family: 'Lexend', sans-serif; min-height: 100vh; }
        .glass-card { background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 25px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); backdrop-filter: blur(20px); margin-bottom: 20px; }
        .table-dark { background: rgba(15, 23, 42, 0.8) !important; }
        .form-control, .form-select { background: #1e293b !important; color: white !important; border: 1px solid #334155 !important; }
        .badge-source { padding: 5px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; }
        .bg-boutique { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .bg-online { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .nav-pills .nav-link { color: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.1); margin-right: 10px; border-radius: 50px; padding: 10px 25px; }
        .nav-pills .nav-link.active { background: #00f2ff !important; color: #020617 !important; font-weight: 700; }
        .filter-bar { background: rgba(255, 255, 255, 0.03); border-radius: 15px; padding: 15px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="p-4">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-900 text-info"><i class="bi bi-clock-history"></i> Historique Global</h2>
        <a href="dashboard.php" class="btn btn-outline-light rounded-pill px-4"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>

    <ul class="nav nav-pills mb-4" id="historyTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link <?= $active_tab == 'sales' ? 'active' : '' ?>" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab">
                Ventes <span class="badge bg-dark text-info ms-2"><?= count($ventes) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $active_tab == 'purchases' ? 'active' : '' ?>" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab">
                Achats <span class="badge bg-dark text-warning ms-2"><?= count($achats) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="historyTabsContent">
        <!-- ONGLETS VENTES -->
        <div class="tab-pane fade <?= $active_tab == 'sales' ? 'show active' : '' ?>" id="sales" role="tabpanel">
            <div class="glass-card">
                <form method="GET" class="filter-bar row g-3 align-items-end">
                    <input type="hidden" name="active_tab" value="sales">
                    <div class="col-md-2">
                        <label class="small text-white-50 mb-1">Du</label>
                        <input type="date" name="s_start" class="form-control" value="<?= htmlspecialchars($s_start) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-white-50 mb-1">Au</label>
                        <input type="date" name="s_end" class="form-control" value="<?= htmlspecialchars($s_end) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-white-50 mb-1">Canal</label>
                        <select name="s_source" class="form-select">
                            <option value="Both" <?= $s_source === 'Both' ? 'selected' : '' ?>>Tous</option>
                            <option value="Boutique" <?= $s_source === 'Boutique' ? 'selected' : '' ?>>Boutique</option>
                            <option value="Online" <?= $s_source === 'Online' ? 'selected' : '' ?>>Online</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-white-50 mb-1">Recherche (Article / Client)</label>
                        <input type="text" name="s_search" class="form-control" placeholder="Nom article, client..." value="<?= htmlspecialchars($s_search) ?>">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-info flex-grow-1 fw-bold rounded-pill">Filtrer</button>
                        <a href="?active_tab=sales" class="btn btn-outline-secondary rounded-pill px-3" title="Réinitialiser"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>

                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-dark table-hover align-middle">
                        <thead class="sticky-top bg-dark">
                            <tr class="text-white-50 small">
                                <th>Date & Heure</th>
                                <th>Source</th>
                                <th>Client</th>
                                <th>Article</th>
                                <th>Qté</th>
                                <th>Prix Unit.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ventes as $v): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($v['date_vente'])) ?></td>
                                <td><span class="badge-source <?= $v['source'] == 'Boutique' ? 'bg-boutique' : 'bg-online' ?>"><?= $v['source'] ?></span></td>
                                <td><?= htmlspecialchars($v['client']) ?></td>
                                <td><strong><?= htmlspecialchars($v['article']) ?></strong></td>
                                <td><?= $v['qte'] ?></td>
                                <td><?= number_format($v['prix_vente'], 2) ?> DA</td>
                                <td class="fw-bold text-info"><?= number_format($v['total'], 2) ?> DA</td>
                            </tr>
                            <?php endforeach; if(empty($ventes)) echo "<tr><td colspan='7' class='text-center py-4 opacity-50'>Aucun résultat</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ONGLET ACHATS FOURNISSEURS -->
        <div class="tab-pane fade <?= $active_tab == 'purchases' ? 'show active' : '' ?>" id="purchases" role="tabpanel">
            <div class="glass-card">
                <form method="GET" class="filter-bar row g-3 align-items-end">
                    <input type="hidden" name="active_tab" value="purchases">
                    <div class="col-md-2">
                        <label class="small text-white-50 mb-1">Du</label>
                        <input type="date" name="p_start" class="form-control" value="<?= htmlspecialchars($p_start) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-white-50 mb-1">Au</label>
                        <input type="date" name="p_end" class="form-control" value="<?= htmlspecialchars($p_end) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="small text-white-50 mb-1">Fournisseur</label>
                        <select name="p_fourn" class="form-select">
                            <option value="All" <?= $p_fourn === 'All' ? 'selected' : '' ?>>Tous</option>
                            <?php foreach($liste_fournisseurs as $f): ?>
                                <option value="<?= htmlspecialchars($f) ?>" <?= $p_fourn === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small text-white-50 mb-1">Recherche Article</label>
                        <input type="text" name="p_search" class="form-control" placeholder="Nom article..." value="<?= htmlspecialchars($p_search) ?>">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-warning flex-grow-1 fw-bold rounded-pill text-dark">Filtrer</button>
                        <a href="?active_tab=purchases" class="btn btn-outline-secondary rounded-pill px-3" title="Réinitialiser"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>

                <div class="table-responsive" style="max-height: 600px;">
                    <table class="table table-dark table-hover align-middle">
                        <thead class="sticky-top bg-dark">
                            <tr class="text-white-50 small">
                                <th>Date</th>
                                <th>Fournisseur</th>
                                <th>Article</th>
                                <th>Qté</th>
                                <th>P.A Unit</th>
                                <th>Total</th>
                                <th>Dette</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($achats as $h): $dette = max(0, $h['montant_total'] - $h['montant_paye']); ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($h['date_achat'])) ?></td>
                                <td><strong><?= htmlspecialchars($h['fournisseur_nom']) ?></strong></td>
                                <td><?= htmlspecialchars($h['article_nom']) ?></td>
                                <td><?= $h['quantite'] ?></td>
                                <td class="text-warning fw-bold"><?= number_format($h['prix_achat_unitaire'], 2) ?> DA</td>
                                <td><?= number_format($h['montant_total'], 2) ?> DA</td>
                                <td class="text-danger fw-bold"><?= number_format($dette, 2) ?> DA</td>
                                <td><span class="badge <?= $h['statut']=='Payé'?'bg-success':'bg-danger' ?>"><?= $h['statut'] ?></span></td>
                            </tr>
                            <?php endforeach; if(empty($achats)) echo "<tr><td colspan='8' class='text-center py-4 opacity-50'>Aucun résultat</td></tr>"; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>