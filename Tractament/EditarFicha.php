<?php
require '../db.php';

$idFicha = $_GET['id'] ?? null;
if (!$idFicha) {
    header('Location: FichasTractament.php');
    exit;
}

// Obtener ficha
$stmt = $pdo->prepare("SELECT * FROM fichas_tractament WHERE id_ficha = ?");
$stmt->execute([$idFicha]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ficha) {
    die('Ficha no encontrada');
}

// Obtener grupos con trabajadores
$stmt = $pdo->prepare("
    SELECT gt.*, r.nom_complet as responsable_nom
    FROM grups_treball gt
    LEFT JOIN treballadors r ON gt.responsable_id = r.id_treballador
    WHERE gt.id_ficha = ?
");
$stmt->execute([$idFicha]);
$grups = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($grups as &$grup) {
    $stmt = $pdo->prepare("
        SELECT t.id_treballador, t.nom_complet, gtt.rol_en_grup
        FROM grup_treballadors gtt
        JOIN treballadors t ON gtt.id_treballador = t.id_treballador
        WHERE gtt.id_grup = ?
    ");
    $stmt->execute([$grup['id_grup']]);
    $grup['treballadors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($grup);

// Datos para formulario
$stmt = $pdo->query("SELECT id_treballador, nom_complet FROM treballadors WHERE estat_actiu = 1 ORDER BY nom_complet");
$treballadors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, nombre_comercial, tipo_producto FROM productos_fitosanitarios WHERE activo = 1");
$productes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Actualizar ficha
        $stmt = $pdo->prepare("
            UPDATE fichas_tractament SET
                tipus_tractament = ?,
                descripcio = ?,
                estat = ?,
                data_inici = ?,
                data_fi_prevista = ?,
                superficie_ha = ?,
                producte_utilitzat = ?,
                dosis_aplicada = ?,
                unitat_dosis = ?,
                observacions = ?,
                id_supervisor = ?
            WHERE id_ficha = ?
        ");
        
        $stmt->execute([
            $_POST['tipus_tractament'],
            $_POST['descripcio'],
            $_POST['estat'],
            $_POST['data_inici'] ?: null,
            $_POST['data_fi_prevista'] ?: null,
            $_POST['superficie_ha'] ?: null,
            $_POST['producte_utilitzat'] ?: null,
            $_POST['dosis_aplicada'] ?: null,
            $_POST['unitat_dosis'] ?: null,
            $_POST['observacions'] ?: null,
            $_POST['id_supervisor'] ?: null,
            $idFicha
        ]);
        
        // Si se marca como completada, poner fecha real
        if ($_POST['estat'] === 'COMPLETAT' && !$ficha['data_fi_real']) {
            $stmt = $pdo->prepare("UPDATE fichas_tractament SET data_fi_real = CURDATE() WHERE id_ficha = ?");
            $stmt->execute([$idFicha]);
        }
        
        $pdo->commit();
        
        header("Location: VeureFicha.php?id=$idFicha&status=actualitzada");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al actualizar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Editar Ficha #<?= $idFicha ?></title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
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
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3282b8;
            outline: none;
        }
        .btn-guardar {
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        .btn-cancelar {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .grup-actual {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #3282b8;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

<div class="container">
    <a href="VeureFicha.php?id=<?= $idFicha ?>" class="btn-cancelar">← Volver</a>
    
    <h1>✏️ Editar Ficha #<?= $idFicha ?></h1>
    
    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        
        <div class="form-section">
            <h2>📋 Información General</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Tratamiento *</label>
                    <select name="tipus_tractament" required>
                        <?php $tipos = ['FITOSANITARI', 'FERTILITZACIO', 'PREPARACIO_SOL', 'SEMBRA', 'PODA', 'RECOLLECCIO', 'ALTRES']; ?>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t ?>" <?= $ficha['tipus_tractament'] == $t ? 'selected' : '' ?>>
                                <?= str_replace('_', ' ', $t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Estado *</label>
                    <select name="estat" required>
                        <?php $estados = ['PENDENT', 'EN_CURS', 'PAUSAT', 'COMPLETAT', 'CANCELAT']; ?>
                        <?php foreach ($estados as $e): ?>
                            <option value="<?= $e ?>" <?= $ficha['estat'] == $e ? 'selected' : '' ?>>
                                <?= str_replace('_', ' ', $e) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Descripción *</label>
                <textarea name="descripcio" rows="3" required><?= htmlspecialchars($ficha['descripcio']) ?></textarea>
            </div>
        </div>
        
        <div class="form-section">
            <h2>🧪 Producto y Superficie</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Superficie (ha)</label>
                    <input type="number" name="superficie_ha" step="0.0001" 
                           value="<?= $ficha['superficie_ha'] ?>">
                </div>
                
                <div class="form-group">
                    <label>Producto</label>
                    <select name="producte_utilitzat">
                        <option value="">-- Sin producto --</option>
                        <?php foreach ($productes as $p): ?>
                            <option value="<?= htmlspecialchars($p['nombre_comercial']) ?>" 
                                <?= $ficha['producte_utilitzat'] == $p['nombre_comercial'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre_comercial']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Dosis</label>
                    <input type="number" name="dosis_aplicada" step="0.01" 
                           value="<?= $ficha['dosis_aplicada'] ?>">
                </div>
                <div class="form-group">
                    <label>Unidad</label>
                    <select name="unitat_dosis">
                        <option value="">-- --</option>
                        <?php $unidades = ['L/ha', 'kg/ha', 'ml/L', 'g/L', 'L', 'kg']; ?>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= $u ?>" <?= $ficha['unitat_dosis'] == $u ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h2>📅 Fechas y Supervisor</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Fecha inicio</label>
                    <input type="date" name="data_inici" value="<?= $ficha['data_inici'] ?>">
                </div>
                <div class="form-group">
                    <label>Fecha fin prevista</label>
                    <input type="date" name="data_fi_prevista" value="<?= $ficha['data_fi_prevista'] ?>">
                </div>
                <div class="form-group">
                    <label>Supervisor</label>
                    <select name="id_supervisor">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach ($treballadors as $t): ?>
                            <option value="<?= $t['id_treballador'] ?>" 
                                <?= $ficha['id_supervisor'] == $t['id_treballador'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nom_complet']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observacions" rows="2"><?= htmlspecialchars($ficha['observacions'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="form-section">
            <h2>👥 Grupos de Trabajo (No editable - ver detalle)</h2>
            <p style="color: #666; margin-bottom: 15px;">
                Para modificar los grupos, elimina la ficha y crea una nueva, o contacta al administrador.
            </p>
            
            <?php foreach ($grups as $grup): ?>
                <div class="grup-actual">
                    <strong><?= htmlspecialchars($grup['nom_grup']) ?></strong>
                    <?php if ($grup['descripcio']): ?>
                        <br><small><?= htmlspecialchars($grup['descripcio']) ?></small>
                    <?php endif; ?>
                    <br>
                    <small>
                        Responsable: <?= htmlspecialchars($grup['responsable_nom'] ?? 'Sin asignar') ?> | 
                        <?= count($grup['treballadors']) ?> trabajadores
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
        
        <button type="submit" class="btn-guardar">
            💾 Guardar Cambios
        </button>
        
    </form>
</div>

</body>
</html>