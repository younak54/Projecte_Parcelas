<?php
require 'db.php';
include_once 'generar_alertas.php';

// Funció per generar l'URL correcta segons el tipus d'alerta
function obtenirUrlAlerta($alerta) {
    $tipus = $alerta['tipus_alerta'];
    $taula = $alerta['taula_referencia'];
    $id = $alerta['id_referencia'];
    
    switch(true) {
        // ALERTES DE DOCUMENTS
        case $tipus == 'VENCIMENT_DOCUMENT' && $taula == 'documentacio':
            return "/pro/documentacio/veure.php?id={$id}";
            
        // ALERTES DE CONTRACTES
        case $tipus == 'VENCIMENT_CONTRAT' && $taula == 'contractes':
            return "/pro/contractes/veure.php?id={$id}&alerta_id={$alerta['id_alerta']}";
            
        // ALERTES D'HERBICIDES/STOCK
        case $tipus == 'ESTOC_MINIM' && $taula == 'stock_herbicidas':
            return "/pro/herbicidas/stock/detalle.php?id={$id}";
            
        // ALERTES DE PLAGUES
        case $tipus == 'PLAGA_DETECTADA' && $taula == 'monitoratge_plagues':
            return "/pro/plagues/detalle.php?id={$id}";
            
        // ALERTES DE TASQUES
        case $tipus == 'TRACTAMENT_PENDENT' && $taula == 'tasques':
            return "/pro/tasques/detall.php?id={$id}";
            
        // ALERTES D'EMPRESES/CLIENTS
        case $tipus == 'VENCIMENT_CERTIFICACIO' && $taula == 'empreses':
            return "/pro/empreses/certificacions.php?id={$id}";
            
        // Alertes genèriques de sistema
        case $taula == 'alertes_sistema':
            return "/pro/alertes/sistema/detalle.php?id={$id}";
            
        // Fallback: pàgina de detall genèrica
        default:
            return "/pro/alertes/detalle.php?id={$alerta['id_alerta']}";
    }
}


$stmt_alertes = $pdo->query("
    SELECT 
        a.id_alerta,
        a.tipus_alerta,
        a.missatge,
        a.data_generacio,
        a.data_venciment,
        a.urgencia,
        a.id_referencia,     
        a.taula_referencia,  
        COALESCE(t.nom_complet, 'Sistema') AS usuari_afectat
    FROM alertes a
    LEFT JOIN treballadors t ON a.id_treballador = t.id_treballador
    WHERE a.resolta = 0
    ORDER BY 
        CASE a.urgencia 
            WHEN 'CRITICA' THEN 1 
            WHEN 'ALTA' THEN 2 
            WHEN 'MITJA' THEN 3 
            ELSE 4 
        END,
        a.data_venciment ASC
");
$alertes = $stmt_alertes->fetchAll(PDO::FETCH_ASSOC);

// Consulta d'incidències dels darrers 30 dies
$stmt_incidencies = $pdo->query("
    SELECT 
        i.id_incidencia,
        t.nom_complet,
        i.data_incidencia,
        i.tipus_incidencia,
        i.descripcio
    FROM incidencies i
    JOIN treballadors t ON i.id_treballador = t.id_treballador
    WHERE i.data_incidencia >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ORDER BY i.data_incidencia DESC
    LIMIT 10
");
$incidencies = $stmt_incidencies->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AgriManager - Sistema Integral</title>
    <link rel="stylesheet" href="menu.css">
    <?php include 'menu.php'; ?>
    <meta http-equiv="refresh" content="300">
</head>

<body>
    <!-- PANELL D'ALERTES A LA PANTALLA PRINCIPAL -->
    <?php if(count($alertes) > 0): ?>
        <div class="alertas-dashboard">
            <div class="alertas-header">
                <h3>🚨 Alertes Actives (<?php echo count($alertes); ?>)</h3>
                <a href="javascript:location.reload()" class="refrescar-btn">🔄 Actualitzar</a>
            </div>
            <div class="alertas-grid">
                <?php foreach($alertes as $alerta): ?>
                    <a href="<?php echo obtenirUrlAlerta($alerta); ?>" 
                        class="alerta-tarjeta urgencia-<?php echo strtolower($alerta['urgencia']); ?>">
                        <div class="alerta-icono">
                            <?php 
                            $iconos = [
                                'VENCIMENT_DOCUMENT' => '📄',
                                'VENCIMENT_CONTRAT' => '📋',
                                'ESTOC_MINIM' => '🧪',
                                'TRACTAMENT_PENDENT' => '⚠️',
                                'PLAGA_DETECTADA' => '🐛',
                                'COSECHA_PREVISTA' => '🌾'
                            ];
                            echo $iconos[$alerta['tipus_alerta']] ?? '📌';
                            ?>
                        </div>
                        <div class="alerta-contenido">
                            <div class="alerta-tipo"><?php echo htmlspecialchars($alerta['tipus_alerta']); ?></div>
                            <div class="alerta-mensaje"><?php echo htmlspecialchars($alerta['missatge']); ?></div>
                            <div class="alerta-meta">
                                <span>📅 Venc: <?php echo date('d/m/Y', strtotime($alerta['data_venciment'])); ?></span>
                                <span>👤 <?php echo htmlspecialchars($alerta['usuari_afectat']); ?></span>
                            </div>
                        </div>
                        <div class="alerta-urgencia-badge"><?php echo $alerta['urgencia']; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php include 'menu.php'; ?>
    
    <h1 style="text-align: center; font-size: 2.8em; margin-bottom: 20px; color: #1b262c; text-shadow: 1px 1px 5px rgba(0,0,0,0.1);">
        🌾 Sistema de Gestión Agrícola
    </h1>
    
    <div class="dashboard-grid">
        <a href="/pro/RRHH/menuP.php" class="modulo-card">
            <div class="icon">👥</div>
            <h2>Gestión de Personal</h2>
            <p>Gestiona empleados, contratos y control de jornadas. Módulo completo de recursos humanos para la explotación.</p>
        </a>
        
        <a href="/pro/Parcelas/index.php" class="modulo-card">
            <div class="icon">🌳</div>
            <h2>Gestión de Parcelas</h2>
            <p>Controla parcelas, sectores de cultivo, variedades, historial de cultivos y visualización en mapa interactivo georreferenciado.</p>
        </a>

        <a href="/pro/Cultivos/menuP.php" class="modulo-card">
            <div class="icon">🌿</div>
            <h2>Alta/Gestión de Cultius</h2>
            <p>Control de herbicidas, fertilizantes y tratamientos fitosanitarios con cumplimiento normativo y alertas automáticas.</p>
        </a>
        
        <a href="/pro/Tractament/index.php" class="modulo-card">
            <div class="icon">🧪</div>
            <h2>Tractament, Fertilizacio</h2>
            <p>Registro inicial de cada siembra con datos clave para su seguimiento y control agronómico</p>
        </a>

        <div class="modulo-card" onclick="alert('Módulo en desarrollo')">
            <div class="icon">📊</div>
            <h2>Dashboard y Análisis</h2>
            <p>Visualiza métricas clave, rendimientos, costes laborales y toma decisiones basadas en datos.</p>
        </div>
    </div>
</body>
</html>