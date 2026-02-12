<?php
session_start();
require 'config/database.php';

$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Par défaut : début du mois
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

// Requête pour les statistiques globales sur la période
$stmt = $pdo->prepare("
    SELECT 
        COUNT(id) as nb_factures,
        SUM(quantite) as total_articles,
        SUM(montant_total) as depense_totale,
        SUM(montant_total - montant_paye) as total_dettes
    FROM fournisseur_achats 
    WHERE DATE(date_achat) BETWEEN ? AND ?
");
$stmt->execute([$date_debut, $date_fin]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Requête pour le détail par fournisseur sur la période
$stmt_f = $pdo->prepare("
    SELECT 
        fournisseur_nom, 
        COUNT(*) as nb_achats,
        SUM(quantite) as qte_total,
        SUM(montant_total) as montant_f
    FROM fournisseur_achats 
    WHERE DATE(date_achat) BETWEEN ? AND ?
    GROUP BY fournisseur_nom
    ORDER BY montant_f DESC
");
$stmt_f->execute([$date_debut, $date_fin]);
$details_f = $stmt_f->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Stats Fournisseurs - BOUTIQUE DINA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; }
        .glass-card { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 25px; margin-bottom: 20px; }
        .stat-val { font-size: 1.8rem; font-weight: 900; color: #38bdf8; }
        .form-control { background: rgba(0,0,0,0.4) !important; color: white !important; border: 1px solid rgba(255,255,255,0.2) !important; }
        .table { color: white !important; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-graph-up text-info"></i> Statistiques Achats</h3>
        <a href="caisse_fournisseur.php" class="btn btn-outline-light btn-sm">Retour Caisse</a>
    </div>

    <div class="glass-card shadow">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="small mb-1">Date Début</label>
                <input type="date" name="date_debut" class="form-control" value="<?= $date_debut ?>">
            </div>
            <div class="col-md-4">
                <label class="small mb-1">Date Fin</label>
                <input type="date" name="date_fin" class="form-control" value="<?= $date_fin ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-info w-100 fw-bold text-white">Calculer les Statistiques</button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="glass-card text-center">
                <p class="small text-white-50">Articles Entrés</p>
                <div class="stat-val"><?= number_format($stats['total_articles'] ?: 0, 0) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card text-center">
                <p class="small text-white-50">Dépense Totale</p>
                <div class="stat-val text-warning"><?= number_format($stats['depense_totale'] ?: 0, 2) ?> <small>DA</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card text-center">
                <p class="small text-white-50">Dettes Fournisseurs</p>
                <div class="stat-val text-danger"><?= number_format($stats['total_dettes'] ?: 0, 2) ?> <small>DA</small></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card text-center">
                <p class="small text-white-50">Nombre de Factures</p>
                <div class="stat-val"><?= $stats['nb_factures'] ?></div>
            </div>
        </div>
    </div>

    <div class="glass-card">
        <h5 class="mb-4">Répartition par Fournisseur</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr class="text-white-50">
                        <th>Nom du Fournisseur</th>
                        <th>Nombre d'Achats</th>
                        <th>Quantité Totale</th>
                        <th class="text-end">Montant Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($details_f as $f): ?>
                    <tr>
                        <td class="fw-bold text-info"><?= htmlspecialchars($f['fournisseur_nom']) ?></td>
                        <td><?= $f['nb_achats'] ?> fois</td>
                        <td><span class="badge bg-secondary"><?= $f['qte_total'] ?> pcs</span></td>
                        <td class="text-end fw-bold"><?= number_format($f['montant_f'], 2) ?> DA</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>