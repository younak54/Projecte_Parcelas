<?php
require "../db.php";
$missatge = '';

// Comprovar que es passa id_tasca (NO id_assignacio)
if (!isset($_GET['id_tasca'])){
    die("Error: ID de tasca no vàlid");
}

$id_tasca = $_GET['id_tasca'];

// Obtenir les dades de la TASCA
$stmt = $pdo->prepare("
    SELECT t.*, a.id_treballador, a.id_assignacio 
    FROM tasques t
    JOIN assignacions a ON t.id_tasca = a.id_tasca
    WHERE t.id_tasca = ?
");
$stmt->execute([$id_tasca]);
$tasca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tasca) {
    die("Tasca no trobada");
}

// Processar l'actualització
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualitzar_tasca'])) {
    try {
        // Actualitzar tasca
        $stmt = $pdo->prepare("
            UPDATE tasques 
            SET tipus_tasca = ?, descripcio = ?, id_sector = ?, 
                data_inici_finestra = ?, data_final_finestra = ?, prioritat = ?
            WHERE id_tasca = ?
        ");
        $stmt->execute([
            $_POST['tipus_tasca'],
            $_POST['descripcio'],
            $_POST['id_sector'],
            $_POST['data_inici_finestra'],
            $_POST['data_final_finestra'],
            $_POST['prioritat'],
            $id_tasca
        ]);
        
        // Actualitzar assignació (treballador)
        $stmt = $pdo->prepare("
            UPDATE assignacions 
            SET id_treballador = ?
            WHERE id_assignacio = ?
        ");
        $stmt->execute([
            $_POST['id_treballador'],
            $tasca['id_assignacio']
        ]);
        
        header("Location: index.php?status=updated");
        exit;
    } catch(PDOException $e) {
        $missatge = "<div style='background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;'>
                     ❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$sectors = $pdo->query("SELECT id, codigo, nombre FROM sectores_cultivo WHERE activo = 1")->fetchAll();
$treballadors = $pdo->query("SELECT id_treballador, nom_complet FROM treballadors WHERE estat_actiu = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Editar Tasca</title>
    <link rel="stylesheet" href="../css/tasques.css">
</head>
<body>
    <div class="container">
        <h1 style="color: #0f4c75; margin-bottom: 30px;">✏️ Editar Tasca</h1>
        
        <?php echo $missatge; ?>
        
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="tipus_tasca">Tipus de Tasca *</label>
                    <input type="text" id="tipus_tasca" name="tipus_tasca" required 
                           value="<?= htmlspecialchars($tasca['tipus_tasca']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="id_sector">Sector *</label>
                    <select id="id_sector" name="id_sector" required>
                        <?php foreach($sectors as $sector): ?>
                            <option value="<?php echo $sector['id']; ?>" 
                                <?= $sector['id'] == $tasca['id_sector'] ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($sector['codigo'] . ' - ' . $sector['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="id_treballador">Assignar a Treballador *</label>
                    <select id="id_treballador" name="id_treballador" required>
                        <?php foreach($treballadors as $treballador): ?>
                            <option value="<?php echo $treballador['id_treballador']; ?>" 
                                <?= $treballador['id_treballador'] == $tasca['id_treballador'] ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($treballador['nom_complet']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="data_inici_finestra">Data Inici Finestra *</label>
                    <input type="date" id="data_inici_finestra" name="data_inici_finestra" required 
                           value="<?= date('Y-m-d', strtotime($tasca['data_inici_finestra'])) ?>">
                </div>
                
                <div class="form-group">
                    <label for="data_final_finestra">Data Final Finestra *</label>
                    <input type="date" id="data_final_finestra" name="data_final_finestra" required 
                           value="<?= date('Y-m-d', strtotime($tasca['data_final_finestra'])) ?>">
                </div>
                
                <div class="form-group">
                    <label for="prioritat">Prioritat *</label>
                    <select id="prioritat" name="prioritat" required>
                        <?php 
                        $opcions = ['BAIXA', 'MITJA', 'ALTA', 'URGENT'];
                        foreach($opcions as $opcio): ?>
                            <option value="<?= $opcio ?>" <?= $tasca['prioritat'] == $opcio ? 'selected' : '' ?>>
                                <?= $opcio ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label for="descripcio">Descripció Detallada</label>
                <textarea id="descripcio" name="descripcio"><?= htmlspecialchars($tasca['descripcio']) ?></textarea>
            </div>
            
            <div style="margin-top: 25px; text-align: center;">
                <button type="submit" name="actualitzar_tasca">💾 Actualitzar Tasca</button>
                <a href="index.php" class="btn-cancelar">❌ Cancel·lar</a>
            </div>
        </form>
    </div>
</body>
</html>