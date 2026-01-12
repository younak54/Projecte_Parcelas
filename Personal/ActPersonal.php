<?php
require '../db.php';

if (!isset($_GET['id'])) {
    header("Location: ../empleados.php");
    exit;
}

$id_treballador = $_GET['id'];

// Obtener datos del trabajador
$stmt = $pdo->prepare("SELECT * FROM treballadors WHERE id_treballador = ?");
$stmt->execute([$id_treballador]);
$treballador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$treballador) {
    die("Trabajador no encontrado");
}

// Dividir nom_complet en nombre y apellidos (asumiendo formato "Nombre Apellido1 Apellido2")
$parts_nombre = explode(' ', $treballador['nom_complet'], 3);
$nombre = $parts_nombre[0] ?? '';
$apellido1 = $parts_nombre[1] ?? '';
$apellido2 = $parts_nombre[2] ?? '';

// Obtener listados para los selects
$departamentos = $pdo->query("SELECT id_departament, nom_departament FROM departaments ORDER BY nom_departament")->fetchAll(PDO::FETCH_ASSOC);
$equipos = $pdo->query("SELECT id_equip, nom_equip FROM equips ORDER BY nom_equip")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualizar Trabajador</title>
    <link rel="stylesheet" href="../css/personal.css">
</head>
<body>
    <h1>Actualizar Trabajador</h1>

    <form id="form-principal" method="POST" action="ControlActualizarPersonal.php">
        <input type="hidden" name="id_treballador" value="<?= htmlspecialchars($treballador['id_treballador']) ?>">
        
        <h3>Datos Básicos</h3>
        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
        
        <label>Primer Apellido:</label>
        <input type="text" name="apellido1" value="<?= htmlspecialchars($apellido1) ?>" required>
        
        <label>Segundo Apellido:</label>
        <input type="text" name="apellido2" value="<?= htmlspecialchars($apellido2) ?>">

        <label>Tipo Documento:</label>
        <select name="tipus_document" required>
            <option value="DNI" <?= ($treballador['tipus_document'] == 'DNI') ? 'selected' : '' ?>>DNI</option>
            <option value="NIE" <?= ($treballador['tipus_document'] == 'NIE') ? 'selected' : '' ?>>NIE</option>
            <option value="PASSAPORT" <?= ($treballador['tipus_document'] == 'PASSAPORT') ? 'selected' : '' ?>>PASAPORTE</option>
        </select>
        
        <label>Número Documento:</label>
        <input type="text" name="document_identitat" value="<?= htmlspecialchars($treballador['document_identitat']) ?>" required>
        
        <label>Fecha Nacimiento:</label>
        <input type="date" name="data_naixement" value="<?= $treballador['data_naixement'] ?>" required>
        
        <label>Lugar Nacimiento:</label>
        <input type="text" name="lloc_naixement" value="<?= htmlspecialchars($treballador['lloc_naixement'] ?? '') ?>">
        
        <label>Nacionalidad:</label>
        <input type="text" name="nacionalitat" value="<?= htmlspecialchars($treballador['nacionalitat']) ?>" required>

        <h3>Contacto</h3>
        <label>Teléfono:</label>
        <input type="tel" name="telefon" value="<?= htmlspecialchars($treballador['telefon'] ?? '') ?>">
        
        <label>Email:</label>
        <input type="email" name="email_personal" value="<?= htmlspecialchars($treballador['email'] ?? '') ?>">
        
        <label>Dirección:</label>
        <textarea name="direccion_completa"><?= htmlspecialchars($treballador['adreca'] ?? '') ?></textarea>

        <h3>Información Laboral</h3>
        <label>Número Seguridad Social:</label>
        <input type="text" name="numero_seguretat_social" value="<?= htmlspecialchars($treballador['numero_seguretat_social']) ?>" required>
        
        <label>IBAN Bancario:</label>
        <input type="text" name="iban_bancari" value="<?= htmlspecialchars($treballador['iban_bancari'] ?? '') ?>">
        
        <label>Permiso de Trabajo:</label>
        <input type="text" name="tipus_permis_treball" value="<?= htmlspecialchars($treballador['tipus_permis_treball'] ?? '') ?>">

        <h3>Asignaciones</h3>
        <label>Departamento:</label>
        <select name="id_departament">
            <option value="">-- Sin departamento --</option>
            <?php foreach ($departamentos as $dept): ?>
                <option value="<?= $dept['id_departament'] ?>" <?= ($treballador['id_departament'] == $dept['id_departament']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept['nom_departament']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label>Equipo:</label>
        <select name="id_equip">
            <option value="">-- Sin equipo --</option>
            <?php foreach ($equipos as $equip): ?>
                <option value="<?= $equip['id_equip'] ?>" <?= ($treballador['id_equip'] == $equip['id_equip']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($equip['nom_equip']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <h3>Otros Datos</h3>
        <label>Contacto Emergencia:</label>
        <textarea name="contacte_emergencia" placeholder="Nombre, relación y teléfono"><?= htmlspecialchars($treballador['contacte_emergencia'] ?? '') ?></textarea>
        
        <label>Idiomas (separados por comas):</label>
        <input type="text" name="idiomes" value="<?= htmlspecialchars($treballador['idiomes'] ?? '') ?>">
        
        <label>Habilidades:</label>
        <textarea name="habilitats"><?= htmlspecialchars($treballador['habilitats'] ?? '') ?></textarea>
        
        <label>Certificaciones Adicionales:</label>
        <textarea name="certificacions_addicionals"><?= htmlspecialchars($treballador['certificacions_addicionals'] ?? '') ?></textarea>

        <button type="submit">Actualizar Trabajador</button>
    </form>

    <p><a href="empleados.php">← Volver</a></p>
</body>
</html>