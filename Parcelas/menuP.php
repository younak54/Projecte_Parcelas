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
        <a href="/pro/Parcelas/index.php" class="modulo-card">
            <div class="icon">➕</div>
            <h2>Nueva Parcela</h2>
            <p>Gestiona empleados, contratos y control de jornadas. Módulo completo de recursos humanos para la explotación.</p>
        </a>

        <a href="/pro/Parcelas/sectorCultivos.php" class="modulo-card">
            <div class="icon">🧩</div>
            <h2>Alta Sector</h2>
            <p>Registro inicial de cada Sector de la parcela con datos clave para su seguimiento y control agronómico.</p>
        </a>

        <a href="/pro/Parcelas/filas.php" class="modulo-card">
            <div class="icon">🏗️</div>
            <h2>Gestión de filas</h2>
            <p>Gestiona filas, módulo completo de filas para la explotación.</p>
        </a>
        </div>
    </div>
</body>
</html>