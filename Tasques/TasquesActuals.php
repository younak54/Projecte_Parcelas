<?php
require '../db.php';
$missatge = '';

// Processar formulari
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_tasca'])) {
    try {
        // 1. Insertar la tasca
        $stmt = $pdo->prepare("
            INSERT INTO tasques (tipus_tasca, descripcio, id_sector, data_inici_finestra, 
                                 data_final_finestra, prioritat, estat)
            VALUES (?, ?, ?, ?, ?, ?, 'PENDENT')
        ");
        $stmt->execute([
            $_POST['tipus_tasca'],
            $_POST['descripcio'],
            $_POST['id_sector'],
            $_POST['data_inici_finestra'],
            $_POST['data_final_finestra'],
            $_POST['prioritat']
        ]);
        
        $id_tasca = $pdo->lastInsertId();
        
        // 2. Assignar al treballador
        $stmt = $pdo->prepare("
            INSERT INTO assignacions (id_tasca, id_treballador, data_assignacio, estat)
            VALUES (?, ?, CURDATE(), 'PENDENT')
        ");
        $stmt->execute([
            $id_tasca,
            $_POST['id_treballador']
        ]);
        
        header("Location: index.php?status=success");
        exit;
    } catch(PDOException $e) {
        $missatge = "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;'>
                     ❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Obtenir dades per al formulari
$sectors = $pdo->query("SELECT id, codigo, nombre FROM sectores_cultivo WHERE activo = 1")->fetchAll();
$treballadors = $pdo->query("SELECT id_treballador, nom_complet FROM treballadors WHERE estat_actiu = 1")->fetchAll();

// Obtenir tasques existents amb id_assignacio
$stmt = $pdo->query("
    SELECT t.id_tasca, t.tipus_tasca, t.descripcio, t.prioritat, t.estat,
           s.codigo as sector_codigo, tr.nom_complet as treballador,
           a.data_assignacio, a.id_assignacio
    FROM tasques t
    JOIN assignacions a ON t.id_tasca = a.id_tasca
    JOIN treballadors tr ON a.id_treballador = tr.id_treballador
    JOIN sectores_cultivo s ON t.id_sector = s.id
    WHERE t.estat != 'FINALITZADA'
    ORDER BY FIELD(t.prioritat, 'URGENT', 'ALTA', 'MITJA', 'BAIXA'), t.data_final_finestra ASC
");
$tasques = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Gestió de Tasques</title>
    <link rel="stylesheet" href="../css/tasques.css">
</head>
<body>
    <div class="container">
        <!-- TAULES EXISTENTS -->
        <div class="taula-tasques">
            <h2 style="color: #0f4c75; margin-bottom: 20px;">📊 Tasques Actuals</h2>
            <?php if(count($tasques) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tasca</th>
                            <th>Sector</th>
                            <th>Treballador</th>
                            <th>Prioritat</th>
                            <th>Estat</th>
                            <th>Data Assignació</th>
                            <th>Accions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tasques as $tasca): ?>
                            <tr>
                                <td><?php echo $tasca['id_tasca']; ?></td>
                                <td><?php echo htmlspecialchars($tasca['tipus_tasca']); ?></td>
                                <td><?php echo htmlspecialchars($tasca['sector_codigo']); ?></td>
                                <td><?php echo htmlspecialchars($tasca['treballador']); ?></td>
                                <td>
                                    <span class="urgencia-badge <?php echo $tasca['prioritat']; ?>">
                                        <?php echo $tasca['prioritat']; ?>
                                    </span>
                                </td>
                                <td><?php echo $tasca['estat']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($tasca['data_assignacio'])); ?></td>
                                <td style="white-space: nowrap;">
                                    <!-- 🔽 AQUEST ÉS EL FORMULARI CORRECTE -->
                                    <form method="GET" action="EditarTasca.php" style="display:inline;">    
                                        <input type="hidden" name="id_tasca" value="<?= htmlspecialchars($tasca['id_tasca']) ?>">
                                        <button type="submit" class="btn_tasca">✏️ Editar</button>
                                    </form>

                                    <form method="POST" action="DesassignarTasca.php" style="display:inline;">
                                        <input type="hidden" name="id_assignacio" value="<?= $tasca['id_assignacio'] ?>">
                                        <button type="submit" class="btn_tasca">👤 Desassignar</button>
                                    </form>

                                    <!-- Si vols ELIMINAR TOTALMENT -->
                                    <form method="POST" action="EliminarTasca.php" style="display:inline;" 
                                        onsubmit="return confirm('⚠️ ELIMINARÀS LA TASCA SENCERA. Segur?');">
                                        <input type="hidden" name="id_tasca" value="<?= $tasca['id_tasca'] ?>">
                                        <button type="submit" class="btn_tasca">🗑️ Eliminar</button>
                                    </form>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align:center; color:#666;">No hi ha tasques pendents</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>