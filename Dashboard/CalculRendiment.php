<?php
require '../db.php';

$sectorId = $_GET['sector_id'] ?? null;
$anyCampanya = $_GET['any'] ?? date('Y');

if (!$sectorId) {
    header('Location: LlistatRendiments.php');
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
        c.nombre_comun as cultiu_nom,
        c.nombre_cientifico
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

// Función para calcular costes de la campaña
function calcularCostesCampanya($pdo, $sectorId, $any) {
    $iniciAny = "$any-01-01";
    $fiAny = "$any-12-31";
    
    // 1. Coste de plantación (de historial_cultivos)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(inversion_inicial), 0) as cost_plantacio
        FROM historial_cultivos
        WHERE sector_id = ?
        AND YEAR(fecha_plantacion) = ?
    ");
    $stmt->execute([$sectorId, $any]);
    $costPlantacio = $stmt->fetchColumn() ?: 0;
    
    // 2. Coste de mano de obra (registre_hores × salario)
    $stmt = $pdo->prepare("
        SELECT 
            rh.id_treballador,
            SUM(
                CASE 
                    WHEN rh.hora_final IS NOT NULL THEN
                        (TIMESTAMPDIFF(SECOND, rh.hora_inici, rh.hora_final) / 3600 - rh.pausa_durada)
                    ELSE 0
                END
            ) as hores_totals,
            c.salari_base,
            c.hores_setmanals
        FROM registre_hores rh
        JOIN contractes c ON rh.id_treballador = c.id_treballador
        WHERE rh.id_treballador IN (
            SELECT DISTINCT id_treballador FROM assignacions a
            JOIN tasques t ON a.id_tasca = t.id_tasca
            WHERE t.id_sector = ?
        )
        AND rh.data BETWEEN ? AND ?
        AND c.estat = 'ACTIU'
        GROUP BY rh.id_treballador
    ");
    $stmt->execute([$sectorId, $iniciAny, $fiAny]);
    $horesTreballadors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $costMaDObra = 0;
    foreach ($horesTreballadors as $ht) {
        // Calcular coste por hora: salario mensual / (horas semanales × 4.33 semanas)
        $costHora = ($ht['salari_base'] / ($ht['hores_setmanals'] * 4.33)) / 60; // por hora
        $costMaDObra += $ht['hores_totals'] * $costHora;
    }
    
    // 3. Coste de fertilizantes (aplicaciones)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            a.dosis_aplicada * f.stock_id * 
            (SELECT AVG(cantidad_inicial/cantidad_actual) FROM lotes_herbicidas WHERE stock_id = s.id)
        ), 0) as cost_fert
        FROM aplicacions a
        JOIN fertilizantes f ON a.id_fertilizant = f.id
        JOIN stock_herbicidas s ON f.stock_id = s.id
        WHERE a.id_sector = ?
        AND YEAR(a.data_aplicacio) = ?
        AND a.tipus_aplicacio = 'FERTILITZACIO'
    ");
    $stmt->execute([$sectorId, $any]);
    $costFertilizants = $stmt->fetchColumn() ?: 0;
    
    // 4. Coste de fitosanitarios
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            a.dosis_aplicada * 
            (SELECT cost_unitari FROM costes_tasques WHERE id_tasca = 
                (SELECT id_tasca FROM tasques WHERE id_sector = ? LIMIT 1)
             AND categoria = 'FITOSANITARI' LIMIT 1)
        ), 0) as cost_fito
        FROM aplicacions a
        WHERE a.id_sector = ?
        AND YEAR(a.data_aplicacio) = ?
        AND a.tipus_aplicacio = 'FITOSANITARI'
    ");
    $stmt->execute([$sectorId, $sectorId, $any]);
    $costFitosanitaris = $stmt->fetchColumn() ?: 0;
    
    // 5. Coste de maquinaria
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cost_total), 0) 
        FROM costes_tasques
        WHERE id_tasca IN (SELECT id_tasca FROM tasques WHERE id_sector = ?)
        AND categoria = 'MAQUINARIA'
        AND YEAR(data_cost) = ?
    ");
    $stmt->execute([$sectorId, $any]);
    $costMaquinaria = $stmt->fetchColumn() ?: 0;
    
    // 6. Otros costes
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cost_total), 0) 
        FROM costes_tasques
        WHERE id_tasca IN (SELECT id_tasca FROM tasques WHERE id_sector = ?)
        AND categoria = 'ALTRES'
        AND YEAR(data_cost) = ?
    ");
    $stmt->execute([$sectorId, $any]);
    $costAltres = $stmt->fetchColumn() ?: 0;
    
    // Producción (cosechas)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantitat_kg), 0) as produccio
        FROM registre_collites
        WHERE id_sector = ?
        AND YEAR(data_collita) = ?
    ");
    $stmt->execute([$sectorId, $any]);
    $produccio = $stmt->fetchColumn() ?: 0;
    
    return [
        'cost_plantacio' => round($costPlantacio, 2),
        'cost_ma_d_obra' => round($costMaDObra, 2),
        'cost_fertilizants' => round($costFertilizants, 2),
        'cost_fitosanitaris' => round($costFitosanitaris, 2),
        'cost_maquinaria' => round($costMaquinaria, 2),
        'cost_altres' => round($costAltres, 2),
        'produccio_total_kg' => round($produccio, 2),
        'hores_treballadors' => $horesTreballadors
    ];
}

// Calcular o recuperar datos
$stmt = $pdo->prepare("SELECT * FROM rendiment_parcela WHERE id_sector = ? AND any_campanya = ?");
$stmt->execute([$sectorId, $anyCampanya]);
$rendimentExistent = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guardar/actualizar cálculo
    $costes = calcularCostesCampanya($pdo, $sectorId, $anyCampanya);
    
    $produccio = $_POST['produccio_total_kg'] ?: $costes['produccio_total_kg'];
    $preu = $_POST['preu_venda_kg'] ?: 0;
    
    if ($rendimentExistent) {
        // Actualizar
        $stmt = $pdo->prepare("
            UPDATE rendiment_parcela SET
                produccio_total_kg = ?,
                preu_venda_kg = ?,
                cost_plantacio = ?,
                cost_ma_d_obra = ?,
                cost_fertilizants = ?,
                cost_fitosanitaris = ?,
                cost_maquinaria = ?,
                cost_altres = ?,
                superficie_ha = ?,
                observacions = ?,
                data_calcul = CURDATE()
            WHERE id_rendiment = ?
        ");
        $stmt->execute([
            $produccio, $preu,
            $_POST['cost_plantacio'] ?: $costes['cost_plantacio'],
            $_POST['cost_ma_d_obra'] ?: $costes['cost_ma_d_obra'],
            $_POST['cost_fertilizants'] ?: $costes['cost_fertilizants'],
            $_POST['cost_fitosanitaris'] ?: $costes['cost_fitosanitaris'],
            $_POST['cost_maquinaria'] ?: $costes['cost_maquinaria'],
            $_POST['cost_altres'] ?: $costes['cost_altres'],
            $_POST['superficie_ha'] ?: $sector['superficie_efectiva_ha'],
            $_POST['observacions'] ?? null,
            $rendimentExistent['id_rendiment']
        ]);
    } else {
        // Insertar nuevo
        $stmt = $pdo->prepare("
            INSERT INTO rendiment_parcela 
            (id_sector, any_campanya, data_calcul, produccio_total_kg, preu_venda_kg,
             cost_plantacio, cost_ma_d_obra, cost_fertilizants, cost_fitosanitaris,
             cost_maquinaria, cost_altres, superficie_ha, observacions)
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sectorId, $anyCampanya, $produccio, $preu,
            $_POST['cost_plantacio'] ?: $costes['cost_plantacio'],
            $_POST['cost_ma_d_obra'] ?: $costes['cost_ma_d_obra'],
            $_POST['cost_fertilizants'] ?: $costes['cost_fertilizants'],
            $_POST['cost_fitosanitaris'] ?: $costes['cost_fitosanitaris'],
            $_POST['cost_maquinaria'] ?: $costes['cost_maquinaria'],
            $_POST['cost_altres'] ?: $costes['cost_altres'],
            $_POST['superficie_ha'] ?: $sector['superficie_efectiva_ha'],
            $_POST['observacions'] ?? null
        ]);
    }
    
    header("Location: VeureRendiment.php?sector_id=$sectorId&any=$anyCampanya&status=guardat");
    exit;
}

// Obtener datos calculados
$costesCalculats = calcularCostesCampanya($pdo, $sectorId, $anyCampanya);

// Usar datos guardados o calculados
$datos = $rendimentExistent ?: [
    'produccio_total_kg' => $costesCalculats['produccio_total_kg'],
    'preu_venda_kg' => 0,
    'cost_plantacio' => $costesCalculats['cost_plantacio'],
    'cost_ma_d_obra' => $costesCalculats['cost_ma_d_obra'],
    'cost_fertilizants' => $costesCalculats['cost_fertilizants'],
    'cost_fitosanitaris' => $costesCalculats['cost_fitosanitaris'],
    'cost_maquinaria' => $costesCalculats['cost_maquinaria'],
    'cost_altres' => $costesCalculats['cost_altres'],
    'superficie_ha' => $sector['superficie_efectiva_ha'],
    'observacions' => ''
];

// Calcular totales para mostrar
$ingressos = $datos['produccio_total_kg'] * $datos['preu_venda_kg'];
$costTotal = array_sum([
    $datos['cost_plantacio'],
    $datos['cost_ma_d_obra'],
    $datos['cost_fertilizants'],
    $datos['cost_fitosanitaris'],
    $datos['cost_maquinaria'],
    $datos['cost_altres']
]);
$benefici = $ingressos - $costTotal;
$rendimentHa = $datos['superficie_ha'] > 0 ? $datos['produccio_total_kg'] / $datos['superficie_ha'] : 0;
$beneficiHa = $datos['superficie_ha'] > 0 ? $benefici / $datos['superficie_ha'] : 0;
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Cálculo de Rendimiento - <?= htmlspecialchars($sector['nombre']) ?></title>
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
        
        .sector-header h1 { margin: 0 0 10px 0; }
        .sector-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px; opacity: 0.95; }
        .sector-meta span { background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section h2 {
            margin-top: 0;
            color: #1b262c;
            border-bottom: 2px solid #3282b8;
            padding-bottom: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group input {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .form-group input:focus {
            border-color: #3282b8;
            outline: none;
        }
        
        .cost-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3282b8;
        }
        
        .cost-box label {
            font-size: 0.9em;
            color: #666;
        }
        
        .cost-box input {
            font-size: 1.1em;
            font-weight: bold;
            color: #1b262c;
        }
        
        .resumen-economico {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .resumen-economico h2 {
            margin-top: 0;
            border-bottom: 2px solid rgba(255,255,255,0.3);
            padding-bottom: 15px;
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .resumen-item {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .resumen-valor {
            font-size: 2em;
            font-weight: bold;
        }
        
        .resumen-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .positiu { color: #d4edda; }
        .negatiu { color: #f8d7da; }
        
        .detalle-costes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .coste-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .coste-item.plantacio { border-left-color: #6f42c1; }
        .coste-item.maobra { border-left-color: #fd7e14; }
        .coste-item.fertil { border-left-color: #20c997; }
        .coste-item.fito { border-left-color: #dc3545; }
        .coste-item.maquinaria { border-left-color: #6c757d; }
        .coste-item.altres { border-left-color: #17a2b8; }
        
        .btn-guardar {
            background: #28a745;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-guardar:hover {
            background: #218838;
        }
        
        .hores-detall {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .hores-detall h4 {
            margin-top: 0;
            color: #3282b8;
        }
        
        .treballador-hores {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #ddd;
        }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container">
    
    <div class="sector-header">
        <h1>📊 Cálculo de Rendimiento</h1>
        <h2><?= htmlspecialchars($sector['nombre']) ?></h2>
        <p><?= htmlspecialchars($sector['parcela_nombre']) ?> (<?= htmlspecialchars($sector['parcela_codigo']) ?>)</p>
        
        <div class="sector-meta">
            <?php if ($sector['cultiu_nom']): ?>
                <span>🌱 <?= htmlspecialchars($sector['cultiu_nom']) ?></span>
            <?php endif; ?>
            <span>📐 <?= number_format($sector['superficie_efectiva_ha'], 2) ?> ha</span>
            <span>📅 Campaña <?= $anyCampanya ?></span>
        </div>
    </div>
    
    <!-- RESUMEN ECONÓMICO -->
    <div class="resumen-economico">
        <h2>💰 Resumen Económico (Preview)</h2>
        
        <div class="resumen-grid">
            <div class="resumen-item">
                <div class="resumen-valor"><?= number_format($ingressos, 2) ?> €</div>
                <div class="resumen-label">INGRESOS</div>
            </div>
            <div class="resumen-item">
                <div class="resumen-valor"><?= number_format($costTotal, 2) ?> €</div>
                <div class="resumen-label">COSTES TOTALES</div>
            </div>
            <div class="resumen-item">
                <div class="resumen-valor <?= $benefici >= 0 ? 'positiu' : 'negatiu' ?>">
                    <?= number_format($benefici, 2) ?> €
                </div>
                <div class="resumen-label">BENEFICIO NETO</div>
            </div>
            <div class="resumen-item">
                <div class="resumen-valor"><?= number_format($rendimentHa, 2) ?> kg/ha</div>
                <div class="resumen-label">RENDIMIENTO</div>
            </div>
            <div class="resumen-item">
                <div class="resumen-valor <?= $beneficiHa >= 0 ? 'positiu' : 'negatiu' ?>">
                    <?= number_format($beneficiHa, 2) ?> €/ha
                </div>
                <div class="resumen-label">BENEFICIO/HA</div>
            </div>
        </div>
    </div>
    
    <form method="POST" action="">
        
        <!-- PRODUCCIÓN -->
        <div class="form-section">
            <h2>🌾 Producción e Ingresos</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Producción total (kg)</label>
                    <input type="number" name="produccio_total_kg" step="0.01" 
                           value="<?= $datos['produccio_total_kg'] ?>"
                           placeholder="Cálculo automático de cosechas">
                </div>
                <div class="form-group">
                    <label>Precio de venta (€/kg)</label>
                    <input type="number" name="preu_venda_kg" step="0.01" 
                           value="<?= $datos['preu_venda_kg'] ?>"
                           placeholder="Ej: 2.50">
                </div>
                <div class="form-group">
                    <label>Superficie efectiva (ha)</label>
                    <input type="number" name="superficie_ha" step="0.0001" 
                           value="<?= $datos['superficie_ha'] ?>">
                </div>
            </div>
        </div>
        
        <!-- COSTES DETALLADOS -->
        <div class="form-section">
            <h2>💸 Costes Detallados (€)</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Los valores se calculan automáticamente, pero puedes ajustarlos manualmente.
            </p>
            
            <div class="detalle-costes">
                <div class="coste-item plantacio">
                    <div>
                        <strong>🌱 Plantación</strong>
                        <br><small>Inversión inicial</small>
                    </div>
                    <input type="number" name="cost_plantacio" step="0.01" 
                           value="<?= $datos['cost_plantacio'] ?>" style="width: 120px;">
                </div>
                
                <div class="coste-item maobra">
                    <div>
                        <strong>👷 Mano de Obra</strong>
                        <br><small>Horas trabajadas × salario</small>
                    </div>
                    <input type="number" name="cost_ma_d_obra" step="0.01" 
                           value="<?= $datos['cost_ma_d_obra'] ?>" style="width: 120px;">
                </div>
                
                <div class="coste-item fertil">
                    <div>
                        <strong>🧪 Fertilizantes</strong>
                        <br><small>Aplicaciones de fertilización</small>
                    </div>
                    <input type="number" name="cost_fertilizants" step="0.01" 
                           value="<?= $datos['cost_fertilizants'] ?>" style="width: 120px;">
                </div>
                
                <div class="coste-item fito">
                    <div>
                        <strong>🧫 Fitosanitarios</strong>
                        <br><small>Tratamientos fitosanitarios</small>
                    </div>
                    <input type="number" name="cost_fitosanitaris" step="0.01" 
                           value="<?= $datos['cost_fitosanitaris'] ?>" style="width: 120px;">
                </div>
                
                <div class="coste-item maquinaria">
                    <div>
                        <strong>🚜 Maquinaria</strong>
                        <br><small>Uso de maquinaria</small>
                    </div>
                    <input type="number" name="cost_maquinaria" step="0.01" 
                           value="<?= $datos['cost_maquinaria'] ?>" style="width: 120px;">
                </div>
                
                <div class="coste-item altres">
                    <div>
                        <strong>📦 Otros</strong>
                        <br><small>Costes varios</small>
                    </div>
                    <input type="number" name="cost_altres" step="0.01" 
                           value="<?= $datos['cost_altres'] ?>" style="width: 120px;">
                </div>
            </div>
            
            <!-- Detalle de horas por trabajador -->
            <?php if (!empty($costesCalculats['hores_treballadors'])): ?>
            <div class="hores-detall">
                <h4>👥 Detalle de horas trabajadas (cálculo automático)</h4>
                <?php foreach ($costesCalculats['hores_treballadors'] as $ht): ?>
                    <div class="treballador-hores">
                        <span><?= htmlspecialchars($ht['id_treballador']) ?></span>
                        <span><?= number_format($ht['hores_totals'], 2) ?> h × 
                              <?= number_format($ht['salari_base'] / ($ht['hores_setmanals'] * 4.33) / 60, 2) ?> €/h</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- OBSERVACIONES -->
        <div class="form-section">
            <h2>📝 Observaciones</h2>
            <textarea name="observacions" rows="3" style="width: 100%; padding: 12px; border-radius: 8px; border: 2px solid #ddd;"><?= htmlspecialchars($datos['observacions'] ?? '') ?></textarea>
        </div>
        
        <button type="submit" class="btn-guardar">
            💾 Guardar Cálculo de Rendimiento
        </button>
        
    </form>
    
</div>

</body>
</html>