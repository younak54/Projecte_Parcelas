<?php
require '../db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AgriManager - Sistema Integral</title>
    <link rel="stylesheet" href="../menu.css">
</head>

<body>
    
    <h1 style="text-align: center; font-size: 2.8em; margin-bottom: 20px; color: #1b262c; text-shadow: 1px 1px 5px rgba(0,0,0,0.1);">
        🌿 Alta/Gestión de Cultius
    </h1>
    
    <div class="dashboard-grid">
        <a href="/pro/Cultivos/index.php" class="modulo-card">
            <div class="icon">🌱</div>
            <h2>Alta de Cultivos</h2>
            <p>Registro inicial de cada siembra con datos clave para su seguimiento y control agronómico.</p>
        </a>

        <a href="/pro/Cultivos/GestioCulti.php" class="modulo-card">
            <div class="icon">🌿</div>
            <h2>Gestión de Cultius</h2>
            <p>Assignacio de Cultius a cada parcel·la</p>
        </a>

        <a href="/pro/Cultivos/Assignacio.php" class="modulo-card">
            <div class="icon">📊</div>
            <h2>Menu Assignacio</h2>
            <p>Menu de Assignacions.</p>
        </a>
    </div>
</body>
</html>