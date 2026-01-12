<?php
require '../db.php';

// 1️⃣ LISTADO DE EMPLEADOS (Mapeando campos de tu BD a los nombres del HTML)
$stmt = $pdo->query("SELECT 
                       id_treballador as id,
                       -- Dividir nom_complet en partes (asumimos formato 'Nombre Apellido1 Apellido2')
                       SUBSTRING_INDEX(nom_complet, ' ', 1) as nombre,
                       SUBSTRING_INDEX(SUBSTRING_INDEX(nom_complet, ' ', 2), ' ', -1) as apellido1,
                       SUBSTRING_INDEX(nom_complet, ' ', -1) as apellido2,
                       -- El ENUM directo como nombre del tipo
                       tipus_document as tipo_documento_nombre,
                       document_identitat as numero_documento,
                       data_naixement as fecha_nacimiento,
                       lloc_naixement as lugar_nacimiento,
                       nacionalitat as nacionalidad,
                       telefon as telefono_principal,
                       NULL as telefono_secundario,
                       email as email_personal,
                       NULL as email_empresa,
                       adreca as direccion_completa,
                       estat_actiu as activo,
                       data_incorporacio as fecha_alta_sistema,
                       NULL as rol,
                       NULL as codigo_empleado
                     FROM treballadors
                     WHERE estat_actiu = 1
                     ORDER BY data_incorporacio DESC");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2️⃣ TIPOS DE DOCUMENTO (Simulando la tabla que no existe)
$tipos_doc = $pdo->query("SELECT 
                           'DNI' as id, 
                           'Document Nacional d''Identitat' as tipo 
                         UNION ALL SELECT 
                           'NIE', 
                           'Número d''Identificació d''Estranger' 
                         UNION ALL SELECT 
                           'PASSAPORT', 
                           'Passaport' 
                         ORDER BY tipo")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Personal</title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
</head>
<body>
<?php include '../menu.php'; ?>
    <h1>👥 Gestión de Personal</h1>

    <!-- MENSAJES DE ESTADO -->
    <?php if (isset($_GET['status'])): ?>
        <div class="status-message">
            <?php 
            switch($_GET['status']) {
                case 'success': echo '✅ Empleado creado correctamente'; break;
                case 'updated': echo '✅ Empleado actualizado correctamente'; break;
                case 'deleted': echo '⚠️ Empleado desactivado (borrado lógico)'; break;
                case 'error': echo '❌ Error: ' . htmlspecialchars($_GET['message']); break;
            }
            ?>
        </div>
    <?php endif; ?>

    <h2>➕ Insertar Nuevo Empleado</h2>
    <form id="form-principal" method="POST" action="ControlPersonal.php">
        
        <h3>📋 Datos Básicos</h3>
        <label>Código Empleado:</label>
        <input type="text" name="codigo_empleado" required placeholder="EJ: 001001" maxlength="20">
        
        <label>Nombre:</label>
        <input type="text" name="nombre" required placeholder="Juan" maxlength="100">
        
        <label>Primer Apellido:</label>
        <input type="text" name="apellido1" required placeholder="García" maxlength="100">
        
        <label>Segundo Apellido:</label>
        <input type="text" name="apellido2" placeholder="López" maxlength="100">

        <h3>🆔 Documentación</h3>
        <label>Tipo Documento:</label>
        <select name="tipo_documento_id" required>
            <option value="">-- Seleccionar tipo --</option>
            <?php foreach ($tipos_doc as $td): ?>
                <option value="<?= $td['id'] ?>"><?= $td['tipo'] ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>Número Documento:</label>
        <input type="text" name="numero_documento" required placeholder="12345678A" maxlength="50">
        
        <label>Fecha Nacimiento:</label>
        <input type="date" name="fecha_nacimiento" required>
        
        <label>Lugar Nacimiento:</label>
        <input type="text" name="lugar_nacimiento" placeholder="Barcelona" maxlength="100">
        
        <label>Nacionalidad:</label>
        <input type="text" name="nacionalidad" required placeholder="Española" maxlength="50">

        <h3>📞 Contacto</h3>
        <label>Teléfono Principal:</label>
        <input type="tel" name="telefono_principal" placeholder="612345678" maxlength="20">
        
        <label>Teléfono Secundario:</label>
        <input type="tel" name="telefono_secundario" placeholder="938765432" maxlength="20">
        
        <label>Email Personal:</label>
        <input type="email" name="email_personal" placeholder="juan@personal.com" maxlength="100">
        
        <label>Email Empresa:</label>
        <input type="email" name="email_empresa" placeholder="juan.garcia@explotacion.com" maxlength="100">
        
        <label>Dirección Completa:</label>
        <textarea name="direccion_completa" rows="3" placeholder="C/ Mayor 123, 1º A, 08001 Barcelona"></textarea>
        
        <label>Numero Seguridad Social:</label>
        <textarea name="numero_SS" rows="3" placeholder="25/1234567"></textarea>

        <button type="submit">💾 Guardar Empleado</button>
    </form>

    <h2>📋 Listado de Empleados Activos</h2>
    <div class="cards-container">
        <?php foreach ($empleados as $emp): ?>
            <div class="card">

                <h3><?= htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido1'] . ' ' . $emp['apellido2']) ?></h3>
                
                <div class="card-section">
                    <p><strong>🆔 Código:</strong> <?= htmlspecialchars($emp['id'] ?? 'SIN-COD') ?></p>
                    <p><strong><?= htmlspecialchars($emp['tipo_documento_nombre']) ?>:</strong> <?= htmlspecialchars($emp['numero_documento']) ?></p>
                    <p><strong>🎂 Nacimiento:</strong> <?= date('d/m/Y', strtotime($emp['fecha_nacimiento'])) ?></p>
                    <p><strong>📍 Lugar:</strong> <?= htmlspecialchars($emp['lugar_nacimiento'] ?? 'No especificado') ?></p>
                </div>

                <div class="card-section">
                    <p><strong>📧 Empresa:</strong> <?= htmlspecialchars($emp['email_empresa'] ?? 'No asignado') ?></p>
                    <p><strong>📱 Teléfono:</strong> <?= htmlspecialchars($emp['telefono_principal'] ?? 'No asignado') ?></p>
                </div>

                <div class="card-section">
                    <p><strong>🎭 Rol:</strong> <span class="badge rol-<?= strtolower($emp['rol'] ?? 'operario') ?>"><?= $emp['rol'] ?? 'Sin asignar' ?></span></p>
                    <p><strong>🇪🇸 Nacionalidad:</strong> <?= htmlspecialchars($emp['nacionalidad']) ?></p>
                </div>

                <div class="card-actions">
                    <form method="POST" action="EliminarPersonal.php" style="display:inline;" 
                          onsubmit="return confirm('⚠️ ¿Estás seguro de desactivar este empleado?');">
                        <input type="hidden" name="id_treballador" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn-borrar">🗑️ Borrar</button>
                    </form>
                    
                    <form method="GET" action="ActPersonal.php" style="display:inline;">
                        <input type="hidden" name="id_treballador" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn-actualizar">✏️ Actualizar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>