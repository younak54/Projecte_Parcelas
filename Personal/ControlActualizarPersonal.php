<?php
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_treballador'])) {
    try {
        // Concatenar nombre completo
        $nom_complet = trim(
            ($_POST['nombre'] ?? '') . ' ' .
            ($_POST['apellido1'] ?? '') . ' ' .
            ($_POST['apellido2'] ?? '')
        );

        // SQL UPDATE corregido (sin "id = ?")
        $sql = "UPDATE treballadors SET
                    nom_complet = ?,
                    document_identitat = ?,
                    tipus_document = ?,
                    data_naixement = ?,
                    lloc_naixement = ?,
                    nacionalitat = ?,
                    telefon = ?,
                    email = ?,
                    adreca = ?,
                    numero_seguretat_social = ?,
                    iban_bancari = ?,
                    tipus_permis_treball = ?,
                    id_departament = ?,
                    id_equip = ?,
                    contacte_emergencia = ?,
                    idiomes = ?,
                    habilitats = ?,
                    certificacions_addicionals = ?
                WHERE id_treballador = ?";

        $stmt = $pdo->prepare($sql);

        // Preparar valores (coinciden exactamente con los placeholders)
        $values = [
            $nom_complet,
            $_POST['document_identitat'] ?? '',
            $_POST['tipus_document'] ?? '',
            $_POST['data_naixement'] ?: null,
            !empty($_POST['lloc_naixement']) ? $_POST['lloc_naixement'] : null,
            $_POST['nacionalitat'] ?? '',
            !empty($_POST['telefon']) ? $_POST['telefon'] : null,
            !empty($_POST['email']) ? $_POST['email'] : null,
            !empty($_POST['adreca']) ? $_POST['adreca'] : null,
            $_POST['numero_seguretat_social'] ?? '',
            !empty($_POST['iban_bancari']) ? $_POST['iban_bancari'] : null,
            !empty($_POST['tipus_permis_treball']) ? $_POST['tipus_permis_treball'] : null,
            !empty($_POST['id_departament']) ? $_POST['id_departament'] : null,
            !empty($_POST['id_equip']) ? $_POST['id_equip'] : null,
            !empty($_POST['contacte_emergencia']) ? $_POST['contacte_emergencia'] : null,
            !empty($_POST['idiomes']) ? $_POST['idiomes'] : null,
            !empty($_POST['habilitats']) ? $_POST['habilitats'] : null,
            !empty($_POST['certificacions_addicionals']) ? $_POST['certificacions_addicionals'] : null,
            $_POST['id_treballador']  // este es para el WHERE
        ];

        $stmt->execute($values);

        header("Location: empleados.php?status=updated");
        exit;

    } catch (PDOException $e) {
        error_log("Error en ControlActualizarPersonal.php: " . $e->getMessage());
        header("Location: empleados.php?status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Acceso directo no permitido
    header("Location: empleados.php");
    exit;
}