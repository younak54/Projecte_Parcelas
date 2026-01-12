<?php
require '../db.php';
$missatge = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_fertilizacion'])) {
    $id_sector = $_POST['sector_id'];
    $id_fertilizante = $_POST['fertilizante_id'];
    $data_abonat = $_POST['data_abonat'];
    $quantitat = $_POST['quantitat'];
    $observacions = $_POST['observacions'];

    try {
        $sql = "INSERT INTO registro_fertilizacions (sector_id, fertilizante_id, data_abonat, quantitat, observacions) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_sector, $id_fertilizante, $data_abonat, $quantitat, $observacions]);
        $missatge = "<div style='background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;'>✅ Fertilització registrada correctament a l'historial.</div>";
    } catch (PDOException $e) {
        $missatge = "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin-bottom:20px;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Consultas para los desplegables
$sectors = $pdo->query("SELECT s.id, p.nombre as p_nom, s.codigo FROM sectores_cultivo s JOIN sectores_parcelas sp ON s.id = sp.sector_id JOIN parcelas p ON sp.parcela_id = p.id WHERE s.activo = 1 ORDER BY p.nombre, s.codigo")->fetchAll();
$fertilitzants = $pdo->query("SELECT id, nombre_comercial FROM fertilizantes WHERE activo = 1 ORDER BY nombre_comercial")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Registre de Fertilització</title>
    <link rel="stylesheet" href="../css/tasques.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 600px; margin: 40px auto; font-family: sans-serif; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50; }
        select, input, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #0f4c75; color: white; padding: 12px; border: none; width: 100%; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0a3554; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>
    <div class="container">
        <h1>🚜 Registre de Fertilització</h1>
        <?= $missatge ?>
        <form method="POST">
            <div class="form-group">
                <label>Sector / Parcel·la:</label>
                <select name="sector_id" required>
                    <option value="">-- Selecciona Sector --</option>
                    <?php foreach($sectors as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['p_nom'] . " - " . $s['codigo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Fertilitzant:</label>
                <select name="fertilizante_id" required>
                    <option value="">-- Selecciona Producte --</option>
                    <?php foreach($fertilitzants as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nombre_comercial']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Quantitat (Kg o L/ha):</label>
                <input type="number" step="0.01" name="quantitat" required>
            </div>

            <div class="form-group">
                <label>Data d'aplicació:</label>
                <input type="date" name="data_abonat" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label>Observacions:</label>
                <textarea name="observacions" rows="3"></textarea>
            </div>

            <button type="submit" name="registrar_fertilizacion">💾 Guardar Fertilització</button>
        </form>
    </div>
</body>
</html>