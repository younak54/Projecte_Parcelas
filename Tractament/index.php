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
    
    <h1 style="text-align: center; font-size: 2.8em; margin-bottom: 20px; color: #1b262c; text-shadow: 1px 1px 5px rgba(0,0,0,0.1);">
        🧪 Tractament, Fertilizacio
    </h1>
    
    <div class="dashboard-grid">
        <a href="/pro/Tractament/AltaFertilizant.php" class="modulo-card">
            <div class="icon">🌳</div>
            <h2>Alta Fertilizante</h2>
            <p>Registro inicial de cada fertilizante datos clave para su seguimiento y control agronómico.</p>
        </a>
        <a href="/pro/Tractament/AltaHerbicida.php" class="modulo-card">
            <div class="icon">🧪</div>
            <h2>Alta Herbicida</h2>
            <p>Registro inicial de cada Herbicida con datos clave para su seguimiento y control agronómico.</p>
        </a>
        <a href="/pro/Tractament/Tractament.php" class="modulo-card">
            <div class="icon">🌿</div>
            <h2>Registro de Tratamiento</h2>
            <p>Registro inicial de cada siembra con datos clave para su seguimiento y control agronómico.</p>
        </a>

        <a href="/pro/Tractament/Fertilizacio.php" class="modulo-card">
            <div class="icon">🚜</div>
            <h2>Registro de Fertilizante</h2>
            <p>Control de herbicidas, fertilizantes y tratamientos fitosanitarios con cumplimiento normativo y alertas automáticas.</p>
        </a>

        <a href="/pro/Tractament/Observacions.php" class="modulo-card">
            <div class="icon">O</div>
            <h2>Observacions</h2>
            <p>Control de Secotrs, Observacions indicades per els responsables/empleats</p>
        </a>

        <a href="/pro/Tractament/FichasTractament.php" class="modulo-card">
            <div class="icon">T</div>
            <h2>Fichas de Tratamiento por Parcela</h2>
            <p>Control de Secotrs, Observacions indicades per els responsables/empleats</p>
        </a>
    </div>
</body>
</html>