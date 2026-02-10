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
<?php include '../menu.php'; ?>
    
    <div class="dashboard-grid">
        <a href="/pro/Dashboard/dashboard_cultivos.php" class="modulo-card">
            <div class="icon">📊</div>
            <h2>Dashboard Cultivos</h2>
            <p>Muestra el dashboard de cultivos</p>
        </a>

        <a href="/pro/Parcelas/sectorCultivos.php" class="modulo-card">
            <div class="icon">📊</div>
            <h2>Alta Sector</h2>
            <p>Registro inicial de cada Sector de la parcela con datos clave para su seguimiento y control agronómico.</p>
        </a>

        <a href="/pro/Parcelas/filas.php" class="modulo-card">
            <div class="icon">📊</div>
            <h2>Gestión de filas</h2>
            <p>Gestiona filas, módulo completo de filas para la explotación.</p>
        </a>
        </div>
    </div>
</body>
</html>