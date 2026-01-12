<?php
require '../db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Obtener parcela con detalles
$stmt = $pdo->prepare("
    SELECT p.*, ts.tipo as tipo_suelo_nombre 
    FROM parcelas p 
    LEFT JOIN tipos_suelo ts ON p.tipo_suelo_id = ts.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$parcela = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parcela) {
    die("Parcela no encontrada");
}

// Sectores asociados
$sectores = $pdo->prepare("
    SELECT sc.*, v.nombre as variedad, c.nombre_comun as cultivo,
           hc.fecha_plantacion, hc.marco_plantacion
    FROM sectores_parcelas sp
    JOIN sectores_cultivo sc ON sp.sector_id = sc.id
    LEFT JOIN historial_cultivos hc ON sc.id = hc.sector_id AND hc.fecha_arrancada IS NULL
    LEFT JOIN variedades v ON hc.variedad_id = v.id
    LEFT JOIN cultivos c ON v.cultivo_id = c.id
    WHERE sp.parcela_id = ? AND sc.activo = 1
");
$sectores->execute([$id]);
$sectores_asociados = $sectores->fetchAll(PDO::FETCH_ASSOC);

// Documentos
$docs = $pdo->prepare("SELECT * FROM documentos_parcela WHERE parcela_id = ? ORDER BY fecha_subida DESC");
$docs->execute([$id]);
$documentos = $docs->fetchAll(PDO::FETCH_ASSOC);

// Incidencias
$incs = $pdo->prepare("SELECT * FROM incidencias_parcela WHERE parcela_id = ? ORDER BY fecha DESC");
$incs->execute([$id]);
$incidencias = $incs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle: <?= htmlspecialchars($parcela['nombre']) ?></title>
    <link rel="stylesheet" href="../css/parcela.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .card { border: 1px solid #ddd; padding: 10px; border-radius: 5px; margin-bottom: 10px; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 5px; }
        .alert-baja { background: #f8d7da; color: #721c24; }
        .alert-media { background: #fff3cd; color: #856404; }
        .alert-alta { background: #d4edda; color: #155724; }
        .form-buttons a { padding: 8px 12px; margin-right: 10px; text-decoration: none; border-radius: 5px; }
        .btn-cancelar { background: #ccc; color: #000; }
        .btn-actualizar { background: #4CAF50; color: #fff; }
    </style>
</head>
<body>
    <h1>📋 Detalle de Parcela</h1>
    
    <div class="detail-container">
        <h2><?= htmlspecialchars($parcela['nombre']) ?> (<?= htmlspecialchars($parcela['codigo']) ?>)</h2>
        
        <!-- Características básicas -->
        <div class="detail-section">
            <h3>📍 Características Básicas</h3>
            <p><strong>Superficie Total:</strong> <?= $parcela['superficie_total_ha'] ?> ha</p>
            <p><strong>Superficie Efectiva:</strong> <?= $parcela['superficie_efectiva_ha'] ?? 'No calculada' ?> ha</p>
            <p><strong>Tipo de Suelo:</strong> <?= htmlspecialchars($parcela['tipo_suelo_nombre'] ?? 'No asignado') ?></p>
            <p><strong>pH:</strong> <?= $parcela['ph'] ?? 'No registrado' ?></p>
            <p><strong>Materia Orgánica:</strong> <?= $parcela['materia_organica'] ? $parcela['materia_organica'] . '%' : 'No registrada' ?></p>
            <p><strong>Pendiente:</strong> <?= $parcela['pendiente'] ? $parcela['pendiente'] . '%' : 'Plana' ?></p>
            <p><strong>Orientación:</strong> <?= htmlspecialchars($parcela['orientacion'] ?? 'No registrada') ?></p>
            <p><strong>Estado:</strong> <?= $parcela['activa'] ? 'Activa' : 'Inactiva' ?></p>
            <p><strong>Fecha de alta:</strong> <?= date('d/m/Y H:i', strtotime($parcela['fecha_alta'])) ?></p>
            <?php if ($parcela['coordenadas_geojson']): ?>
                <p><strong>Coordenadas GeoJSON:</strong></p>
                <pre><?= htmlspecialchars($parcela['coordenadas_geojson']) ?></pre>
            <?php endif; ?>
            <?php if ($parcela['infraestructuras']): ?>
                <p><strong>Infraestructuras:</strong></p>
                <pre><?= htmlspecialchars($parcela['infraestructuras']) ?></pre>
            <?php endif; ?>
        </div>

        <!-- Sectores -->
        <?php if ($sectores_asociados): ?>
        <div class="detail-section">
            <h3>🌱 Sectores de Cultivo (<?= count($sectores_asociados) ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Superficie (ha)</th>
                        <th>Cultivo / Variedad</th>
                        <th>Plantación</th>
                        <th>Marco</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectores_asociados as $sector): ?>
                    <tr>
                        <td><?= htmlspecialchars($sector['codigo']) ?></td>
                        <td><?= htmlspecialchars($sector['nombre']) ?></td>
                        <td><?= $sector['superficie_efectiva_ha'] ?? 'No calculada' ?></td>
                        <td><?= $sector['variedad'] ? htmlspecialchars($sector['cultivo'] . ' - ' . $sector['variedad']) : 'Sin asignar' ?></td>
                        <td><?= $sector['fecha_plantacion'] ? date('d/m/Y', strtotime($sector['fecha_plantacion'])) : '-' ?></td>
                        <td><?= htmlspecialchars($sector['marco_plantacion'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Documentos -->
        <?php if ($documentos): ?>
        <div class="detail-section">
            <h3>📄 Documentos (<?= count($documentos) ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tipo / Descripción</th>
                        <th>Fecha Subida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentos as $doc): ?>
                    <tr>
                        <td><a href="<?= htmlspecialchars($doc['archivo_url']) ?>" target="_blank">📎 Ver</a></td>
                        <td><?= htmlspecialchars($doc['descripcion'] ?: $doc['tipo_documento']) ?></td>
                        <td><?= date('d/m/Y', strtotime($doc['fecha_subida'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Incidencias -->
        <?php if ($incidencias): ?>
        <div class="detail-section">
            <h3>⚠️ Incidencias Recientes (<?= count($incidencias) ?>)</h3>
            <?php foreach ($incidencias as $inc): ?>
                <?php
                    $clase = 'alert-media';
                    if ($inc['gravedad'] === 'alta') $clase = 'alert-alta';
                    elseif ($inc['gravedad'] === 'baja') $clase = 'alert-baja';
                ?>
                <div class="alert <?= $clase ?>">
                    <strong><?= date('d/m/Y', strtotime($inc['fecha'])) ?>:</strong> 
                    <?= htmlspecialchars($inc['tipo']) ?> - <?= htmlspecialchars($inc['descripcion']) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Botones -->
        <div class="form-buttons">
            <a href="../index.php" class="btn-cancelar">← Volver al listado</a>
            <a href="ActParcela.php?id=<?= htmlspecialchars($parcela['id']) ?>" class="btn-actualizar">✏️ Editar</a>
        </div>
    </div>
</body>
</html>
