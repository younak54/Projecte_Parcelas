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
        👥 Sistema de Gestión Personal
    </h1>
    
    <div class="dashboard-grid">
        <a href="/pro/Personal/empleados.php" class="modulo-card">
            <div class="icon">👥</div>
            <h2>Gestión de Personal</h2>
            <p>Gestiona empleados, contratos y control de jornadas. Módulo completo de recursos humanos para la explotación.</p>
        </a>

        <a href="/pro/RRHH/empleadosHorario.php" class="modulo-card">
            <div class="icon">📅</div>
            <h2>Gestión de Horarios</h2>
            <p>Gestiona empleados, contratos, certificaciones y control de jornadas. Módulo completo de recursos humanos para la explotación.</p>
        </a>
        
        <a href="/pro/Tasques/index.php" class="modulo-card" ">
            <div class="icon">📋</div>
            <h2>Tasques de Personal</h2>
            <p>Controla parcelas, sectores de cultivo, variedades, historial de cultivos y visualización en mapa interactivo georreferenciado.</p>
        </a>
        
        <div class="modulo-card" onclick="alert('Módulo en desarrollo')">
            <div class="icon">🌿</div>
            <h2>Gestión de Tratamientos</h2>
            <p>Control de herbicidas, fertilizantes y tratamientos fitosanitarios con cumplimiento normativo y alertas automáticas.</p>
        </div>
        
        <div class="modulo-card" onclick="alert('Módulo en desarrollo')">
            <div class="icon">📊</div>
            <h2>Dashboard y Análisis</h2>
            <p>Visualiza métricas clave, rendimientos, costes laborales y toma decisiones basadas en datos.</p>
        </div>
    </div>
</body>
</html>