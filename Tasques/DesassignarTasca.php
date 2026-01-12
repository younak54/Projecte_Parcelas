<?php
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_assignacio = intval($_POST['id_assignacio'] ?? 0);

    if ($id_assignacio <= 0) {
        die("❌ Error: ID d'assignació no vàlid");
    }

    try {
        // Comprovar que l'assignació existeix
        $stmt = $pdo->prepare("SELECT id_assignacio FROM assignacions WHERE id_assignacio = ?");
        $stmt->execute([$id_assignacio]);
        
        if ($stmt->rowCount() === 0) {
            die("⚠️ Aquesta assignació ja no existeix o ja s'ha eliminat");
        }

        // Eliminar l'assignació
        $sql = "DELETE FROM assignacions WHERE id_assignacio = :id_assignacio";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_assignacio' => $id_assignacio]);

        // Comprovar que s'ha eliminat
        if ($stmt->rowCount() > 0) {
            header("Location: index.php?status=unassigned");
        } else {
            die("⚠️ No s'ha pogut eliminar l'assignació");
        }
        exit;
        
    } catch (PDOException $e) {
        error_log("Error eliminant assignació: " . $e->getMessage());
        die("❌ Error del sistema. Contacta amb l'administrador.");
    }
} else {
    header("Location: index.php");
    exit;
}