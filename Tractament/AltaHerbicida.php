<?php
require '../db.php';
$missatge = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alta_herbicida'])) {
    try {
        $sql = "INSERT INTO herbicidas (
                    nombre_comercial, principio_activo, codigo_registro, fabricante, 
                    tipo_hierba, modo_accion, descripcion, dosis_recomendada, 
                    unidad_dosis, toxicidad_clp, activo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['nombre_comercial'],
            $_POST['principio_activo'],
            $_POST['codigo_registro'],
            $_POST['fabricante'],
            $_POST['tipo_hierba'],
            $_POST['modo_accion'],
            $_POST['descripcion'],
            $_POST['dosis_recomendada'],
            $_POST['unidad_dosis'],
            $_POST['toxicidad_clp']
        ]);
        
        $missatge = "<div style='background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;'>✅ Producte fitosanitari registrat correctament amb totes les seves especificacions.</div>";
    } catch (PDOException $e) {
        $missatge = "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin-bottom:20px;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Alta d'Herbicida</title>
    <link rel="stylesheet" href="../css/tasques.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 800px; margin: 30px auto; font-family: sans-serif; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); background: #fff; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        h2 { border-bottom: 2px solid #0f4c75; color: #0f4c75; padding-bottom: 10px; margin-top: 0; }
        button { background: #0f4c75; color: white; padding: 15px; border: none; width: 100%; border-radius: 4px; cursor: pointer; font-size: 1.1em; margin-top: 20px; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>
    <div class="container">
        <form method="POST">
            <h2>🆕 Nou Producte Herbicida / Fitosanitari</h2>
            <?= $missatge ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Nom Comercial *</label>
                    <input type="text" name="nombre_comercial" required placeholder="Ex: Roundup UltraPlus">
                </div>
                <div class="form-group">
                    <label>Codi de Registre *</label>
                    <input type="text" name="codigo_registro" required placeholder="Ex: ES-12345">
                </div>

                <div class="form-group">
                    <label>Principi Actiu *</label>
                    <input type="text" name="principio_activo" required placeholder="Ex: Glifosat 36%">
                </div>
                <div class="form-group">
                    <label>Fabricant</label>
                    <input type="text" name="fabricante" placeholder="Ex: Bayer, Syngenta...">
                </div>

                <div class="form-group">
                    <label>Tipus d'Herba *</label>
                    <select name="tipo_hierba" required>
                        <option value="gramineas">Gramínies</option>
                        <option value="dicotiledoneas">Dicotiledònies</option>
                        <option value="ambas">Ambdues (Amplia aspectre)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Toxicitat (CLP)</label>
                    <input type="text" name="toxicidad_clp" placeholder="Ex: GHS07, GHS09">
                </div>

                <div class="form-group">
                    <label>Dosi Recomanada</label>
                    <input type="text" name="dosis_recomendada" placeholder="Ex: 2.5">
                </div>
                <div class="form-group">
                    <label>Unitat Dosi</label>
                    <select name="unidad_dosis">
                        <option value="L/ha">L/ha</option>
                        <option value="Kg/ha">Kg/ha</option>
                        <option value="%">% (Concentració)</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label>Mode d'Acció</label>
                    <input type="text" name="modo_accion" placeholder="Ex: Sistèmic, contacte, pre-emergència...">
                </div>

                <div class="form-group full-width">
                    <label>Descripció i Advertències</label>
                    <textarea name="descripcion" rows="4"></textarea>
                </div>
            </div>

            <button type="submit" name="alta_herbicida">💾 Registrar al Catàleg</button>
        </form>
    </div>
</body>
</html>