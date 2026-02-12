<?php
session_start();
require 'config/database.php';
// Vérification admin ici...
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Type de Vente - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        @keyframes gradientBG { 0% {background-position:0% 50%;} 50% {background-position:100% 50%;} 100% {background-position:0% 50%;} }

        .choice-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 40px;
            text-align: center;
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            color: white;
            display: block;
            height: 100%;
        }

        .choice-card:hover {
            transform: translateY(-15px);
            background: rgba(255, 255, 255, 0.2);
            border-color: #43cea2;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }

        .icon-circle {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            transition: 0.3s;
        }

        .choice-card:hover .icon-circle {
            background: #43cea2;
            color: #0f2027;
        }

        h2 { font-weight: 700; letter-spacing: 1px; }
        p { opacity: 0.7; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold">Nouvelle Vente</h1>
        <p class="lead">Veuillez sélectionner le canal de vente pour continuer</p>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-md-5 col-lg-4">
            <a href="pos_sale.php" class="choice-card">
                <div class="icon-circle">
                    <i class="bi bi-shop"></i>
                </div>
                <h2>Vente Boutique</h2>
                <hr class="mx-5 opacity-25">
                <p>Vente directe au comptoir, encaissement immédiat et mise à jour du stock physique.</p>
                <div class="btn btn-outline-light rounded-pill px-4 mt-3">Sélectionner</div>
            </a>
        </div>

        <div class="col-md-5 col-lg-4">
            <a href="online_sale.php" class="choice-card">
                <div class="icon-circle">
                    <i class="bi bi-globe"></i>
                </div>
                <h2>Vente On-line</h2>
                <hr class="mx-5 opacity-25">
                <p>Commandes via site web ou réseaux sociaux, gestion des frais de livraison et suivi client.</p>
                <div class="btn btn-outline-light rounded-pill px-4 mt-3">Sélectionner</div>
            </a>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="dashboard.php" class="text-white-50 text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>