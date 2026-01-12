<?php
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0){
        die("Erro: ID invalid");
    }

    try {
        $sql = "DELETE FROM parcelas WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        header("Location: index.php?status=deleted");
        exit;
    } catch (PDOException $e){
        echo "<h2>Error al borrar:</h2>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        exit;
    }

} else {
    header("Location: index.php");
    exit;
}