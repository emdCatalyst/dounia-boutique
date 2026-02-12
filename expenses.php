<?php
session_start();
date_default_timezone_set('Africa/Algiers'); // GMT+1
require 'config/database.php';

if(!isset($_SESSION['admin_logged'])){ header("Location: index.php"); exit; }

// 1. FILTRAGE PAR INTERVALLE (Par défaut : jour actuel - s'actualise chaque jour)
$date_start = $_GET['date_start'] ?? date('Y-m-d');
$date_end   = $_GET['date_end']   ?? date('Y-m-d');

// 2. ENREGISTREMENT D'UNE DÉCHARGE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $nom   = trim($_POST['n_cat']); // Nom de la décharge (ex: Manger)
    $type  = $_POST['expense_type']; // Magasin, Amine, ou Yasser
    $prix  = (float)$_POST['p_amt']; 
    $note  = trim($_POST['d_note']); 
    $date  = !empty($_POST['e_date']) ? $_POST['e_date'] : date('Y-m-d');

    // On ajoute is_deleted = 0 par défaut à l'insertion
    $sql = "INSERT INTO expenses (category, amount, description, expense_date, admin_name, is_deleted) VALUES (?, ?, ?, ?, ?, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nom, $prix, $note, $date, $type]);
    
    header("Location: expenses.php?date_start=$date_start&date_end=$date_end&success=1");
    exit;
}

// 3. SUPPRIMER (ENVOI À LA CORBEILLE)
if (isset($_GET['delete_id'])) {
    // AU LIEU DE DELETE, ON FAIT UPDATE is_deleted = 1
    $pdo->prepare("UPDATE expenses SET is_deleted = 1 WHERE id = ?")->execute([(int)$_GET['delete_id']]);
    header("Location: expenses.php?date_start=$date_start&date_end=$date_end&deleted=1");
    exit;
}

// 4. RÉCUPÉRATION DES DONNÉES FILTRÉES (Uniquement is_deleted = 0)
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE is_deleted = 0 AND expense_date BETWEEN ? AND ? ORDER BY id DESC");
$stmt->execute([$date_start, $date_end]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_magasin = 0; $total_amine = 0; $total_yasser = 0;
foreach($expenses as $ex) {
    if($ex['admin_name'] == 'Magasin') $total_magasin += $ex['amount'];
    if($ex['admin_name'] == 'Amine')   $total_amine += $ex['amount'];
    if($ex['admin_name'] == 'Yasser')  $total_yasser += $ex['amount'];
}
$total_general_periode = $total_magasin + $total_amine + $total_yasser;

$suggestions = $pdo->query("SELECT DISTINCT category FROM expenses")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Décharges - Boutique DINA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;900&display=swap');
        body { background: #020617; color: #ffffff; font-family: 'Lexend', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 20px; }
        .form-control-dark { background: rgba(15, 23, 42, 0.9) !important; border: 1px solid #334155 !important; color: white !important; border-radius: 10px; }
        .badge-type { padding: 6px 12px; border-radius: 8px; font-weight: 900; text-transform: uppercase; font-size: 0.75rem; }
        .bg-magasin { background: #00f2ff; color: #000; }
        .bg-amine { background: #facc15; color: #000; }
        .bg-yasser { background: #a855f7; color: #fff; }
        .table { color: white !important; }
        .btn-neon { background: #00f2ff; color: #020617; font-weight: 900; border-radius: 10px; border: none; }
        .total-box { background: rgba(0, 242, 255, 0.1); border: 1px solid #00f2ff; border-radius: 15px; padding: 15px; }
        .dynamic-total-zone { background: linear-gradient(45deg, #1e293b, #0f172a); border-left: 5px solid #00f2ff; padding: 10px 20px; border-radius: 12px; margin-bottom: 15px; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-wallet2 text-info"></i> Gestion Commune des Décharges</h3>
        <div>
            <a href="corbeille.php" class="btn btn-outline-danger btn-sm rounded-pill px-4 me-2"><i class="bi bi-trash3"></i> Corbeille</a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-4">Retour</a>
        </div>
    </div>

    <div class="row g-3 mb-4 text-center">
        <div class="col-md-4">
            <div class="glass-card border-start border-info border-4">
                <small class="text-white-50 uppercase">Partagé (Magasin)</small>
                <h4 class="text-info"><?= number_format($total_magasin, 2) ?> DA</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border-start border-warning border-4">
                <small class="text-white-50 uppercase">Budget Amine</small>
                <h4 class="text-warning"><?= number_format($total_amine, 2) ?> DA</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card border-start border-primary border-4">
                <small class="text-white-50 uppercase">Budget Yasser</small>
                <h4 style="color: #a855f7;"><?= number_format($total_yasser, 2) ?> DA</h4>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass-card">
                <h5 class="fw-bold mb-4">Ajouter une décharge</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="small fw-bold text-white-50 mb-2">TYPE DE CHARGE</label>
                        <select name="expense_type" class="form-control form-control-dark" required>
                            <option value="Magasin">Magasin (Partagé)</option>
                            <option value="Amine">Amine (Personnel)</option>
                            <option value="Yasser">Yasser (Personnel)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold text-white-50 mb-2">NOM / DÉSIGNATION</label>
                        <input type="text" name="n_cat" list="suggests" class="form-control form-control-dark" required placeholder="Ex: Loyer, Sandwich...">
                        <datalist id="suggests">
                            <?php foreach($suggestions as $s): ?><option value="<?= htmlspecialchars($s) ?>"><?php endforeach; ?>
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
                        <label class="small fw-bold text-white-50 mb-2">NOTE</label>
                        <textarea name="d_note" class="form-control form-control-dark" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_expense" class="btn btn-neon w-100 py-3">ENREGISTRER</button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass-card mb-4">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <input type="date" name="date_start" class="form-control form-control-dark" value="<?= $date_start ?>">
                    </div>
                    <div class="col-md-4">
                        <input type="date" name="date_end" class="form-control form-control-dark" value="<?= $date_end ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-info w-100 text-white">Filtrer Période</button>
                    </div>
                </form>
            </div>

            <div class="glass-card">
                <input type="text" id="searchInput" class="form-control form-control-dark mb-4" placeholder="🔍 Rechercher par Désignation (ex: Nourriture, Transport...)">
                
                <div class="dynamic-total-zone d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-bold text-white-50">TOTAL POUR CETTE RECHERCHE :</span>
                    <span class="fs-4 fw-900 text-info"><span id="dynamicTotal"><?= number_format($total_general_periode, 2) ?></span> DA</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-white-50 small">
                                <th>Date</th>
                                <th>Type</th>
                                <th>Désignation</th>
                                <th class="text-end">Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="expenseTable">
                            <?php foreach($expenses as $ex): 
                                $badge_class = "bg-magasin";
                                if($ex['admin_name'] == 'Amine') $badge_class = "bg-amine";
                                if($ex['admin_name'] == 'Yasser') $badge_class = "bg-yasser";
                            ?>
                            <tr class="row-item" data-amount="<?= $ex['amount'] ?>">
                                <td class="small opacity-50"><?= date('d/m/y', strtotime($ex['expense_date'])) ?></td>
                                <td><span class="badge-type <?= $badge_class ?>"><?= $ex['admin_name'] ?></span></td>
                                <td>
                                    <span class="fw-bold designation-text"><?= htmlspecialchars($ex['category']) ?></span><br>
                                    <small class="opacity-50"><?= htmlspecialchars($ex['description']) ?></small>
                                </td>
                                <td class="text-end fw-bold text-info amount-value"><?= number_format($ex['amount'], 2, '.', '') ?> DA</td>
                                <td class="text-end">
                                    <a href="?delete_id=<?= $ex['id'] ?>&date_start=<?= $date_start ?>&date_end=<?= $date_end ?>" class="text-danger fs-5" onclick="return confirm('Envoyer à la corbeille ?')"><i class="bi bi-trash"></i></a>
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
        let total = 0;
        
        document.querySelectorAll('.row-item').forEach(row => {
            let text = row.innerText.toLowerCase();
            if (text.includes(val)) {
                row.style.display = "";
                total += parseFloat(row.getAttribute('data-amount'));
            } else {
                row.style.display = "none";
            }
        });
        
        document.getElementById('dynamicTotal').innerText = total.toLocaleString('fr-FR', {minimumFractionDigits: 2});
    });
</script>
</body>
</html>