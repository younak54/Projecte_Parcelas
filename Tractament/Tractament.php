<?php
require '../db.php';
$missatge = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_tratamiento'])) {
    $id_sector = $_POST['sector_id'];
    $id_herbicida = $_POST['herbicida_id'];
    $data_aplicacio = $_POST['data_aplicacio'];
    $dosi = $_POST['dosi'];
    $unitat = $_POST['unitat'];
    $observacions = $_POST['observacions'];

    try {
        $sql = "INSERT INTO registro_tractaments (sector_id, herbicida_id, data_aplicacio, dosi, unitat, observacions) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_sector, $id_herbicida, $data_aplicacio, $dosi, $unitat, $observacions]);
        $missatge = "<div style='background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;'>✅ Tractament fitosanitari registrat correctament.</div>";
    } catch (PDOException $e) {
        $missatge = "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin-bottom:20px;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Consultas
$sectors = $pdo->query("SELECT s.id, p.nombre as p_nom, s.codigo FROM sectores_cultivo s JOIN sectores_parcelas sp ON s.id = sp.sector_id JOIN parcelas p ON sp.parcela_id = p.id WHERE s.activo = 1 ORDER BY p.nombre, s.codigo")->fetchAll();
$herbicides = $pdo->query("SELECT id, nombre_comercial FROM herbicidas ORDER BY nombre_comercial")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Registre de Tractament</title>
    <link rel="stylesheet" href="../css/tasques.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 600px; margin: 40px auto; font-family: sans-serif; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50; }
        select, input, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .inline-group { display: flex; gap: 10px; }
        button { background: #0f4c75; color: white; padding: 12px; border: none; width: 100%; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0a3554; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>
    <div class="container">
        <h1>🌿 Registre de Tractament</h1>
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
                <label>Producte Fitosanitari:</label>
                <select name="herbicida_id" required>
                    <option value="">-- Selecciona Producte --</option>
                    <?php foreach($herbicides as $h): ?>
                        <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['nombre_comercial']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Dosi i Unitat:</label>
                <div class="inline-group">
                    <input type="number" step="0.01" name="dosi" placeholder="Ex: 2.5" required>
                    <select name="unitat" style="width: 150px;">
                        <option value="L/ha">L/ha</option>
                        <option value="Kg/ha">Kg/ha</option>
                        <option value="cc/100L">cc/100L</option>
                        <option value="g/100L">g/100L</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Data de l'aplicació:</label>
                <input type="date" name="data_aplicacio" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label>Observacions:</label>
                <textarea name="observacions" rows="3"></textarea>
            </div>

            <button type="submit" name="registrar_tratamiento">💾 Guardar Tractament</button>
        </form>
    </div>
</body>
</html>