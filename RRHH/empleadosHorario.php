<?php
require '../db.php';

//  ilstado de epmleados
$stmt = $pdo->query("SELECT 
                        t.id_treballador as 'id',
                        t.nom_complet AS 'Empleado',
                        t.estat_actiu as 'actiu',
                        h.nom_horari AS 'Turno',
                        h.hores_entrada AS 'Entrada',
                        h.hores_sortida AS 'Salida',
                        h.durada_pausa AS 'Pausa'
                        FROM treballadors t
                        LEFT JOIN horaris h ON t.id_horari = h.id_horari");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios</title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
</head>
<body>
<?php include '../menu.php'; ?>
<link rel="stylesheet" href="../menu.css">
    
    <h1>📅 Horarios</h1>

    <!-- MENSAJE DE ESTADO -->
    <?php if (isset($_GET['status'])): ?>
        <div class="status-message">
            <?php 
            switch($_GET['status']){
                case 'success': echo '✅ Empleado creado correctamente'; break;
                case 'updated': echo '✅ Empleado actualizado correctamente'; break;
                case 'deleted': echo '⚠️ Empleado desactivado (borrado logico)'; break;
                case 'error': echo '❌ Error: ' . htmlspecialchars($_GET['message']); break;
            }
            ?>
        </div>
    <?php endif; ?>

    <h2>👥 Empleados</h2>
    <div class="cards-container">
        <?php foreach ($empleados as $emp): ?>
            <div class="card">

                <h3><?= htmlspecialchars($emp['Empleado']) ?></h3>
                <div class="card-section">
                    <p><strong>🆔 Código:</strong> <?= htmlspecialchars($emp['id']) ?></p>
                    <p><strong>🕒 Turno:</strong> <?= htmlspecialchars($emp['Turno']) ?></p>
                    <p><strong>➡️ Entrada:</strong> <?= htmlspecialchars($emp['Entrada']) ?></p>
                </div>

                <div class="card-section">
                    <p><strong>⬅️ Salida:</strong> <?= htmlspecialchars($emp['Salida']) ?></p>
                    <p><strong>⏸️ Pausa:</strong> <?= htmlspecialchars($emp['Pausa']) ?></p>
                    <p><strong>Actiu:</strong> <?= htmlspecialchars($emp['actiu'] == 1 ? '🟢 Activo' : '🔴 Inactivo') ?></p>
                </div>

                <div class="card-section">
                    <form method="POST" action="../Personal/ControlPersonal.php"
                        onsubmit="return confirm('⚠️ ¿Estas seguro de desactivar este empleado?');">
                        <input type="hidden" name="delete:id" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn-borrar">🗑️ Borrar</button>
                    </form>

                    <form method="GET" action="ActHoraris.php">
                        <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn-actualizar">✏️ Actualizar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>