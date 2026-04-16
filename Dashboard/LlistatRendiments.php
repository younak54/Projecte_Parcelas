<?php
require '../db.php';

$any = $_GET['any'] ?? date('Y');

// Obtener todos los rendimientos calculados
$stmt = $pdo->prepare("
    SELECT 
        rp.*,
        sc.nombre as sector_nombre,
        sc.codigo as sector_codigo,
        p.nombre as parcela_nombre,
        p.codigo as parcela_codigo,
        v.nombre as varietat_nom,
        c.nombre_comun as cultiu_nom
    FROM rendiment_parcela rp
    LEFT JOIN sectores_cultivo sc ON rp.id_sector = sc.id
    LEFT JOIN parcelas p ON sc.parcela_id = p.id
    LEFT JOIN historial_cultivos hc ON sc.id = hc.sector_id AND hc.fecha_arrancada IS NULL
    LEFT JOIN variedades v ON hc.variedad_id = v.id
    LEFT JOIN cultivos c ON v.cultivo_id = c.id
    WHERE rp.any_campanya = ?
    ORDER BY rp.benefici_net DESC
");
$stmt->execute([$any]);
$rendiments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas globales
$totalIngressos = array_sum(array_column($rendiments, 'ingressos_totals'));
$totalCostes = array_sum(array_column($rendiments, 'cost_total'));
$totalBenefici = array_sum(array_column($rendiments, 'benefici_net'));
$totalSuperficie = array_sum(array_column($rendiments, 'superficie_ha'));
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Rendimientos - Campaña <?= $any ?></title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .resumen-global {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .resumen-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 5px solid;
        }
        
        .resumen-card.ingressos { border-top-color: #28a745; }
        .resumen-card.costes { border-top-color: #dc3545; }
        .resumen-card.benefici { border-top-color: #3282b8; }
        .resumen-card.rendiment { border-top-color: #ffc107; }
        
        .resumen-valor {
            font-size: 2.2em;
            font-weight: bold;
            color: #1b262c;
            margin: 10px 0;
        }
        
        .resumen-card.benefici .resumen-valor {
            color: <?= $totalBenefici >= 0 ? '#28a745' : '#dc3545' ?>;
        }
        
        .tabla-rendiments {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }
        
        .tabla-rendiments th {
            background: #1b262c;
            color: white;
            padding: 15px;
            text-align: left;
        }
        
        .tabla-rendiments td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .tabla-rendiments tr:hover {
            background: #f8f9fa;
        }
        
        .benefici-positiu { color: #28a745; font-weight: bold; }
        .benefici-negatiu { color: #dc3545; font-weight: bold; }
        
        .rendiment-bar {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            width: 100px;
        }
        
        .rendiment-fill {
            height: 100%;
            background: #28a745;
        }
        
        .btn-calcular {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        
        .filtro-any {
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px solid #3282b8;
            font-size: 1em;
        }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container">
    
    <div class="header-actions">
        <h1>📊 Rendimientos por Parcela - Campaña <?= $any ?></h1>
        
        <form method="GET" action="">
            <select name="any" class="filtro-any" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $any == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
    
    <!-- RESUMEN GLOBAL -->
    <div class="resumen-global">
        <div class="resumen-card ingressos">
            <div>💰 INGRESOS TOTALES</div>
            <div class="resumen-valor"><?= number_format($totalIngressos, 2) ?> €</div>
        </div>
        <div class="resumen-card costes">
            <div>💸 COSTES TOTALES</div>
            <div class="resumen-valor"><?= number_format($totalCostes, 2) ?> €</div>
        </div>
        <div class="resumen-card benefici">
            <div>📈 BENEFICIO NETO</div>
            <div class="resumen-valor"><?= number_format($totalBenefici, 2) ?> €</div>
        </div>
        <div class="resumen-card rendiment">
            <div>📐 SUPERFICIE TOTAL</div>
            <div class="resumen-valor"><?= number_format($totalSuperficie, 2) ?> ha</div>
        </div>
    </div>
    
    <!-- TABLA DE RENDIMIENTOS -->
    <table class="tabla-rendiments">
        <thead>
            <tr>
                <th>Parcela/Sector</th>
                <th>Cultivo</th>
                <th>Superficie</th>
                <th>Producción</th>
                <th>Ingresos</th>
                <th>Costes</th>
                <th>Beneficio</th>
                <th>€/ha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rendiments as $r): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($r['parcela_nombre']) ?></strong>
                        <br><small><?= htmlspecialchars($r['sector_nombre']) ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($r['cultiu_nom'] ?? 'N/A') ?>
                        <?php if ($r['varietat_nom']): ?>
                            <br><small><?= htmlspecialchars($r['varietat_nom']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= number_format($r['superficie_ha'], 2) ?> ha</td>
                    <td>
                        <?= number_format($r['produccio_total_kg'], 0) ?> kg
                        <br><small><?= number_format($r['rendiment_ha_kg'], 0) ?> kg/ha</small>
                    </td>
                    <td><?= number_format($r['ingressos_totals'], 2) ?> €</td>
                    <td><?= number_format($r['cost_total'], 2) ?> €</td>
                    <td class="<?= $r['benefici_net'] >= 0 ? 'benefici-positiu' : 'benefici-negatiu' ?>">
                        <?= number_format($r['benefici_net'], 2) ?> €
                    </td>
                    <td class="<?= $r['benefici_ha'] >= 0 ? 'benefici-positiu' : 'benefici-negatiu' ?>">
                        <?= number_format($r['benefici_ha'], 2) ?> €/ha
                    </td>
                    <td>
                        <a href="VeureRendiment.php?sector_id=<?= $r['id_sector'] ?>&any=<?= $any ?>" class="btn-actualizar" style="padding: 8px 15px;">
                            👁️ Ver
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (empty($rendiments)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <p>No hay cálculos de rendimiento para esta campaña.</p>
                        <a href="../Tractament/FichasTractament.php" class="btn-calcular" style="display: inline-block; margin-top: 15px;">
                            Ir a Parcelas para calcular
                        </a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
</div>

</body>
</html>