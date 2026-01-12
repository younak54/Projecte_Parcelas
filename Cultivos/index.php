<?php
require '../db.php';

// Obtener sectores activos con variedad actual
$sectores = $pdo->query("SELECT id, nombre_comun, nombre_cientifico, familia, categoria from
                            cultivos c")->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🌱 Alta de Cultivos</title>
    <link rel="stylesheet" href="../css/parcela.css">
</head>
<body>
    <h1>🌱 Alta de Cultivos</h1>

    <!-- MENSAJES DE ESTADO -->
    <?php if (isset($_GET['status'])): ?>
        <div class="status-message">
            <?php 
            switch($_GET['status']) {
                case 'success': echo '✅ Cultivo creado correctamente'; break;
                case 'updated': echo '✅ Cultivo actualizado correctamente'; break;
                case 'deleted': echo '⚠️ Cultivo desactivado (borrado lógico)'; break;
                case 'error': echo '❌ Error: ' . htmlspecialchars($_GET['message']); break;
            }
            ?>
        </div>
    <?php endif; ?>

    <h2>➕ Nuevo Cultivo</h2>
    <form id="form-principal" method="POST" action="ControlCultivos.php" enctype="multipart/form-data">
        
        <h3>Cultivos</h3>
        <label>ID:</label>
        <input type="text" name="id" required placeholder="001" maxlength="5">
        
        <label>Nombre:</label>
        <input type="text" name="nombre" required placeholder="Manzanos" maxlength="20">
        
        <label>Nombre Cientifico:</label>
        <input type="text" name="cientifico" placeholder="Malus domestica" maxlength="100">
        
        <label>Familia:</label>
        <input type="text" name="familia" placeholder="Rosaceae" maxlength="100">
        
        <label>Categoria:</label>
        <input type="text" name="categoria" placeholder="Fruta de pepita" maxlength="100">

        <button type="submit">💾 Guardar Parcela</button>
    </form>

    <h2>📦 Cultivos Registrados</h2>
    <div class="cards-container">
        <?php foreach ($sectores as $par): ?>
            <div class="card">
                <h3>ID: <?= htmlspecialchars($par['id']) ?></h3>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($par['nombre_comun']) ?></p>
                <p><strong>Nombre Cientifico:</strong> <?= $par['nombre_cientifico'] ?></p>
                
                <div class="card-section">
                    <p><strong>Familia:</strong> <?= $par['familia'] ?></p>
                    <p><strong>Categoria:</strong> <?= $par['categoria'] ?></p>
                </div>

                <div class="card-actions">
                    <form method="POST" action="EliminarCultivos.php" style="display:inline;" 
                          onsubmit="return confirm('⚠️ ¿Desactivar parcela?');">
                        <input type="hidden" name="id" value="<?= $par['id'] ?>">
                        <button type="submit" class="btn-borrar">🗑️ Borrar</button>
                    </form>
                    
                    <form method="GET" action="ActCultius.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $par['id'] ?>">
                        <button type="submit" class="btn-actualizar">✏️ Editar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
