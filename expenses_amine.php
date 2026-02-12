<?php
session_start();
require 'config/database.php';

// ON DÉFINIT L'ADMIN SUR AMINE
$current_admin = "AMINE"; 

// 1. FILTRAGE PAR PÉRIODE
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// 2. ENREGISTREMENT D'UNE DÉPENSE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $nom   = trim($_POST['n_cat']); 
    $prix  = (float)$_POST['p_amt']; 
    $note  = trim($_POST['d_note']); 
    $date  = !empty($_POST['e_date']) ? $_POST['e_date'] : date('Y-m-d');

    $sql = "INSERT INTO expenses (category, amount, description, expense_date, admin_name) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom, $prix, $note, $date, $current_admin]);
    
    // REDIRECTION CORRIGÉE VERS AMINE
    header("Location: expenses_amine.php?start_date=$start_date&end_date=$end_date&success=1");
    exit;
}

// 3. SUPPRIMER UNE DÉPENSE
if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM expenses WHERE id = ? AND admin_name = ?")->execute([(int)$_GET['delete_id'], $current_admin]);
    
    // REDIRECTION CORRIGÉE VERS AMINE
    header("Location: expenses_amine.php?start_date=$start_date&end_date=$end_date&deleted=1");
    exit;
}

// 4. RÉCUPÉRATION DES DONNÉES FILTRÉES
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE admin_name = ? AND expense_date BETWEEN ? AND ? ORDER BY id DESC");
$stmt->execute([$current_admin, $start_date, $end_date]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_total = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE admin_name = ? AND expense_date BETWEEN ? AND ?");
$stmt_total->execute([$current_admin, $start_date, $end_date]);
$total_expenses = $stmt_total->fetchColumn() ?: 0;

// 5. LOGIQUE DES SUGGESTIONS (S'adapte à Amine)
$stmt_sug = $pdo->prepare("SELECT DISTINCT category FROM expenses WHERE admin_name = ? AND category != ''");
$stmt_sug->execute([$current_admin]);
$suggestions = $stmt_sug->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Décharges - <?= $current_admin ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;900&display=swap');
        body { background: #020617; color: #ffffff; font-family: 'Lexend', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 25px; }
        .text-neon { color: #00f2ff; text-shadow: 0 0 10px rgba(0, 242, 255, 0.5); }
        .form-control-dark { background: rgba(15, 23, 42, 0.9) !important; border: 1px solid #334155 !important; color: white !important; border-radius: 12px; }
        .badge-designation { background: #00f2ff !important; color: #020617 !important; padding: 7px 15px; border-radius: 8px; font-weight: 900; text-transform: uppercase; display: inline-block; }
        .table { color: white !important; }
        .btn-neon { background: #00f2ff; color: #020617; font-weight: 900; border: none; border-radius: 12px; transition: 0.3s; }
        .btn-neon:hover { background: #00d8e4; transform: scale(1.02); }
    </style>
</head>
<body>

<nav class="py-3 border-bottom border-secondary mb-4" style="background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(10px);">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-4">RETOUR</a>
        <div class="fw-bold fs-4 text-uppercase">ADMIN : <span class="text-neon"><?= $current_admin ?></span></div>
    </div>
</nav>

<div class="container">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass-card shadow-lg">
                <h4 class="fw-bold mb-4">Nouvelle Décharge</h4>
                <form method="POST">
                    <div class="mb-3">
                        <label class="small fw-bold text-white-50 mb-2">NOM / TYPE (Suggestions)</label>
                        <input type="text" name="n_cat" list="suggested_names" class="form-control form-control-dark" required placeholder="Tapez ou choisissez...">
                        <datalist id="suggested_names">
                            <?php foreach($suggestions as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-white-50 mb-2">MONTANT (DA)</label>
                        <input type="number" step="0.01" name="p_amt" class="form-control form-control-dark" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-white-50 mb-2">DATE</label>
                        <input type="date" name="e_date" class="form-control form-control-dark" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-4">
                        <label class="small fw-bold text-white-50 mb-2">NOTE / DESCRIPTION</label>
                        <textarea name="d_note" class="form-control form-control-dark" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_expense" class="btn btn-neon w-100 py-3">ENREGISTRER</button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass-card mb-4">
                <div class="row g-2">
                    <div class="col-md-7">
                        <input type="text" id="searchInput" class="form-control form-control-dark" placeholder="🔍 Rechercher...">
                    </div>
                    <div class="col-md-5">
                        <form method="GET" class="d-flex gap-2">
                            <input type="date" name="start_date" class="form-control form-control-dark p-1" value="<?= $start_date ?>">
                            <input type="date" name="end_date" class="form-control form-control-dark p-1" value="<?= $end_date ?>">
                            <button type="submit" class="btn btn-info btn-sm text-white">OK</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="glass-card">
                <h5 class="fw-bold mb-4">Total Période : <span class="text-neon"><?= number_format($total_expenses, 2) ?> DA</span></h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-white-50 small text-uppercase">
                                <th>Date</th>
                                <th>Désignation</th>
                                <th>Note</th>
                                <th class="text-end">Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="expenseList">
                            <?php foreach($expenses as $ex): ?>
                            <tr class="item-row">
                                <td class="small opacity-50"><?= date('d/m/y', strtotime($ex['expense_date'])) ?></td>
                                <td><span class="badge-designation"><?= htmlspecialchars($ex['category']) ?></span></td>
                                <td class="small opacity-75"><?= htmlspecialchars($ex['description']) ?></td>
                                <td class="text-end fw-bold text-info"><?= number_format($ex['amount'], 2) ?> DA</td>
                                <td class="text-center">
                                    <a href="?delete_id=<?= $ex['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="text-danger"><i class="bi bi-trash3-fill"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let val = this.value.toLowerCase().trim();
        let rows = document.querySelectorAll('.item-row');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
        });
    });
</script>
</body>
</html>