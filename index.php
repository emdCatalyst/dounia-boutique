<?php
session_start();
require 'config/database.php';

// Rediriger si déjà connecté
if(isset($_SESSION['admin_logged'])){
    header("Location: dashboard.php");
    exit;
}

if(isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // 1. Détection automatique de la table (admin ou admins)
        $stmtTable = $pdo->query("SHOW TABLES LIKE 'admins'");
        $tableName = ($stmtTable->rowCount() > 0) ? "admins" : "admin";

        // 2. Recherche de l'utilisateur dans la BDD
        $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // 3. Vérification du mot de passe (en texte clair comme dans ta BDD)
        if($user && $password === $user['password']) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Identifiants incorrects";
        }
    } catch (Exception $e) {
        $error = "Erreur de connexion à la base de données";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion Admin - Dina Boutique</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family: 'Lexend', sans-serif;
    height: 100vh;
    margin: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    background: radial-gradient(circle at top right, #1e1b4b, #020617);
}

.login-box {
    backdrop-filter: blur(15px);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 25px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    padding: 45px 35px;
    width: 100%;
    max-width: 400px;
    color: #fff;
}

@keyframes shake {
    0% { transform: translateX(0); }
    25% { transform: translateX(-7px); }
    50% { transform: translateX(7px); }
    75% { transform: translateX(-7px); }
    100% { transform: translateX(0); }
}

.shake { animation: shake 0.4s ease-in-out; }

.login-box h2 {
    text-align: center;
    margin-bottom: 35px;
    font-weight: 900;
    letter-spacing: 1px;
    background: linear-gradient(to right, #00f2ff, #ff00e5);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.form-control {
    border-radius: 15px;
    background: rgba(255,255,255,0.08) !important;
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff !important;
    padding: 12px 12px 12px 45px;
}

.form-control:focus {
    box-shadow: 0 0 15px rgba(0, 242, 255, 0.2);
    border-color: #00f2ff;
}

.input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #00f2ff;
    font-size: 1.2rem;
}

.btn-login {
    border-radius: 15px;
    background: linear-gradient(45deg, #00f2ff, #38bdf8);
    color: #020617;
    font-weight: 900;
    padding: 12px;
    border: none;
    margin-top: 10px;
    transition: 0.3s;
}

.btn-login:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 242, 255, 0.3);
}

.error-msg {
    background: rgba(255, 77, 77, 0.1);
    border: 1px solid #ff4d4d;
    color: #ff4d4d;
    border-radius: 10px;
    padding: 10px;
    text-align: center;
    margin-bottom: 20px;
    font-size: 0.9rem;
}
</style>
</head>
<body>



<div class="login-box <?php if(isset($error)) echo 'shake'; ?>">
    <h2>BOUTIQUE DINA</h2>
    
    <?php if(isset($error)): ?>
        <div class="error-msg"><i class="bi bi-exclamation-octagon me-2"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3 position-relative">
            <i class="bi bi-person-circle input-icon"></i>
            <input type="text" name="username" class="form-control" placeholder="Identifiant" required>
        </div>
        <div class="mb-4 position-relative">
            <i class="bi bi-shield-lock-fill input-icon"></i>
            <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
        </div>
        <button type="submit" name="login" class="btn btn-login w-100 uppercase">Accéder au Système</button>
    </form>
</div>

</body>
</html>