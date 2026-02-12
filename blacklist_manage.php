<?php
session_start();
require 'config/database.php';

// 1. AJOUTER UN CLIENT A LA LISTE NOIRE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_blacklist'])) {
    $name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $reason = $_POST['reason'];

    $stmt = $pdo->prepare("INSERT INTO blacklisted_clients (full_name, phone, reason) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$name, $phone, $reason]);
        header("Location: blacklist_manage.php?msg=added");
    } catch (Exception $e) {
        header("Location: blacklist_manage.php?msg=exists");
    }
    exit;
}

// 2. SUPPRIMER DE LA LISTE NOIRE (Pardonner le client)
if (isset($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    $pdo->prepare("DELETE FROM blacklisted_clients WHERE id = ?")->execute([$id]);
    header("Location: blacklist_manage.php?msg=removed");
    exit;
}

// 3. RECUPERER LA LISTE
$blacklist = $pdo->query("SELECT * FROM blacklisted_clients ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion de la Liste Noire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #ef4444; --dark: #0f172a; }
        body { background-color: var(--dark); color: white; font-family: 'Segoe UI', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 25px; }
        .form-control { background: rgba(0, 0, 0, 0.5) !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; color: white !important; }
        .table { color: white !important; }
        .btn-add { background: var(--primary); border: none; font-weight: bold; border-radius: 10px; color: white; }
        .btn-add:hover { background: #dc2626; color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="fw-bold mb-0 text-danger"><i class="bi bi-shield-slash"></i> Liste Noire Clients</h1>
            <p class="text-muted">Bloquez les numéros de téléphone pour éviter les faux retours.</p>
        </div>
        <a href="online_sale.php" class="btn btn-outline-light rounded-pill px-4">Retour aux ventes</a>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] == 'added'): ?>
            <div class="alert alert-success bg-success text-white border-0">Client ajouté à la liste noire.</div>
        <?php elseif($_GET['msg'] == 'exists'): ?>
            <div class="alert alert-warning">Ce numéro de téléphone est déjà bloqué.</div>
        <?php elseif($_GET['msg'] == 'removed'): ?>
            <div class="alert alert-info">Le client a été retiré de la liste noire.</div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass-card shadow">
                <h5 class="mb-4">Bloquer un Client</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="small text-muted mb-2">Nom & Prénom</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Ex: Jean Dupont" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted mb-2">Numéro de Téléphone</label>
                        <input type="text" name="phone" class="form-control" placeholder="0XXXXXXXXX" required>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted mb-2">Motif du retour</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Ex: N'a pas répondu au livreur"></textarea>
                    </div>
                    <button type="submit" name="add_to_blacklist" class="btn btn-add w-100 py-2 mt-2">BLOQUER LE NUMÉRO</button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass-card shadow h-100">
                <h5 class="mb-4">Numéros Restreints</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="text-danger opacity-75">
                            <tr>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th>Motif</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($blacklist as $client): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($client['full_name']) ?></td>
                                <td class="text-info"><?= htmlspecialchars($client['phone']) ?></td>
                                <td class="small opacity-75"><?= htmlspecialchars($client['reason']) ?></td>
                                <td class="text-center">
                                    <a href="blacklist_manage.php?remove=<?= $client['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Pardonner ce client et le retirer de la liste noire ?')">
                                        <i class="bi bi-person-check"></i> Débloquer
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($blacklist)): ?>
                                <tr><td colspan="4" class="text-center opacity-25 py-4">Aucun client bloqué pour le moment.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>