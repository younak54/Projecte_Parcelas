<?php
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idFicha = $_POST['id_ficha'];
    $nouEstat = $_POST['nou_estat'];
    
    $dataFiReal = null;
    if ($nouEstat === 'COMPLETAT') {
        $dataFiReal = date('Y-m-d');
    }
    
    $stmt = $pdo->prepare("
        UPDATE fichas_tractament 
        SET estat = ?, 
            data_fi_real = COALESCE(?, data_fi_real),
            updated_at = NOW()
        WHERE id_ficha = ?
    ");
    $stmt->execute([$nouEstat, $dataFiReal, $idFicha]);
    
    header("Location: VeureFicha.php?id=$idFicha&status=actualitzat");
    exit;
}