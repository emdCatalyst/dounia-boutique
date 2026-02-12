<?php
// 0. CONFIGURATION TIMEZONE (GMT+1 - Algérie)
date_default_timezone_set('Africa/Algiers');

session_start();
require 'config/database.php';

// 1. TRAITEMENT DE LA VENTE (MULTI-ARTICLES / PANIER)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['validate_sale'])) {
    $product_ids = $_POST['product_ids']; // Array de produits
    $quantities = $_POST['quantities'];   // Array de quantités
    $prices = $_POST['custom_prices'];    // Array de prix
    $discount = (float)$_POST['discount'];
    
    // On génère un identifiant unique pour le groupe de vente (panier)
    $sale_group_id = time() . rand(10, 99); 
    $exact_time = date('Y-m-d H:i:s');

    foreach ($product_ids as $index => $p_id) {
        $qty = (int)$quantities[$index];
        $price = (float)$prices[$index];

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$p_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product && $product['stock'] >= $qty) {
            // Calcul du total au prorata de la remise pour cette ligne
            $line_total = ($price * $qty) - ($discount / count($product_ids));
            
            $sql = "INSERT INTO sales_boutique (product_id, product_name, quantity, discount, sell_price, total_amount, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_sale = $pdo->prepare($sql);
            $stmt_sale->execute([$product['id'], $product['name'], $qty, $discount / count($product_ids), $price, $line_total, $exact_time]);

            $update = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $update->execute([$qty, $p_id]);
        }
    }
    header("Location: pos_sale.php?status=success");
    exit;
}

// 2. SUPPRESSION ET MISE À JOUR DU STOCK
if (isset($_GET['delete_sale'])) {
    $sale_id = (int)$_GET['delete_sale'];
    $stmt_find = $pdo->prepare("SELECT product_id, quantity FROM sales_boutique WHERE id = ?");
    $stmt_find->execute([$sale_id]);
    $sale_data = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if ($sale_data) {
        $update_stock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $update_stock->execute([$sale_data['quantity'], $sale_data['product_id']]);
        $pdo->prepare("DELETE FROM sales_boutique WHERE id = ?")->execute([$sale_id]);
    }
    header("Location: pos_sale.php"); 
    exit;
}

// 3. EXPORT EXCEL (CSV) AVEC TIMEZONE
if (isset($_GET['export'])) {
    $start = $_GET['start_date'] ?? date('Y-m-d');
    $end = $_GET['end_date'] ?? date('Y-m-d');
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ventes_boutique_'.$start.'.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
    fputcsv($output, ['ID', 'Heure (GMT+1)', 'Produit', 'Qte', 'Remise', 'Total']);
    
    $stmt_exp = $pdo->prepare("SELECT * FROM sales_boutique WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY id DESC");
    $stmt_exp->execute([$start, $end]);
    
    while($row = $stmt_exp->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['id'], date('H:i:s', strtotime($row['created_at'])), $row['product_name'], $row['quantity'], $row['discount'], $row['total_amount']]);
    }
    fclose($output); exit;
}

// 4. RÉCUPÉRATION FILTRÉE
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$products_list = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt_sales = $pdo->prepare("SELECT s.*, p.description as p_achat 
                             FROM sales_boutique s 
                             LEFT JOIN products p ON s.product_id = p.id 
                             WHERE DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.id DESC");
$stmt_sales->execute([$start_date, $end_date]);
$all_sales = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Caisse Boutique Wander</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: white; font-family: 'Lexend', sans-serif; }
        .glass-card { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); padding: 20px; margin-bottom: 20px; }
        .form-control, .form-select { background: rgba(0,0,0,0.3) !important; color: white !important; border: 1px solid rgba(255,255,255,0.2) !important; }
        .table { color: white !important; }
        .text-profit { color: #00ffcc; font-weight: bold; }
        .btn-remove { color: #ff4d4d; cursor: pointer; }
        option { color: black; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-calculator"></i> Caisse Boutique <span class="badge bg-primary fs-6 ms-2"><?= date('H:i') ?></span></h3>
        <div>
            <a href="online_sale.php" class="btn btn-info btn-sm text-white me-2">Vente En Ligne</a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="glass-card shadow border-top border-primary border-4">
                <form method="POST" id="posForm">
                    <div id="cartItems">
                        <div class="cart-item mb-3 p-2 border-bottom border-secondary">
                            <label class="small">Article</label>
                            <select name="product_ids[]" class="form-select mb-2 prod-select" required onchange="updateRow(this)">
                                <option value="" data-price="0">-- Choisir --</option>
                                <?php foreach($products_list as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= $p['name'] ?> (<?= $p['stock'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="row g-2">
                                <div class="col-6"><input type="number" step="0.01" name="custom_prices[]" class="form-control price-input" placeholder="Prix" oninput="calc()"></div>
                                <div class="col-4"><input type="number" name="quantities[]" class="form-control qty-input" value="1" oninput="calc()"></div>
                                <div class="col-2 text-center pt-2"><i class="bi bi-x-circle btn-remove" onclick="removeRow(this)"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-outline-info mb-3" onclick="addRow()"><i class="bi bi-plus-circle"></i> Ajouter un article</button>
                    
                    <label class="small text-danger">Remise Totale (DZD)</label>
                    <input type="number" step="0.01" name="discount" id="discountInput" class="form-control mb-3" value="0" oninput="calc()">
                    
                    <div class="p-3 bg-dark rounded text-center mb-3">
                        <h2 class="text-primary mb-0"><span id="displayTotal">0.00</span> DZD</h2>
                    </div>
                    <button type="submit" name="validate_sale" class="btn btn-primary w-100 fw-bold py-2">VALIDER LA VENTE</button>
                </form>
            </div>
        </div>

        <div class="col-md-7">
            <div class="glass-card">
                <div class="d-flex justify-content-between mb-3 align-items-center">
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $start_date ?>">
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $end_date ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
                    </form>
                    <a href="?export=1&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success btn-sm">Excel</a>
                </div>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover">
                        <thead class="small text-white-50"><tr><th>Heure</th><th>Produit</th><th>Profit</th><th>Total</th><th></th></tr></thead>
                        <tbody>
                            <?php $t_j = 0; $p_j = 0; foreach($all_sales as $s): 
                                $gain = ($s['total_amount']) - ((float)$s['p_achat'] * $s['quantity']);
                                $t_j += $s['total_amount']; $p_j += $gain; ?>
                                <tr>
                                    <td><small class="opacity-50"><?= date('H:i', strtotime($s['created_at'])) ?></small></td>
                                    <td><?= htmlspecialchars($s['product_name']) ?> <small class="text-white-50">(x<?= $s['quantity'] ?>)</small></td>
                                    <td class="text-profit"><?= number_format($gain, 0) ?></td>
                                    <td><?= number_format($s['total_amount'], 0) ?></td>
                                    <td><a href="?delete_sale=<?= $s['id'] ?>" class="text-danger" onclick="return confirm('Annuler cette vente ?')"><i class="bi bi-trash"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between p-3 bg-dark rounded border-top border-info mt-3">
                    <span class="text-profit">Profit : <?= number_format($p_j, 0) ?> DA</span>
                    <span class="text-primary fw-bold">Total : <?= number_format($t_j, 0) ?> DA</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addRow() {
    let container = document.getElementById('cartItems');
    let firstItem = container.querySelector('.cart-item').cloneNode(true);
    firstItem.querySelector('.prod-select').value = "";
    firstItem.querySelector('.price-input').value = "";
    firstItem.querySelector('.qty-input').value = "1";
    container.appendChild(firstItem);
}

function removeRow(btn) {
    let rows = document.querySelectorAll('.cart-item');
    if(rows.length > 1) {
        btn.closest('.cart-item').remove();
        calc();
    }
}

function updateRow(select) {
    let price = select.options[select.selectedIndex].getAttribute('data-price');
    select.closest('.cart-item').querySelector('.price-input').value = price;
    calc();
}

function calc() {
    let total = 0;
    let prices = document.querySelectorAll('.price-input');
    let qties = document.querySelectorAll('.qty-input');
    
    prices.forEach((p, i) => {
        let val = parseFloat(p.value) || 0;
        let q = parseInt(qties[i].value) || 0;
        total += (val * q);
    });
    
    let discount = parseFloat(document.getElementById('discountInput').value) || 0;
    document.getElementById('displayTotal').innerText = (total - discount).toFixed(2);
}
</script>
</body>
</html>