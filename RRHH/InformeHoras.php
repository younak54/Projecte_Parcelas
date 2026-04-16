<?php
require '../db.php';

$idTreballador = $_GET['id_treballador'] ?? null;
if (!$idTreballador) {
    header('Location: Horaris.php');
    exit;
}

// Obtener info del empleado
$stmt = $pdo->prepare("SELECT t.*, h.nom_horari, h.hores_entrada, h.hores_sortida, h.durada_pausa
                       FROM treballadors t
                       LEFT JOIN horaris h ON t.id_horari = h.id_horari
                       WHERE t.id_treballador = ?");
$stmt->execute([$idTreballador]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die('Empleado no encontrado');
}

// Período seleccionado
$meses = $_GET['meses'] ?? 1;
$fechaFin = date('Y-m-d');
$fechaInicio = date('Y-m-d', strtotime("-$meses months"));

// Obtener todos los registros horarios con información de asignaciones
$stmt = $pdo->prepare("
    SELECT 
        rh.id_registre,
        rh.data,
        rh.hora_inici,
        rh.hora_final,
        rh.pausa_durada,
        rh.ubicacio,
        rh.incidencies_observacions,
        rh.validat,
        a.id_tasca,
        t.tipus_tasca,
        t.descripcio as tasca_descripcio,
        sc.nombre as sector_nombre
    FROM registre_hores rh
    LEFT JOIN assignacions a ON rh.id_assignacio = a.id_assignacio
    LEFT JOIN tasques t ON a.id_tasca = t.id_tasca
    LEFT JOIN sectores_cultivo sc ON t.id_sector = sc.id
    WHERE rh.id_treballador = ?
      AND rh.data BETWEEN ? AND ?
    ORDER BY rh.data DESC, rh.hora_inici DESC
");
$stmt->execute([$idTreballador, $fechaInicio, $fechaFin]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular resumen
$totalHoras = 0;
$totalPausas = 0;
$diasTrabajados = [];
$horasPorSemana = [];
$incidencias = [];

foreach ($registros as $reg) {
    if ($reg['hora_final']) {
        $inicio = strtotime($reg['hora_inici']);
        $final = strtotime($reg['hora_final']);
        $pausa = floatval($reg['pausa_durada']) * 3600;
        
        $horasDia = ($final - $inicio - $pausa) / 3600;
        $totalHoras += $horasDia;
        $totalPausas += $pausa / 3600;
        
        $semana = date('W', strtotime($reg['data']));
        $anio = date('Y', strtotime($reg['data']));
        $key = "$anio-W$semana";
        
        $horasPorSemana[$key] = ($horasPorSemana[$key] ?? 0) + $horasDia;
        $diasTrabajados[$reg['data']] = true;
        
        if (!empty($reg['incidencies_observacions'])) {
            $incidencias[] = $reg;
        }
    }
}

$totalDias = count($diasTrabajados);
$mediaDiaria = $totalDias > 0 ? $totalHoras / $totalDias : 0;

// Horario teórico semanal
$horasTeoricasSemana = 0;
if ($empleado['hores_entrada'] && $empleado['hores_sortida']) {
    $entrada = strtotime($empleado['hores_entrada']);
    $salida = strtotime($empleado['hores_sortida']);
    $pausa = floatval($empleado['durada_pausa'] ?? 0) * 3600;
    $horasDiarias = ($salida - $entrada - $pausa) / 3600;
    $horasTeoricasSemana = $horasDiarias * 5; // 5 días laborables
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Horas - <?= htmlspecialchars($empleado['nom_complet']) ?></title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .informe-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .resumen-box { 
            background: #3282b8; 
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px;
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 15px;
            margin-top: 15px;
        }
        .stat-card { 
            background: rgba(255,255,255,0.2); 
            padding: 15px; 
            border-radius: 8px; 
            text-align: center;
        }
        .tabla-registros { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabla-registros th, .tabla-registros td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        .tabla-registros th { background: #1b262c; color: white; }
        .validado { background: #d4edda; }
        .no-validado { background: #fff3cd; }
        .grafico-semanas { margin: 20px 0; }
        .barra-semana { 
            display: flex; 
            align-items: center; 
            margin: 8px 0;
        }
        .barra-label { width: 100px; font-size: 0.9em; }
        .barra-visual { 
            height: 25px; 
            background: #3282b8; 
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            color: white;
            font-size: 0.85em;
            transition: width 0.3s;
        }
        .alerta-horas { background: #dc3545 !important; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="informe-container">
    <h1>📊 Informe de Jornada Laboral</h1>
    <h2><?= htmlspecialchars($empleado['nom_complet']) ?></h2>
    
    <div class="resumen-box">
        <h3>Resumen del Período: <?= date('d/m/Y', strtotime($fechaInicio)) ?> - <?= date('d/m/Y', strtotime($fechaFin)) ?></h3>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div style="font-size: 2em;"><?= number_format($totalHoras, 1) ?>h</div>
                <div>Total horas trabajadas</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 2em;"><?= $totalDias ?></div>
                <div>Días trabajados</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 2em;"><?= number_format($mediaDiaria, 1) ?>h</div>
                <div>Media horas/día</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 2em;"><?= number_format($totalPausas, 1) ?>h</div>
                <div>Total tiempo pausas</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 2em;"><?= count($incidencias) ?></div>
                <div>Incidencias registradas</div>
            </div>
        </div>
        
        <?php if ($horasTeoricasSemana > 0): ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.3);">
            <strong>Horario teórico:</strong> <?= number_format($horasTeoricasSemana, 1) ?>h/semana | 
            <strong>Media real:</strong> <?= number_format(array_sum($horasPorSemana) / max(count($horasPorSemana), 1), 1) ?>h/semana
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Gráfico de horas por semana -->
    <?php if (!empty($horasPorSemana)): ?>
    <div class="grafico-semanas">
        <h3>📈 Horas por Semana</h3>
        <?php foreach ($horasPorSemana as $semana => $horas): 
            $porcentaje = min(($horas / ($horasTeoricasSemana ?: 40)) * 100, 100);
            $clase = $horas > ($horasTeoricasSemana * 1.1) ? 'alerta-horas' : '';
        ?>
            <div class="barra-semana">
                <div class="barra-label">Sem <?= $semana ?></div>
                <div class="barra-visual <?= $clase ?>" style="width: <?= $porcentaje ?>%; min-width: 50px;">
                    <?= number_format($horas, 1) ?>h
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Tabla detallada -->
    <h3>📝 Registros Detallados</h3>
    <table class="tabla-registros">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Pausa</th>
                <th>Horas</th>
                <th>Ubicación</th>
                <th>Tarea</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $reg): 
                $clase = $reg['validat'] ? 'validado' : 'no-validado';
                $horas = 0;
                if ($reg['hora_final']) {
                    $inicio = strtotime($reg['hora_inici']);
                    $final = strtotime($reg['hora_final']);
                    $pausa = floatval($reg['pausa_durada']) * 3600;
                    $horas = ($final - $inicio - $pausa) / 3600;
                }
            ?>
                <tr class="<?= $clase ?>">
                    <td><?= date('d/m/Y', strtotime($reg['data'])) ?></td>
                    <td><?= date('H:i', strtotime($reg['hora_inici'])) ?></td>
                    <td><?= $reg['hora_final'] ? date('H:i', strtotime($reg['hora_final'])) : '---' ?></td>
                    <td><?= $reg['pausa_durada'] ?>h</td>
                    <td><?= $reg['hora_final'] ? number_format($horas, 2) . 'h' : '---' ?></td>
                    <td><?= htmlspecialchars($reg['ubicacio'] ?? 'N/A') ?></td>
                    <td>
                        <?php if ($reg['tipus_tasca']): ?>
                            <small title="<?= htmlspecialchars($reg['tasca_descripcio'] ?? '') ?>">
                                <?= htmlspecialchars($reg['tipus_tasca']) ?>
                                <?php if ($reg['sector_nombre']): ?>
                                    <br>(<?= htmlspecialchars($reg['sector_nombre']) ?>)
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            ---
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $reg['validat'] ? '✓ Validado' : '○ Pendiente' ?>
                        <?php if ($reg['incidencies_observacions']): ?>
                            <br><span style="color: #dc3545;" title="<?= htmlspecialchars($reg['incidencies_observacions']) ?>">⚠️ Incidencia</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <a href="Horaris.php" class="btn-actualizar">← Volver al listado</a>
    </div>
</div>

</body>
</html>