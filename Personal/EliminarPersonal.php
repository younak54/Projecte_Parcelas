<?php
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recibir id_treballador correctamente
    $id = intval($_POST['id_treballador'] ?? 0);

    if ($id <= 0) {
        die("Error: ID de trabajador inválido.");
    }

    try {
        // Eliminar el empleado
        $sql = "DELETE FROM treballadors WHERE id_treballador = :id_treballador";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_treballador' => $id]);

        header("Location: empleados.php?status=deleted");
        exit;

    } catch (PDOException $e) {
        echo "<h2>Error al borrar:</h2>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        exit;
    }

} else {
    header("Location: empleados.php");
    exit;
}