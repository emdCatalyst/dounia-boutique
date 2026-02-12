<?php
session_start();
require 'config/database.php';

// Vérification de sécurité
if(!isset($_SESSION['admin_logged'])){
    header("Location: index.php");
    exit;
}

$success_msg = "";
$error_msg = "";

// LOGIQUE : CHANGEMENT DE MOT DE PASSE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $new_pass = trim($_POST['new_pass']);
    $confirm_pass = trim($_POST['confirm_pass']);

    if (!empty($new_pass) && $new_pass === $confirm_pass) {
        try {
            // 1. Détection automatique du nom de la table (admin ou admins)
            $stmtTable = $pdo->query("SHOW TABLES LIKE 'admins'");
            $tableName = ($stmtTable->rowCount() > 0) ? "admins" : "admin";

            // 2. Mise à jour du mot de passe en texte clair
            $sql = "UPDATE $tableName SET password = ? WHERE 1 LIMIT 1";
            $stmt = $pdo->prepare($sql);
            
            if($stmt->execute([$new_pass])) {
                $success_msg = "Accès mis à jour ! Nouveau code : " . htmlspecialchars($new_pass);
            } else {
                $error_msg = "Erreur lors de la mise à jour.";
            }
        } catch (Exception $e) {
            $error_msg = "Erreur technique : " . $e->getMessage();
        }
    } else {
        $error_msg = "Les mots de passe ne correspondent pas ou sont vides.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SETTINGS - DINA PREMIUM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;600;900&family=Orbitron:wght@400;900&display=swap');
        
        :root {
            --neon-blue: #00f2ff;
            --neon-pink: #ff00e5;
            --glass-bg: rgba(15, 23, 42, 0.9);
        }

        body {
            font-family: 'Lexend', sans-serif;
            background: radial-gradient(circle at top right, #1e1b4b, #020617);
            color: #fff;
            min-height: 100vh;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            border-radius: 15px;
            padding: 12px 20px;
            text-align: center;
        }

        .form-control:focus {
            border-color: var(--neon-blue) !important;
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.2);
        }

        .btn-save {
            background: linear-gradient(45deg, var(--neon-blue), #38bdf8);
            color: #020617;
            font-weight: 900;
            border: none;
            border-radius: 15px;
            padding: 15px;
            transition: 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 242, 255, 0.3);
        }

        .settings-icon {
            font-size: 3rem;
            background: linear-gradient(to right, var(--neon-blue), var(--neon-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="p-4 d-flex align-items-center">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="text-center mb-4">
                <a href="dashboard.php" class="btn btn-outline-light rounded-pill px-4 mb-3">
                    <i class="bi bi-arrow-left me-2"></i> Retour au Dashboard
                </a>
            </div>

            <div class="glass-card">
                <div class="text-center">
                    <i class="bi bi-gear-wide-connected settings-icon"></i>
                    <h2 class="fw-900 mb-4 text-uppercase">Paramètres Système</h2>
                </div>

                <?php if($success_msg): ?>
                    <div class="alert alert-success bg-success bg-opacity-10 border-success text-success rounded-4 text-center">
                        <i class="bi bi-check-circle me-2"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>

                <?php if($error_msg): ?>
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-danger text-danger rounded-4 text-center">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4 text-center">
                        <label class="text-white-50 small fw-bold mb-2 uppercase d-block">Identifiant Administrateur</label>
                        <span class="badge bg-info text-dark px-3 py-2 fs-6 rounded-pill">YASSER (ADMIN)</span>
                    </div>

                    <hr class="my-4 opacity-25">

                    <h5 class="text-info mb-3 text-center"><i class="bi bi-shield-lock me-2"></i> Sécurité du Terminal</h5>
                    
                    <div class="mb-3">
                        <label class="small mb-2">Nouveau Mot de Passe (Visible en BDD)</label>
                        <input type="text" name="new_pass" class="form-control fw-bold" placeholder="Entrez le nouveau code" required>
                    </div>

                    <div class="mb-4">
                        <label class="small mb-2">Confirmer le Mot de Passe</label>
                        <input type="text" name="confirm_pass" class="form-control fw-bold" placeholder="Confirmez le code" required>
                    </div>

                    <button type="submit" name="update_password" class="btn btn-save w-100 mb-4">
                        SAUVEGARDER LES MODIFICATIONS
                    </button>
                </form>

                <div class="row g-2">
                    <div class="col-6">
                        <div class="p-3 rounded-4 bg-white bg-opacity-5 text-center small border border-secondary border-opacity-25">
                            <i class="bi bi-hdd-network d-block mb-1 text-info"></i>
                            DB Status: <span class="text-success">Online</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-4 bg-white bg-opacity-5 text-center small border border-secondary border-opacity-25">
                            <i class="bi bi-shield-check d-block mb-1 text-info"></i>
                            SSL: <span class="text-success">Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="mt-4 text-center text-white-50 small">
                DINA PREMIUM // SECURITY LAYER v4.5
            </footer>
        </div>
    </div>
</div>

</body>
</html>