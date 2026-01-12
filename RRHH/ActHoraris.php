<?php
require '../db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("❌ ID inválido");

// SELECT para obtener los datos
$stmt = $pdo->prepare("SELECT 
                        t.id_treballador as id,
                        t.nom_complet AS Empleado,
                        t.estat_actiu as actiu,
                        t.id_horari,
                        h.nom_horari AS Turno,
                        h.hores_entrada AS Entrada,
                        h.hores_sortida AS Salida,
                        h.durada_pausa AS Pausa
                        FROM treballadors t
                        LEFT JOIN horaris h ON t.id_horari = h.id_horari
                        WHERE t.id_treballador = ?");
$stmt->execute([$id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) die("❌ Registro no encontrado");

// ====================
// PROCESAR POST
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Actualizar trabajadors
    $stmt1 = $pdo->prepare("UPDATE treballadors SET 
                                nom_complet = ?, 
                                estat_actiu = ?, 
                                id_horari = ? 
                            WHERE id_treballador = ?");
    $stmt1->execute([
        $_POST['Empleado'],
        isset($_POST['actiu']) ? 1 : 0,
        $_POST['id_horari'],
        $id
    ]);

    // Actualizar horaris
    $stmt2 = $pdo->prepare("UPDATE horaris SET 
                                nom_horari = ?, 
                                hores_entrada = ?, 
                                hores_sortida = ?, 
                                durada_pausa = ? 
                            WHERE id_horari = ?");
    $stmt2->execute([
        $_POST['Turno'],
        $_POST['Entrada'],
        $_POST['Salida'],
        $_POST['Pausa'],
        $_POST['id_horari']
    ]);

    // Redirigir sin imprimir nada antes
    header("Location: empleadosHorario.php?status=updated");
    exit;
}

// ====================
// FORMULARIO (solo se renderiza si NO es POST)
// ====================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../css/personal.css">
</head>
<body>
<form id="form-principal" method="POST">
    <h2>Editar Trabajador</h2>
    <label>Empleado:</label>
    <input type="text" name="Empleado" value="<?= htmlspecialchars($registro['Empleado']) ?>"><br><br>

    <label>Activo:</label>
    <input type="checkbox" name="actiu" <?= $registro['actiu'] ? "checked" : "" ?>><br><br>
    
    <label>Turno:</label>
    <input type="text" name="Turno" value="<?= htmlspecialchars($registro['Turno']) ?>"><br><br>

    <label>Entrada:</label>
    <input type="time" name="Entrada" value="<?= $registro['Entrada'] ?>"><br><br>

    <label>Salida:</label>
    <input type="time" name="Salida" value="<?= $registro['Salida'] ?>"><br><br>

    <label>Pausa:</label>
    <input type="number" step="0.25" name="Pausa" value="<?= $registro['Pausa'] ?>"><br><br>

    <input type="hidden" name="id_horari" value="<?= $registro['id_horari'] ?>">

    <button type="submit">💾 Guardar cambios</button>
</form>
</body>
</html>