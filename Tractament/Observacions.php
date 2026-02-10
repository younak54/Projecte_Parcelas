<?php
ob_start(); // Previene errores de redirección
require '../db.php';
session_start();

$mensaje = '';
if (isset($_SESSION['alerta'])) {
    $mensaje = "<div class='alert alert-success'>" . $_SESSION['alerta'] . "</div>";
    unset($_SESSION['alerta']);
}

// 1. Procesar el Formulario de Observaciones
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_observacio'])) {
    try {
        $sql = "INSERT INTO observacions_sector (id_sector, data_observacio, observacio, id_treballador, tipus) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['id_sector'],
            $_POST['data_observacio'],
            $_POST['observacio'],
            $_POST['id_treballador'],
            $_POST['tipus']
        ]);

        $_SESSION['alerta'] = "✅ Observació registrada correctament.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $mensaje = "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
    }
}

// 2. Consultas para Selectores e Historial de Notas
$sectores = $pdo->query("SELECT s.id, p.nombre as p_nom, s.codigo FROM sectores_cultivo s JOIN parcelas p ON s.parcela_id = p.id ORDER BY p.nombre")->fetchAll();
$operarios = $pdo->query("SELECT id_treballador, nom_complet FROM treballadors ORDER BY nom_complet")->fetchAll();

$sql_notas = "SELECT o.*, s.codigo as sector_nom, t.nom_complet as operari_nom
              FROM observacions_sector o
              JOIN sectores_cultivo s ON o.id_sector = s.id
              LEFT JOIN treballadors t ON o.id_treballador = t.id_treballador
              ORDER BY o.data_observacio DESC LIMIT 30";
$notas = $pdo->query($sql_notas)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Observacions de Sector</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .table-responsive { background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="bg-light pb-5">

<div class="container mt-4">
    <div class="card p-4">
        <h2 class="mb-4">📝 Nova Observació de Sector</h2>
        <?= $mensaje ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold">Sector / Parcela</label>
                    <select name="id_sector" class="form-select" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach($sectores as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['p_nom'] ?> - <?= $s['codigo'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold">Treballador</label>
                    <select name="id_treballador" class="form-select" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach($operarios as $o): ?>
                            <option value="<?= $o['id_treballador'] ?>"><?= $o['nom_complet'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="fw-bold">Tipus de Nota</label>
                    <select name="tipus" class="form-select">
                        <option value="GENERAL">General</option>
                        <option value="FITOSANITARI">Fitosanitari (Plaga/Malaltia)</option>
                        <option value="FENOLÒGIC">Fenològic (Estat planta)</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold">Data Observació</label>
                    <input type="date" name="data_observacio" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="fw-bold">Descripció de l'Observació</label>
                <textarea name="observacio" class="form-control" rows="3" placeholder="Què has vist de rellevant al sector?" required></textarea>
            </div>

            <button type="submit" name="guardar_observacio" class="btn btn-primary btn-lg w-100 fw-bold">💾 Guardar Observació</button>
        </form>
    </div>

    <h3 class="mb-3">📋 Notes Recents de Camp</h3>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Data / Treballador</th>
                    <th>Ubicació</th>
                    <th>Tipus</th>
                    <th>Observació / Comentaris</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($notas as $n): ?>
                <tr>
                    <td>
                        <small class="text-muted"><?= date('d/m/Y', strtotime($n['data_observacio'])) ?></small><br>
                        <strong>👤 <?= htmlspecialchars($n['operari_nom']) ?></strong>
                    </td>
                    <td>
                        <span class="badge bg-secondary">Sector: <?= $n['sector_nom'] ?></span>
                    </td>
                    <td>
                        <?php 
                            $color = ($n['tipus'] == 'FITOSANITARI') ? 'danger' : (($n['tipus'] == 'FENOLÒGIC') ? 'info' : 'success');
                        ?>
                        <span class="badge bg-<?= $color ?>"><?= $n['tipus'] ?></span>
                    </td>
                    <td class="bg-light-subtle rounded">
                        <div class="p-2 border-start border-3 border-<?= $color ?>">
                            <?= nl2br(htmlspecialchars($n['observacio'])) ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($notas)): ?>
                    <tr><td colspan="4" class="text-center p-4 text-muted">No hi ha observacions registrades.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>