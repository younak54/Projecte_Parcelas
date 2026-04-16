<?php
require '../db.php';

// Obtener empleados con su horario
$stmt = $pdo->query("SELECT 
                        t.id_treballador as 'id',
                        t.nom_complet AS 'Empleado',
                        t.estat_actiu as 'actiu',
                        h.nom_horari AS 'Turno',
                        h.hores_entrada AS 'Entrada',
                        h.hores_sortida AS 'Salida',
                        h.durada_pausa AS 'Pausa'
                        FROM treballadors t
                        LEFT JOIN horaris h ON t.id_horari = h.id_horari
                        WHERE t.estat_actiu = 1
                        ORDER BY t.nom_complet");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener información de fichajes de hoy para todos los empleados
$stmtFichajes = $pdo->query("
    SELECT 
        rh.id_treballador,
        rh.hora_inici,
        rh.hora_final,
        rh.ubicacio,
        rh.validat
    FROM registre_hores rh
    WHERE DATE(rh.data) = CURDATE()
    AND rh.id_registre IN (
        SELECT MAX(id_registre) 
        FROM registre_hores 
        WHERE DATE(data) = CURDATE() 
        GROUP BY id_treballador
    )
");
$fichajesHoy = [];
while ($row = $stmtFichajes->fetch(PDO::FETCH_ASSOC)) {
    $fichajesHoy[$row['id_treballador']] = $row;
}

// Función para obtener estadísticas de la semana
function obtenerEstadisticasSemana($pdo, $idTreballador) {
    $inicioSemana = date('Y-m-d', strtotime('monday this week'));
    $finSemana = date('Y-m-d', strtotime('sunday this week'));
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as dias_trabajados,
            SUM(CASE WHEN hora_final IS NOT NULL THEN 
                (TIMESTAMPDIFF(SECOND, hora_inici, hora_final) / 3600 - pausa_durada) 
                ELSE 0 END) as total_horas
        FROM registre_hores
        WHERE id_treballador = ?
        AND data BETWEEN ? AND ?
        AND hora_final IS NOT NULL
    ");
    $stmt->execute([$idTreballador, $inicioSemana, $finSemana]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Procesar creación/edición de fichaje manual (desde modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_fichaje_manual'])) {
    $idTreballador = $_POST['id_treballador'];
    $fecha = $_POST['fecha_fichaje'];
    $horaEntrada = $_POST['hora_entrada'];
    $horaSalida = $_POST['hora_salida'];
    $pausa = floatval($_POST['pausa'] ?? 0);
    $ubicacion = $_POST['ubicacion'] ?? 'Oficina Principal';
    $incidencia = $_POST['incidencia'] ?? null;
    $validar = isset($_POST['validar']) ? 1 : 0;
    
    // Verificar si ya existe fichaje para ese día
    $stmt = $pdo->prepare("SELECT id_registre FROM registre_hores 
                          WHERE id_treballador = ? AND data = ? 
                          ORDER BY hora_inici DESC LIMIT 1");
    $stmt->execute([$idTreballador, $fecha]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existente) {
        // Actualizar fichaje existente
        $stmt = $pdo->prepare("UPDATE registre_hores SET
            hora_inici = ?,
            hora_final = ?,
            pausa_durada = ?,
            ubicacio = ?,
            incidencies_observacions = ?,
            validat = ?
            WHERE id_registre = ?");
        $stmt->execute([
            "$fecha $horaEntrada",
            "$fecha $horaSalida",
            $pausa,
            $ubicacion,
            $incidencia,
            $validar,
            $existente['id_registre']
        ]);
    } else {
        // Crear nuevo fichaje
        $stmt = $pdo->prepare("INSERT INTO registre_hores 
            (id_treballador, data, hora_inici, hora_final, pausa_durada, 
             ubicacio, incidencies_observacions, validat, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $idTreballador,
            $fecha,
            "$fecha $horaEntrada",
            "$fecha $horaSalida",
            $pausa,
            $ubicacion,
            $incidencia,
            $validar
        ]);
    }
    
    header("Location: Horaris.php?status=fichaje_ok");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios y Fichajes</title>
    <link rel="stylesheet" href="../css/personal.css">
    <link rel="stylesheet" href="../menu.css">
    <style>
        /* Estilos para el estado de fichaje */
        .fichaje-status {
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            text-align: center;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .status-dentro {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-fuera {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-no-fichado {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .btn-fichar {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-fichar-entrada {
            background: #28a745;
            color: white;
        }
        
        .btn-fichar-entrada:hover {
            background: #218838;
        }
        
        .btn-fichar-salida {
            background: #dc3545;
            color: white;
        }
        
        .btn-fichar-salida:hover {
            background: #c82333;
        }
        
        .btn-fichar-ver {
            background: #17a2b8;
            color: white;
        }
        
        .btn-editar-fichaje {
            background: #ffc107;
            color: #333;
            width: 100%;
            padding: 8px;
            margin-top: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .btn-editar-fichaje:hover {
            background: #e0a800;
        }
        
        .stats-semana {
            background: #e9ecef;
            padding: 8px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85em;
        }
        
        .stats-semana strong {
            color: #3282b8;
        }
        
        .hora-actual {
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
        }
        
        /* Grid mejorado */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .card-section {
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #eee;
        }
        
        .card-section:first-of-type {
            border-top: none;
        }
        
        .acciones-fichaje {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .acciones-fichaje form {
            flex: 1;
            min-width: 80px;
        }
        
        .acciones-fichaje button {
            width: 100%;
            padding: 8px;
            font-size: 0.85em;
        }
        
        /* Panel superior de resumen */
        .resumen-fichajes {
            background: #1b262c;
            color: white;
            padding: 20px;
            margin: 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .resumen-item {
            text-align: center;
        }
        
        .resumen-numero {
            font-size: 2.5em;
            font-weight: bold;
            color: #3282b8;
        }
        
        .resumen-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        /* MODAL PARA EDITAR FICHAJE */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.activo {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #1b262c;
        }
        
        .btn-cerrar-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3282b8;
            outline: none;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        .btn-guardar {
            background: #28a745;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-guardar:hover {
            background: #218838;
        }
        
        .info-empleado-modal {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-empleado-modal strong {
            color: #3282b8;
        }
    </style>
</head>
<body>
<?php include '../menu.php'; ?>

    <!-- RESUMEN DE FICHAJES HOY -->
    <?php
    $totalEmpleados = count($empleados);
    $dentro = count(array_filter($fichajesHoy, fn($f) => $f['hora_final'] === null));
    $fuera = count(array_filter($fichajesHoy, fn($f) => $f['hora_final'] !== null));
    $noFichado = $totalEmpleados - count($fichajesHoy);
    ?>
    
    <div class="resumen-fichajes">
        <div class="resumen-item">
            <div class="resumen-numero"><?= $dentro ?></div>
            <div class="resumen-label">🟢 Trabajando</div>
        </div>
        <div class="resumen-item">
            <div class="resumen-numero"><?= $fuera ?></div>
            <div class="resumen-label">🔴 Finalizado</div>
        </div>
        <div class="resumen-item">
            <div class="resumen-numero"><?= $noFichado ?></div>
            <div class="resumen-label">⚪ Sin fichar</div>
        </div>
        <div class="resumen-item">
            <a href="Fichar.php" class="btn-actualizar" style="display: inline-block; margin-top: 10px;">
                ⏱️ Panel de Fichaje
            </a>
        </div>
    </div>

    <h1 style="text-align: center; margin: 20px 0;">📅 Horarios y Control de Fichajes</h1>

    <!-- MENSAJE DE ESTADO -->
    <?php if (isset($_GET['status'])): ?>
        <div class="status-message" style="max-width: 800px; margin: 20px auto;">
            <?php 
            switch($_GET['status']){
                case 'success': echo '✅ Empleado creado correctamente'; break;
                case 'updated': echo '✅ Empleado actualizado correctamente'; break;
                case 'deleted': echo '⚠️ Empleado desactivado (borrado logico)'; break;
                case 'error': echo '❌ Error: ' . htmlspecialchars($_GET['message']); break;
                case 'fichaje_ok': echo '✅ Fichaje registrado/actualizado correctamente'; break;
            }
            ?>
        </div>
    <?php endif; ?>

    <h2 style="margin-left: 20px;">👥 Empleados</h2>
    
    <div class="cards-container">
        <?php foreach ($empleados as $emp): 
            $fichaje = $fichajesHoy[$emp['id']] ?? null;
            $stats = obtenerEstadisticasSemana($pdo, $emp['id']);
        ?>
            <div class="card">
                <h3><?= htmlspecialchars($emp['Empleado']) ?></h3>
                
                <!-- HORARIO TEÓRICO -->
                <div class="card-section">
                    <p><strong>🆔 Código:</strong> <?= htmlspecialchars($emp['id']) ?></p>
                    <p><strong>🕒 Turno:</strong> <?= htmlspecialchars($emp['Turno'] ?? 'Sin asignar') ?></p>
                    <p><strong>➡️ Entrada:</strong> <span class="hora-actual"><?= htmlspecialchars(substr($emp['Entrada'], 0, 5)) ?></span></p>
                    <p><strong>⬅️ Salida:</strong> <span class="hora-actual"><?= htmlspecialchars(substr($emp['Salida'], 0, 5)) ?></span></p>
                    <p><strong>⏸️ Pausa:</strong> <?= htmlspecialchars($emp['Pausa'] ?? '0') ?> h</p>
                </div>

                <!-- ESTADO DE FICHAJE HOY -->
                <div class="card-section">
                    <?php if ($fichaje): ?>
                        <?php if ($fichaje['hora_final'] === null): ?>
                            <!-- DENTRO (fichaje abierto) -->
                            <div class="fichaje-status status-dentro">
                                🟢 DENTRO desde <?= date('H:i', strtotime($fichaje['hora_inici'])) ?>
                                <br><small>📍 <?= htmlspecialchars($fichaje['ubicacio'] ?? 'No especificada') ?></small>
                            </div>
                            <form method="GET" action="Fichar.php">
                                <input type="hidden" name="id_treballador" value="<?= $emp['id'] ?>">
                                <button type="submit" class="btn-fichar btn-fichar-salida">
                                    ⬆️ FICHAR SALIDA
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- FUERA (fichaje cerrado) -->
                            <div class="fichaje-status status-fuera">
                                🔴 FUERA
                                <br>
                                <small>
                                    <?= date('H:i', strtotime($fichaje['hora_inici'])) ?> - 
                                    <?= date('H:i', strtotime($fichaje['hora_final'])) ?>
                                    <?= $fichaje['validat'] ? '✓' : '' ?>
                                </small>
                            </div>
                            <form method="GET" action="Fichar.php">
                                <input type="hidden" name="id_treballador" value="<?= $emp['id'] ?>">
                                <button type="submit" class="btn-fichar btn-fichar-entrada">
                                    ⬇️ NUEVO FICHAJE
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- NO FICHADO HOY -->
                        <div class="fichaje-status status-no-fichado">
                            ⚪ NO HA FICHADO HOY
                        </div>
                        <form method="GET" action="Fichar.php">
                            <input type="hidden" name="id_treballador" value="<?= $emp['id'] ?>">
                            <button type="submit" class="btn-fichar btn-fichar-entrada">
                                ⬇️ FICHAR ENTRADA
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- BOTÓN PARA EDITAR/CREAR FICHAJE MANUAL -->
                    <button type="button" class="btn-editar-fichaje" onclick="abrirModalFichaje(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['Empleado']) ?>', '<?= htmlspecialchars($emp['Entrada'] ?? '08:00') ?>', '<?= htmlspecialchars($emp['Salida'] ?? '17:00') ?>', '<?= htmlspecialchars($emp['Pausa'] ?? '1') ?>')">
                        ✏️ Editar/Crear Fichaje (Admin)
                    </button>
                </div>

                <!-- ESTADÍSTICAS SEMANA -->
                <div class="stats-semana">
                    <strong>📊 Esta semana:</strong><br>
                    <?= intval($stats['dias_trabajados']) ?> días | 
                    <?= number_format($stats['total_horas'] ?? 0, 1) ?> h trabajadas
                </div>

                <!-- ACCIONES -->
                <div class="card-section acciones-fichaje">
                    <form method="POST" action="../Personal/ControlPersonal.php"
                        onsubmit="return confirm('⚠️ ¿Estas seguro de desactivar este empleado?');">
                        <input type="hidden" name="delete:id" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn-borrar">🗑️</button>
                    </form>

                    <form method="GET" action="ActHoraris.php">
                        <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn-actualizar">✏️ Horario</button>
                    </form>
                    
                    <form method="GET" action="InformeHoras.php">
                        <input type="hidden" name="id_treballador" value="<?= $emp['id'] ?>">
                        <button type="submit" class="btn-actualizar" style="background: #6c757d;">
                            📊 Horas
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- MODAL PARA EDITAR/CREAR FICHAJE -->
    <div id="modalFichaje" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Editar/Crear Fichaje</h2>
                <button type="button" class="btn-cerrar-modal" onclick="cerrarModal()">×</button>
            </div>
            
            <div class="info-empleado-modal">
                <strong>Empleado:</strong> <span id="modalNombreEmpleado"></span><br>
                <strong>ID:</strong> <span id="modalIdEmpleado"></span>
            </div>
            
            <form method="POST" action="" id="formFichajeManual">
                <input type="hidden" name="id_treballador" id="modalIdTreballador">
                <input type="hidden" name="guardar_fichaje_manual" value="1">
                
                <div class="form-group">
                    <label for="fecha_fichaje">📅 Fecha del fichaje:</label>
                    <input type="date" name="fecha_fichaje" id="fecha_fichaje" required 
                           max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hora_entrada">➡️ Hora de entrada:</label>
                        <input type="time" name="hora_entrada" id="hora_entrada" required 
                               value="08:00" step="60">
                    </div>
                    <div class="form-group">
                        <label for="hora_salida">⬅️ Hora de salida:</label>
                        <input type="time" name="hora_salida" id="hora_salida" required 
                               value="17:00" step="60">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="pausa">⏸️ Pausa (horas):</label>
                    <input type="number" name="pausa" id="pausa" step="0.25" min="0" max="8" 
                           value="1" placeholder="Ej: 1 o 0.5">
                    <small style="color: #666;">Ejemplos: 1 = 1 hora, 0.5 = 30 minutos, 0.75 = 45 minutos</small>
                </div>
                
                <div class="form-group">
                    <label for="ubicacion">📍 Ubicación:</label>
                    <select name="ubicacion" id="ubicacion">
                        <option value="Oficina Principal">Oficina Principal</option>
                        <option value="Campo - Parcela 1">Campo - Parcela 1</option>
                        <option value="Campo - Parcela 2">Campo - Parcela 2</option>
                        <option value="Campo - Parcela 3">Campo - Parcela 3</option>
                        <option value="Almacén">Almacén</option>
                        <option value="Remoto">Remoto</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="incidencia">📝 Incidencias/Observaciones:</label>
                    <textarea name="incidencia" id="incidencia" rows="3" 
                              placeholder="Ej: Fichaje manual por olvido, llegada tarde, etc."></textarea>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="validar" id="validar" value="1" checked>
                    <label for="validar">✓ Marcar como validado</label>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em;">
                    <strong>⚠️ Nota:</strong> Si ya existe un fichaje para esta fecha, se actualizará con los nuevos datos.
                </div>
                
                <button type="submit" class="btn-guardar">
                    💾 Guardar Fichaje
                </button>
            </form>
        </div>
    </div>

    <script>
        function abrirModalFichaje(id, nombre, horaEntradaDefault, horaSalidaDefault, pausaDefault) {
            document.getElementById('modalIdTreballador').value = id;
            document.getElementById('modalNombreEmpleado').textContent = nombre;
            document.getElementById('modalIdEmpleado').textContent = id;
            
            // Prellenar con valores del horario del empleado
            document.getElementById('hora_entrada').value = horaEntradaDefault ? horaEntradaDefault.substring(0, 5) : '08:00';
            document.getElementById('hora_salida').value = horaSalidaDefault ? horaSalidaDefault.substring(0, 5) : '17:00';
            document.getElementById('pausa').value = pausaDefault || '1';
            
            // Calcular fecha por defecto (ayer si hoy no hay fichaje, o hoy)
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('fecha_fichaje').value = hoy;
            
            document.getElementById('modalFichaje').classList.add('activo');
        }
        
        function cerrarModal() {
            document.getElementById('modalFichaje').classList.remove('activo');
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalFichaje').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Validar que hora salida > hora entrada
        document.getElementById('formFichajeManual').addEventListener('submit', function(e) {
            const entrada = document.getElementById('hora_entrada').value;
            const salida = document.getElementById('hora_salida').value;
            
            if (salida <= entrada) {
                e.preventDefault();
                alert('⚠️ La hora de salida debe ser posterior a la hora de entrada');
                return false;
            }
            
            return true;
        });
    </script>

</body>
</html>