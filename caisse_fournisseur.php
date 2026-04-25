<?php
session_start();
require 'config/database.php';

if (!isset($_SESSION['supplier_cart'])) { $_SESSION['supplier_cart'] = []; }

// 1. RÉCUPÉRATION DES LISTES POUR LES MENUS DÉROULANTS
$liste_f = $pdo->query("SELECT DISTINCT fournisseur_nom FROM fournisseur_achats")->fetchAll(PDO::FETCH_COLUMN);
$liste_a = $pdo->query("SELECT DISTINCT name FROM products")->fetchAll(PDO::FETCH_COLUMN);

// --- LOGIQUE PHP IDENTIQUE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $_SESSION['supplier_name'] = $_POST['fournisseur_nom'];
    $_SESSION['supplier_tel'] = $_POST['fournisseur_tel']; 

    $item = [
        'name' => $_POST['article_nom'], 
        'p_unit' => (float)$_POST['prix_achat_unitaire'], 
        'p_vente' => (float)$_POST['prix_vente_unitaire'], 
        'qty' => (int)$_POST['quantite_achat'], 
        'total' => (float)$_POST['prix_achat_unitaire'] * (int)$_POST['quantite_achat']
    ];

    $_SESSION['supplier_cart'][] = $item;

    // Si on éditait un article, on s'assure qu'on a fini l'édition
    if (isset($_SESSION['edit_item'])) {
        unset($_SESSION['edit_item']);
    }
}

// EDIT / DELETE LOGIC
if (isset($_GET['del_cart'])) {
    $idx = (int)$_GET['del_cart'];
    if (isset($_SESSION['supplier_cart'][$idx])) {
        unset($_SESSION['supplier_cart'][$idx]);
        $_SESSION['supplier_cart'] = array_values($_SESSION['supplier_cart']); // reindex
    }
    header("Location: caisse_fournisseur.php");
    exit;
}

if (isset($_GET['edit_cart'])) {
    $idx = (int)$_GET['edit_cart'];
    if (isset($_SESSION['supplier_cart'][$idx])) {
        $_SESSION['edit_item'] = $_SESSION['supplier_cart'][$idx];
        unset($_SESSION['supplier_cart'][$idx]);
        $_SESSION['supplier_cart'] = array_values($_SESSION['supplier_cart']);
    }
    header("Location: caisse_fournisseur.php");
    exit;
}

$edit_item = $_SESSION['edit_item'] ?? null;


if (isset($_GET['clear_cart'])) { $_SESSION['supplier_cart'] = []; unset($_SESSION['supplier_name'], $_SESSION['supplier_tel']); header("Location: caisse_fournisseur.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['validate_invoice'])) {
    $fournisseur = $_SESSION['supplier_name']; 
    $tel = $_SESSION['supplier_tel']; 
    $paye_restant = (float)$_POST['montant_paye'];

    foreach ($_SESSION['supplier_cart'] as $item) {
        $versement = min($paye_restant, $item['total']); 
        $statut = ($versement >= $item['total']) ? 'Payé' : 'Dette';

        // 1. ADD TO FOURNISSEUR ACHATS
        $pdo->prepare("INSERT INTO fournisseur_achats (fournisseur_nom, fournisseur_tel, article_nom, prix_achat_unitaire, quantite, montant_total, montant_paye, statut) VALUES (?,?,?,?,?,?,?,?)")->execute([$fournisseur, $tel, $item['name'], $item['p_unit'], $item['qty'], $item['total'], $versement, $statut]);
        $paye_restant = max(0, $paye_restant - $item['total']);

        // 2. AUTOMATICALLY ADD TO STOCK
        $nom = $item['name']; 
        $p_v = $item['p_vente']; 
        $qty = $item['qty']; 
        $p_a = $item['p_unit'];

        $check = $pdo->prepare("SELECT id, stock FROM products WHERE name = ?"); 
        $check->execute([$nom]); 
        $existing = $check->fetch();

        if ($existing) { 
            $new_s = $existing['stock'] + $qty; 
            $pdo->prepare("UPDATE products SET price=?, stock=?, description=? WHERE id=?")->execute([$p_v, $new_s, $p_a, $existing['id']]); 
        } else { 
            $pdo->prepare("INSERT INTO products (name, price, stock, image, description) VALUES (?,?,?,?,?)")->execute([$nom, $p_v, $qty, 'default.png', $p_a]); 
        }
    }

    $_SESSION['supplier_cart'] = []; 
    unset($_SESSION['supplier_name'], $_SESSION['supplier_tel']); 
    header("Location: caisse_fournisseur.php?success=facture_validee"); 
    exit;
}


if (isset($_POST['update_purchase'])) {
    $id = $_POST['edit_id']; $qty = (int)$_POST['edit_qty']; $pu = (float)$_POST['edit_p_unit']; $paye = (float)$_POST['edit_paye']; $tot = $qty * $pu; $stat = ($paye >= $tot) ? 'Payé' : 'Dette';
    $pdo->prepare("UPDATE fournisseur_achats SET quantite=?, prix_achat_unitaire=?, montant_total=?, montant_paye=?, statut=? WHERE id=?")->execute([$qty, $pu, $tot, $paye, $stat, $id]);
    header("Location: caisse_fournisseur.php?updated=1"); exit;
}

if (isset($_GET['delete'])) { $pdo->prepare("DELETE FROM fournisseur_achats WHERE id = ?")->execute([(int)$_GET['delete']]); header("Location: caisse_fournisseur.php"); exit; }

$date_s = $_GET['date_start'] ?? date('Y-m-d'); $date_e = $_GET['date_end'] ?? date('Y-m-d');
$historique = $pdo->prepare("SELECT * FROM fournisseur_achats WHERE DATE(date_achat) BETWEEN ? AND ? ORDER BY id DESC");
$historique->execute([$date_s, $date_e]); $achats = $historique->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Caisse Fournisseur - BOUTIQUE DINA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body { background: #0f172a; color: white; font-family: sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        .form-control { background: #1e293b !important; color: white !important; border: 1px solid #334155 !important; }
        /* Style spécial pour Select2 en mode sombre */
        .select2-container--default .select2-selection--single { background-color: #1e293b !important; border: 1px solid #334155 !important; height: 38px !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: white !important; line-height: 35px !important; }
        .select2-dropdown { background-color: #1e293b !important; color: white !important; border: 1px solid #334155 !important; }
        .select2-search__field { background-color: #0f172a !important; color: white !important; }
        .table { color: white !important; }
        @media print { .no-print { display: none !important; } .print-area { display: block !important; color: black !important; } }
        .print-area { display: none; }
    </style>
</head>
<body class="p-4">

<div class="container no-print">

    <?php if(isset($_GET['success']) && $_GET['success'] === 'facture_validee'): ?>
        <div class="alert alert-success alert-dismissible fade show fw-bold" role="alert">
            Facture validée avec succès — articles ajoutés au stock !
            <button type="button" class="btn-close btn-close-black" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif(isset($_GET['updated'])): ?>
        <div class="alert alert-info alert-dismissible fade show fw-bold" role="alert">
            Achat mis à jour avec succès.
            <button type="button" class="btn-close btn-close-black" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between mb-4">
        <h3><i class="bi bi-truck text-warning"></i> Caisse Fournisseur</h3>
        <a href="products.php" class="btn btn-outline-light btn-sm">Stock</a>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="glass-card shadow">
                <form method="POST">
        <div class="mb-3">
            <label class="small text-info fw-bold">FOURNISSEUR (Sélectionner ou Taper)</label>
            <select name="fournisseur_nom" class="form-control select2-tags" required>
                <?php $s_name = $_SESSION['supplier_name'] ?? ''; ?>
                <option value="<?= htmlspecialchars($s_name) ?>" selected><?= $s_name ? htmlspecialchars($s_name) : 'Choisir...' ?></option>
                <?php foreach($liste_f as $f): ?>
                    <?php if($f !== $s_name): ?>
                        <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="small">Téléphone</label>
            <input type="text" name="fournisseur_tel" class="form-control" value="<?= htmlspecialchars($_SESSION['supplier_tel'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label class="small text-info fw-bold">ARTICLE (Sélectionner ou Taper)</label>
            <select name="article_nom" class="form-control select2-tags" required>
                <?php $e_name = $edit_item ? $edit_item['name'] : ''; ?>
                <option value="<?= htmlspecialchars($e_name) ?>" selected><?= $e_name ? htmlspecialchars($e_name) : "Choisir l'article..." ?></option>
                <?php foreach($liste_a as $a): ?>
                    <?php if($a !== $e_name): ?>
                        <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="small">P.A Unit</label>
                <input type="number" step="0.01" name="prix_achat_unitaire" class="form-control" value="<?= $edit_item ? $edit_item['p_unit'] : '' ?>" required>
            </div>
            <div class="col-6">
                <label class="small">Prix Vente</label>
                <input type="number" step="0.01" name="prix_vente_unitaire" class="form-control" value="<?= $edit_item ? ($edit_item['p_vente'] ?? '') : '' ?>" required>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-12">
                <label class="small">Qté</label>
                <input type="number" name="quantite_achat" class="form-control" value="<?= $edit_item ? $edit_item['qty'] : '1' ?>" required>
            </div>
        </div>
        <button type="submit" name="add_to_cart" class="btn btn-warning w-100 mt-3 fw-bold">
            <?= $edit_item ? "METTRE À JOUR (AJOUTER)" : "AJOUTER AU PANIER" ?>
        </button>
    </form>
            </div>
        </div>

        <div class="col-md-7">
            <div class="glass-card shadow">
                <h5 class="text-info">Panier Actuel : <?= $_SESSION['supplier_name'] ?? '' ?></h5>
                <table class="table table-dark table-sm mt-3">
        <thead><tr><th>Article</th><th>Qté</th><th>P.A</th><th>P.V</th><th>Total</th><th>Action</th></tr></thead>
        <tbody>
            <?php $gt = 0; foreach($_SESSION['supplier_cart'] as $k => $i): $gt += $i['total']; ?>
            <tr>
                <td><?= htmlspecialchars($i['name']) ?></td>
                <td><?= $i['qty'] ?></td>
                <td><?= number_format($i['p_unit'], 2) ?></td>
                <td><?= number_format($i['p_vente'] ?? 0, 2) ?></td>
                <td><?= number_format($i['total'], 2) ?></td>
                <td class="text-end">
                    <a href="?edit_cart=<?= $k ?>" class="btn btn-sm btn-outline-info px-2 py-0" title="Modifier"><i class="bi bi-pencil"></i></a>
                    <a href="?del_cart=<?= $k ?>" class="btn btn-sm btn-outline-danger px-2 py-0" title="Supprimer"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
                <div class="text-end h5 text-primary">Total : <?= number_format($gt, 2) ?> DZD</div>
                <form method="POST" class="mt-3">
                    <input type="number" step="0.01" name="montant_paye" class="form-control mb-2" placeholder="Montant versé" required>
                    <button type="submit" name="validate_invoice" class="btn btn-success w-100 fw-bold">VALIDER LA FACTURE</button>
                    <a href="?clear_cart=1" class="btn btn-outline-danger btn-sm w-100 mt-2">Vider</a>
                </form>
            </div>
        </div>
    </div>

    <div class="glass-card mt-4">
        <div class="d-flex justify-content-between mb-3">
            <h5 class="text-info">Historique</h5>
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="date_start" class="form-control form-control-sm" value="<?= $date_s ?>">
                <input type="date" name="date_end" class="form-control form-control-sm" value="<?= $date_e ?>">
                <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
            </form>
        </div>
        <table class="table align-middle">
            <thead><tr><th>Fournisseur</th><th>Article</th><th>Dette</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach($achats as $h): $dette = max(0, $h['montant_total'] - $h['montant_paye']); ?>
                <tr>
                    <td><?= htmlspecialchars($h['fournisseur_nom']) ?></td>
                    <td><?= htmlspecialchars($h['article_nom']) ?></td>
                    <td class="text-danger fw-bold"><?= number_format($dette, 2) ?></td>
                    <td><span class="badge <?= $h['statut']=='Payé'?'bg-success':'bg-danger' ?>"><?= $h['statut'] ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#edit<?= $h['id'] ?>"><i class="bi bi-pencil"></i></button>
                        
                        <button onclick='printGroupedInvoice("<?= $h['fournisseur_nom'] ?>")' class="btn btn-sm btn-light"><i class="bi bi-printer"></i></button>
                        <a href="?delete=<?= $h['id'] ?>" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                
                <div class="modal fade" id="edit<?= $h['id'] ?>" tabindex="-1">
                  <div class="modal-dialog text-dark"><div class="modal-content"><form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" value="<?= $h['id'] ?>">
                        <label>Qté</label><input type="number" name="edit_qty" class="form-control mb-2" value="<?= $h['quantite'] ?>">
                        <label>P.A Unit</label><input type="number" step="0.01" name="edit_p_unit" class="form-control mb-2" value="<?= $h['prix_achat_unitaire'] ?>">
                        <label>Versé</label><input type="number" step="0.01" name="edit_paye" class="form-control mb-2" value="<?= $h['montant_paye'] ?>">
                        <button type="submit" name="update_purchase" class="btn btn-warning w-100 mt-2">Mettre à jour</button>
                    </div></form></div></div>
                </div>

                                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // ACTIVE LA RECHERCHE DANS LES MENUS
    $('.select2-tags').select2({
        tags: true, // PERMET DE TAPER UN NOUVEAU NOM S'IL N'EST PAS DANS LA LISTE
        placeholder: "Sélectionner ou taper...",
        allowClear: true,
        width: '100%'
    });
});

function printGroupedInvoice(supplierName) {
    const allData = <?php echo json_encode($achats); ?>;
    const filtered = allData.filter(item => item.fournisseur_nom === supplierName);
    let h = ""; let tG = 0; let pG = 0;
    filtered.forEach(i => {
        h += `<tr><td>${i.article_nom}</td><td>${i.prix_achat_unitaire}</td><td>${i.quantite}</td><td>${parseFloat(i.montant_total).toFixed(2)}</td></tr>`;
        tG += parseFloat(i.montant_total); pG += parseFloat(i.montant_paye);
    });
    // On peut ouvrir une nouvelle fenêtre pour imprimer proprement
    let printWin = window.open('', '', 'height=600,width=800');
    printWin.document.write('<html><head><title>Facture</title></head><body>');
    printWin.document.write('<h2 style="text-align:center">BOUTIQUE DINA - FACTURE</h2>');
    printWin.document.write('<p>Fournisseur: '+supplierName+'</p>');
    printWin.document.write('<table border="1" width="100%" style="border-collapse:collapse;text-align:center"><tr><th>Article</th><th>P.U</th><th>Qté</th><th>Total</th></tr>'+h+'</table>');
    printWin.document.write('<p style="text-align:right">Total: '+tG.toFixed(2)+' DA<br>Versé: '+pG.toFixed(2)+' DA<br><b>Reste: '+(tG-pG).toFixed(2)+' DA</b></p>');
    printWin.document.write('</body></html>');
    printWin.document.close();
    printWin.print();
}
</script>
</body>
</html>