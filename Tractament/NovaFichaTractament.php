<?php
require '../db.php';

$sectorId = $_GET['sector_id'] ?? null;

// Obtener información del sector si se especificó
$sectorInfo = null;
if ($sectorId) {
    $stmt = $pdo->prepare("
        SELECT sc.*, p.nombre as parcela_nombre, p.codigo as parcela_codigo
        FROM sectores_cultivo sc
        LEFT JOIN parcelas p ON sc.parcela_id = p.id
        WHERE sc.id = ?
    ");
    $stmt->execute([$sectorId]);
    $sectorInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener lista de trabajadores activos
$stmt = $pdo->query("
    SELECT id_treballador, nom_complet 
    FROM treballadors 
    WHERE estat_actiu = 1 
    ORDER BY nom_complet
");
$treballadors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos fitosanitarios disponibles
$stmt = $pdo->query("
    SELECT id, nombre_comercial, tipo_producto 
    FROM productos_fitosanitarios 
    WHERE activo = 1
    ORDER BY tipo_producto, nombre_comercial
");
$productes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener sectores para selector
$stmt = $pdo->query("
    SELECT sc.id, sc.nombre, sc.codigo, p.nombre as parcela_nombre
    FROM sectores_cultivo sc
    LEFT JOIN parcelas p ON sc.parcela_id = p.id
    WHERE sc.activo = 1
    ORDER BY p.nombre, sc.nombre
");
$sectores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 1. Crear ficha principal
        $stmt = $pdo->prepare("
            INSERT INTO fichas_tractament 
            (id_sector, tipus_tractament, descripcio, estat, data_inici, data_fi_prevista,
             superficie_ha, producte_utilitzat, dosis_aplicada, unitat_dosis, 
             observacions, id_supervisor)
            VALUES (?, ?, ?, 'PENDENT', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['sector_id'],
            $_POST['tipus_tractament'],
            $_POST['descripcio'],
            $_POST['data_inici'] ?: null,
            $_POST['data_fi_prevista'] ?: null,
            $_POST['superficie_ha'] ?: null,
            $_POST['producte_utilitzat'] ?: null,
            $_POST['dosis_aplicada'] ?: null,
            $_POST['unitat_dosis'] ?: null,
            $_POST['observacions'] ?: null,
            $_POST['id_supervisor'] ?: null
        ]);
        
        $idFicha = $pdo->lastInsertId();
        
        // 2. Crear grupos de trabajo
        $grups = $_POST['grups'] ?? [];
        foreach ($grups as $index => $grup) {
            if (empty($grup['nom'])) continue;
            
            $stmt = $pdo->prepare("
                INSERT INTO grups_treball (id_ficha, nom_grup, descripcio, responsable_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $idFicha,
                $grup['nom'],
                $grup['descripcio'] ?? null,
                $grup['responsable_id'] ?: null
            ]);
            
            $idGrup = $pdo->lastInsertId();
            
            // 3. Asignar trabajadores al grupo
            $treballadorsGrup = $grup['treballadors'] ?? [];
            foreach ($treballadorsGrup as $idTreballador) {
                $stmt = $pdo->prepare("
                    INSERT INTO grup_treballadors (id_grup, id_treballador, rol_en_grup)
                    VALUES (?, ?, 'OPERARI')
                ");
                $stmt->execute([$idGrup, $idTreballador]);
            }
            
            // El responsable también se añade como trabajador si no está ya
            if ($grup['responsable_id'] && !in_array($grup['responsable_id'], $treballadorsGrup)) {
                $stmt = $pdo->prepare("
                    INSERT INTO grup_treballadors (id_grup, id_treballador, rol_en_grup)
                    VALUES (?, ?, 'RESPONSABLE')
                ");
                $stmt->execute([$idGrup, $grup['responsable_id']]);
            }
        }
        
        $pdo->commit();
        
        header("Location: VeureFicha.php?id=$idFicha&status=creada");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error al crear la ficha: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Nueva Ficha de Tratamiento - AgriManager</title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
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
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3282b8;
            outline: none;
        }
        
        /* Sección de grupos */
        .grup-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e9ecef;
        }
        
        .grup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .grup-header h3 {
            margin: 0;
            color: #3282b8;
        }
        
        .treballadors-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: white;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        .treballador-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px;
        }
        
        .treballador-checkbox input {
            width: auto;
        }
        
        .btn-afegir-grup {
            background: #17a2b8;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .btn-eliminar-grup {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
        }
        
        .btn-guardar {
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            width: 100%;
        }
        
        .sector-info-box {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .error-message {
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
    <h1>📝 Nueva Ficha de Tratamiento</h1>
    
    <?php if (isset($error)): ?>
        <div class="error-message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="formFicha">
        
        <!-- SECCIÓN 1: Información básica -->
        <div class="form-section">
            <h2>📍 Ubicación y Tipo</h2>
            
            <?php if ($sectorInfo): ?>
                <div class="sector-info-box">
                    <strong>Sector seleccionado:</strong> <?= htmlspecialchars($sectorInfo['nombre']) ?><br>
                    <strong>Parcela:</strong> <?= htmlspecialchars($sectorInfo['parcela_nombre']) ?> (<?= htmlspecialchars($sectorInfo['parcela_codigo']) ?>)<br>
                    <strong>Superficie:</strong> <?= number_format($sectorInfo['superficie_efectiva_ha'], 2) ?> ha
                    <input type="hidden" name="sector_id" value="<?= $sectorInfo['id'] ?>">
                </div>
            <?php else: ?>
                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="sector_id">Seleccionar Sector/Parcela *</label>
                        <select name="sector_id" id="sector_id" required>
                            <option value="">-- Selecciona un sector --</option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $sectorId == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['parcela_nombre'] ?? 'Sin parcela') ?> - 
                                    <?= htmlspecialchars($s['nombre']) ?> (<?= htmlspecialchars($s['codigo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="tipus_tractament">Tipo de Tratamiento *</label>
                    <select name="tipus_tractament" id="tipus_tractament" required>
                        <option value="">-- Selecciona --</option>
                        <option value="FITOSANITARI">🧪 Fitosanitario</option>
                        <option value="FERTILITZACIO">🌱 Fertilización</option>
                        <option value="PREPARACIO_SOL">🚜 Preparación de suelo</option>
                        <option value="SEMBRA">🌾 Siembra/Plantación</option>
                        <option value="PODA">✂️ Poda</option>
                        <option value="RECOLLECCIO">🧺 Recolección</option>
                        <option value="ALTRES">🔧 Otros</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="superficie_ha">Superficie (ha)</label>
                    <input type="number" name="superficie_ha" id="superficie_ha" 
                           step="0.0001" min="0"
                           value="<?= $sectorInfo['superficie_efectiva_ha'] ?? '' ?>"
                           placeholder="Ej: 0.5000">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="descripcio">Descripción del tratamiento *</label>
                    <textarea name="descripcio" id="descripcio" rows="3" required
                              placeholder="Describe el tratamiento a realizar..."></textarea>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN 2: Producto y dosis (solo para fitosanitarios/fertilización) -->
        <div class="form-section" id="seccionProducto" style="display: none;">
            <h2>🧪 Producto y Dosis</h2>
            <div class="form-row">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="producte_utilitzat">Producto a utilizar</label>
                    <select name="producte_utilitzat" id="producte_utilitzat">
                        <option value="">-- Selecciona producto --</option>
                        <?php foreach ($productes as $prod): ?>
                            <option value="<?= htmlspecialchars($prod['nombre_comercial']) ?>">
                                <?= htmlspecialchars($prod['nombre_comercial']) ?> 
                                (<?= htmlspecialchars($prod['tipo_producto']) ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="OTRO">Otro (especificar en observaciones)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="dosis_aplicada">Dosis aplicada</label>
                    <input type="number" name="dosis_aplicada" id="dosis_aplicada" 
                           step="0.01" min="0" placeholder="Cantidad">
                </div>
                <div class="form-group">
                    <label for="unitat_dosis">Unidad</label>
                    <select name="unitat_dosis" id="unitat_dosis">
                        <option value="">-- --</option>
                        <option value="L/ha">L/ha</option>
                        <option value="kg/ha">kg/ha</option>
                        <option value="ml/L">ml/L</option>
                        <option value="g/L">g/L</option>
                        <option value="L">Litros totales</option>
                        <option value="kg">Kg totales</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN 3: Fechas -->
        <div class="form-section">
            <h2>📅 Planificación</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="data_inici">Fecha de inicio prevista</label>
                    <input type="date" name="data_inici" id="data_inici" 
                           min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="data_fi_prevista">Fecha fin prevista</label>
                    <input type="date" name="data_fi_prevista" id="data_fi_prevista"
                           min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="id_supervisor">Supervisor responsable</label>
                    <select name="id_supervisor" id="id_supervisor">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach ($treballadors as $t): ?>
                            <option value="<?= $t['id_treballador'] ?>">
                                <?= htmlspecialchars($t['nom_complet']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="observacions">Observaciones generales</label>
                    <textarea name="observacions" id="observacions" rows="2"
                              placeholder="Notas adicionales..."></textarea>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN 4: Grupos de trabajo -->
        <div class="form-section">
            <h2>👥 Grupos de Trabajo</h2>
            <p style="color: #666; margin-bottom: 15px;">
                Crea grupos con nombre (ej: "Equipo A", "Podadores Norte", etc.) y asigna trabajadores.
            </p>
            
            <div id="contenedorGrups">
                <!-- Los grupos se añaden dinámicamente aquí -->
            </div>
            
            <button type="button" class="btn-afegir-grup" onclick="afegirGrup()">
                ➕ Añadir Grupo de Trabajo
            </button>
        </div>
        
        <button type="submit" class="btn-guardar">
            💾 Crear Ficha de Tratamiento
        </button>
        
    </form>
</div>

<script>
    // Mostrar/ocultar sección de producto según tipo
    document.getElementById('tipus_tractament').addEventListener('change', function() {
        const tipus = this.value;
        const seccionProducto = document.getElementById('seccionProducto');
        
        if (tipus === 'FITOSANITARI' || tipus === 'FERTILITZACIO') {
            seccionProducto.style.display = 'block';
        } else {
            seccionProducto.style.display = 'none';
        }
    });
    
    // Contador de grupos
    let numGrup = 0;
    
    function afegirGrup() {
        numGrup++;
        const contenedor = document.getElementById('contenedorGrups');
        
        const grupDiv = document.createElement('div');
        grupDiv.className = 'grup-container';
        grupDiv.id = `grup-${numGrup}`;
        grupDiv.innerHTML = `
            <div class="grup-header">
                <h3>Grupo #${numGrup}</h3>
                <button type="button" class="btn-eliminar-grup" onclick="eliminarGrup(${numGrup})">
                    🗑️ Eliminar
                </button>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre del grupo *</label>
                    <input type="text" name="grups[${numGrup}][nom]" required 
                           placeholder="Ej: Equipo A, Podadores Norte...">
                </div>
                <div class="form-group">
                    <label>Responsable del grupo</label>
                    <select name="grups[${numGrup}][responsable_id]">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach ($treballadors as $t): ?>
                            <option value="<?= $t['id_treballador'] ?>">
                                <?= htmlspecialchars($t['nom_complet']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Descripción del grupo</label>
                <input type="text" name="grups[${numGrup}][descripcio]" 
                       placeholder="Ej: Encargados de poda de formación...">
            </div>
            
            <div class="form-group">
                <label>Trabajadores asignados *</label>
                <div class="treballadors-selector">
                    <?php foreach ($treballadors as $t): ?>
                        <label class="treballador-checkbox">
                            <input type="checkbox" name="grups[${numGrup}][treballadors][]" 
                                   value="<?= $t['id_treballador'] ?>">
                            <?= htmlspecialchars($t['nom_complet']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        `;
        
        contenedor.appendChild(grupDiv);
    }
    
    function eliminarGrup(id) {
        const grup = document.getElementById(`grup-${id}`);
        grup.remove();
    }
    
    // Añadir primer grupo por defecto
    afegirGrup();
    
    // Validación antes de enviar
    document.getElementById('formFicha').addEventListener('submit', function(e) {
        const grups = document.querySelectorAll('.grup-container');
        if (grups.length === 0) {
            e.preventDefault();
            alert('Debes añadir al menos un grupo de trabajo');
            return false;
        }
        
        // Verificar que cada grupo tenga al menos un trabajador
        let valid = true;
        grups.forEach((grup, index) => {
            const checkboxes = grup.querySelectorAll('input[type="checkbox"]:checked');
            if (checkboxes.length === 0) {
                valid = false;
                alert(`El Grupo #${index + 1} debe tener al menos un trabajador asignado`);
            }
        });
        
        if (!valid) {
            e.preventDefault();
            return false;
        }
    });
</script>

</body>
</html>