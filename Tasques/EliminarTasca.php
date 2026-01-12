<?php
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tasca = intval($_POST['id_tasca'] ?? 0);

    if ($id_tasca <= 0) {
        die("❌ Error: ID de tasca no vàlid");
    }

    try {
        // Començar transacció
        $pdo->beginTransaction();

        // 1. Eliminar assignacions associades
        $stmt = $pdo->prepare("DELETE FROM assignacions WHERE id_tasca = ?");
        $stmt->execute([$id_tasca]);

        // 2. Eliminar la tasca
        $stmt = $pdo->prepare("DELETE FROM tasques WHERE id_tasca = ?");
        $stmt->execute([$id_tasca]);

        // Confirmar transacció
        $pdo->commit();

        header("Location: index.php?status=deleted");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error eliminant tasca: " . $e->getMessage());
        die("❌ No s'ha pogut eliminar la tasca. Pot tenir registres dependents.");
    }
} else {
    header("Location: index.php");
    exit;
}