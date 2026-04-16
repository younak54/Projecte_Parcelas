<?php
require '../db.php';

// Obtener sectores/parcelas con información
$stmt = $pdo->query("
    SELECT 
        sc.id as sector_id,
        sc.codigo as sector_codigo,
        sc.nombre as sector_nombre,
        sc.superficie_efectiva_ha,
        p.id as parcela_id,
        p.nombre as parcela_nombre,
        p.codigo as parcela_codigo,
        v.nombre as varietat_nom,
        c.nombre_comun as cultiu_nom,
        COUNT(ft.id_ficha) as total_fichas,
        SUM(CASE WHEN ft.estat = 'EN_CURS' THEN 1 ELSE 0 END) as fichas_actives,
        SUM(CASE WHEN ft.estat = 'PENDENT' THEN 1 ELSE 0 END) as fichas_pendents
    FROM sectores_cultivo sc
    LEFT JOIN parcelas p ON sc.parcela_id = p.id
    LEFT JOIN historial_cultivos hc ON sc.id = hc.sector_id AND hc.fecha_arrancada IS NULL
    LEFT JOIN variedades v ON hc.variedad_id = v.id
    LEFT JOIN cultivos c ON v.cultivo_id = c.id
    LEFT JOIN fichas_tractament ft ON sc.id = ft.id_sector
    WHERE sc.activo = 1
    GROUP BY sc.id
    ORDER BY p.nombre, sc.nombre
");
$sectores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtros de estado
$filtroEstat = $_GET['estat'] ?? '';
$filtroTipus = $_GET['tipus'] ?? '';
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Fichas de Tratamiento - AgriManager</title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .filtros-box { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 10px; 
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filtros-box select, .filtros-box input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        /* Grid de sectores */
        .sectores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .sector-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }
        
        .sector-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .sector-header {
            background: #1b262c;
            color: white;
            padding: 15px;
        }
        
        .sector-header h3 {
            margin: 0 0 5px 0;
            font-size: 1.1em;
        }
        
        .sector-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.85em;
        }
        
        .sector-info {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        
        .cultiu-badge {
            display: inline-block;
            background: #3282b8;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-bottom: 8px;
        }
        
        .superficie-badge {
            color: #666;
            font-size: 0.9em;
        }
        
        .fichas-summary {
            display: flex;
            gap: 10px;
            padding: 10px 15px;
            background: #e9ecef;
        }
        
        .badge-ficha {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
        }
        
        .badge-activas { background: #d4edda; color: #155724; }
        .badge-pendientes { background: #fff3cd; color: #856404; }
        .badge-total { background: #d1ecf1; color: #0c5460; }
        
        .sector-actions {
            padding: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn-ver-fichas {
            flex: 1;
            background: #3282b8;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
        }
        
        .btn-nueva-ficha {
            flex: 1;
            background: #28a745;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
        }
        
        /* Lista de fichas detallada */
        .fichas-lista {
            padding: 15px;
        }
        
        .ficha-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 4px solid #ddd;
        }
        
        .ficha-item.en-curs { border-left-color: #28a745; }
        .ficha-item.pendent { border-left-color: #ffc107; }
        .ficha-item.completat { border-left-color: #17a2b8; }
        
        .ficha-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .ficha-tipo {
            font-weight: bold;
            color: #1b262c;
        }
        
        .ficha-estat {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        .estat-en-curs { background: #d4edda; color: #155724; }
        .estat-pendent { background: #fff3cd; color: #856404; }
        .estat-completat { background: #d1ecf1; color: #0c5460; }
        .estat-pausat { background: #f8d7da; color: #721c24; }
        
        .ficha-meta {
            font-size: 0.85em;
            color: #666;
            margin-bottom: 8px;
        }
        
        .grup-info {
            background: white;
            padding: 8px;
            border-radius: 6px;
            margin-top: 8px;
        }
        
        .grup-nom {
            font-weight: bold;
            color: #3282b8;
            font-size: 0.9em;
        }
        
        .grup-treballadors {
            font-size: 0.8em;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container">
    <div class="header-actions">
        <h1>🌾 Fichas de Tratamiento por Parcela</h1>
        <a href="NovaFichaTractament.php" class="btn-nueva-ficha" style="display: inline-block; padding: 12px 20px;">
            ➕ Nueva Ficha Global
        </a>
    </div>
    
    <!-- Filtros -->
    <div class="filtros-box">
        <form method="GET" action="" style="display: contents;">
            <select name="estat">
                <option value="">Todos los estados</option>
                <option value="EN_CURS" <?= $filtroEstat == 'EN_CURS' ? 'selected' : '' ?>>En curso</option>
                <option value="PENDENT" <?= $filtroEstat == 'PENDENT' ? 'selected' : '' ?>>Pendiente</option>
                <option value="COMPLETAT" <?= $filtroEstat == 'COMPLETAT' ? 'selected' : '' ?>>Completado</option>
            </select>
            
            <select name="tipus">
                <option value="">Todos los tipos</option>
                <option value="FITOSANITARI" <?= $filtroTipus == 'FITOSANITARI' ? 'selected' : '' ?>>Fitosanitario</option>
                <option value="FERTILITZACIO" <?= $filtroTipus == 'FERTILITZACIO' ? 'selected' : '' ?>>Fertilización</option>
                <option value="PODA" <?= $filtroTipus == 'PODA' ? 'selected' : '' ?>>Poda</option>
                <option value="RECOLLECCIO" <?= $filtroTipus == 'RECOLLECCIO' ? 'selected' : '' ?>>Recolección</option>
            </select>
            
            <input type="text" name="buscar" placeholder="Buscar parcela..." value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
            
            <button type="submit" class="btn-actualizar">🔍 Filtrar</button>
        </form>
    </div>
    
    <!-- Grid de sectores -->
    <div class="sectores-grid">
        <?php foreach ($sectores as $sector): ?>
            <div class="sector-card">
                <div class="sector-header">
                    <h3><?= htmlspecialchars($sector['sector_nombre']) ?></h3>
                    <p><?= htmlspecialchars($sector['parcela_nombre'] ?? 'Sin parcela asignada') ?> (<?= htmlspecialchars($sector['parcela_codigo'] ?? $sector['sector_codigo']) ?>)</p>
                </div>
                
                <div class="sector-info">
                    <?php if ($sector['cultiu_nom']): ?>
                        <span class="cultiu-badge">
                            <?= htmlspecialchars($sector['cultiu_nom']) ?>
                            <?php if ($sector['varietat_nom']): ?>
                                - <?= htmlspecialchars($sector['varietat_nom']) ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                    <div class="superficie-badge">
                        📐 <?= number_format($sector['superficie_efectiva_ha'], 2) ?> ha
                    </div>
                </div>
                
                <div class="fichas-summary">
                    <?php if ($sector['fichas_actives'] > 0): ?>
                        <span class="badge-ficha badge-activas">
                            🟢 <?= $sector['fichas_actives'] ?> activa<?= $sector['fichas_actives'] > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($sector['fichas_pendents'] > 0): ?>
                        <span class="badge-ficha badge-pendientes">
                            ⏳ <?= $sector['fichas_pendents'] ?> pendiente<?= $sector['fichas_pendents'] > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                    <span class="badge-ficha badge-total">
                        📋 <?= $sector['total_fichas'] ?> total
                    </span>
                </div>
                
                <div class="sector-actions">
                    <a href="VeureFichasSector.php?sector_id=<?= $sector['sector_id'] ?>" class="btn-ver-fichas">
                        📂 Ver Fichas
                    </a>
                    <a href="NovaFichaTractament.php?sector_id=<?= $sector['sector_id'] ?>" class="btn-nueva-ficha">
                        ➕ Nueva
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>