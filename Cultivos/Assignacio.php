<?php
require '../db.php';

// --- 1. PROCESSAR LA FINALITZACIÓ MANUAL ---
if (isset($_POST['finalitzar_id'])) {
    $id_historial = $_POST['finalitzar_id'];
    $data_avui = date('Y-m-d');

    try {
        $stmt_fin = $pdo->prepare("UPDATE historial_cultivos SET fecha_arrancada = ? WHERE id = ?");
        $stmt_fin->execute([$data_avui, $id_historial]);
        header("Location: Assignacio.php?status=finalitzat");
        exit;
    } catch (PDOException $e) {
        $error = "Error al finalitzar: " . $e->getMessage();
    }
}

// --- 2. CONSULTA PER MOSTRAR LA TAULA ---
$sql = "SELECT 
            h.id,
            p.nombre AS parcela_nombre,
            s.codigo AS sector_codigo,
            c.nombre_comun AS cultivo_nombre,
            v.nombre AS variedad_nombre,
            h.fecha_plantacion,
            h.fecha_arrancada
        FROM historial_cultivos h
        LEFT JOIN sectores_cultivo s ON h.sector_id = s.id
        LEFT JOIN sectores_parcelas sp ON s.id = sp.sector_id
        LEFT JOIN parcelas p ON sp.parcela_id = p.id
        LEFT JOIN variedades v ON h.variedad_id = v.id
        LEFT JOIN cultivos c ON v.cultivo_id = c.id
        ORDER BY h.fecha_arrancada ASC, h.fecha_plantacion DESC";

$stmt = $pdo->query($sql);
$assignacions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Historial de Cultius</title>
    <link rel="stylesheet" href="../css/tasques.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-family: sans-serif; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #0f4c75; color: white; }
        .actiu { color: green; font-weight: bold; }
        .tancat { color: #888; }
        .btn-finalitzar {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
        }
        .btn-finalitzar:hover { background-color: #c0392b; }
        .alerta { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div style="padding: 40px;">
        <h1>📋 Historial d'Assignacions</h1>

        <?php if(isset($_GET['status']) && $_GET['status'] == 'finalitzat'): ?>
            <div class="alerta">✅ El cultiu s'ha marcat com a finalitzat amb la data d'avui.</div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Sector</th>
                    <th>Cultiu (Varietat)</th>
                    <th>Inici</th>
                    <th>Final</th>
                    <th>Estat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignacions as $asig): ?>
                <tr>
                    <td><?= htmlspecialchars($asig['parcela_nombre']) ?> (<?= $asig['sector_codigo'] ?>)</td>
                    <td><?= htmlspecialchars($asig['cultivo_nombre']) ?> - <i><?= htmlspecialchars($asig['variedad_nombre']) ?></i></td>
                    <td><?= date('d/m/Y', strtotime($asig['fecha_plantacion'])) ?></td>
                    <td>
                        <?php if ($asig['fecha_arrancada']): ?>
                            <?= date('d/m/Y', strtotime($asig['fecha_arrancada'])) ?>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Vols finalitzar aquest cultiu avui?');">
                                <input type="hidden" name="finalitzar_id" value="<?= $asig['id'] ?>">
                                <button type="submit" class="btn-finalitzar">⏹ Finalitzar ara</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$asig['fecha_arrancada']): ?>
                            <span class="actiu">🟢 Actiu</span>
                        <?php else: ?>
                            <span class="tancat">⚪ Tancat</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <br>
        <a href="GestioCulti.php" style="text-decoration: none; color: #0f4c75;">➕ Assignar nou cultiu</a>
    </div>
</body>
</html>