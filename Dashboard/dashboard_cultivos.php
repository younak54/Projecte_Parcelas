<?php
require '../db.php';

// 1. Obtener datos para los selectores (Filtros)
$parcelas = $pdo->query("SELECT id, nombre FROM parcelas ORDER BY nombre")->fetchAll();
$sectores = $pdo->query("SELECT id, nombre FROM sectores_cultivo ORDER BY nombre")->fetchAll();
$variedades = $pdo->query("SELECT id, nombre FROM variedades ORDER BY nombre")->fetchAll();

// 2. Lógica de Filtrado
$where = " WHERE 1=1 ";
$params = [];

if (!empty($_GET['parcela_id'])) {
    $where .= " AND s.parcela_id = :p_id ";
    $params[':p_id'] = $_GET['parcela_id'];
}
if (!empty($_GET['sector_id'])) {
    $where .= " AND h.sector_id = :s_id ";
    $params[':s_id'] = $_GET['sector_id'];
}
if (!empty($_GET['variedad_id'])) {
    $where .= " AND h.variedad_id = :v_id ";
    $params[':v_id'] = $_GET['variedad_id'];
}

// 3. Consulta Principal (Historial + Relaciones)
$sql = "SELECT h.*, s.nombre as sector_nombre, p.nombre as parcela_nombre, v.nombre as variedad_nombre 
        FROM historial_cultivos h
        JOIN sectores_cultivo s ON h.sector_id = s.id
        JOIN parcelas p ON s.parcela_id = p.id
        JOIN variedades v ON h.variedad_id = v.id
        $where 
        ORDER BY h.fecha_plantacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Datos para Gráficos (Rendimiento por Variedad)
$grafico_data = $pdo->query("SELECT v.nombre, AVG(h.rendimiento_kg_ha) as promedio 
                             FROM historial_cultivos h 
                             JOIN variedades v ON h.variedad_id = v.id 
                             GROUP BY v.nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Agrícola</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card-stats { border-left: 5px solid #2d5a27; }
        .bg-agri { background: #2d5a27; color: white; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <h2 class="mb-4">📊 Dashboard de Seguimiento de Cultivos</h2>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Filtrar por Parcela</label>
                    <select name="parcela_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas las Parcelas</option>
                        <?php foreach($parcelas as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= @$_GET['parcela_id'] == $p['id'] ? 'selected' : '' ?>><?= $p['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Filtrar por Sector</label>
                    <select name="sector_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los Sectores</option>
                        <?php foreach($sectores as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= @$_GET['sector_id'] == $s['id'] ? 'selected' : '' ?>><?= $s['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Variedad</label>
                    <select name="variedad_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas las Variedades</option>
                        <?php foreach($variedades as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= @$_GET['variedad_id'] == $v['id'] ? 'selected' : '' ?>><?= $v['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="dashboard_cultivos.php" class="btn btn-secondary w-100">Limpiar Filtros</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-agri">Plantaciones Activas / Historial</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Parcela > Sector</th>
                                <th>Variedad</th>
                                <th>Plantación</th>
                                <th>Marco</th>
                                <th>Rendimiento</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($historial as $h): ?>
                            <tr>
                                <td>
                                    <small class="text-muted d-block"><?= $h['parcela_nombre'] ?></small>
                                    <strong><?= $h['sector_nombre'] ?></strong>
                                </td>
                                <td><span class="badge bg-primary"><?= $h['variedad_nombre'] ?></span></td>
                                <td><?= date('d/m/Y', strtotime($h['fecha_plantacion'])) ?></td>
                                <td><?= $h['marco_plantacion'] ?></td>
                                <td><strong><?= number_format($h['rendimiento_kg_ha'], 0) ?></strong> kg/ha</td>
                                <td>
                                    <?php if($h['fecha_arrancada']): ?>
                                        <span class="badge bg-secondary">Finalizado</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4 card-stats">
                <div class="card-body">
                    <h6 class="text-muted">Inversión Total en Selección</h6>
                    <h3 class="text-success">
                        <?php 
                        $total_inv = array_sum(array_column($historial, 'inversion_inicial'));
                        echo number_format($total_inv, 2) . " €";
                        ?>
                    </h3>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Rendimiento Promedio por Variedad</div>
                <div class="card-body">
                    <canvas id="chartRendimiento"></canvas>
                </div>
            </div>
        </div>
    </div>

    <a href="index.php"><--Volver</a>
</div>

<script>
// Configuración del Gráfico
const ctx = document.getElementById('chartRendimiento').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($grafico_data, 'nombre')) ?>,
        datasets: [{
            label: 'Promedio kg/ha',
            data: <?= json_encode(array_column($grafico_data, 'promedio')) ?>,
            backgroundColor: 'rgba(45, 90, 39, 0.7)',
            borderColor: '#2d5a27',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
    }
});
</script>

</body>
</html>