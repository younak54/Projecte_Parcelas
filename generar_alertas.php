<?php
require 'db.php';

// Función mejorada para gestionar alertas con actualización automática
function gestionarAlertas($pdo, $tipoAlerta, $datos, $esSistema = false) {
    $tabla = $esSistema ? 'alertes_sistema' : 'alertes';

    try {
        // Primero, verificar si ya existe una alerta para este elemento
        $whereClause = "taula_referencia = ? AND id_referencia = ? AND tipus_alerta = ? AND resolta = 0";
        $params = [$datos['taula_referencia'], $datos['id_referencia'], $tipoAlerta];

        if (!$esSistema && isset($datos['id_treballador'])) {
            $whereClause .= " AND id_treballador = ?";
            $params[] = $datos['id_treballador'];
        }

        $stmt_check = $pdo->prepare("SELECT id_alerta, data_venciment, missatge FROM $tabla WHERE $whereClause");
        $stmt_check->execute($params);
        $alerta_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($alerta_existente) {
            // Si existe, verificar si necesita actualización
            $necesita_actualizar = false;

            // Comparar fecha de vencimiento
            if ($alerta_existente['data_venciment'] != $datos['data_venciment']) {
                $necesita_actualizar = true;
            }

            // Comparar mensaje (puede cambiar por días restantes, etc.)
            if ($alerta_existente['missatge'] != $datos['missatge']) {
                $necesita_actualizar = true;
            }

            if ($necesita_actualizar) {
                // Actualizar alerta existente
                $update_sql = "UPDATE $tabla SET missatge = ?, data_venciment = ?, urgencia = ?, data_actualitzacio = NOW() WHERE id_alerta = ?";
                $stmt_update = $pdo->prepare($update_sql);
                $stmt_update->execute([
                    $datos['missatge'],
                    $datos['data_venciment'],
                    $datos['urgencia'],
                    $alerta_existente['id_alerta']
                ]);
                return 'actualizada';
            }

            return 'existente'; // No necesita cambios
        } else {
            // Crear nueva alerta
            return crearAlerta($pdo, array_merge($datos, ['tipus_alerta' => $tipoAlerta]), $esSistema) > 0 ? 'creada' : 'error';
        }
    } catch(PDOException $e) {
        error_log("Error gestionando alerta $tipoAlerta: " . $e->getMessage());
        return 'error';
    }
}

// Función para limpiar alertas obsoletas
function limpiarAlertasObsoletas($pdo, $esSistema = false) {
    $tabla = $esSistema ? 'alertes_sistema' : 'alertes';
    $eliminadas = 0;

    try {
        // Documentos que ya no están próximos a caducar
        if (!$esSistema) {
            $stmt = $pdo->prepare("
                DELETE a FROM $tabla a
                INNER JOIN documentacio d ON a.taula_referencia = 'documentacio' AND a.id_referencia = d.id_document
                WHERE a.tipus_alerta = 'VENCIMENT_DOCUMENT'
                AND d.data_venciment > CURDATE() + INTERVAL 30 DAY
            ");
            $stmt->execute();
            $eliminadas += $stmt->rowCount();

            // Contratos que ya no están próximos a caducar
            $stmt = $pdo->prepare("
                DELETE a FROM $tabla a
                INNER JOIN contractes c ON a.taula_referencia = 'contractes' AND a.id_referencia = c.id_contracte
                WHERE a.tipus_alerta = 'VENCIMENT_CONTRAT'
                AND (c.data_final > CURDATE() + INTERVAL 60 DAY OR c.estat != 'ACTIU')
            ");
            $stmt->execute();
            $eliminadas += $stmt->rowCount();

            // Certificaciones que ya no están próximas a caducar
            $stmt = $pdo->prepare("
                DELETE a FROM $tabla a
                INNER JOIN certificacions cert ON a.taula_referencia = 'certificacions' AND a.id_referencia = cert.id_certificacio
                WHERE a.tipus_alerta = 'VENCIMENT_CERTIFICACIO'
                AND cert.data_caducitat > CURDATE() + INTERVAL 45 DAY
            ");
            $stmt->execute();
            $eliminadas += $stmt->rowCount();
        }

        // Stock que ya no está bajo mínimo
        if ($esSistema) {
            $stmt = $pdo->prepare("
                DELETE a FROM $tabla a
                INNER JOIN stock_herbicidas s ON a.taula_referencia = 'stock_herbicidas' AND a.id_referencia = s.id
                WHERE a.tipus_alerta = 'ESTOC_MINIM'
                AND s.cantidad_actual > s.stock_minimo * 1.2
            ");
            $stmt->execute();
            $eliminadas += $stmt->rowCount();

            // Lotes que ya no están próximos a caducar
            $stmt = $pdo->prepare("
                DELETE a FROM $tabla a
                INNER JOIN lotes_herbicidas lh ON a.taula_referencia = 'lotes_herbicidas' AND a.id_referencia = lh.id
                WHERE a.tipus_alerta = 'VENCIMENT_PRODUCTE'
                AND (lh.fecha_caducidad > CURDATE() + INTERVAL 90 DAY OR lh.cantidad_actual = 0 OR lh.activo = 0)
            ");
            $stmt->execute();
            $eliminadas += $stmt->rowCount();

            // Plagas que ya no requieren intervención
            $stmt = $pdo->prepare("
                DELETE a FROM $tabla a
                INNER JOIN monitoratge_plagues mp ON a.taula_referencia = 'monitoratge_plagues' AND a.id_referencia = mp.id
                WHERE a.tipus_alerta = 'PLAGA_DETECTADA'
                AND (mp.nivell_incidencia NOT IN ('ALT', 'CRITIC') OR mp.llindar_intervencio = 0)
            ");
            $stmt->execute();
            $eliminadas += $stmt->rowCount();
        }

        return $eliminadas;
    } catch(PDOException $e) {
        error_log("Error limpiando alertas obsoletas: " . $e->getMessage());
        return 0;
    }
}

function crearAlerta($pdo, $datos, $esSistema = false) {
    try {
        if ($esSistema) {
            // Guardar en alertes_sistema
            $sql = "INSERT INTO alertes_sistema
                    (tipus_alerta, id_referencia, taula_referencia, missatge,
                     data_generacio, data_venciment, urgencia, resolta)
                    VALUES (?, ?, ?, ?, NOW(), ?, ?, 0)";

            $params = [
                $datos['tipus_alerta'],
                $datos['id_referencia'],
                $datos['taula_referencia'],
                $datos['missatge'],
                $datos['data_venciment'],
                $datos['urgencia']
            ];
        } else {
            // Guardar en alertes (con trabajador)
            $sql = "INSERT INTO alertes
                    (id_treballador, id_referencia, taula_referencia, tipus_alerta,
                     data_generacio, data_venciment, missatge, urgencia, resolta)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";

            $params = [
                $datos['id_treballador'] ?? null,
                $datos['id_referencia'],
                $datos['taula_referencia'],
                $datos['tipus_alerta'],
                $datos['data_generacio'],
                $datos['data_venciment'],
                $datos['missatge'],
                $datos['urgencia']
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    } catch(PDOException $e) {
        error_log("Error creant alerta: " . $e->getMessage());
        return 0;
    }
}

$totalAlertes = 0;
$log = [];
$alertas_creadas = 0;
$alertas_actualizadas = 0;
$alertas_eliminadas = 0;

// Limpiar alertas obsoletas primero
$alertas_eliminadas += limpiarAlertasObsoletas($pdo, false); // Alertas de trabajadores
$alertas_eliminadas += limpiarAlertasObsoletas($pdo, true);  // Alertas de sistema

try {
    $stmt = $pdo->query("
        SELECT d.id_document, d.id_treballador, d.tipus_document, d.data_venciment
        FROM documentacio d
        WHERE d.data_venciment BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY
    ");
    foreach($stmt->fetchAll() as $doc) {
        $dias = ceil((strtotime($doc['data_venciment']) - time()) / 86400);
        $urgencia = $dias <= 3 ? 'CRITICA' : ($dias <= 7 ? 'ALTA' : 'MITJA');

        $resultado = gestionarAlertas($pdo, 'VENCIMENT_DOCUMENT', [
            'id_treballador' => $doc['id_treballador'],
            'id_referencia' => $doc['id_document'],
            'taula_referencia' => 'documentacio',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => $doc['data_venciment'],
            'missatge' => "Document '{$doc['tipus_document']}' vence en $dias dies",
            'urgencia' => $urgencia
        ], false);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
        $totalAlertes++;
    }
    $log[] = "Documents: OK (" . $stmt->rowCount() . " verificats, $alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Documents ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT c.id_contracte, c.id_treballador, c.tipus_contracte, c.data_final 
        FROM contractes c
        WHERE c.data_final BETWEEN CURDATE() AND CURDATE() + INTERVAL 60 DAY
          AND c.estat = 'ACTIU'
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $contracte) {
        $dias = ceil((strtotime($contracte['data_final']) - time()) / 86400);
        $urgencia = $dias <= 7 ? 'ALTA' : 'MITJA';

        $resultado = gestionarAlertas($pdo, 'VENCIMENT_CONTRAT', [
            'id_treballador' => $contracte['id_treballador'],
            'id_referencia' => $contracte['id_contracte'],
            'taula_referencia' => 'contractes',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => $contracte['data_final'],
            'missatge' => "Contracte {$contracte['tipus_contracte']} vence en $dias dies",
            'urgencia' => $urgencia
        ], false);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Contractes: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Contractes ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT c.id_certificacio, c.id_treballador, c.tipus_certificacio, c.data_caducitat 
        FROM certificacions c
        WHERE c.data_caducitat BETWEEN CURDATE() AND CURDATE() + INTERVAL 45 DAY
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $cert) {
        $dias = ceil((strtotime($cert['data_caducitat']) - time()) / 86400);
        $urgencia = $dias <= 5 ? 'CRITICA' : ($dias <= 15 ? 'ALTA' : 'MITJA');

        $resultado = gestionarAlertas($pdo, 'VENCIMENT_CERTIFICACIO', [
            'id_treballador' => $cert['id_treballador'],
            'id_referencia' => $cert['id_certificacio'],
            'taula_referencia' => 'certificacions',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => $cert['data_caducitat'],
            'missatge' => "Certificació '{$cert['tipus_certificacio']}' caduca en $dias dies",
            'urgencia' => $urgencia
        ], false);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Certificacions: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Certificacions ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT vp.id_absencia, vp.id_treballador, vp.tipus_absencia, 
               vp.data_inici, vp.data_final, vp.dies
        FROM vacances_permisos vp
        WHERE vp.estat = 'PENDENT'
          AND vp.data_inici <= CURDATE() + INTERVAL 7 DAY
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $absencia) {
        $dias = ceil((strtotime($absencia['data_inici']) - time()) / 86400);
        $urgencia = ($dias <= 2) ? 'ALTA' : 'MITJA';

        $resultado = gestionarAlertas($pdo, 'VACANCES_PENDENTS', [
            'id_treballador' => $absencia['id_treballador'],
            'id_referencia' => $absencia['id_absencia'],
            'taula_referencia' => 'vacances_permisos',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => $absencia['data_inici'],
            'missatge' => "Sol·licitud {$absencia['tipus_absencia']} pendent d'aprovació (inici en $dias dies)",
            'urgencia' => $urgencia
        ], false);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Vacances/Permisos: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Vacances/Permisos ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT s.id as stock_id, h.nombre_comercial, s.cantidad_actual, s.stock_minimo 
        FROM stock_herbicidas s
        JOIN herbicidas h ON s.herbicida_id = h.id
        WHERE s.cantidad_actual <= s.stock_minimo * 1.2
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $stock) {
        $resultado = gestionarAlertas($pdo, 'ESTOC_MINIM', [
            'id_treballador' => null,
            'id_referencia' => $stock['stock_id'],
            'taula_referencia' => 'stock_herbicidas',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => date('Y-m-d', strtotime('+7 days')),
            'missatge' => "Stock baix: {$stock['nombre_comercial']} ({$stock['cantidad_actual']} uds)",
            'urgencia' => $stock['cantidad_actual'] <= $stock['stock_minimo'] ? 'ALTA' : 'MITJA'
        ], true);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Estoc: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Estoc ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT lh.id as lote_id, lh.numero_lote, lh.fecha_caducidad, lh.cantidad_actual,
               h.nombre_comercial
        FROM lotes_herbicidas lh
        JOIN stock_herbicidas sh ON lh.stock_id = sh.id
        JOIN herbicidas h ON sh.herbicida_id = h.id
        WHERE lh.fecha_caducidad BETWEEN CURDATE() AND CURDATE() + INTERVAL 90 DAY
          AND lh.cantidad_actual > 0
          AND lh.activo = 1
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $lote) {
        $dias = ceil((strtotime($lote['fecha_caducidad']) - time()) / 86400);
        $urgencia = ($dias <= 15) ? 'ALTA' : 'MITJA';

        $resultado = gestionarAlertas($pdo, 'VENCIMENT_PRODUCTE', [
            'id_treballador' => null,
            'id_referencia' => $lote['lote_id'],
            'taula_referencia' => 'lotes_herbicidas',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => $lote['fecha_caducidad'],
            'missatge' => "Lot {$lote['numero_lote']} de {$lote['nombre_comercial']} caduca en $dias dies",
            'urgencia' => $urgencia
        ], true);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Lots herbicides: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Lots herbicides ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT t.id_tasca, t.id_sector, t.tipus_tasca, t.data_inici_finestra, t.data_final_finestra,
               sc.nombre as sector_nombre
        FROM tasques t
        LEFT JOIN sectores_cultivo sc ON t.id_sector = sc.id
        WHERE t.estat = 'PENDENT'
          AND t.tipus_tasca IN ('Tractament fitosanitari', 'Fertilització', 'Control plagues')
          AND t.data_inici_finestra <= CURDATE() + INTERVAL 3 DAY
          AND t.data_final_finestra >= CURDATE()
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $tasca) {
        $dias_final = ceil((strtotime($tasca['data_final_finestra']) - time()) / 86400);
        $urgencia = (strtotime($tasca['data_inici_finestra']) <= time()) ? 'ALTA' : 'MITJA';
        $sector = $tasca['sector_nombre'] ?? 'Sector desconegut';

        $resultado = gestionarAlertas($pdo, 'TRACTAMENT_PENDENT', [
            'id_treballador' => null,
            'id_referencia' => $tasca['id_tasca'],
            'taula_referencia' => 'tasques',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => $tasca['data_final_finestra'],
            'missatge' => "Tractament pendent: {$tasca['tipus_tasca']} a $sector ($dias_final dies restants)",
            'urgencia' => $urgencia
        ], true);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Tractaments pendents: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Tractaments pendents ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT mp.id as plaga_id, mp.id_sector, mp.tipus_plaga, mp.nivell_incidencia, 
               mp.data_observacio, sc.nombre as sector_nombre
        FROM monitoratge_plagues mp
        LEFT JOIN sectores_cultivo sc ON mp.id_sector = sc.id
        WHERE mp.nivell_incidencia IN ('ALT', 'CRITIC')
          AND mp.llindar_intervencio = 1
          AND mp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $plaga) {
        $urgencia = ($plaga['nivell_incidencia'] == 'CRITIC') ? 'CRITICA' : 'ALTA';
        $sector = $plaga['sector_nombre'] ?? 'Sector desconegut';

        $resultado = gestionarAlertas($pdo, 'PLAGA_DETECTADA', [
            'id_treballador' => null,
            'id_referencia' => $plaga['plaga_id'],
            'taula_referencia' => 'monitoratge_plagues',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => date('Y-m-d', strtotime('+3 days')),
            'missatge' => "PLAGA {$plaga['nivell_incidencia']}: {$plaga['tipus_plaga']} a $sector",
            'urgencia' => $urgencia
        ], true);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Plagues: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Plagues ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT sf.id as fenologic_id, sf.id_sector, sf.estat_fenologic, 
               sf.data_observacio, sf.intensitat,
               sc.nombre as sector_nombre, 
               v.nombre as varietat_nombre,
               c.nombre_comun as cultiu_nom
        FROM seguiment_fenologic sf
        LEFT JOIN sectores_cultivo sc ON sf.id_sector = sc.id
        LEFT JOIN historial_cultivos hc ON sf.id_sector = hc.sector_id AND hc.fecha_arrancada IS NULL
        LEFT JOIN variedades v ON hc.variedad_id = v.id
        LEFT JOIN cultivos c ON v.cultivo_id = c.id
        WHERE sf.estat_fenologic = 'MADURACIO'
          AND sf.intensitat IN ('MITJA', 'ALTA')
          AND sf.data_observacio >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
          AND NOT EXISTS (
              SELECT 1 FROM registre_collites rc 
              WHERE rc.id_sector = sf.id_sector 
              AND rc.data_collita >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          )
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $cosecha) {
        $cultiu = $cosecha['cultiv_nom'] ?? 'Cultiu';
        $varietat = $cosecha['varietat_nombre'] ?? 'Varietat desconeguda';
        $sector = $cosecha['sector_nombre'] ?? 'Sector desconegut';

        $resultado = gestionarAlertas($pdo, 'COSECHA_PREVISTA', [
            'id_treballador' => null,
            'id_referencia' => $cosecha['id_sector'],
            'taula_referencia' => 'registre_collites',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => date('Y-m-d', strtotime('+14 days')),
            'missatge' => "Cosecha prevista: $cultiu ($varietat) a $sector",
            'urgencia' => ($cosecha['intensitat'] == 'ALTA') ? 'ALTA' : 'MITJA'
        ], false);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Coseches previstes: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Coseches previstes ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT ma.id_maquinaria, ma.nom_maquinaria, ma.tipus, ma.hores_us_acumulades,
               mm.proper_manteniment_hores
        FROM maquinaria_agricola ma
        JOIN (
            SELECT id_maquinaria, MAX(proper_manteniment_hores) as proper_manteniment_hores
            FROM maquinaria_manteniment
            WHERE proper_manteniment_hores IS NOT NULL
            GROUP BY id_maquinaria
        ) mm ON ma.id_maquinaria = mm.id_maquinaria
        WHERE ma.estat = 'OPERATIVA'
          AND ma.hores_us_acumulades >= (mm.proper_manteniment_hores - 50)
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $maq) {
        $hores_restantes = max(0, $maq['proper_manteniment_hores'] - $maq['hores_us_acumulades']);
        $urgencia = ($hores_restantes <= 10) ? 'ALTA' : 'MITJA';

        $resultado = gestionarAlertas($pdo, 'MANTENIMENT_PENDENT', [
            'id_treballador' => null,
            'id_referencia' => $maq['id_maquinaria'],
            'taula_referencia' => 'maquinaria_agricola',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => date('Y-m-d', strtotime('+7 days')),
            'missatge' => "Manteniment: {$maq['nom_maquinaria']} ({$maq['tipus']}) - $hores_restantes hores restants",
            'urgencia' => $urgencia
        ], true);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Maquinària: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Maquinària ERROR: " . $e->getMessage();
}

try {
    $stmt = $pdo->query("
        SELECT am.id as analisi_id, am.id_parcela, am.id_sector, 
               am.tipus_mostra, am.data_mostra, am.laboratori
        FROM analisis_muestras am
        WHERE am.resultats IS NULL
          AND am.data_mostra <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          AND am.created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $alertas_creadas = 0;
    $alertas_actualizadas = 0;
    foreach($stmt->fetchAll() as $analisi) {
        $dias_espera = ceil((time() - strtotime($analisi['data_mostra'])) / 86400);

        $resultado = gestionarAlertas($pdo, 'ANALISI_PENDENT', [
            'id_treballador' => null,
            'id_referencia' => $analisi['analisi_id'],
            'taula_referencia' => 'analisis_muestras',
            'data_generacio' => date('Y-m-d'),
            'data_venciment' => date('Y-m-d', strtotime('+5 days')),
            'missatge' => "Anàlisi {$analisi['tipus_mostra']} pendent de resultats des de fa $dias_espera dies",
            'urgencia' => ($dias_espera > 14) ? 'ALTA' : 'MITJA'
        ], true);

        if ($resultado === 'creada') $alertas_creadas++;
        elseif ($resultado === 'actualizada') $alertas_actualizadas++;
    }
    $log[] = "Anàlisis: OK ($alertas_creadas creadas, $alertas_actualizadas actualizadas)";
} catch(PDOException $e) {
    $log[] = "Anàlisis ERROR: " . $e->getMessage();
}

// Estadísticas finales mejoradas
$alertas_creadas_total = 0;
$alertas_actualizadas_total = 0;

// Recopilar estadísticas de todas las secciones
foreach ($log as $entry) {
    if (preg_match('/(\d+) creadas/', $entry, $matches)) {
        $alertas_creadas_total += $matches[1];
    }
    if (preg_match('/(\d+) actualizadas/', $entry, $matches)) {
        $alertas_actualizadas_total += $matches[1];
    }
}

$log[] = "=== RESUMEN TOTAL ===";
$log[] = "Alertas creadas: $alertas_creadas_total";
$log[] = "Alertas actualizadas: $alertas_actualizadas_total";
$log[] = "Alertas eliminadas: $alertas_eliminadas";
$log[] = "Total procesadas: " . ($alertas_creadas_total + $alertas_actualizadas_total);

// Solo mostrar log si hay actividad significativa
if ($alertas_creadas_total > 0 || $alertas_actualizadas_total > 0 || $alertas_eliminadas > 0) {
    echo "Sistema d'alertes actualitzat - " . date('Y-m-d H:i:s') . "\n";
    foreach ($log as $entry) {
        echo "- $entry\n";
    }
}