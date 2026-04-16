<?php
require '../db.php';
session_start();

// Verificar si hay sesión de usuario (empleado logueado)
// Si no hay sesión, permitir seleccionar empleado para pruebas
$idTreballador = $_SESSION['id_treballador'] ?? $_GET['id_treballador'] ?? null;

if (!$idTreballador) {
    // Mostrar selector de empleado para pruebas
    $stmt = $pdo->query("SELECT id_treballador, nom_complet FROM treballadors WHERE estat_actiu = 1 ORDER BY nom_complet");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $mostrarSelector = true;
} else {
    $mostrarSelector = false;
    
    // Obtener info del empleado
    $stmt = $pdo->prepare("SELECT t.*, h.nom_horari, h.hores_entrada, h.hores_sortida 
                          FROM treballadors t 
                          LEFT JOIN horaris h ON t.id_horari = h.id_horari 
                          WHERE t.id_treballador = ?");
    $stmt->execute([$idTreballador]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {
        die('Empleado no encontrado');
    }
    
    // Verificar si hay fichaje abierto (sin hora de salida)
    $stmt = $pdo->prepare("SELECT * FROM registre_hores 
                          WHERE id_treballador = ? 
                          AND DATE(hora_inici) = CURDATE()
                          AND hora_final IS NULL
                          ORDER BY hora_inici DESC LIMIT 1");
    $stmt->execute([$idTreballador]);
    $fichajeAbierto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener últimos fichajes
    $stmt = $pdo->prepare("SELECT * FROM registre_hores 
                          WHERE id_treballador = ? 
                          AND hora_final IS NOT NULL
                          ORDER BY data DESC, hora_final DESC 
                          LIMIT 5");
    $stmt->execute([$idTreballador]);
    $ultimosFichajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar acciones de fichaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idTreballador) {
    $accion = $_POST['accion'] ?? '';
    $ubicacion = $_POST['ubicacion'] ?? 'Oficina Principal';
    $incidencia = $_POST['incidencia'] ?? null;
    
    if ($accion === 'entrada') {
        // Verificar que no haya fichaje abierto
        if ($fichajeAbierto) {
            $error = "Ya tienes un fichaje abierto desde las " . date('H:i', strtotime($fichajeAbierto['hora_inici']));
        } else {
            // Crear nuevo fichaje de entrada
            $stmt = $pdo->prepare("INSERT INTO registre_hores 
                (id_treballador, data, hora_inici, ubicacio, created_at) 
                VALUES (?, CURDATE(), NOW(), ?, NOW())");
            $stmt->execute([$idTreballador, $ubicacion]);
            $success = "Fichaje de entrada registrado: " . date('H:i:s');
            
            // Recargar para actualizar estado
            header("Location: Fichar.php?id_treballador=$idTreballador&status=entrada");
            exit;
        }
        
    } elseif ($accion === 'salida' && $fichajeAbierto) {
        // Calcular pausa si se indicó
        $pausa = floatval($_POST['pausa'] ?? 0);
        
        // Cerrar fichaje
        $stmt = $pdo->prepare("UPDATE registre_hores 
            SET hora_final = NOW(), 
                pausa_durada = ?,
                incidencies_observacions = ?
            WHERE id_registre = ?");
        $stmt->execute([$pausa, $incidencia, $fichajeAbierto['id_registre']]);
        
        // Calcular horas trabajadas
        $inicio = strtotime($fichajeAbierto['hora_inici']);
        $final = time();
        $horasTrabajadas = ($final - $inicio - ($pausa * 3600)) / 3600;
        
        $success = "Fichaje de salida registrado. Horas trabajadas: " . number_format($horasTrabajadas, 2);
        header("Location: Fichar.php?id_treballador=$idTreballador&status=salida&horas=" . number_format($horasTrabajadas, 2));
        exit;
    }
}

// Mensajes de estado
$status = $_GET['status'] ?? '';
$horasRegistradas = $_GET['horas'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Fichaje - AgriManager</title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #f0f8ff, #d1e8ff);  
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .fichaje-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .fichaje-header {
            background: #1b262c;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .fichaje-header h1 {
            font-size: 1.8em;
            margin-bottom: 10px;
        }
        
        .empleado-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }
        
        .empleado-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #3282b8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
        }
        
        .empleado-datos h3 {
            margin: 0;
            font-size: 1.2em;
        }
        
        .empleado-datos p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 0.9em;
        }
        
        .reloj-container {
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .reloj {
            font-size: 4em;
            font-weight: 300;
            color: #1b262c;
            font-family: 'Courier New', monospace;
            letter-spacing: 5px;
        }
        
        .fecha {
            font-size: 1.2em;
            color: #666;
            margin-top: 10px;
            text-transform: capitalize;
        }
        
        .estado-fichaje {
            padding: 20px 40px;
            text-align: center;
        }
        
        .estado-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .estado-dentro {
            background: #d4edda;
            color: #155724;
        }
        
        .estado-fuera {
            background: #f8d7da;
            color: #721c24;
        }
        
        .botones-fichaje {
            padding: 30px 40px;
            display: flex;
            gap: 20px;
            justify-content: center;
        }
        
        .btn-fichaje {
            flex: 1;
            padding: 20px;
            border: none;
            border-radius: 15px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .btn-entrada {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-entrada:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
        }
        
        .btn-salida {
            background: linear-gradient(135deg, #dc3545 0%, #f093fb 100%);
            color: white;
        }
        
        .btn-salida:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.4);
        }
        
        .btn-fichaje:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-icono {
            font-size: 2em;
        }
        
        .info-jornada {
            padding: 20px 40px;
            background: #e9ecef;
            border-top: 1px solid #dee2e6;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #ccc;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .historial-fichajes {
            padding: 30px 40px;
        }
        
        .historial-fichajes h3 {
            margin-bottom: 20px;
            color: #1b262c;
        }
        
        .fichaje-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #3282b8;
        }
        
        .fichaje-horas {
            text-align: right;
        }
        
        .horas-trabajadas {
            font-size: 1.2em;
            font-weight: bold;
            color: #3282b8;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 40px;
            border-radius: 10px;
            text-align: center;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .selector-empleado {
            padding: 40px;
            text-align: center;
        }
        
        .selector-empleado select {
            width: 100%;
            padding: 15px;
            font-size: 1.1em;
            border-radius: 10px;
            border: 2px solid #3282b8;
            margin-bottom: 20px;
        }
        
        .form-salida {
            padding: 20px 40px;
            background: #fff3cd;
            border-top: 1px solid #ffeaa7;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
    </style>
</head>
<body>

<?php if ($mostrarSelector): ?>
    <!-- SELECTOR DE EMPLEADO (para pruebas) -->
    <div class="fichaje-container">
        <div class="fichaje-header">
            <h1>🔐 Acceso al Sistema de Fichaje</h1>
            <p>Selecciona tu usuario para continuar</p>
        </div>
        <div class="selector-empleado">
            <form method="GET" action="">
                <select name="id_treballador" required>
                    <option value="">-- Selecciona un empleado --</option>
                    <?php foreach ($empleados as $emp): ?>
                        <option value="<?= $emp['id_treballador'] ?>">
                            <?= htmlspecialchars($emp['nom_complet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-actualizar" style="width: 100%; padding: 15px; font-size: 1.1em;">
                    Acceder al Panel de Fichaje
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- PANEL DE FICHAJE -->
    <div class="fichaje-container">
        <div class="fichaje-header">
            <h1>⏱️ Sistema de Fichaje</h1>
            <div class="empleado-info">
                <div class="empleado-avatar">👤</div>
                <div class="empleado-datos">
                    <h3><?= htmlspecialchars($empleado['nom_complet']) ?></h3>
                    <p><?= htmlspecialchars($empleado['nom_horari'] ?? 'Sin horario asignado') ?></p>
                </div>
            </div>
        </div>
        
        <!-- Reloj en tiempo real -->
        <div class="reloj-container">
            <div class="reloj" id="reloj">00:00:00</div>
            <div class="fecha" id="fecha">Cargando...</div>
        </div>
        
        <!-- Estado actual -->
        <div class="estado-fichaje">
            <?php if ($fichajeAbierto): ?>
                <span class="estado-badge estado-dentro">
                    🟢 DENTRO (desde <?= date('H:i', strtotime($fichajeAbierto['hora_inici'])) ?>)
                </span>
            <?php else: ?>
                <span class="estado-badge estado-fuera">
                    🔴 FUERA
                </span>
            <?php endif; ?>
        </div>
        
        <!-- Mensajes -->
        <?php if ($status === 'entrada'): ?>
            <div class="alert alert-success">
                ✅ Entrada registrada correctamente
            </div>
        <?php elseif ($status === 'salida'): ?>
            <div class="alert alert-success">
                ✅ Salida registrada. Total horas: <?= htmlspecialchars($horasRegistradas) ?>h
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Botones de fichaje -->
        <div class="botones-fichaje">
            <form method="POST" action="" style="flex: 1;">
                <input type="hidden" name="accion" value="entrada">
                <input type="hidden" name="id_treballador" value="<?= $idTreballador ?>">
                <button type="submit" class="btn-fichaje btn-entrada" <?= $fichajeAbierto ? 'disabled' : '' ?>>
                    <span class="btn-icono">⬇️</span>
                    <span>FICHAR ENTRADA</span>
                </button>
            </form>
            
            <form method="POST" action="" style="flex: 1;" id="formSalida" onsubmit="return confirmarSalida()">
                <input type="hidden" name="accion" value="salida">
                <input type="hidden" name="id_treballador" value="<?= $idTreballador ?>">
                <button type="button" class="btn-fichaje btn-salida" onclick="mostrarFormSalida()" <?= !$fichajeAbierto ? 'disabled' : '' ?>>
                    <span class="btn-icono">⬆️</span>
                    <span>FICHAR SALIDA</span>
                </button>
            </form>
        </div>
        
        <!-- Formulario de salida (se muestra al pulsar el botón) -->
        <?php if ($fichajeAbierto): ?>
        <div id="formSalidaDetalle" class="form-salida" style="display: none;">
            <h4>📋 Detalles de la jornada</h4>
            <div class="form-group">
                <label>⏸️ Pausa (horas):</label>
                <input type="number" name="pausa" form="formSalida" step="0.25" min="0" max="8" value="1" placeholder="Ej: 1 o 0.5">
                <small>Tiempo de descanso en horas (ej: 1 = 1 hora, 0.5 = 30 min)</small>
            </div>
            <div class="form-group">
                <label>📍 Ubicación:</label>
                <select name="ubicacion" form="formSalida">
                    <option value="Oficina Principal">Oficina Principal</option>
                    <option value="Campo - Parcela 1">Campo - Parcela 1</option>
                    <option value="Campo - Parcela 2">Campo - Parcela 2</option>
                    <option value="Almacén">Almacén</option>
                    <option value="Remoto">Remoto</option>
                </select>
            </div>
            <div class="form-group">
                <label>📝 Incidencias/Observaciones:</label>
                <textarea name="incidencia" form="formSalida" placeholder="Alguna incidencia durante la jornada..."></textarea>
            </div>
            <button type="submit" form="formSalida" class="btn-salida" style="width: 100%; padding: 15px;">
                ✅ Confirmar Salida
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Info de jornada -->
        <div class="info-jornada">
            <h4 style="margin-bottom: 15px; color: #1b262c;">📊 Tu jornada hoy</h4>
            <?php if ($fichajeAbierto): ?>
                <?php 
                $inicio = strtotime($fichajeAbierto['hora_inici']);
                $ahora = time();
                $transcurrido = $ahora - $inicio;
                $horas = floor($transcurrido / 3600);
                $minutos = floor(($transcurrido % 3600) / 60);
                ?>
                <div class="info-item">
                    <span>⏰ Entrada:</span>
                    <strong><?= date('H:i:s', $inicio) ?></strong>
                </div>
                <div class="info-item">
                    <span>⏱️ Tiempo transcurrido:</span>
                    <strong><?= $horas ?>h <?= $minutos ?>m</strong>
                </div>
                <div class="info-item">
                    <span>📍 Ubicación:</span>
                    <strong><?= htmlspecialchars($fichajeAbierto['ubicacio'] ?? 'No registrada') ?></strong>
                </div>
            <?php else: ?>
                <div class="info-item">
                    <span>Estado:</span>
                    <strong>No has fichado entrada hoy</strong>
                </div>
                <?php if ($empleado['hores_entrada']): ?>
                <div class="info-item">
                    <span>🕒 Tu horario:</span>
                    <strong><?= substr($empleado['hores_entrada'], 0, 5) ?> - <?= substr($empleado['hores_sortida'], 0, 5) ?></strong>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Historial reciente -->
        <div class="historial-fichajes">
            <h3>📅 Últimos fichajes</h3>
            <?php if (empty($ultimosFichajes)): ?>
                <p style="color: #666; text-align: center;">No hay registros recientes</p>
            <?php else: ?>
                <?php foreach ($ultimosFichajes as $fichaje): 
                    $inicio = strtotime($fichaje['hora_inici']);
                    $final = strtotime($fichaje['hora_final']);
                    $pausa = floatval($fichaje['pausa_durada']) * 3600;
                    $horas = ($final - $inicio - $pausa) / 3600;
                ?>
                    <div class="fichaje-item">
                        <div>
                            <strong><?= date('d/m/Y', strtotime($fichaje['data'])) ?></strong>
                            <br>
                            <small>
                                <?= date('H:i', $inicio) ?> - <?= date('H:i', $final) ?>
                                <?php if ($fichaje['pausa_durada'] > 0): ?>
                                    (<?= $fichaje['pausa_durada'] ?>h pausa)
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="fichaje-horas">
                            <div class="horas-trabajadas"><?= number_format($horas, 2) ?>h</div>
                            <small><?= htmlspecialchars($fichaje['ubicacio'] ?? '') ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Reloj en tiempo real
        function actualizarReloj() {
            const ahora = new Date();
            const horas = String(ahora.getHours()).padStart(2, '0');
            const minutos = String(ahora.getMinutes()).padStart(2, '0');
            const segundos = String(ahora.getSeconds()).padStart(2, '0');
            
            document.getElementById('reloj').textContent = `${horas}:${minutos}:${segundos}`;
            
            const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('fecha').textContent = ahora.toLocaleDateString('es-ES', opciones);
        }
        
        setInterval(actualizarReloj, 1000);
        actualizarReloj();
        
        // Mostrar formulario de salida
        function mostrarFormSalida() {
            document.getElementById('formSalidaDetalle').style.display = 'block';
        }
        
        // Confirmar salida
        function confirmarSalida() {
            return confirm('¿Confirmas que deseas fichar la salida?');
        }
    </script>
<?php endif; ?>

</body>
</html>