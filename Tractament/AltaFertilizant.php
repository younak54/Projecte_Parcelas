<?php
require '../db.php';
session_start();

$missatge = '';

if (isset($_SESSION['status_msg'])) {
    $missatge = $_SESSION['status_msg'];
    unset($_SESSION['status_msg']);
}

if (isset($_POST['eliminar_fertilizante'])) {
    try {
        $id_prod = $_POST['id_prod'];
        
        // Intentamos borrar. 
        // Nota: Si ya lo has usado en un "registro_fertilizacions", 
        // la base de datos te dará error por seguridad (integridad referencial).
        $sql_delete = "DELETE FROM fertilizantes WHERE id = ?";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([$id_prod]);
        
        $_SESSION['status_msg'] = "<div class='alerta exit'>🗑️ Producte eliminat correctament.</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        // Si el producto ya está en uso, mejor solo desactivarlo
        $missatge = "<div class='alerta error'>❌ No es pot eliminar perquè s'ha fet servir en registres. Prova a Desactivar-lo.</div>";
    }
}

// --- 1. LÓGICA PHP CORREGIDA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ACCIÓN: ALTA DE FERTILIZANTE
    if (isset($_POST['alta_fertilizante'])) {
        try {
            $sql = "INSERT INTO fertilizantes (nombre_comercial, composicion_npk, tipo, concentracion, unidad, activo) 
                    VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            // IMPORTANTE: trim() elimina espacios accidentales que causan el error en MICRONUTRIENTES
            $stmt->execute([
                $_POST['nombre_comercial'], 
                $_POST['composicion_npk'], 
                trim($_POST['tipo']), 
                $_POST['concentracion'], 
                $_POST['unidad']
            ]);

            $_SESSION['status_msg'] = "<div class='alerta exit'>✅ Fertilitzant guardat correctament.</div>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();

        } catch (PDOException $e) {
            $missatge = "<div class='alerta error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // ACCIÓN: ACTIVAR/DESACTIVAR (Esta es la parte que te faltaba)
    if (isset($_POST['toggle_estado'])) {
        try {
            $id_prod = $_POST['id_prod'];
            // Cambia el estado al opuesto (si es 1 pasa a 0, si es 0 pasa a 1)
            $sql_toggle = "UPDATE fertilizantes SET activo = NOT activo WHERE id = ?";
            $stmt_toggle = $pdo->prepare($sql_toggle);
            $stmt_toggle->execute([$id_prod]);
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $missatge = "<div class='alerta error'>❌ Error al canviar l'estat.</div>";
        }
    }
}

$fertilitzants = $pdo->query("SELECT * FROM fertilizantes ORDER BY activo DESC, nombre_comercial ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestió de Fertilitzants</title>
    <link rel="stylesheet" href="../css/tasques.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        :root { --primari: #0f4c75; --primari-fosc: #0f4c75; --bg: #f4f7f6; --text: #333; }
        body { background-color: var(--bg); color: var(--text); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        
        /* Targetes */
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border: 1px solid #e1e8e5; }
        h2 { margin-top: 0; color: #2c3e50; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }

        /* Formulari */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; color: #666; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; transition: border 0.3s; }
        input:focus { border-color: var(--primari); outline: none; }
        
        /* Botons */
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-principal { background: var(--primari); color: white; width: 100%; margin-top: 10px; }
        .btn-principal:hover { background: var(--primari-fosc); transform: translateY(-1px); }
        .btn-status { padding: 5px 10px; font-size: 0.8rem; }

        /* Taula */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8fbf9; color: #666; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; padding: 15px 10px; border-bottom: 2px solid #eee; text-align: left; }
        td { padding: 15px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background-color: #f9fdfb; }
        
        /* Estats */
        .status-badge { padding: 4px 8px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .bg-success { background: #e6f4ea; color: #1e7e34; }
        .bg-danger { background: #fce8e6; color: #c5221f; }
        .inactiu { opacity: 0.6; grayscale: 1; }

        /* Filtre */
        .search-bar { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }

        /* Alertes */
        .alerta { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .exit { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>
<div class="container">
    <div class="card">
        <h2>🌱 Alta de Nou Fertilitzant</h2>
        <?= $missatge ?>
        <form method="POST">
            <div class="form-group">
                <label>Nom Comercial del Producte</label>
                <input type="text" name="nombre_comercial" required placeholder="Ex: Nitrofoska">
            </div>
            <div class="grid">
                <div class="form-group">
                    <label>Tipus</label>
                    <select name="tipo" required>
                        <option value="NITROGENADO">Nitrogenat</option>
                        <option value="FOSFATADO">Fosfatat</option>
                        <option value="POTASICO">Potàssic</option>
                        <option value="MICRONUTRIENTES">Micronutrients</option>
                        <option value="ORGANICO">Orgànic</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Composició NPK</label>
                    <input type="text" name="composicion_npk" placeholder="Ex: 12-8-16">
                </div>
                <div class="form-group">
                    <label>Concentració</label>
                    <input type="number" step="0.01" name="concentracion" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Unitat</label>
                    <input type="text" name="unidad" value="%" placeholder="%">
                </div>
            </div>
            <button type="submit" name="alta_fertilizante" class="btn btn-principal">💾 Guardar al Catàleg</button>
        </form>
    </div>

    <h2>📋 Inventari de Productes</h2>
    <input type="text" id="searchInput" class="search-bar" placeholder="🔍 Cerca per nom, tipus o NPK...">

    <div class="card table-container">
        <table id="fertilizerTable">
            <thead>
                <tr>
                    <th>Producte</th>
                    <th>Tipus</th>
                    <th>NPK</th>
                    <th>Concentració</th>
                    <th>Estat</th>
                    <th>Acció</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fertilitzants as $f): ?>
                <tr class="<?= $f['activo'] ? '' : 'inactiu' ?>">
                    <td><strong><?= htmlspecialchars($f['nombre_comercial']) ?></strong></td>
                    <td><small><?= htmlspecialchars($f['tipo']) ?></small></td>
                    <td><code><?= htmlspecialchars($f['composicion_npk'] ?: '-') ?></code></td>
                    <td><?= htmlspecialchars($f['concentracion']) ?> <?= htmlspecialchars($f['unidad']) ?></td>
                    <td>
                        <span class="status-badge <?= $f['activo'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $f['activo'] ? 'ACTIU' : 'INACTIU' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="id_prod" value="<?= $f['id'] ?>">
                            <button type="submit" name="toggle_estado" class="btn btn-status" style="background:#eee; color:#333;">
                                <?= $f['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Segur que vols eliminar-lo per complet?');">
                            <input type="hidden" name="id_prod" value="<?= $f['id'] ?>">
                            <button type="submit" name="eliminar_fertilizante" class="btn btn-status" style="background:#fce8e6; color:#c5221f;">
                                Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Filtre de cerca en temps real
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#fertilizerTable tbody tr');
        
        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>

</body>
</html>