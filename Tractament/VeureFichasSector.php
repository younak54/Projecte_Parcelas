<?php
require '../db.php';

$sectorId = $_GET['sector_id'] ?? null;
if (!$sectorId) {
    header('Location: FichasTractament.php');
    exit;
}

// Obtener información del sector
$stmt = $pdo->prepare("
    SELECT 
        sc.*,
        p.nombre as parcela_nombre,
        p.codigo as parcela_codigo,
        p.superficie_total_ha,
        v.nombre as varietat_nom,
        c.nombre_comun as cultiu_nom
    FROM sectores_cultivo sc
    LEFT JOIN parcelas p ON sc.parcela_id = p.id
    LEFT JOIN historial_cultivos hc ON sc.id = hc.sector_id AND hc.fecha_arrancada IS NULL
    LEFT JOIN variedades v ON hc.variedad_id = v.id
    LEFT JOIN cultivos c ON v.cultivo_id = c.id
    WHERE sc.id = ?
");
$stmt->execute([$sectorId]);
$sector = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sector) {
    die('Sector no encontrado');
}

// Obtener fichas de este sector
$filtroEstat = $_GET['estat'] ?? '';
$filtroTipus = $_GET['tipus'] ?? '';

$sql = "
    SELECT 
        ft.*,
        s.nom_complet as supervisor_nom,
        COUNT(DISTINCT gt.id_grup) as num_grups,
        COUNT(DISTINCT gtt.id_treballador) as num_treballadors
    FROM fichas_tractament ft
    LEFT JOIN treballadors s ON ft.id_supervisor = s.id_treballador
    LEFT JOIN grups_treball gt ON ft.id_ficha = gt.id_ficha
    LEFT JOIN grup_treballadors gtt ON gt.id_grup = gtt.id_grup
    WHERE ft.id_sector = ?
";
$params = [$sectorId];

if ($filtroEstat) {
    $sql .= " AND ft.estat = ?";
    $params[] = $filtroEstat;
}

if ($filtroTipus) {
    $sql .= " AND ft.tipus_tractament = ?";
    $params[] = $filtroTipus;
}

$sql .= " GROUP BY ft.id_ficha ORDER BY ft.data_inici DESC, ft.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats = [
    'total' => count($fichas),
    'pendent' => count(array_filter($fichas, fn($f) => $f['estat'] === 'PENDENT')),
    'en_curs' => count(array_filter($fichas, fn($f) => $f['estat'] === 'EN_CURS')),
    'completat' => count(array_filter($fichas, fn($f) => $f['estat'] === 'COMPLETAT')),
    'pausat' => count(array_filter($fichas, fn($f) => $f['estat'] === 'PAUSAT')),
];
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Fichas - <?= htmlspecialchars($sector['nombre']) ?></title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .sector-header {
            background: linear-gradient(135deg, #1b262c 0%, #3282b8 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .sector-header h1 {
            margin: 0 0 10px 0;
            font-size: 1.8em;
        }
        
        .sector-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
            opacity: 0.95;
        }
        
        .sector-meta span {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-top: 4px solid;
        }
        
        .stat-box.total { border-top-color: #6c757d; }
        .stat-box.pendent { border-top-color: #ffc107; }
        .stat-box.en-curs { border-top-color: #28a745; }
        .stat-box.completat { border-top-color: #17a2b8; }
        .stat-box.pausat { border-top-color: #dc3545; }
        
        .stat-numero {
            font-size: 2em;
            font-weight: bold;
            color: #1b262c;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85em;
            margin-top: 5px;
        }
        
        .filtros-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filtros-box select,
        .filtros-box input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .btn-nueva {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-left: auto;
        }
        
        .fichas-list {
            display: grid;
            gap: 15px;
        }
        
        .ficha-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        
        .ficha-card:hover {
            transform: translateX(5px);
        }
        
        .ficha-card.pendent { border-left-color: #ffc107; }
        .ficha-card.en-curs { border-left-color: #28a745; }
        .ficha-card.completat { border-left-color: #17a2b8; }
        .ficha-card.pausat { border-left-color: #dc3545; }
        
        .ficha-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        
        .ficha-titulo {
            font-size: 1.2em;
            font-weight: bold;
            color: #1b262c;
        }
        
        .ficha-tipo {
            display: inline-block;
            background: #e9ecef;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            color: #495057;
            margin-top: 5px;
        }
        
        .estat-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .estat-pendent { background: #fff3cd; color: #856404; }
        .estat-en-curs { background: #d4edda; color: #155724; }
        .estat-completat { background: #d1ecf1; color: #0c5460; }
        .estat-pausat { background: #f8d7da; color: #721c24; }
        
        .ficha-descripcio {
            color: #666;
            margin: 10px 0;
            line-height: 1.4;
        }
        
        .ficha-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 0.85em;
            color: #666;
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px dashed #ddd;
            border-bottom: 1px dashed #ddd;
        }
        
        .ficha-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .grup-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .grup-info strong {
            color: #3282b8;
        }
        
        .ficha-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .ficha-actions a {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .btn-ver {
            background: #3282b8;
            color: white;
        }
        
        .btn-editar {
            background: #ffc107;
            color: #333;
        }
        
        .no-fichas {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .no-fichas-icono {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .breadcrumb {
            margin-bottom: 20px;
            color: #666;
        }
        
        .breadcrumb a {
            color: #3282b8;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container">
    
    <div class="breadcrumb">
        <a href="FichasTractament.php">← Fichas de Tratamiento</a>
        <span> / <?= htmlspecialchars($sector['nombre']) ?></span>
    </div>
    
    <div class="sector-header">
        <h1>🌾 <?= htmlspecialchars($sector['nombre']) ?></h1>
        <p><?= htmlspecialchars($sector['parcela_nombre']) ?> (<?= htmlspecialchars($sector['parcela_codigo']) ?>)</p>
        
        <div class="sector-meta">
            <?php if ($sector['cultiu_nom']): ?>
                <span>🌱 <?= htmlspecialchars($sector['cultiu_nom']) ?></span>
            <?php endif; ?>
            <?php if ($sector['varietat_nom']): ?>
                <span>🌿 <?= htmlspecialchars($sector['varietat_nom']) ?></span>
            <?php endif; ?>
            <span>📐 <?= number_format($sector['superficie_efectiva_ha'] ?? $sector['superficie_total_ha'], 2) ?> ha</span>
        </div>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-bar">
        <div class="stat-box total">
            <div class="stat-numero"><?= $stats['total'] ?></div>
            <div class="stat-label">Total fichas</div>
        </div>
        <div class="stat-box pendent">
            <div class="stat-numero"><?= $stats['pendent'] ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
        <div class="stat-box en-curs">
            <div class="stat-numero"><?= $stats['en_curs'] ?></div>
            <div class="stat-label">En curso</div>
        </div>
        <div class="stat-box completat">
            <div class="stat-numero"><?= $stats['completat'] ?></div>
            <div class="stat-label">Completadas</div>
        </div>
        <div class="stat-box pausat">
            <div class="stat-numero"><?= $stats['pausat'] ?></div>
            <div class="stat-label">Pausadas</div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filtros-box">
        <form method="GET" action="" style="display: contents;">
            <input type="hidden" name="sector_id" value="<?= $sectorId ?>">
            
            <div>
                <label>Estado:</label>
                <select name="estat">
                    <option value="">Todos</option>
                    <option value="PENDENT" <?= $filtroEstat == 'PENDENT' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="EN_CURS" <?= $filtroEstat == 'EN_CURS' ? 'selected' : '' ?>>En curso</option>
                    <option value="COMPLETAT" <?= $filtroEstat == 'COMPLETAT' ? 'selected' : '' ?>>Completado</option>
                    <option value="PAUSAT" <?= $filtroEstat == 'PAUSAT' ? 'selected' : '' ?>>Pausado</option>
                </select>
            </div>
            
            <div>
                <label>Tipo:</label>
                <select name="tipus">
                    <option value="">Todos</option>
                    <option value="FITOSANITARI" <?= $filtroTipus == 'FITOSANITARI' ? 'selected' : '' ?>>Fitosanitario</option>
                    <option value="FERTILITZACIO" <?= $filtroTipus == 'FERTILITZACIO' ? 'selected' : '' ?>>Fertilización</option>
                    <option value="PODA" <?= $filtroTipus == 'PODA' ? 'selected' : '' ?>>Poda</option>
                    <option value="RECOLLECCIO" <?= $filtroTipus == 'RECOLLECCIO' ? 'selected' : '' ?>>Recolección</option>
                    <option value="ALTRES" <?= $filtroTipus == 'ALTRES' ? 'selected' : '' ?>>Otros</option>
                </select>
            </div>
            
            <button type="submit" class="btn-actualizar">🔍 Filtrar</button>
        </form>
        
        <a href="NovaFichaTractament.php?sector_id=<?= $sectorId ?>" class="btn-nueva">
            ➕ Nueva Ficha
        </a>
    </div>
    
    <!-- Listado de fichas -->
    <div class="fichas-list">
        <?php if (empty($fichas)): ?>
            <div class="no-fichas">
                <div class="no-fichas-icono">📋</div>
                <h3>No hay fichas en este sector</h3>
                <p>Crea una nueva ficha para empezar a registrar tratamientos</p>
                <br>
                <a href="NovaFichaTractament.php?sector_id=<?= $sectorId ?>" class="btn-nueva">
                    Crear primera ficha
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($fichas as $ficha): 
                $claseEstat = strtolower(str_replace('_', '-', $ficha['estat']));
            ?>
                <div class="ficha-card <?= $claseEstat ?>">
                    <div class="ficha-header">
                        <div>
                            <div class="ficha-titulo">
                                Ficha #<?= $ficha['id_ficha'] ?>
                            </div>
                            <span class="ficha-tipo">
                                <?= str_replace('_', ' ', $ficha['tipus_tractament']) ?>
                            </span>
                        </div>
                        <span class="estat-badge estat-<?= $claseEstat ?>">
                            <?= str_replace('_', ' ', $ficha['estat']) ?>
                        </span>
                    </div>
                    
                    <div class="ficha-descripcio">
                        <?= nl2br(htmlspecialchars(substr($ficha['descripcio'], 0, 200))) ?>
                        <?= strlen($ficha['descripcio']) > 200 ? '...' : '' ?>
                    </div>
                    
                    <div class="ficha-meta">
                        <?php if ($ficha['data_inici']): ?>
                            <span>📅 <?= date('d/m/Y', strtotime($ficha['data_inici'])) ?></span>
                        <?php endif; ?>
                        
                        <?php if ($ficha['data_fi_prevista']): ?>
                            <span>⏳ Fin: <?= date('d/m/Y', strtotime($ficha['data_fi_prevista'])) ?></span>
                        <?php endif; ?>
                        
                        <?php if ($ficha['superficie_ha']): ?>
                            <span>📐 <?= number_format($ficha['superficie_ha'], 2) ?> ha</span>
                        <?php endif; ?>
                        
                        <?php if ($ficha['producte_utilitzat']): ?>
                            <span>🧪 <?= htmlspecialchars($ficha['producte_utilitzat']) ?></span>
                        <?php endif; ?>
                        
                        <?php if ($ficha['supervisor_nom']): ?>
                            <span>👤 <?= htmlspecialchars($ficha['supervisor_nom']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grup-info">
                        <strong>👥 Equipos:</strong> 
                        <?= $ficha['num_grups'] ?> grupo<?= $ficha['num_grups'] != 1 ? 's' : '' ?> 
                        (<?= $ficha['num_treballadors'] ?> trabajador<?= $ficha['num_treballadors'] != 1 ? 'es' : '' ?>)
                    </div>
                    
                    <div class="ficha-actions">
                        <a href="VeureFicha.php?id=<?= $ficha['id_ficha'] ?>" class="btn-ver">
                            👁️ Ver detalle
                        </a>
                        <a href="EditarFicha.php?id=<?= $ficha['id_ficha'] ?>" class="btn-editar">
                            ✏️ Editar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
</div>

</body>
</html>