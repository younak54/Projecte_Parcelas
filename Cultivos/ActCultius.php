<?php
require "../db.php";

//Comprobar que arribi lal ID
if (!isset($_GET['id'])){
    die("Cultiu no especificat.");
}

$id = $_GET['id'];

//Obtenir el cultiu
$stmt = $pdo->prepare("SELECT * FROM cultivos WHERE id = ?");
$stmt->execute([$id]);
$cultius = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cultius) {
    die("Cultius no trobat");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ Editar Cultius: <?= htmlspecialchars($cultius['nombre_comun']) ?></title>
    <link rel="stylesheet" href="../css/parcela.css">
</head>
<body>
    <h1>✏️ Editar Cultius:</h1>

    <form id="form-principal" method="POST" action="ControlCultivos.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $cultius['id'] ?>">

        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($cultius['nombre_comun']) ?>">

        <label>Nombre Cientifico:</label>
        <input type="text" name="cientifico" value="<?= htmlspecialchars($cultius['nombre_cientifico']) ?>">

        <label>Familia:</label>
        <input type="text" name="familia" value="<?= htmlspecialchars($cultius['familia']) ?>">

        <label>Categoria:</label>
        <input type="text" name="categoria" value="<?= htmlspecialchars($cultius['categoria']) ?>">

        <button type="submit">💾 Actualizar Cultiu </button>

    </form>

</body>
</html>