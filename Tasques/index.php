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
    <link rel="stylesheet" href="../menu.css">
</head>
<body>
<?php include '../menu.php'; ?>
    <div class="container">
        <h1 style="color: #0f4c75; margin-bottom: 30px;">📋 Crear Nova Tasca</h1>
        
        <?php 
        if(isset($_GET['status']) && $_GET['status'] == 'success') {
            echo "<div style='background:#d4edda;color:#155724;padding:15px;border-radius:5px;'>
                  ✅ Tasca creada i assignada correctament!</div>";
        }
        echo $missatge; 
        ?>
        
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="tipus_tasca">Tipus de Tasca *</label>
                    <input type="text" id="tipus_tasca" name="tipus_tasca" required 
                           placeholder="Ex: Fertilització, Podar, Collita...">
                </div>
                
                <div class="form-group">
                    <label for="id_sector">Sector *</label>
                    <select id="id_sector" name="id_sector" required>
                        <option value="">Selecciona un sector...</option>
                        <?php foreach($sectors as $sector): ?>
                            <option value="<?php echo $sector['id']; ?>">
                                <?php echo htmlspecialchars($sector['codigo'] . ' - ' . $sector['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="id_treballador">Assignar a Treballador *</label>
                    <select id="id_treballador" name="id_treballador" required>
                        <option value="">Selecciona un empleat...</option>
                        <?php foreach($treballadors as $treballador): ?>
                            <option value="<?php echo $treballador['id_treballador']; ?>">
                                <?php echo htmlspecialchars($treballador['nom_complet']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="data_inici_finestra">Data Inici Finestra *</label>
                    <input type="date" id="data_inici_finestra" name="data_inici_finestra" required 
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="data_final_finestra">Data Final Finestra *</label>
                    <input type="date" id="data_final_finestra" name="data_final_finestra" required 
                           value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
                
                <div class="form-group">
                    <label for="prioritat">Prioritat *</label>
                    <select id="prioritat" name="prioritat" required>
                        <option value="MITJA">Mitjana</option>
                        <option value="ALTA">Alta</option>
                        <option value="URGENT">Urgent</option>
                        <option value="BAIXA">Baixa</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label for="descripcio">Descripció Detallada</label>
                <textarea id="descripcio" name="descripcio" 
                          placeholder="Descripció de la tasca, requisits, observacions..."></textarea>
            </div>
            
            <div style="margin-top: 25px; text-align: center;">
                <button type="submit" name="crear_tasca">✅ Crear i Assignar Tasca</button>
            </div>
        </form>

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
            <form action="/pro/Tasques/TasquesActuals.php" method="get" style="display:inline;">
                <button type="submit" class="btn_tasca">
                    Tasques Actuals
                </button>
            </form>
        </div>
    </div>
</body>
</html>