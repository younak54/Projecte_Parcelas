<?php
require '../db.php';

$idFicha = $_GET['id'] ?? null;
if (!$idFicha) {
    header('Location: FichasTractament.php');
    exit;
}

// Obtener ficha con toda la información
$stmt = $pdo->prepare("
    SELECT 
        ft.*,
        sc.nombre as sector_nombre,
        sc.codigo as sector_codigo,
        sc.superficie_efectiva_ha,
        p.nombre as parcela_nombre,
        p.codigo as parcela_codigo,
        s.nom_complet as supervisor_nom,
        v.nombre as varietat_nom,
        c.nombre_comun as cultiu_nom
    FROM fichas_tractament ft
    LEFT JOIN sectores_cultivo sc ON ft.id_sector = sc.id
    LEFT JOIN parcelas p ON sc.parcela_id = p.id
    LEFT JOIN treballadors s ON ft.id_supervisor = s.id_treballador
    LEFT JOIN historial_cultivos hc ON sc.id = hc.sector_id AND hc.fecha_arrancada IS NULL
    LEFT JOIN variedades v ON hc.variedad_id = v.id
    LEFT JOIN cultivos c ON v.cultivo_id = c.id
    WHERE ft.id_ficha = ?
");
$stmt->execute([$idFicha]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    die('Ficha no encontrada');
}

// Obtener grupos de trabajo con sus trabajadores
$stmt = $pdo->prepare("
    SELECT 
        gt.*,
        r.nom_complet as responsable_nom,
        GROUP_CONCAT(
            CONCAT(t.nom_complet, ' (', gtt.rol_en_grup, ')')
            ORDER BY t.nom_complet
            SEPARATOR ', '
        ) as treballadors_list
    FROM grups_treball gt
    LEFT JOIN treballadors r ON gt.responsable_id = r.id_treballador
    LEFT JOIN grup_treballadors gtt ON gt.id_grup = gtt.id_grup
    LEFT JOIN treballadors t ON gtt.id_treballador = t.id_treballador
    WHERE gt.id_ficha = ?
    GROUP BY gt.id_grup
");
$stmt->execute([$idFicha]);
$grups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular progreso si está en curso
$progreso = 0;
if ($ficha['estat'] === 'EN_CURS' && $ficha['data_inici']) {
    $inicio = strtotime($ficha['data_inici']);
    $ahora = time();
    $previsto = $ficha['data_fi_prevista'] ? strtotime($ficha['data_fi_prevista']) : $inicio + (7 * 86400);
    
    if ($previsto > $inicio) {
        $progreso = min(100, max(0, (($ahora - $inicio) / ($previsto - $inicio)) * 100));
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Ficha #<?= $idFicha ?> - <?= htmlspecialchars($ficha['sector_nombre']) ?></title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        .ficha-header {
            background: #1b262c;
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .ficha-header h1 {
            margin: 0 0 10px 0;
        }
        
        .estat-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        .estat-en-curs { background: #28a745; }
        .estat-pendent { background: #ffc107; color: #333; }
        .estat-completat { background: #17a2b8; }
        .estat-pausat { background: #dc3545; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .info-card h3 {
            margin-top: 0;
            color: #3282b8;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .grup-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #3282b8;
        }
        
        .grup-card h4 {
            margin-top: 0;
            color: #1b262c;
        }
        
        .treballador-tag {
            display: inline-block;
            background: #3282b8;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            margin: 3px;
            font-size: 0.85em;
        }
        
        .treballador-tag.responsable {
            background: #28a745;
        }
        
        .progreso-bar {
            background: #e9ecef;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .progreso-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transition: width 0.3s;
        }
        
        .acciones-ficha {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .acciones-ficha a, .acciones-ficha button {
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        
        .btn-editar { background: #ffc107; color: #333; }
        .btn-completar { background: #28a745; color: white; }
        .btn-pausar { background: #dc3545; color: white; }
        .btn-reanudar { background: #17a2b8; color: white; }
        .btn-volver { background: #6c757d; color: white; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container">
    
    <div class="ficha-header">
        <h1>📝 Ficha de Tratamiento #<?= $idFicha ?></h1>
        <span class="estat-badge estat-<?= strtolower(str_replace('_', '-', $ficha['estat'])) ?>">
            <?= str_replace('_', ' ', $ficha['estat']) ?>
        </span>
        
        <?php if ($ficha['estat'] === 'EN_CURS'): ?>
            <div class="progreso-bar">
                <div class="progreso-fill" style="width: <?= $progreso ?>%">
                    <?= round($progreso) ?>%
                </div>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 15px; opacity: 0.9;">
            📍 <?= htmlspecialchars($ficha['parcela_nombre']) ?> - 
            <?= htmlspecialchars($ficha['sector_nombre']) ?>
            <?php if ($ficha['cultiu_nom']): ?>
                | 🌾 <?= htmlspecialchars($ficha['cultiu_nom']) ?>
                <?php if ($ficha['varietat_nom']): ?>
                    (<?= htmlspecialchars($ficha['varietat_nom']) ?>)
                <?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    
    <div class="info-grid">
        <div class="info-card">
            <h3>📋 Información General</h3>
            <p><strong>Tipo:</strong> <?= htmlspecialchars($ficha['tipus_tractament']) ?></p>
            <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($ficha['descripcio'])) ?></p>
            <p><strong>Superficie:</strong> <?= $ficha['superficie_ha'] ? number_format($ficha['superficie_ha'], 4) . ' ha' : 'No especificada' ?></p>
            <?php if ($ficha['producte_utilitzat']): ?>
                <p><strong>Producto:</strong> <?= htmlspecialchars($ficha['producte_utilitzat']) ?></p>
                <p><strong>Dosis:</strong> <?= $ficha['dosis_aplicada'] ?> <?= $ficha['unitat_dosis'] ?></p>
            <?php endif; ?>
        </div>
        
        <div class="info-card">
            <h3>📅 Planificación</h3>
            <p><strong>Inicio previsto:</strong> <?= $ficha['data_inici'] ? date('d/m/Y', strtotime($ficha['data_inici'])) : 'No definida' ?></p>
            <p><strong>Fin previsto:</strong> <?= $ficha['data_fi_prevista'] ? date('d/m/Y', strtotime($ficha['data_fi_prevista'])) : 'No definida' ?></p>
            <?php if ($ficha['data_fi_real']): ?>
                <p><strong>Fin real:</strong> <?= date('d/m/Y', strtotime($ficha['data_fi_real'])) ?></p>
            <?php endif; ?>
            <p><strong>Supervisor:</strong> <?= htmlspecialchars($ficha['supervisor_nom'] ?? 'Sin asignar') ?></p>
        </div>
        
        <?php if ($ficha['observacions']): ?>
        <div class="info-card" style="grid-column: 1 / -1;">
            <h3>📝 Observaciones</h3>
            <p><?= nl2br(htmlspecialchars($ficha['observacions'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <h2>👥 Grupos de Trabajo (<?= count($grups) ?>)</h2>
    
    <?php foreach ($grups as $grup): ?>
        <div class="grup-card">
            <h4>👷 <?= htmlspecialchars($grup['nom_grup']) ?></h4>
            <?php if ($grup['descripcio']): ?>
                <p style="color: #666; margin-top: 5px;"><?= htmlspecialchars($grup['descripcio']) ?></p>
            <?php endif; ?>
            
            <?php if ($grup['responsable_nom']): ?>
                <p style="margin-top: 10px;">
                    <strong>👤 Responsable:</strong> 
                    <span class="treballador-tag responsable"><?= htmlspecialchars($grup['responsable_nom']) ?></span>
                </p>
            <?php endif; ?>
            
            <div style="margin-top: 15px;">
                <strong>Equipo:</strong><br>
                <?php 
                $treballadors = explode(', ', $grup['treballadors_list']);
                foreach ($treballadors as $t): 
                    $clase = strpos($t, '(RESPONSABLE)') !== false ? 'responsable' : '';
                    $nom = str_replace([' (RESPONSABLE)', ' (OPERARI)', ' (AUXILIAR)'], '', $t);
                ?>
                    <span class="treballador-tag <?= $clase ?>"><?= htmlspecialchars($nom) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="acciones-ficha">
        <a href="FichasTractament.php" class="btn-volver">← Volver al listado</a>
        <a href="EditarFicha.php?id=<?= $idFicha ?>" class="btn-editar">✏️ Editar Ficha</a>
        
        <?php if ($ficha['estat'] === 'PENDENT'): ?>
            <form method="POST" action="CanviarEstatFicha.php" style="display: inline;">
                <input type="hidden" name="id_ficha" value="<?= $idFicha ?>">
                <input type="hidden" name="nou_estat" value="EN_CURS">
                <button type="submit" class="btn-reanudar">▶️ Iniciar Tratamiento</button>
            </form>
        <?php elseif ($ficha['estat'] === 'EN_CURS'): ?>
            <form method="POST" action="CanviarEstatFicha.php" style="display: inline;">
                <input type="hidden" name="id_ficha" value="<?= $idFicha ?>">
                <input type="hidden" name="nou_estat" value="PAUSAT">
                <button type="submit" class="btn-pausar">⏸️ Pausar</button>
            </form>
            <form method="POST" action="CanviarEstatFicha.php" style="display: inline;">
                <input type="hidden" name="id_ficha" value="<?= $idFicha ?>">
                <input type="hidden" name="nou_estat" value="COMPLETAT">
                <button type="submit" class="btn-completar">✅ Completar</button>
            </form>
        <?php elseif ($ficha['estat'] === 'PAUSAT'): ?>
            <form method="POST" action="CanviarEstatFicha.php" style="display: inline;">
                <input type="hidden" name="id_ficha" value="<?= $idFicha ?>">
                <input type="hidden" name="nou_estat" value="EN_CURS">
                <button type="submit" class="btn-reanudar">▶️ Reanudar</button>
            </form>
        <?php endif; ?>
    </div>
    
</div>

</body>
</html>