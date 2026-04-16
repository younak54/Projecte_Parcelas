<?php
require '../db.php';

$sectorId = $_GET['sector_id'] ?? null;
$any = $_GET['any'] ?? date('Y');

if (!$sectorId) {
    header('Location: LlistatRendiments.php');
    exit;
}

// Obtener rendimiento
$stmt = $pdo->prepare("
    SELECT rp.*, sc.nombre as sector_nombre, sc.codigo as sector_codigo,
           p.nombre as parcela_nombre, p.codigo as parcela_codigo,
           v.nombre as varietat_nom, c.nombre_comun as cultiu_nom
    FROM rendiment_parcela rp
    JOIN sectores_cultivo sc ON rp.id_sector = sc.id
    LEFT JOIN parcelas p ON sc.parcela_id = p.id
    LEFT JOIN historial_cultivos hc ON sc.id = hc.sector_id AND hc.fecha_arrancada IS NULL
    LEFT JOIN variedades v ON hc.variedad_id = v.id
    LEFT JOIN cultivos c ON v.cultivo_id = c.id
    WHERE rp.id_sector = ? AND rp.any_campanya = ?
");
$stmt->execute([$sectorId, $any]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    die('Rendimiento no encontrado. <a href="CalculRendiment.php?sector_id=' . $sectorId . '&any=' . $any . '">Calcular ahora</a>');
}

// Desglose de costes
$costes = [
    ['nombre' => 'Plantación', 'valor' => $r['cost_plantacio'], 'icono' => '🌱', 'color' => '#6f42c1'],
    ['nombre' => 'Mano de Obra', 'valor' => $r['cost_ma_d_obra'], 'icono' => '👷', 'color' => '#fd7e14'],
    ['nombre' => 'Fertilizantes', 'valor' => $r['cost_fertilizants'], 'icono' => '🧪', 'color' => '#20c997'],
    ['nombre' => 'Fitosanitarios', 'valor' => $r['cost_fitosanitaris'], 'icono' => '🧫', 'color' => '#dc3545'],
    ['nombre' => 'Maquinaria', 'valor' => $r['cost_maquinaria'], 'icono' => '🚜', 'color' => '#6c757d'],
    ['nombre' => 'Otros', 'valor' => $r['cost_altres'], 'icono' => '📦', 'color' => '#17a2b8'],
];
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Rendimiento <?= $r['sector_nombre'] ?> - <?= $any ?></title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        .rendiment-header {
            background: linear-gradient(135deg, #1b262c 0%, #3282b8 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .rendiment-header h1 { margin: 0 0 10px 0; }
        .rendiment-header p { margin: 0; opacity: 0.9; }
        
        .big-number {
            font-size: 3.5em;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .big-number.positiu { color: #d4edda; }
        .big-number.negatiu { color: #f8d7da; }
        
        .desglose-costes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .coste-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .coste-icono {
            font-size: 2.5em;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f8f9fa;
        }
        
        .coste-datos { flex: 1; }
        .coste-nombre { color: #666; font-size: 0.9em; }
        .coste-valor { font-size: 1.5em; font-weight: bold; color: #1b262c; }
        .coste-porcentaje { font-size: 0.85em; color: #888; }
        
        .resumen-final {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            text-align: center;
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 25px;
        }
        
        .resumen-item h3 {
            color: #666;
            font-size: 1em;
            margin-bottom: 10px;
        }
        
        .resumen-item .valor {
            font-size: 2em;
            font-weight: bold;
            color: #1b262c;
        }
        
        .acciones {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        
        .btn-editar { background: #ffc107; color: #333; }
        .btn-tornar { background: #6c757d; color: white; }
        .btn-llistat { background: #3282b8; color: white; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container">
    
    <div class="rendiment-header">
        <h1>📊 Rendimiento <?= $any ?></h1>
        <p><?= htmlspecialchars($r['parcela_nombre']) ?> - <?= htmlspecialchars($r['sector_nombre']) ?></p>
        <p><?= htmlspecialchars($r['cultiu_nom'] ?? '') ?> <?= htmlspecialchars($r['varietat_nom'] ?? '') ?></p>
        
        <div class="big-number <?= $r['benefici_net'] >= 0 ? 'positiu' : 'negatiu' ?>">
            <?= number_format($r['benefici_net'], 2) ?> €
        </div>
        <p>Beneficio Neto</p>
    </div>
    
    <!-- DESGLOSE DE COSTES -->
    <h2 style="margin-bottom: 20px;">💸 Desglose de Costes</h2>
    <div class="desglose-costes">
        <?php foreach ($costes as $coste): 
            $porcentaje = $r['cost_total'] > 0 ? ($coste['valor'] / $r['cost_total'] * 100) : 0;
        ?>
            <div class="coste-card">
                <div class="coste-icono" style="color: <?= $coste['color'] ?>">
                    <?= $coste['icono'] ?>
                </div>
                <div class="coste-datos">
                    <div class="coste-nombre"><?= $coste['nombre'] ?></div>
                    <div class="coste-valor"><?= number_format($coste['valor'], 2) ?> €</div>
                    <div class="coste-porcentaje"><?= number_format($porcentaje, 1) ?>% del total</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- RESUMEN FINAL -->
    <div class="resumen-final">
        <h2>📈 Resumen Económico</h2>
        
        <div class="resumen-grid">
            <div class="resumen-item">
                <h3>PRODUCCIÓN</h3>
                <div class="valor" style="color: #28a745;">
                    <?= number_format($r['produccio_total_kg'], 0) ?> kg
                </div>
                <small><?= number_format($r['rendiment_ha_kg'], 0) ?> kg/ha</small>
            </div>
            
            <div class="resumen-item">
                <h3>INGRESOS</h3>
                <div class="valor" style="color: #3282b8;">
                    <?= number_format($r['ingressos_totals'], 2) ?> €
                </div>
                <small><?= number_format($r['preu_venda_kg'], 2) ?> €/kg</small>
            </div>
            
            <div class="resumen-item">
                <h3>COSTES TOTALES</h3>
                <div class="valor" style="color: #dc3545;">
                    <?= number_format($r['cost_total'], 2) ?> €
                </div>
                <small><?= number_format($r['cost_total'] / $r['superficie_ha'], 2) ?> €/ha</small>
            </div>
        </div>
        
        <div class="resumen-grid" style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #eee;">
            <div class="resumen-item">
                <h3>BENEFICIO / HA</h3>
                <div class="valor" style="color: <?= $r['benefici_ha'] >= 0 ? '#28a745' : '#dc3545' ?>">
                    <?= number_format($r['benefici_ha'], 2) ?> €
                </div>
            </div>
            
            <div class="resumen-item">
                <h3>MARGEN</h3>
                <div class="valor" style="color: <?= $r['benefici_net'] >= 0 ? '#28a745' : '#dc3545' ?>">
                    <?= $r['ingressos_totals'] > 0 ? number_format($r['benefici_net'] / $r['ingressos_totals'] * 100, 1) : 0 ?>%
                </div>
            </div>
            
            <div class="resumen-item">
                <h3>RENTABILIDAD</h3>
                <div class="valor">
                    <?= $r['benefici_net'] >= 0 ? '✅ Positiva' : '❌ Negativa' ?>
                </div>
            </div>
        </div>
        
        <?php if ($r['observacions']): ?>
            <div style="margin-top: 30px; text-align: left; background: #f8f9fa; padding: 20px; border-radius: 10px;">
                <strong>📝 Observaciones:</strong><br>
                <?= nl2br(htmlspecialchars($r['observacions'])) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="acciones">
        <a href="CalculRendiment.php?sector_id=<?= $sectorId ?>&any=<?= $any ?>" class="btn btn-editar">
            ✏️ Editar Cálculo
        </a>
        <a href="LlistatRendiments.php?any=<?= $any ?>" class="btn btn-llistat">
            📋 Ver Listado
        </a>
        <a href="FichasTractament.php" class="btn btn-tornar">
            ← Volver a Parcelas
        </a>
    </div>
    
</div>

</body>
</html>