<?php
require 'db.php';
include_once 'generar_alertas.php';

// Función para verificar si las alertas necesitan actualización (cada 30 minutos)
function alertasNecesitanActualizacion() {
    $cache_file = sys_get_temp_dir() . '/alertas_cache_' . md5(__FILE__) . '.txt';
    $tiempo_actual = time();

    if (file_exists($cache_file)) {
        $ultima_actualizacion = (int)file_get_contents($cache_file);
        // Actualizar cada 30 minutos (1800 segundos)
        if (($tiempo_actual - $ultima_actualizacion) < 1800) {
            return false;
        }
    }

    // Actualizar timestamp
    file_put_contents($cache_file, $tiempo_actual);
    return true;
}

// Actualizar alertas automáticamente si es necesario
if (alertasNecesitanActualizacion()) {}

// Funció per generar l'URL correcta segons el tipus d'alerta
function obtenirUrlAlerta($alerta) {
    $tipus = $alerta['tipus_alerta'];
    $taula = $alerta['taula_referencia'];
    $id = $alerta['id_referencia'];
    $esSistema = $alerta['es_sistema'] ?? false;
    
    // Si es de sistema, añadir parámetro para diferenciar
    $sistemaParam = $esSistema ? '&sistema=1' : '';
    
    switch(true) {
        case $tipus == 'VENCIMENT_DOCUMENT' && $taula == 'documentacio':
            return "/pro/documentacio/veure.php?id={$id}";
            
        case $tipus == 'VENCIMENT_CONTRAT' && $taula == 'contractes':
            return "/pro/contractes/veure.php?id={$id}&alerta_id={$alerta['id_alerta']}";
            
        case $tipus == 'VENCIMENT_CERTIFICACIO' && $taula == 'certificacions':
            return "/pro/certificacions/veure.php?id={$id}";
            
        case $tipus == 'ESTOC_MINIM' && $taula == 'stock_herbicidas':
            return "/pro/herbicidas/stock/detalle.php?id={$id}{$sistemaParam}";
            
        case $tipus == 'VENCIMENT_PRODUCTE' && $taula == 'lotes_herbicidas':
            return "/pro/herbicidas/lotes/detalle.php?id={$id}{$sistemaParam}";
            
        case $tipus == 'PLAGA_DETECTADA' && $taula == 'monitoratge_plagues':
            return "/pro/plagues/detalle.php?id={$id}{$sistemaParam}";
            
        case $tipus == 'TRACTAMENT_PENDENT' && $taula == 'tasques':
            return "/pro/tasques/detall.php?id={$id}{$sistemaParam}";
            
        case $tipus == 'COSECHA_PREVISTA':
            return "/pro/collites/previsio.php?sector_id={$id}";
            
        case $tipus == 'MANTENIMENT_PENDENT' && $taula == 'maquinaria_agricola':
            return "/pro/maquinaria/manteniment.php?id={$id}{$sistemaParam}";
            
        case $tipus == 'VACANCES_PENDENTS' && $taula == 'vacances_permisos':
            return "/pro/RRHH/vacances/veure.php?id={$id}";
            
        case $tipus == 'ANALISI_PENDENT' && $taula == 'analisis_muestras':
            return "/pro/analisis/detalle.php?id={$id}{$sistemaParam}";
            
        case $taula == 'alertes_sistema':
            return "/pro/alertes/sistema/detalle.php?id={$id}";
            
        default:
            return "/pro/alertes/detalle.php?id={$alerta['id_alerta']}" . ($esSistema ? '&tipo=sistema' : '');
    }
}

$stmt_alertes = $pdo->query("
    (SELECT 
        a.id_alerta,
        a.tipus_alerta,
        a.missatge,
        a.data_generacio,
        a.data_venciment,
        a.urgencia,
        a.id_referencia,     
        a.taula_referencia,  
        a.id_treballador,
        COALESCE(t.nom_complet, 'Sistema') AS usuari_afectat,
        0 as es_sistema
    FROM alertes a
    LEFT JOIN treballadors t ON a.id_treballador = t.id_treballador
    WHERE a.resolta = 0)
    
    UNION ALL
    
    (SELECT 
        asys.id_alerta,
        asys.tipus_alerta,
        asys.missatge,
        asys.data_generacio as data_generacio,
        asys.data_venciment,
        asys.urgencia,
        asys.id_referencia,     
        asys.taula_referencia,  
        NULL as id_treballador,
        'Sistema' AS usuari_afectat,
        1 as es_sistema
    FROM alertes_sistema asys
    WHERE asys.resolta = 0)
    
    ORDER BY 
        FIELD(urgencia, 'CRITICA', 'ALTA', 'MITJA', 'BAIXA'),
        data_venciment ASC
");

$alertes = $stmt_alertes->fetchAll(PDO::FETCH_ASSOC);

// Consulta d'incidències - ALERTES ACTIVES DEL SISTEMA (últims 30 dies)
$stmt_incidencies = $pdo->query("
    (SELECT 
        a.id_alerta as id_incidencia,
        COALESCE(t.nom_complet, 'Sistema') as nom_complet,
        a.data_generacio as data_incidencia,
        a.tipus_alerta as tipus_incidencia,
        a.missatge as descripcio,
        a.data_venciment as data_venciment,
        'ALERTA' as tipus,
        a.urgencia as urgencia
    FROM alertes a
    LEFT JOIN treballadors t ON a.id_treballador = t.id_treballador
    WHERE a.resolta = 0)
    
    UNION ALL
    
    (SELECT 
        asys.id_alerta as id_incidencia,
        'Sistema' as nom_complet,
        asys.data_generacio as data_incidencia,
        asys.tipus_alerta as tipus_incidencia,
        asys.missatge as descripcio,
        asys.data_venciment as data_venciment,
        'SISTEMA' as tipus,
        asys.urgencia as urgencia
    FROM alertes_sistema asys
    WHERE asys.resolta = 0)
    
    ORDER BY (data_venciment < CURRENT_DATE) DESC,
        FIELD(urgencia, 'CRITICA', 'ALTA', 'MITJA', 'BAIXA'),
        data_venciment ASC
");
$incidencies = $stmt_incidencies->fetchAll(PDO::FETCH_ASSOC);

// Array d'icones actualitzat amb TOTES les alertes
function obtenirIconaAlerta($tipus) {
    $iconos = [
        // Documents i RRHH
        'VENCIMENT_DOCUMENT' => '📄',
        'VENCIMENT_CONTRAT' => '📋',
        'VENCIMENT_CERTIFICACIO' => '🏆',
        'VACANCES_PENDENTS' => '🏖️',
        
        // Stock i productes
        'ESTOC_MINIM' => '🧪',
        'VENCIMENT_PRODUCTE' => '⚗️',
        
        // Producció agrícola
        'TRACTAMENT_PENDENT' => '⚠️',
        'PLAGA_DETECTADA' => '🐛',
        'COSECHA_PREVISTA' => '🌾',
        
        // Maquinària i manteniment
        'MANTENIMENT_PENDENT' => '🔧',
        
        // Laboratori
        'ANALISI_PENDENT' => '🔬'
    ];
    
    return $iconos[$tipus] ?? '📌';
}

// Funció per obtenir color de fons segons urgència
function obtenirColorUrgencia($urgencia) {
    $colors = [
        'CRITICA' => '#ff4444',
        'ALTA' => '#ff8800', 
        'MITJA' => '#ffcc00',
        'BAIXA' => '#44bb44'
    ];
    return $colors[$urgencia] ?? '#888888';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AgriManager - Sistema Integral</title>
    <link rel="stylesheet" href="menu.css">
    <meta http-equiv="refresh" content="300">
    
</head>

<body>
    <?php include 'menu.php'; ?>
    
    <!-- SECCIÓN UNIFICADA DE INCIDENCIAS -->
    <?php if(count($incidencies) > 0): ?>
        <div class="alertas-dashboard">
            <div class="alertas-header">
                <h3>🚨 Incidencias Activas (<?php echo count($incidencies); ?>)</h3>
                <div>
                    <a href="javascript:location.reload()" class="refrescar-btn">🔄 Actualizar Página</a>
                </div>
            </div>
            <div class="alertas-grid">
                <?php foreach($incidencies as $incidencia): ?>
                    <?php
                        $fecha_vencimiento = $incidencia['data_venciment'] ?? $incidencia['data_incidencia'];
                        $es_vencida = !empty($incidencia['data_venciment']) && strtotime($incidencia['data_venciment']) < strtotime(date('Y-m-d'));
                        $urgencia = strtoupper(trim($incidencia['urgencia'] ?? 'MITJA'));
                        $urgencia_class = 'urgencia-media';

                        if ($es_vencida) {
                            $urgencia_class = 'urgencia-vencida';
                        } elseif ($urgencia === 'CRITICA') {
                            $urgencia_class = 'urgencia-critica';
                        } elseif ($urgencia === 'ALTA') {
                            $urgencia_class = 'urgencia-alta';
                        } elseif ($urgencia === 'BAIXA') {
                            $urgencia_class = 'urgencia-baixa';
                        } elseif ($urgencia === 'MITJA') {
                            $urgencia_class = 'urgencia-mitja';
                        }
                    ?>
                    <div class="alerta-tarjeta <?php echo $urgencia_class; ?>">
                        <div class="alerta-icono">
                            <?php echo obtenirIconaAlerta($incidencia['tipus_incidencia']); ?>
                        </div>
                        <div class="alerta-contenido">
                            <div class="alerta-tipo">
                                <?php 
                                $tipos_traduccion = [
                                    'VENCIMENT_DOCUMENT' => 'Documento próximo a caducar',
                                    'VENCIMENT_CONTRAT' => 'Contrato próximo a vencer',
                                    'VENCIMENT_CERTIFICACIO' => 'Certificación próxima a caducar',
                                    'VACANCES_PENDENTS' => 'Vacaciones/permiso pendiente',
                                    'ESTOC_MINIM' => 'Stock bajo',
                                    'VENCIMENT_PRODUCTE' => 'Producto próximo a caducar',
                                    'TRACTAMENT_PENDENT' => 'Tratamiento pendiente',
                                    'PLAGA_DETECTADA' => 'Plaga detectada',
                                    'COSECHA_PREVISTA' => 'Cosecha prevista',
                                    'MANTENIMENT_PENDENT' => 'Mantenimiento pendiente',
                                    'ANALISI_PENDENT' => 'Análisis pendiente'
                                ];
                                echo $tipos_traduccion[$incidencia['tipus_incidencia']] ?? $incidencia['tipus_incidencia'];
                                ?>
                                <?php if($incidencia['tipus'] === 'SISTEMA'): ?>
                                    <span class="sistema-badge">🔧 Sistema</span>
                                <?php endif; ?>
                            </div>
                            <div class="alerta-mensaje" title="<?php echo htmlspecialchars($incidencia['descripcio']); ?>">
                                <?php echo htmlspecialchars($incidencia['descripcio']); ?>
                            </div>
                            <div class="alerta-meta">
                                <span>📅 <?php echo date('d/m/Y', strtotime($fecha_vencimiento)); ?></span>
                                <span>👤 <?php echo htmlspecialchars($incidencia['nom_complet']); ?></span>
                            </div>
                        </div>
                        <div class="alerta-urgencia-badge">
                            <?php echo $es_vencida ? 'VENCIDA' : ($incidencia['tipus'] === 'SISTEMA' ? 'SISTEMA' : 'PERSONAL'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alertas-dashboard">
            <div class="no-alertas">
                <div class="no-alertas-icono">✅</div>
                <h3>No hay incidencias activas</h3>
                <p>Todas las alertas están resueltas o no hay incidencias actuales.</p>
            </div>
        </div>
    <?php endif; ?>

    <h1 style="text-align: center; font-size: 2.8em; margin-bottom: 20px; color: #1b262c; text-shadow: 1px 1px 5px rgba(0,0,0,0.1);">
        🌾 Sistema de Gestión Agrícola
    </h1>
    
    <div class="dashboard-grid">
        <a href="/pro/RRHH/menuP.php" class="modulo-card">
            <div class="icon">👥</div>
            <h2>Gestión de Personal</h2>
            <p>Gestiona empleados, contratos y control de jornadas. Módulo completo de recursos humanos para la explotación.</p>
        </a>
        
        <a href="/pro/Parcelas/menuP.php" class="modulo-card">
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

        <a href="/pro/Dashboard/index.php" class="modulo-card">
            <div class="icon">📊</div>
            <h2>Dashboard y Análisis</h2>
            <p>Visualiza métricas clave, rendimientos, costes laborales y toma decisiones basadas en datos.</p>
        </a>
    </div>
</body>
</html>