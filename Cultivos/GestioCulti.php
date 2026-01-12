<?php
require '../db.php';
$missatge = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assignar_cultiu'])) {
    $id_sector = $_POST['id_sector'];
    $id_variedad = $_POST['id_variedad'];
    $data_plantacio = $_POST['data_plantacio'];
    
    try {
        $pdo->beginTransaction();

        $stmt_check = $pdo->prepare("SELECT id, fecha_plantacion FROM historial_cultivos WHERE sector_id = ? AND fecha_arrancada IS NULL");
        $stmt_check->execute([$id_sector]);
        $anterior = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($anterior) {
            $data_final = date('Y-m-d', strtotime($data_plantacio . ' -1 day'));
            if (strtotime($data_final) < strtotime($anterior['fecha_plantacion'])) $data_final = $anterior['fecha_plantacion'];
            $stmt_upd = $pdo->prepare("UPDATE historial_cultivos SET fecha_arrancada = ? WHERE id = ?");
            $stmt_upd->execute([$data_final, $anterior['id']]);
        }

        $stmt_ins = $pdo->prepare("INSERT INTO historial_cultivos (sector_id, variedad_id, fecha_plantacion, fecha_arrancada) VALUES (?, ?, ?, NULL)");
        $stmt_ins->execute([$id_sector, $id_variedad, $data_plantacio]);
        
        $pdo->commit();
        $missatge = "<div style='background:#d4edda;color:#155724;padding:15px;border-radius:5px;'>✅ Cultiu assignat correctament.</div>";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $missatge = "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;'>❌ Error: " . $e->getMessage() . "</div>";
    }
}

// Obtenir Cultius
$cultius = $pdo->query("SELECT id, nombre_comun FROM cultivos ORDER BY nombre_comun")->fetchAll(PDO::FETCH_ASSOC);

// Obtenir totes les Varietats per al JavaScript
$varietats = $pdo->query("SELECT id, nombre, cultivo_id FROM variedades ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtenir Sectors
$sectors = $pdo->query("SELECT s.id, s.codigo, p.nombre as p_nom FROM sectores_cultivo s JOIN sectores_parcelas sp ON s.id = sp.sector_id JOIN parcelas p ON sp.parcela_id = p.id WHERE s.activo = 1")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Assignar Cultiu</title>
    <link rel="stylesheet" href="../css/tasques.css">
    <style>
        .form-control { width:100%; padding:10px; margin:10px 0; border: 1px solid #ccc; border-radius: 4px; }
        label { font-weight: bold; color: #0f4c75; }
    </style>
</head>
<body>
    <div style="max-width: 600px; margin: 50px auto; font-family: sans-serif;">
        <h1>🌱 Nova Assignació</h1>
        <?= $missatge ?>
        
        <form method="POST">
            <label>Sector / Parcel·la:</label>
            <select name="id_sector" class="form-control" required>
                <option value="">-- Selecciona Sector --</option>
                <?php foreach($sectors as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= $s['p_nom'] ?> - <?= $s['codigo'] ?></option>
                <?php endforeach; ?>
            </select>

            <label>Tipus de Cultiu:</label>
            <select id="select_cultiu" class="form-control" required onchange="filtrarVarietats()">
                <option value="">-- Selecciona Cultiu --</option>
                <?php foreach($cultius as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= $c['nombre_comun'] ?></option>
                <?php endforeach; ?>
            </select>

            <label>Varietat:</label>
            <select id="select_variedad" name="id_variedad" class="form-control" required disabled>
                <option value="">-- Selecciona primer un cultiu --</option>
            </select>

            <label>Data de Plantació:</label>
            <input type="date" name="data_plantacio" value="<?= date('Y-m-d') ?>" class="form-control" required>

            <button type="submit" name="assignar_cultiu" style="background:#4CAF50; color:white; padding:15px; border:none; width:100%; cursor:pointer; border-radius:4px; font-size:16px; margin-top:10px;">💾 Guardar Assignació</button>
        </form>
    </div>

    <script>
        // Passem les varietats de PHP a JavaScript
        const totesLesVarietats = <?= json_encode($varietats) ?>;

        function filtrarVarietats() {
            const idCultiu = document.getElementById('select_cultiu').value;
            const selectVar = document.getElementById('select_variedad');
            
            // Netejar opcions actuals
            selectVar.innerHTML = '<option value="">-- Selecciona Varietat --</option>';
            
            if (idCultiu === "") {
                selectVar.disabled = true;
                return;
            }

            // Filtrar les varietats que coincideixen amb el cultivo_id
            const filtrades = totesLesVarietats.filter(v => v.cultivo_id == idCultiu);

            filtrades.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = v.nombre;
                selectVar.appendChild(opt);
            });

            selectVar.disabled = false;
        }
    </script>
</body>
</html>