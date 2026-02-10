<?php
require '../db.php';

// 1. Manejo de Sesión y Redirección (PRG) para evitar duplicados al refrescar
session_start();
$mensaje = '';
if (isset($_SESSION['alerta'])) {
    $mensaje = "<div class='alert alert-success'>" . $_SESSION['alerta'] . "</div>";
    unset($_SESSION['alerta']);
}

// 2. Procesar el Formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_aplicacion'])) {
    $id_sector = $_POST['id_sector'];
    $tipo = $_POST['tipus_aplicacio'];
    $id_producto = ($tipo == 'FITOSANITARI') ? $_POST['id_producte'] : null;
    $id_fertilizante = ($tipo == 'FERTILITZACIO') ? $_POST['id_fertilizant'] : null;
    
    // Si no se seleccionan filas, se asume sector completo (null)
    $filas = isset($_POST['filas_seleccionadas']) ? $_POST['filas_seleccionadas'] : [null];

    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO aplicacions (
                    id_sector, fila_id, id_producte, id_fertilizant, tipus_aplicacio, 
                    data_aplicacio, superficie_tractada_ha, dosis_aplicada, volum_total_caldo, 
                    metode_aplicacio, condicions_clima, id_operari, observacions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);

        foreach ($filas as $fila) {
            $stmt->execute([
                $id_sector,
                $fila,
                $id_producto,
                $id_fertilizante,
                $tipo,
                $_POST['data_aplicacio'],
                $_POST['superficie'],
                $_POST['dosis'],
                $_POST['volum_caldo'] ?? null,
                $_POST['metode'] ?? null,     
                $_POST['clima'] ?? null,       
                $_POST['id_operari'],
                $_POST['observacions'] ?? null
            ]);
        }

        $pdo->commit();
        $_SESSION['alerta'] = "✅ Aplicació registrada correctament en " . count($filas) . " fila(es).";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
    }
}

// 3. Consultas para Selectores e Historial
$sql_historial = "SELECT a.*, 
    s.codigo as sector_nom, 
    p.nombre_comercial as producto_nom, 
    f.nombre_comercial as ferti_nom, 
    t.nom_complet as operari_nom
    FROM aplicacions a
    JOIN sectores_cultivo s ON a.id_sector = s.id
    LEFT JOIN herbicidas p ON a.id_producte = p.id
    LEFT JOIN fertilizantes f ON a.id_fertilizant = f.id
    JOIN treballadors t ON a.id_operari = t.id_treballador
    ORDER BY a.data_aplicacio DESC LIMIT 20";

$historial = $pdo->query($sql_historial)->fetchAll(PDO::FETCH_ASSOC);
$sectores = $pdo->query("SELECT s.id, p.nombre as p_nom, s.codigo FROM sectores_cultivo s JOIN parcelas p ON s.parcela_id = p.id ORDER BY p.nombre")->fetchAll();
$productos = $pdo->query("SELECT id, nombre_comercial FROM herbicidas ORDER BY nombre_comercial")->fetchAll();
$fertilizantes = $pdo->query("SELECT id, nombre_comercial FROM fertilizantes ORDER BY nombre_comercial")->fetchAll();
$operarios = $pdo->query("SELECT id_treballador, nom_complet FROM treballadors ORDER BY nom_complet")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Aplicaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .row-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(50px, 1fr)); gap: 5px; margin-top: 10px; }
        .fila-btn { border: 1px solid #ddd; padding: 5px; text-align: center; cursor: pointer; border-radius: 5px; font-size: 11px; background: white; }
        .fila-btn.selected { background: #28a745; color: white; border-color: #1e7e34; }
        .badge-fila { font-size: 0.8rem; }
    </style>
</head>
<body class="bg-light pb-5">

<div class="container mt-4">
    <div class="card p-4">
        <h2 class="mb-4">🚜 Nova Aplicació Fitosanitària / Fertilitzant</h2>
        <?= $mensaje ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold">Sector / Parcela</label>
                    <select name="id_sector" class="form-select" required>
                        <?php foreach($sectores as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['p_nom'] ?> - <?= $s['codigo'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold">Operari</label>
                    <select name="id_operari" class="form-select" required>
                        <?php foreach($operarios as $o): ?>
                            <option value="<?= $o['id_treballador'] ?>"><?= $o['nom_complet'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="fw-bold">Tipus d'Aplicació</label>
                    <select name="tipus_aplicacio" id="tipo_app" class="form-select" onchange="toggleProductos()">
                        <option value="FITOSANITARI">Fitosanitari</option>
                        <option value="FERTILITZACIO">Fertilització</option>
                    </select>
                </div>

                <div class="col-md-8 mb-3" id="div_fitos">
                    <label class="fw-bold">Producte Fitosanitari</label>
                    <select name="id_producte" class="form-select">
                        <option value="">-- Selecciona --</option>
                        <?php foreach($productos as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['nombre_comercial'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8 mb-3" id="div_fertis" style="display:none;">
                    <label class="fw-bold">Fertilitzant</label>
                    <select name="id_fertilizant" class="form-select">
                        <option value="">-- Selecciona --</option>
                        <?php foreach($fertilizantes as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= $f['nombre_comercial'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3 p-3 border rounded bg-light">
                <label class="fw-bold d-block mb-2">Àmbit de Treball</label>
                <div class="btn-group mb-2">
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="setAmbito('todo')">Sector Complet</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" onclick="setAmbito('filas')">Seleccionar Files</button>
                </div>

                <div id="contenedor_filas" style="display:none;">
                    <div class="row-grid">
                        <?php for($i=1; $i<=50; $i++): ?>
                            <div class="fila-btn" onclick="toggleFila(this, <?= $i ?>)"><?= $i ?></div>
                        <?php endfor; ?>
                    </div>
                    <div id="inputs_filas"></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="fw-bold small">Dosi</label>
                    <input type="number" step="0.01" name="dosis" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="fw-bold small">Volum Caldo (L)</label>
                    <input type="number" step="0.01" name="volum_caldo" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="fw-bold small">Superfície (Ha)</label>
                    <input type="number" step="0.0001" name="superficie" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="fw-bold small">Mètode</label>
                    <input type="text" name="metode" class="form-control" placeholder="Ex: Atomitzador">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold small">Data</label>
                    <input type="datetime-local" name="data_aplicacio" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold small">Condicions Clima</label>
                    <input type="text" name="clima" class="form-control" placeholder="Vent, Temp...">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold small">Observacions</label>
                    <input type="text" name="observacions" class="form-control">
                </div>
            </div>

            <button type="submit" name="guardar_aplicacion" class="btn btn-success btn-lg w-100">💾 Guardar Registre</button>
        </form>
    </div>

    <h3 class="mb-3">📋 Historial de Tractaments</h3>
    <div class="table-responsive bg-white p-3 rounded shadow-sm">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Data / Operari</th>
                    <th>Ubicació</th>
                    <th>Producte</th>
                    <th>Dades Tècniques</th>
                    <th>Clima / Obs.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($historial as $reg): ?>
                <tr>
                    <td>
                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($reg['data_aplicacio'])) ?></small><br>
                        <strong>👤 <?= htmlspecialchars($reg['operari_nom']) ?></strong>
                    </td>
                    <td>
                        <span class="badge bg-secondary">Sector: <?= $reg['sector_nom'] ?></span><br>
                        <?php if($reg['fila_id']): ?>
                            <span class="badge bg-info text-dark badge-fila">Fila: <?= $reg['fila_id'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-success badge-fila">Tot el sector</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="d-block text-uppercase text-muted" style="font-size:10px;"><?= $reg['tipus_aplicacio'] ?></small>
                        <strong><?= ($reg['tipus_aplicacio'] == 'FITOSANITARI') ? $reg['producto_nom'] : $reg['ferti_nom'] ?></strong>
                    </td>
                    <td>
                        <div style="font-size: 0.85rem;">
                            🧪 <?= $reg['dosis_aplicada'] ?> / Ha<br>
                            💧 <?= $reg['volum_total_caldo'] ?? '-' ?> L Caldo<br>
                            🚜 <?= htmlspecialchars($reg['metode_aplicacio'] ?? '-') ?>
                        </div>
                    </td>
                    <td style="font-size: 0.85rem;">
                        <span class="text-primary fw-bold"><?= htmlspecialchars($reg['condicions_clima'] ?? '') ?></span><br>
                        <span class="text-muted italic"><?= htmlspecialchars($reg['observacions'] ?? '') ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function toggleProductos() {
        const tipo = document.getElementById('tipo_app').value;
        document.getElementById('div_fitos').style.display = (tipo === 'FITOSANITARI') ? 'block' : 'none';
        document.getElementById('div_fertis').style.display = (tipo === 'FERTILITZACIO') ? 'block' : 'none';
    }

    function setAmbito(tipo) {
        const contenedor = document.getElementById('contenedor_filas');
        if(tipo === 'todo') {
            contenedor.style.display = 'none';
            document.getElementById('inputs_filas').innerHTML = '';
            document.querySelectorAll('.fila-btn').forEach(btn => btn.classList.remove('selected'));
        } else {
            contenedor.style.display = 'block';
        }
    }

    function toggleFila(el, num) {
        el.classList.toggle('selected');
        const inputs = document.getElementById('inputs_filas');
        if(el.classList.contains('selected')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'filas_seleccionadas[]';
            input.value = num;
            input.id = 'input_fila_' + num;
            inputs.appendChild(input);
        } else {
            const item = document.getElementById('input_fila_' + num);
            if(item) item.remove();
        }
    }
</script>
</body>
</html>