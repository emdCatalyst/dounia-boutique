<?php
// --- 1. EXPORTATION EXCEL (FORMAT OPTIMISÉ .XLS - OUVERTURE DIRECTE) ---
if (isset($_GET['export'])) {
    require_once 'config/database.php';
    $start = $_GET['start_date'] ?? date('Y-m-d');
    $end = $_GET['end_date'] ?? date('Y-m-d');
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=ecotrack_export_'.date('Y-m-d').'.xls');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM pour Excel
    echo '<table border="1">';
    echo '<tr>';
    echo '<th style="background-color:#00f2ff">reference commande</th>';
    echo '<th style="background-color:#00f2ff">nom et prenom du destinataire*</th>';
    echo '<th style="background-color:#00f2ff">telephone*</th>';
    echo '<th style="background-color:#00f2ff">telephone 2</th>';
    echo '<th style="background-color:#00f2ff">code wilaya*</th>';
    echo '<th style="background-color:#00f2ff">wilaya de livraison</th>';
    echo '<th style="background-color:#00f2ff">commune de livraison*</th>';
    echo '<th style="background-color:#00f2ff">adresse de livraison*</th>';
    echo '<th style="background-color:#00f2ff">produit*</th>';
    echo '<th style="background-color:#00f2ff">poids (kg)</th>';
    echo '<th style="background-color:#00f2ff">montant du colis*</th>';
    echo '<th style="background-color:#00f2ff">remarque</th>';
    echo '<th style="background-color:#00f2ff">FRAGILE&#10;( si oui mettez OUI sinon laissez vide )</th>';
    echo '<th style="background-color:#00f2ff">ESSAYAGE PERMI&#10;( si oui mettez OUI, sinon laissez vide )</th>';
    echo '<th style="background-color:#00f2ff">ECHANGE&#10;( si oui mettez OUI sinon laissez vide )</th>';
    echo '<th style="background-color:#00f2ff">PICK UP&#10;( si oui mettez OUI sinon laissez vide )</th>';
    echo '<th style="background-color:#00f2ff">RECOUVREMENT&#10;( si oui mettez OUI sinon laissez vide )</th>';
    echo '<th style="background-color:#00f2ff">STOP DESK&#10;( si oui mettez OUI sinon laissez vide )</th>';
    echo '<th style="background-color:#00f2ff">Lien map</th>';
    echo '</tr>';
    
    $sql = "SELECT o.*, 
            GROUP_CONCAT(CONCAT(p.name, ' (', o.quantity, 'x)') SEPARATOR ' + ') as all_products,
            SUM(o.total_amount) as total_commande
            FROM orders_online o LEFT JOIN products p ON o.product_id = p.id 
            WHERE DATE(o.created_at) BETWEEN ? AND ? 
            AND (o.status != 'RETOUR' OR o.status IS NULL) 
            AND o.is_deleted = 0 
            GROUP BY o.customer_name, o.phone1, o.created_at 
            ORDER BY o.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start, $end]);

    $wilayas = ["01" => "Adrar", "02" => "Chlef", "03" => "Laghouat", "04" => "Oum El Bouaghi", "05" => "Batna", "06" => "Béjaïa", "07" => "Biskra", "08" => "Béchar", "09" => "Blida", "10" => "Bouira", "11" => "Tamanrasset", "12" => "Tébessa", "13" => "Tlemcen", "14" => "Tiaret", "15" => "Tizi Ouzou", "16" => "Alger", "17" => "Djelfa", "18" => "Jijel", "19" => "Sétif", "20" => "Saïda", "21" => "Skikda", "22" => "Sidi Bel Abbès", "23" => "Annaba", "24" => "Guelma", "25" => "Constantine", "26" => "Médéa", "27" => "Mostaganem", "28" => "M'Sila", "29" => "Mascara", "30" => "Ouargla", "31" => "Oran", "32" => "El Bayadh", "33" => "Illizi", "34" => "Bordj Bou Arreridj", "35" => "Boumerdès", "36" => "El Tarf", "37" => "Tindouf", "38" => "Tissemsilt", "39" => "El Oued", "40" => "Khenchela", "41" => "Souk Ahras", "42" => "Tipaza", "43" => "Mila", "44" => "Aïn Defla", "45" => "Naâma", "46" => "Aïn Témouchent", "47" => "Ghardaïa", "48" => "Relizane", "49" => "Timimoun", "50" => "Bordj Badji Mokhtar", "51" => "Ouled Djellal", "52" => "Béni Abbès", "53" => "In Salah", "54" => "In Guezzam", "55" => "Touggourt", "56" => "Djanet", "57" => "El M'Ghair", "58" => "El Meniaa"];

    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '<tr>';
        echo '<td>'.$r['id'].'</td>';
        echo '<td>'.$r['customer_name'].'</td>';
        echo '<td style="mso-number-format:\@;">'.$r['phone1'].'</td>';
        echo '<td style="mso-number-format:\@;">'.($r['phone2'] ?: '').'</td>';
        echo '<td>'.$r['wilaya_code'].'</td>';
        echo '<td>'.($wilayas[$r['wilaya_code']] ?? '').'</td>';
        echo '<td>'.$r['commune'].'</td>';
        echo '<td>'.$r['address'].'</td>';
        echo '<td>'.$r['all_products'].'</td>';
        echo '<td></td>';
        echo '<td>'.$r['total_commande'].'</td>';
        echo '<td>'.$r['note'].'</td>';
        echo '<td></td>';
        echo '<td>'.($r['is_check'] == 'OUI' ? 'OUI' : '').'</td>';
        echo '<td>'.($r['is_exchange'] == 'OUI' ? 'OUI' : '').'</td>';
        echo '<td></td>';
        echo '<td></td>';
        echo '<td>'.($r['is_stopdesk'] == 'OUI' ? 'OUI' : '').'</td>';
        echo '<td></td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

session_start();
require_once 'config/database.php';

// Définir le fuseau horaire pour l'Algérie (GMT+1)
date_default_timezone_set('Africa/Algiers');

// --- LOGIQUE MODIFICATION (RÉCUPÉRATION DES DONNÉES COMPLÈTES) ---
$edit_data = null;
$edit_products = [];
$edit_total_delivery = 0;
$edit_remise = 0;
if(isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM orders_online WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_GET['edit_id']]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer tous les produits de cette commande (même client, même date/heure)
    if($edit_data) {
        $stmt_prods = $pdo->prepare("SELECT product_id, quantity, product_price, delivery_price, total_amount FROM orders_online 
                                     WHERE customer_name = ? AND phone1 = ? 
                                     AND created_at = ? AND is_deleted = 0");
        $stmt_prods->execute([$edit_data['customer_name'], $edit_data['phone1'], $edit_data['created_at']]);
        
        $sum_products = 0;
        $sum_delivery = 0;
        $sum_total = 0;

        while($row = $stmt_prods->fetch(PDO::FETCH_ASSOC)) {
            $edit_products[] = ['id' => $row['product_id'], 'qty' => $row['quantity']];
            $sum_products += ($row['product_price'] * $row['quantity']);
            $sum_delivery += $row['delivery_price'];
            $sum_total += $row['total_amount'];
        }

        $edit_total_delivery = $sum_delivery;
        // Remise = (Sous-total produits + Total livraison) - Total facturé
        $edit_remise = ($sum_products + $sum_delivery) - $sum_total;
    }
}

// --- ACTIONS : RETOUR / ANNULER RETOUR / SUPPRIMER ---
if (isset($_GET['mark_return'])) {
    $id = (int)$_GET['mark_return'];
    $stmt = $pdo->prepare("SELECT product_id, quantity, status FROM orders_online WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order && $order['status'] !== 'RETOUR') {
        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order['quantity'], $order['product_id']]);
        $pdo->prepare("UPDATE orders_online SET status = 'RETOUR' WHERE id = ?")->execute([$id]);
    }
    header("Location: online_sale.php?start_date=".$_GET['start_date']."&end_date=".$_GET['end_date']);
    exit;
}

if (isset($_GET['cancel_return'])) {
    $id = (int)$_GET['cancel_return'];
    $stmt = $pdo->prepare("SELECT product_id, quantity, status FROM orders_online WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order && $order['status'] === 'RETOUR') {
        $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$order['quantity'], $order['product_id']]);
        $pdo->prepare("UPDATE orders_online SET status = 'VALIDE' WHERE id = ?")->execute([$id]);
    }
    header("Location: online_sale.php?start_date=".$_GET['start_date']."&end_date=".$_GET['end_date']);
    exit;
}

if (isset($_GET['delete_sale'])) {
    $id = (int)$_GET['delete_sale'];
    $stmt = $pdo->prepare("SELECT product_id, quantity, status FROM orders_online WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order && $order['status'] !== 'RETOUR') {
        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$order['quantity'], $order['product_id']]);
    }
    $pdo->prepare("UPDATE orders_online SET is_deleted = 1 WHERE id = ?")->execute([$id]);
    header("Location: online_sale.php");
    exit;
}

// --- LOGIQUE SAUVEGARDE (INSERT OU UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_order'])) {
    $panier_items = json_decode($_POST['panier_data'], true);
    $deliv = (float)$_POST['delivery_price'];
    $remise = (float)$_POST['remise_price'];
    $update_id = $_POST['update_id'] ?? null;
    
    // Gestion de la date et heure exacte
    $back_date = !empty($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d');
    $sale_time = !empty($_POST['sale_time']) ? $_POST['sale_time'] : date('H:i:s');
    $final_datetime = $back_date . " " . $sale_time;

    // Si c'est une modification, on supprime toutes les lignes de cette commande et on recrédite le stock
    if ($update_id) {
        $old = $pdo->prepare("SELECT customer_name, phone1, created_at FROM orders_online WHERE id = ?");
        $old->execute([$update_id]);
        $old_order = $old->fetch();
        
        if($old_order) {
            // Récupérer toutes les lignes de cette commande
            $old_lines = $pdo->prepare("SELECT product_id, quantity FROM orders_online 
                                       WHERE customer_name = ? AND phone1 = ? AND created_at = ? AND is_deleted = 0");
            $old_lines->execute([$old_order['customer_name'], $old_order['phone1'], $old_order['created_at']]);
            
            // Recréditer le stock pour chaque produit
            while($line = $old_lines->fetch()) {
                $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")->execute([$line['quantity'], $line['product_id']]);
            }
            
            // Supprimer toutes les anciennes lignes
            $pdo->prepare("DELETE FROM orders_online WHERE customer_name = ? AND phone1 = ? AND created_at = ? AND is_deleted = 0")
                ->execute([$old_order['customer_name'], $old_order['phone1'], $old_order['created_at']]);
        }
    }

    // Calculer le nombre total d'articles
    $total_items = count($panier_items);
    
    // Insérer les nouvelles lignes
    foreach($panier_items as $index => $item) {
        $p_stmt = $pdo->prepare("SELECT price, description FROM products WHERE id = ?");
        $p_stmt->execute([(int)$item['product_id']]);
        $prod = $p_stmt->fetch();
        
        // On assigne tout le montant de livraison et remise au PREMIER article du panier
        // Les autres articles auront 0. Cela simplifie la gestion et évite les chiffres à virgule croqués.
        $item_delivery = ($index === 0) ? $deliv : 0;
        $item_remise   = ($index === 0) ? $remise : 0;

        $item_subtotal = ($prod['price'] * $item['quantity']);
        $item_total = $item_subtotal + $item_delivery - $item_remise;

        $product_cost = (float)$prod['description'];
        $sql = "INSERT INTO orders_online (customer_name, phone1, phone2, wilaya_code, commune, address, product_id, quantity, product_price, product_cost, delivery_price, total_amount, is_exchange, is_check, is_stopdesk, status, is_deleted, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)";
        $pdo->prepare($sql)->execute([
            $_POST['customer_name'], 
            $_POST['phone1'], 
            $_POST['phone2'], 
            $_POST['wilaya_code'], 
            $_POST['commune'], 
            $_POST['address'], 
            $item['product_id'], 
            $item['quantity'], 
            $prod['price'], 
            $product_cost, 
            $item_delivery, 
            $item_total, 
            $_POST['is_exchange']??'', 
            $_POST['is_check']??'', 
            $_POST['is_stopdesk']??'', 
            'VALIDE', 
            $_POST['note'], 
            $final_datetime
        ]);
        
        // Décrémenter le stock
        $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
    }
    
    header("Location: online_sale.php?success=1");
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$products = $pdo->query("SELECT * FROM products WHERE is_deleted = 0")->fetchAll(PDO::FETCH_ASSOC);

// --- REQUÊTE POUR LE TABLEAU (AVEC PANIER CLIENT) ---
$sql_fetch = "SELECT o.*, 
              GROUP_CONCAT(CONCAT(p.name, ' (', o.quantity, 'x)') SEPARATOR '<br>') as panier_html, 
              SUM(o.total_amount) as total_cmd, 
              SUM(o.product_price - COALESCE(o.product_cost, CAST(p.description AS DECIMAL(10,2))) * o.quantity) as gain_brut,
              SUM(o.delivery_price) as total_delivery
              FROM orders_online o LEFT JOIN products p ON o.product_id = p.id 
              WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.is_deleted = 0 
              GROUP BY o.customer_name, o.phone1, o.created_at ORDER BY o.created_at DESC";
$stmt_fetch = $pdo->prepare($sql_fetch);
$stmt_fetch->execute([$start_date, $end_date]);
$sales_raw = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

// Calculer le profit net en déduisant la remise de chaque commande
$sales = [];
foreach($sales_raw as $sale) {
    // Récupérer la remise totale pour cette commande
    $stmt_remise = $pdo->prepare("SELECT customer_name, phone1, created_at FROM orders_online WHERE id = ? LIMIT 1");
    $stmt_remise->execute([$sale['id']]);
    $order_info = $stmt_remise->fetch();
    
    // Calculer la remise: (total_cmd - total_delivery) - somme des (product_price * quantity)
    $stmt_products = $pdo->prepare("SELECT product_price, quantity FROM orders_online 
                                    WHERE customer_name = ? AND phone1 = ? AND created_at = ? AND is_deleted = 0");
    $stmt_products->execute([$sale['customer_name'], $sale['phone1'], $sale['created_at']]);
    $sum_products = 0;
    while($prod = $stmt_products->fetch()) {
        $sum_products += ($prod['product_price'] * $prod['quantity']);
    }
    $remise_totale = $sum_products - ($sale['total_cmd'] - $sale['total_delivery']);
    
    $sale['gain_total'] = $sale['gain_brut'] - $remise_totale;
    $sales[] = $sale;
}

$wilayas = ["01" => "Adrar", "02" => "Chlef", "03" => "Laghouat", "04" => "Oum El Bouaghi", "05" => "Batna", "06" => "Béjaïa", "07" => "Biskra", "08" => "Béchar", "09" => "Blida", "10" => "Bouira", "11" => "Tamanrasset", "12" => "Tébessa", "13" => "Tlemcen", "14" => "Tiaret", "15" => "Tizi Ouzou", "16" => "Alger", "17" => "Djelfa", "18" => "Jijel", "19" => "Sétif", "20" => "Saïda", "21" => "Skikda", "22" => "Sidi Bel Abbès", "23" => "Annaba", "24" => "Guelma", "25" => "Constantine", "26" => "Médéa", "27" => "Mostaganem", "28" => "M'Sila", "29" => "Mascara", "30" => "Ouargla", "31" => "Oran", "32" => "El Bayadh", "33" => "Illizi", "34" => "Bordj Bou Arreridj", "35" => "Boumerdès", "36" => "El Tarf", "37" => "Tindouf", "38" => "Tissemsilt", "39" => "El Oued", "40" => "Khenchela", "41" => "Souk Ahras", "42" => "Tipaza", "43" => "Mila", "44" => "Aïn Defla", "45" => "Naâma", "46" => "Aïn Témouchent", "47" => "Ghardaïa", "48" => "Relizane", "49" => "Timimoun", "50" => "Bordj Badji Mokhtar", "51" => "Ouled Djellal", "52" => "Béni Abbès", "53" => "In Salah", "54" => "In Guezzam", "55" => "Touggourt", "56" => "Djanet", "57" => "El M'Ghair", "58" => "El Meniaa"];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Online Sales - DINA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;900&display=swap');
        body { background: #020617; color: white; font-family: 'Lexend', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 25px; margin-bottom: 20px; }
        .form-control, .form-select { background: #ffffff !important; color: black !important; border: 1px solid #ffffff !important; }
        .row-return { opacity: 0.4; background: rgba(239, 68, 68, 0.1) !important; text-decoration: line-through; }
        .panier-client { background: rgba(0, 242, 255, 0.05); padding: 8px; border-radius: 12px; border-left: 4px solid #00f2ff; font-size: 0.8rem; }
        .btn-manage-returns { 
            background: linear-gradient(135deg, #f59e0b 0%, #dc2626 100%);
            border: none;
            color: white;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
            transition: all 0.3s ease;
        }
        .btn-manage-returns:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
            color: white;
        }
        .panier-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 242, 255, 0.2);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .panier-container {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        .badge-stopdesk {
            background: linear-gradient(135deg, #f59e0b 0%, #dc2626 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .select2-container--default .select2-selection--single {
            background: #ffffff !important;
            border: 2px solid #00f2ff !important;
            height: 45px !important;
            border-radius: 8px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #000000 !important;
            line-height: 41px !important;
            font-weight: 600 !important;
            font-size: 15px !important;
            padding-left: 15px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px !important;
        }
        .select2-dropdown {
            background: white !important;
            border: 2px solid #00f2ff !important;
            border-radius: 8px !important;
        }
        .select2-search__field {
            color: black !important;
            font-size: 14px !important;
            padding: 8px !important;
        }
        .select2-results__option {
            color: black !important;
            padding: 10px 15px !important;
            font-size: 14px !important;
        }
        .select2-results__option--highlighted {
            background-color: #00f2ff !important;
            color: #000000 !important;
            font-weight: 600 !important;
        }
    </style>
</head>
<body class="p-4">
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-900 text-info">Commandes Online</h2>
        <div>
            <a href="blacklist_manage.php" class="btn btn-manage-returns rounded-pill px-4 me-2">
                <i class="bi bi-arrow-repeat"></i> Gestion des Retours
            </a>
            <a href="corbeille.php" class="btn btn-secondary rounded-pill px-4 me-2">
                <i class="bi bi-trash"></i> Corbeille
            </a>
            <a href="dashboard.php" class="btn btn-outline-light rounded-pill px-4">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="glass-card shadow-lg">
                <form method="POST" id="orderForm">
                    <h5 class="text-info mb-3">
                        <i class="bi bi-<?= $edit_data ? 'pencil-square' : 'plus-circle' ?>"></i>
                        <?= $edit_data ? 'Modifier la commande' : 'Nouveau Colis' ?>
                    </h5>
                    <?php if($edit_data): ?><input type="hidden" name="update_id" value="<?= $edit_data['id'] ?>"><?php endif; ?>
                    <input type="hidden" name="panier_data" id="panierData">
                    
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="small opacity-50 mb-1">Date de vente</label>
                            <input type="date" name="sale_date" class="form-control" value="<?= $edit_data ? date('Y-m-d', strtotime($edit_data['created_at'])) : date('Y-m-d') ?>">
                        </div>
                        <div class="col-5">
                            <label class="small opacity-50 mb-1">Heure exacte</label>
                            <input type="time" name="sale_time" class="form-control" value="<?= $edit_data ? date('H:i', strtotime($edit_data['created_at'])) : date('H:i') ?>" step="1">
                        </div>
                    </div>
                    
                    <input type="text" name="customer_name" class="form-control mb-3" placeholder="Nom Complet" required value="<?= $edit_data['customer_name'] ?? '' ?>">
                    <div class="row g-2 mb-3">
                        <div class="col-6"><input type="text" name="phone1" class="form-control" placeholder="Tél 1" required value="<?= $edit_data['phone1'] ?? '' ?>"></div>
                        <div class="col-6"><input type="text" name="phone2" class="form-control" placeholder="Tél 2" value="<?= $edit_data['phone2'] ?? '' ?>"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <select name="wilaya_code" id="wilayaSelect" class="form-select wilaya-select" onchange="updateDelivery()">
                                <option value="">Wilaya...</option>
                                <?php foreach($wilayas as $c => $n): ?>
                                <option value="<?= $c ?>" <?= ($edit_data && $edit_data['wilaya_code']==$c)?'selected':'' ?>><?= $c ?> - <?= $n ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6"><select name="commune" id="communeSelect" class="form-select" required data-selected="<?= $edit_data['commune'] ?? '' ?>"><option value=""></option></select></div>
                    </div>
                    <input type="text" name="address" class="form-control mb-3" placeholder="Adresse complète" value="<?= $edit_data['address'] ?? '' ?>">
                    <textarea name="note" class="form-control mb-3" placeholder="Note / Remarque"><?= $edit_data['note'] ?? '' ?></textarea>
                    
                    <div class="d-flex justify-content-between p-3 bg-dark bg-opacity-50 rounded-4 mb-3 small">
                        <label><input type="checkbox" name="is_exchange" value="OUI" <?= ($edit_data && $edit_data['is_exchange']=='OUI')?'checked':'' ?>> Échange</label>
                        <label><input type="checkbox" name="is_check" value="OUI" <?= ($edit_data && $edit_data['is_check']=='OUI')?'checked':'' ?>> Vérif.</label>
                        <label><input type="checkbox" name="is_stopdesk" id="stopdeskCheck" value="OUI" <?= ($edit_data && $edit_data['is_stopdesk']=='OUI')?'checked':'' ?> onchange="updateDelivery()"> StopDesk</label>
                    </div>

                    <div class="mb-3">
                        <label class="small opacity-50 mb-1">Ajouter au panier</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <select id="prodSelect" class="form-select">
                                    <option value="">Choisir un produit...</option>
                                    <?php foreach($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-p="<?= $p['price'] ?>" data-name="<?= $p['name'] ?>">
                                            <?= $p['name'] ?> (<?= $p['stock'] ?> pcs) - <?= $p['price'] ?> DA
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-2">
                                <input type="number" id="qtyInput" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-info w-100" onclick="addToPanier()">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="panier-container" id="panierContainer">
                        <!-- Les articles du panier apparaîtront ici -->
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="small opacity-50">Livr.</label><input type="number" step="any" name="delivery_price" id="delivery" class="form-control" value="<?= $edit_data ? number_format($edit_total_delivery, 2, '.', '') : 0 ?>" oninput="calc()"></div>
                        <div class="col-6"><label class="small opacity-50">Remise</label><input type="number" step="any" name="remise_price" id="remise" class="form-control" value="<?= $edit_data ? number_format($edit_remise, 2, '.', '') : 0 ?>" oninput="calc()"></div>
                    </div>

                    <h1 class="text-center text-info fw-900 my-3"><span id="finalTotal">0</span> DA</h1>
                    <button type="submit" name="save_order" class="btn btn-info w-100 py-3 fw-900 rounded-4 text-dark">
                        <i class="bi bi-<?= $edit_data ? 'check-circle' : 'save' ?>"></i>
                        <?= $edit_data ? 'MODIFIER LA VENTE' : 'ENREGISTRER LA VENTE' ?>
                    </button>
                    <?php if($edit_data): ?>
                        <a href="online_sale.php" class="btn btn-link w-100 text-white mt-2 small">
                            <i class="bi bi-x-circle"></i> Annuler modification
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="glass-card">
                <form method="GET" class="row g-2 align-items-end mb-4">
                    <div class="col-md-4"><label class="small opacity-50">Du</label><input type="date" name="start_date" class="form-control" value="<?= $start_date ?>"></div>
                    <div class="col-md-4"><label class="small opacity-50">Au</label><input type="date" name="end_date" class="form-control" value="<?= $end_date ?>"></div>
                    <div class="col-md-4"><button type="submit" class="btn btn-info w-100 fw-bold">Filtrer l'historique</button></div>
                </form>
                
                <div class="d-flex justify-content-between mb-4">
                    <input type="text" id="phoneSearch" class="form-control w-50" placeholder="Chercher par Tél, Commune, Article..." onkeyup="searchGlobal()">
                    <a href="?export=1&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success rounded-pill px-3"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                </div>

                <div class="table-responsive" style="max-height: 500px;">
                    <table class="table align-middle" style="font-size: 0.8rem;">
                        <thead class="sticky-top bg-dark">
                            <tr class="text-white-50 small">
                                <th>Date/Heure</th>
                                <th>Client</th>
                                <th>Localisation</th>
                                <th>Panier Client</th>
                                <th>Note</th>
                                <th>Total</th>
                                <th>Gain</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $tot_v = 0; $prof_v = 0; 
                            foreach($sales as $s): 
                                $isR = ($s['status'] == 'RETOUR');
                                if(!$isR) { 
                                    $tot_v += ($s['total_cmd'] - $s['total_delivery']); 
                                    $prof_v += $s['gain_total']; 
                                } 
                            ?>
                            <tr class="sale-row <?= $isR ? 'row-return' : '' ?>" data-search="<?= strtolower($s['phone1'].' '.$s['commune'].' '.$s['panier_html']) ?>">
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($s['created_at'])) ?></strong><br>
                                    <small class="text-info"><?= date('H:i:s', strtotime($s['created_at'])) ?></small>
                                </td>
                                <td><strong><?= $s['customer_name'] ?></strong><br><small><?= $s['phone1'] ?></small></td>
                                <td>
                                    <?= $s['wilaya_code'] ?>-<?= $s['commune'] ?>
                                    <?php if($s['is_stopdesk'] == 'OUI'): ?>
                                        <br><span class="badge-stopdesk">STOPDESK</span>
                                    <?php endif; ?>
                                </td>
                                <td><div class="panier-client"><?= $s['panier_html'] ?></div></td>
                                <td class="small opacity-75"><?= $s['note'] ?></td>
                                <td class="fw-bold"><?= number_format($s['total_cmd'], 0) ?> DA</td>
                                <td class="text-info fw-bold"><?= $isR ? '0' : number_format($s['gain_total'], 0) ?> DA</td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?edit_id=<?= $s['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-sm btn-outline-info" title="Modifier"><i class="bi bi-pencil"></i></a>
                                        <?php if(!$isR): ?>
                                            <a href="?mark_return=<?= $s['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-sm btn-outline-warning" title="Retourner"><i class="bi bi-arrow-counterclockwise"></i></a>
                                        <?php else: ?>
                                            <a href="?cancel_return=<?= $s['id'] ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-sm btn-outline-success" title="Annuler Retour"><i class="bi bi-arrow-clockwise"></i></a>
                                        <?php endif; ?>
                                        <a href="?delete_sale=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Corbeille ?')"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 mt-4 text-center">
                    <div class="col-6"><div class="p-3 bg-dark rounded-4 border border-success border-opacity-25 text-success"><strong>PROFIT:</strong> <?= number_format($prof_v, 2) ?> DA</div></div>
                    <div class="col-6"><div class="p-3 bg-dark rounded-4 border border-info border-opacity-25 text-info"><strong>CA NET (SANS LIVR):</strong> <?= number_format($tot_v, 2) ?> DA</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="assets/communes.js?v=<?= time(); ?>"></script>
<script src="assets/stopdesk_fees.js?v=<?= time(); ?>"></script>
<script>
const deliveryFees = {"01": 1100, "02": 700, "03": 900, "04": 650, "05": 700, "06": 700, "07": 900, "08": 1100, "09": 500, "10": 700, "11": 1300, "12": 700, "13": 800, "14": 800, "15": 700, "16": 500, "17": 900, "18": 600, "19": 700, "20": 800, "21": 600, "22": 700, "23": 700, "24": 600, "25": 500, "26": 700, "27": 700, "28": 800, "29": 700, "30": 900, "31": 800, "32": 800, "33": 1300, "34": 700, "35": 700, "36": 700, "37": 1300, "38": 800, "39": 900, "40": 700, "41": 700, "42": 700, "43": 600, "44": 700, "45": 800, "46": 800, "47": 1000, "48": 700, "49": 1100, "50": 700, "51": 900, "52": 1100, "53": 1300, "55": 900, "57": 900, "58": 1100};

let panier = [];

// Initialiser Select2 pour la recherche de wilaya
$(document).ready(function() {
    $('.wilaya-select').select2({
        placeholder: 'Chercher une wilaya...',
        allowClear: true,
        language: {
            noResults: function() {
                return "Aucune wilaya trouvée";
            }
        }
    });
    
    // Charger le panier en mode édition
    <?php if($edit_data && !empty($edit_products)): ?>
        <?php foreach($edit_products as $ep): ?>
            panier.push({
                product_id: <?= $ep['id'] ?>,
                quantity: <?= $ep['qty'] ?>,
                name: "<?= addslashes($products[array_search($ep['id'], array_column($products, 'id'))]['name'] ?? '') ?>",
                price: <?= $products[array_search($ep['id'], array_column($products, 'id'))]['price'] ?? 0 ?>
            });
        <?php endforeach; ?>
        renderPanier();
        calc();
    <?php endif; ?>
});

function addToPanier() {
    const select = document.getElementById('prodSelect');
    const qty = parseInt(document.getElementById('qtyInput').value) || 1;
    
    if(!select.value) {
        alert('Veuillez choisir un produit');
        return;
    }
    
    const option = select.options[select.selectedIndex];
    const productId = parseInt(select.value);
    const productName = option.getAttribute('data-name');
    const productPrice = parseFloat(option.getAttribute('data-p'));
    
    // Vérifier si le produit existe déjà dans le panier
    const existingIndex = panier.findIndex(item => item.product_id === productId);
    
    if(existingIndex !== -1) {
        panier[existingIndex].quantity += qty;
    } else {
        panier.push({
            product_id: productId,
            quantity: qty,
            name: productName,
            price: productPrice
        });
    }
    
    renderPanier();
    calc();
    
    // Réinitialiser les champs
    select.value = '';
    document.getElementById('qtyInput').value = 1;
}

function removeFromPanier(index) {
    panier.splice(index, 1);
    renderPanier();
    calc();
}

function updateQuantity(index, newQty) {
    if(newQty <= 0) {
        removeFromPanier(index);
    } else {
        panier[index].quantity = parseInt(newQty);
        calc();
    }
}

function renderPanier() {
    const container = document.getElementById('panierContainer');
    
    if(panier.length === 0) {
        container.innerHTML = '<p class="text-center opacity-50 small">Panier vide</p>';
        return;
    }
    
    let html = '';
    panier.forEach((item, index) => {
        html += `
            <div class="panier-item">
                <div>
                    <strong>${item.name}</strong><br>
                    <small class="text-info">${item.price} DA × ${item.quantity} = ${(item.price * item.quantity).toLocaleString()} DA</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <input type="number" class="form-control form-control-sm" style="width: 60px;" 
                           value="${item.quantity}" min="1" 
                           onchange="updateQuantity(${index}, this.value)">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFromPanier(${index})">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function searchGlobal() {
    let input = document.getElementById('phoneSearch').value.toLowerCase();
    document.querySelectorAll('.sale-row').forEach(row => {
        row.style.display = row.getAttribute('data-search').includes(input) ? "" : "none";
    });
}

function updateDelivery(isInit = false) {
    let code = document.getElementById('wilayaSelect').value;
    const isStopdesk = document.getElementById('stopdeskCheck').checked;
    
    // Ne pas écraser le prix de livraison lors du chargement initial d'une édition
    const isEditing = <?= $edit_data ? 'true' : 'false' ?>;
    if (!(isInit && isEditing)) {
        if(isStopdesk && typeof stopdeskFees !== 'undefined' && stopdeskFees[code]) {
            document.getElementById('delivery').value = stopdeskFees[code];
        } else {
            document.getElementById('delivery').value = deliveryFees[code] || 0;
        }
    }
    
    let cSelect = document.getElementById('communeSelect');
    let currentVal = cSelect.value;
    let selectedCommune = currentVal ? currentVal : cSelect.getAttribute('data-selected');
    
    cSelect.innerHTML = '<option value=""></option>';
    let cleanCode = parseInt(code).toString(); 
    if (typeof communesParWilaya !== 'undefined' && communesParWilaya[cleanCode]) {
        communesParWilaya[cleanCode].forEach(c => {
            let opt = document.createElement('option'); opt.value = c; opt.text = c;
            if(c === selectedCommune) opt.selected = true;
            cSelect.add(opt);
        });
    }
    calc();
}

function calc() {
    let totalP = 0;
    panier.forEach(item => {
        totalP += (item.price * item.quantity);
    });
    
    let res = totalP + 
              parseFloat(document.getElementById('delivery').value || 0) - 
              parseFloat(document.getElementById('remise').value || 0);
    document.getElementById('finalTotal').innerText = res.toLocaleString();
}

// Intercepter la soumission du formulaire pour ajouter les données du panier
document.getElementById('orderForm').addEventListener('submit', function(e) {
    if(panier.length === 0) {
        e.preventDefault();
        alert('Veuillez ajouter au moins un produit au panier');
        return;
    }
    
    document.getElementById('panierData').value = JSON.stringify(panier);
});

window.onload = function() {
    updateDelivery(true);
};
</script>
</body>
</html>