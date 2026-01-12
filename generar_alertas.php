<?php
// generar_alertas.php - Generació automàtica d'alertes
require 'db.php';

function crearAlerta($pdo, $datos) {
    try {
        $sql = "INSERT INTO alertes 
                (id_treballador, id_referencia, taula_referencia, tipus_alerta, 
                 data_generacio, data_venciment, missatge, urgencia)
                SELECT ?, ?, ?, ?, ?, ?, ?, ?
                WHERE NOT EXISTS (
                    SELECT 1 FROM alertes 
                    WHERE taula_referencia = ? 
                    AND id_referencia = ? 
                    AND resolta = 0
                    AND tipus_alerta = ?
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $datos['id_treballador'], $datos['id_referencia'], $datos['taula_referencia'], 
            $datos['tipus_alerta'], $datos['data_generacio'], $datos['data_venciment'], 
            $datos['missatge'], $datos['urgencia'],
            $datos['taula_referencia'], $datos['id_referencia'], $datos['tipus_alerta']
        ]);
        
        return $stmt->rowCount();
    } catch(PDOException $e) {
        error_log("Error creant alerta: " . $e->getMessage());
        return 0;
    }
}

$totalAlertes = 0;

// Generar alertes de documents
$stmt = $pdo->query("
    SELECT id_document, id_treballador, tipus_document, data_venciment 
    FROM documentacio 
    WHERE data_venciment BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY
");
foreach($stmt->fetchAll() as $doc) {
    $dias = ceil((strtotime($doc['data_venciment']) - time()) / 86400);
    $urgencia = $dias <= 3 ? 'CRITICA' : ($dias <= 7 ? 'ALTA' : 'MITJA');
    
    $totalAlertes += crearAlerta($pdo, [
        'id_treballador' => $doc['id_treballador'],
        'id_referencia' => $doc['id_document'],
        'taula_referencia' => 'documentacio',
        'tipus_alerta' => 'VENCIMENT_DOCUMENT',
        'data_generacio' => date('Y-m-d'),
        'data_venciment' => $doc['data_venciment'],
        'missatge' => "Document '{$doc['tipus_document']}' vence en $dias dies",
        'urgencia' => $urgencia
    ]);
}

// Generar alertes de contractes
$stmt = $pdo->query("
    SELECT id_contracte, id_treballador, tipus_contracte, data_final 
    FROM contractes 
    WHERE data_final BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY
      AND estat = 'ACTIU'
");
foreach($stmt->fetchAll() as $contracte) {
    $dias = ceil((strtotime($contracte['data_final']) - time()) / 86400);
    $urgencia = $dias <= 7 ? 'ALTA' : 'MITJA';
    
    $totalAlertes += crearAlerta($pdo, [
        'id_treballador' => $contracte['id_treballador'],
        'id_referencia' => $contracte['id_contracte'],
        'taula_referencia' => 'contractes',
        'tipus_alerta' => 'VENCIMENT_CONTRAT',
        'data_generacio' => date('Y-m-d'),
        'data_venciment' => $contracte['data_final'],
        'missatge' => "Contracte {$contracte['tipus_contracte']} vence en $dias dies",
        'urgencia' => $urgencia
    ]);
}

// Generar alertes d'estoc
$stmt = $pdo->query("
    SELECT s.id, h.nombre_comercial, s.cantidad_actual, s.stock_minimo 
    FROM stock_herbicidas s
    JOIN herbicidas h ON s.herbicida_id = h.id
    WHERE s.cantidad_actual <= s.stock_minimo * 1.2
");
foreach($stmt->fetchAll() as $stock) {
    $totalAlertes += crearAlerta($pdo, [
        'id_treballador' => null,
        'id_referencia' => $stock['id'],
        'taula_referencia' => 'stock_herbicidas',
        'tipus_alerta' => 'ESTOC_MINIM',
        'data_generacio' => date('Y-m-d'),
        'data_venciment' => date('Y-m-d', strtotime('+7 days')),
        'missatge' => "Stock baix: {$stock['nombre_comercial']} ({$stock['cantidad_actual']} uds)",
        'urgencia' => $stock['cantidad_actual'] <= $stock['stock_minimo'] ? 'ALTA' : 'MITJA'
    ]);
}
?>