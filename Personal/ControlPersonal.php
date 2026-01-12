<?php
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: empleados.php");
    exit;
}

try {
    // Recoger datos del formulario y limpiar
    $nombre = $_POST['nombre'] ?? '';
    $apellido1 = $_POST['apellido1'] ?? '';
    $apellido2 = $_POST['apellido2'] ?? '';

    $data = [
        'codigo_empleado' => $_POST['codigo_empleado'] ?? '',
        'nom_complet' => trim("$nombre $apellido1 $apellido2"),
        'document_identitat' => $_POST['numero_documento'] ?? '',
        'tipus_document' => $_POST['tipo_documento_id'] ?? 'DNI',
        'data_naixement' => $_POST['fecha_nacimiento'] ?: null,
        'lloc_naixement' => $_POST['lugar_nacimiento'] ?: null,
        'nacionalitat' => $_POST['nacionalidad'] ?? '',
        'telefon' => $_POST['telefono_principal'] ?: null,
        'email' => $_POST['email_personal'] ?: null,
        'adreca' => $_POST['direccion_completa'] ?: null,
        'numero_seguretat_social' => $_POST['numero_SS'] ?? '',
        'estat_actiu' => 1,
        'data_incorporacio' => date('Y-m-d')
    ];

    // Determinar acción: insertar o actualizar
    $accion = $_POST['accion'] ?? 'insertar';

    if ($accion === 'actualizar' && !empty($data['codigo_empleado'])) {
        // UPDATE
        $sql = "UPDATE treballadors SET
                    nom_complet = :nom_complet,
                    document_identitat = :document_identitat,
                    tipus_document = :tipus_document,
                    data_naixement = :data_naixement,
                    lloc_naixement = :lloc_naixement,
                    nacionalitat = :nacionalitat,
                    telefon = :telefon,
                    email = :email,
                    adreca = :adreca,
                    numero_seguretat_social = :numero_seguretat_social
                WHERE id_treballador = :codigo_empleado";
    } else {
        // INSERT
        $sql = "INSERT INTO treballadors (
                    id_treballador, nom_complet, document_identitat, tipus_document,
                    data_naixement, lloc_naixement, nacionalitat,
                    telefon, email,
                    adreca, numero_seguretat_social, estat_actiu, data_incorporacio
                ) VALUES (
                    :codigo_empleado, :nom_complet, :document_identitat, :tipus_document,
                    :data_naixement, :lloc_naixement, :nacionalitat,
                    :telefon, :email,
                    :adreca, :numero_seguretat_social, :estat_actiu, :data_incorporacio
                )";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    $status = ($accion === 'actualizar') ? 'updated' : 'success';
    header("Location: empleados.php?status=$status");
    exit;

} catch (PDOException $e) {
    echo "<h2>Error en la base de datos:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<h3>Datos enviados:</h3>";
    echo "<pre>" . print_r($data, true) . "</pre>";
    exit;
}
?>