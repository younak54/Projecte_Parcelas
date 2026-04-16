<?php
require '../db.php';

// Filtros
$filtroEmpleado = $_GET['empleado'] ?? '';
$filtroFechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days'));
$filtroFechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtroEstado = $_GET['estado'] ?? '';

// Construir consulta
$sql = "
    SELECT 
        rh.id_registre,
        rh.data,
        rh.hora_inici,
        rh.hora_final,
        rh.pausa_durada,
        rh.ubicacio,
        rh.incidencies_observacions,
        rh.validat,
        t.id_treballador,
        t.nom_complet as empleado,
        h.nom_horari,
        h.hores_entrada,
        h.hores_sortida
    FROM registre_hores rh
    JOIN treballadors t ON rh.id_treballador = t.id_treballador
    LEFT JOIN horaris h ON t.id_horari = h.id_horari
    WHERE rh.data BETWEEN ? AND ?
";
$params = [$filtroFechaDesde, $filtroFechaHasta];

if ($filtroEmpleado) {
    $sql .= " AND rh.id_treballador = ?";
    $params[] = $filtroEmpleado;
}

if ($filtroEstado === 'abiertos') {
    $sql .= " AND rh.hora_final IS NULL";
} elseif ($filtroEstado === 'cerrados') {
    $sql .= " AND rh.hora_final IS NOT NULL";
}

$sql .= " ORDER BY rh.data DESC, rh.hora_inici DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fichajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$totalFichajes = count($fichajes);
$fichajesAbiertos = 0;
$totalHoras = 0;
$empleadosActivosHoy = [];

foreach ($fichajes as $f) {
    if ($f['hora_final'] === null) {
        $fichajesAbiertos++;
        $empleadosActivosHoy[$f['id_treballador']] = $f['empleado'];
    } else {
        $inicio = strtotime($f['hora_inici']);
        $final = strtotime($f['hora_final']);
        $pausa = floatval($f['pausa_durada']) * 3600;
        $totalHoras += ($final - $inicio - $pausa) / 3600;
    }
}

// Lista de empleados para filtro
$stmt = $pdo->query("SELECT id_treballador, nom_complet FROM treballadors WHERE estat_actiu = 1 ORDER BY nom_complet");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar validación masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validar'])) {
    $ids = $_POST['validar'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE registre_hores SET validat = 1 WHERE id_registre IN ($placeholders)");
        $stmt->execute($ids);
        header("Location: GestioFichajes.php?" . http_build_query($_GET));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Fichajes - AgriManager</title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .gestion-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .filtros-box { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .filtros-box input, .filtros-box select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 100%;
        }
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-box.activos { border-top: 4px solid #28a745; }
        .stat-box.pendientes { border-top: 4px solid #ffc107; }
        .stat-box.total { border-top: 4px solid #3282b8; }
        .stat-box.horas { border-top: 4px solid #6f42c1; }
        .stat-numero { font-size: 2em; font-weight: bold; color: #1b262c; }
        .stat-label { color: #666; font-size: 0.9em; }
        .tabla-fichajes { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .tabla-fichajes th { background: #1b262c; color: white; padding: 15px; text-align: left; }
        .tabla-fichajes td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .tabla-fichajes tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .badge-abierto { background: #d4edda; color: #155724; }
        .badge-cerrado { background: #e2e3e5; color: #383d41; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-validado { background: #d1ecf1; color: #0c5460; }
        .horas-calculadas { font-weight: bold; color: #3282b8; }
        .btn-accion { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em; }
        .btn-validar { background: #28a745; color: white; }
        .btn-editar { background: #ffc107; color: #333; }
        .btn-cerrar { background: #dc3545; color: white; }
        .empleados-activos { background: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .empleado-tag { display: inline-block; background: #28a745; color: white; padding: 5px 12px; border-radius: 15px; margin: 3px; font-size: 0.9em; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="gestion-container">
    <h1>⏱️ Gestión de Fichajes</h1>
    
    <!-- Estadísticas rápidas -->
    <div class="stats-bar">
        <div class="stat-box activos">
            <div class="stat-numero"><?= count($empleadosActivosHoy) ?></div>
            <div class="stat-label">Trabajando ahora</div>
        </div>
        <div class="stat-box pendientes">
            <div class="stat-numero"><?= $fichajesAbiertos ?></div>
            <div class="stat-label">Fichajes abiertos</div>
        </div>
        <div class="stat-box total">
            <div class="stat-numero"><?= $totalFichajes ?></div>
            <div class="stat-label">Total registros</div>
        </div>
        <div class="stat-box horas">
            <div class="stat-numero"><?= number_format($totalHoras, 1) ?>h</div>
            <div class="stat-label">Horas registradas</div>
        </div>
    </div>
    
    <!-- Empleados activos ahora -->
    <?php if (!empty($empleadosActivosHoy)): ?>
    <div class="empleados-activos">
        <strong>🟢 Actualmente trabajando:</strong><br>
        <?php foreach ($empleadosActivosHoy as $id => $nombre): ?>
            <span class="empleado-tag"><?= htmlspecialchars($nombre) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="filtros-box">
        <form method="GET" action="" style="display: contents;">
            <div>
                <label>Empleado:</label>
                <select name="empleado">
                    <option value="">Todos</option>
                    <?php foreach ($empleados as $e): ?>
                        <option value="<?= $e['id_treballador'] ?>" <?= $filtroEmpleado == $e['id_treballador'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nom_complet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Desde:</label>
                <input type="date" name="fecha_desde" value="<?= $filtroFechaDesde ?>">
            </div>
            <div>
                <label>Hasta:</label>
                <input type="date" name="fecha_hasta" value="<?= $filtroFechaHasta ?>">
            </div>
            <div>
                <label>Estado:</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="abiertos" <?= $filtroEstado === 'abiertos' ? 'selected' : '' ?>>Abiertos</option>
                    <option value="cerrados" <?= $filtroEstado === 'cerrados' ? 'selected' : '' ?>>Cerrados</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn-actualizar" style="width: 100%;">🔍 Filtrar</button>
            </div>
        </form>
    </div>
    
    <!-- Acciones masivas -->
    <form method="POST" action="" id="formValidacion">
        <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
            <button type="submit" name="accion" value="validar_seleccionados" class="btn-validar" onclick="return confirm('¿Validar registros seleccionados?')">
                ✅ Validar seleccionados
            </button>
            <a href="Fichar.php" target="_blank" class="btn-actualizar">➕ Nuevo fichaje manual</a>
            <a href="InformeHoras.php" class="btn-actualizar">📊 Informes</a>
        </div>
        
        <!-- Tabla de fichajes -->
        <table class="tabla-fichajes">
            <thead>
                <tr>
                    <th><input type="checkbox" id="seleccionarTodos" onclick="toggleTodos()"></th>
                    <th>Fecha</th>
                    <th>Empleado</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Pausa</th>
                    <th>Horas</th>
                    <th>Ubicación</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fichajes as $fichaje): 
                    $esAbierto = $fichaje['hora_final'] === null;
                    $horas = 0;
                    if (!$esAbierto) {
                        $inicio = strtotime($fichaje['hora_inici']);
                        $final = strtotime($fichaje['hora_final']);
                        $pausa = floatval($fichaje['pausa_durada']) * 3600;
                        $horas = ($final - $inicio - $pausa) / 3600;
                    }
                ?>
                    <tr style="<?= $esAbierto ? 'background: #fff3cd;' : '' ?>">
                        <td>
                            <?php if (!$fichaje['validat'] && !$esAbierto): ?>
                                <input type="checkbox" name="validar[]" value="<?= $fichaje['id_registre'] ?>" class="check-validar">
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($fichaje['data'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($fichaje['empleado']) ?></strong>
                            <br><small><?= htmlspecialchars($fichaje['nom_horari'] ?? 'Sin horario') ?></small>
                        </td>
                        <td><?= date('H:i:s', strtotime($fichaje['hora_inici'])) ?></td>
                        <td>
                            <?php if ($esAbierto): ?>
                                <span class="badge badge-abierto">ABIERTO</span>
                                <br><small><?= $fichaje['ubicacio'] ?></small>
                            <?php else: ?>
                                <?= date('H:i:s', strtotime($fichaje['hora_final'])) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $fichaje['pausa_durada'] ? $fichaje['pausa_durada'] . 'h' : '-' ?></td>
                        <td class="horas-calculadas">
                            <?= $esAbierto ? '---' : number_format($horas, 2) . 'h' ?>
                        </td>
                        <td><?= htmlspecialchars($fichaje['ubicacio'] ?? '-') ?></td>
                        <td>
                            <?php if ($fichaje['validat']): ?>
                                <span class="badge badge-validado">✓ VALIDADO</span>
                            <?php elseif ($esAbierto): ?>
                                <span class="badge badge-pendiente">⏳ PENDIENTE</span>
                            <?php else: ?>
                                <span class="badge badge-cerrado">CERRADO</span>
                            <?php endif; ?>
                            <?php if ($fichaje['incidencies_observacions']): ?>
                                <br><small style="color: #dc3545;" title="<?= htmlspecialchars($fichaje['incidencies_observacions']) ?>">⚠️ Incidencia</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($esAbierto): ?>
                                <button type="button" class="btn-cerrar btn-accion" onclick="cerrarFichaje(<?= $fichaje['id_registre'] ?>)">
                                    Cerrar
                                </button>
                            <?php elseif (!$fichaje['validat']): ?>
                                <button type="button" class="btn-validar btn-accion" onclick="validarIndividual(<?= $fichaje['id_registre'] ?>)">
                                    Validar
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn-editar btn-accion" onclick="editarFichaje(<?= $fichaje['id_registre'] ?>)">
                                Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
    function toggleTodos() {
        const checks = document.querySelectorAll('.check-validar');
        const todos = document.getElementById('seleccionarTodos').checked;
        checks.forEach(c => c.checked = todos);
    }
    
    function cerrarFichaje(id) {
        if (confirm('¿Deseas cerrar este fichaje manualmente?')) {
            // Redirigir a página de cierre manual o hacer petición AJAX
            window.location.href = `CerrarFichaje.php?id=${id}`;
        }
    }
    
    function validarIndividual(id) {
        if (confirm('¿Validar este fichaje?')) {
            // Crear formulario temporal para validar
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="validar[]" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function editarFichaje(id) {
        window.location.href = `EditarFichaje.php?id=${id}`;
    }
</script>

</body>
</html>