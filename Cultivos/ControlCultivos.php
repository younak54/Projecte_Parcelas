<?php
require '../db.php';
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: index.php");
    exit;
}

try{
    $id = $_POST['id'] ?? '';
    $nombre_comun = $_POST['nombre'] ?? '';
    $nombre_cientifico = $_POST['cientifico'] ?? '';
    $familia = $_POST['familia'] ?? '';
    $categoria = $_POST['categoria'] ?? '';

    $sql = "INSERT INTO cultivos
                (id, nombre_comun, nombre_cientifico, familia, categoria)
            VALUES
                (:id, :nombre_comun, :nombre_cientifico, :familia, :categoria)
            ON DUPLICATE KEY UPDATE
                nombre_comun = VALUES(nombre_comun),
                nombre_cientifico = VALUES(nombre_cientifico),
                familia = VALUES(familia),
                categoria = VALUES(categoria)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':nombre_comun' => $nombre_comun,
        ':nombre_cientifico' => $nombre_cientifico,
        ':familia' => $familia,
        ':categoria' => $categoria
    ]);

    header("Location: index.php?status=success");
    exit;

} catch (PDOException $e) {
    echo "<h2>Error en la base de datos: </h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<h3>Datos enviados:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    exit;
}
?>