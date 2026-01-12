<?php
require '../db.php'; // Tu conexión PDO

$mensaje = "";

// 1. CARGAR LAS PARCELAS EXISTENTES (Para sacar las coordenadas)
try {
    // Consultamos la tabla 'parcelas' que es donde tienes los perímetros base
    $stmtParcelas = $pdo->query("SELECT id, nombre, coordenadas_geojson FROM parcelas");
    $fuente_parcelas = $stmtParcelas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "<div class='alert alert-warning'>Nota: Asegúrate de que la tabla 'parcelas' tenga datos. Error: " . $e->getMessage() . "</div>";
    $fuente_parcelas = [];
}

// 2. PROCESAR EL GUARDADO EN sectores_cultivo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_sector'])) {
    try {
        $sql = "INSERT INTO sectores_cultivo (codigo, nombre, coordenadas_geojson, superficie_efectiva_ha, fecha_creacion, activo) 
                VALUES (:codigo, :nombre, :geojson, :superficie, :fecha, :activo)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo'     => $_POST['codigo'],
            ':nombre'     => $_POST['nombre'],
            ':geojson'    => $_POST['coordenadas_geojson'],
            ':superficie' => $_POST['superficie'],
            ':fecha'      => $_POST['fecha_creacion'],
            ':activo'     => isset($_POST['activo']) ? 1 : 0
        ]);

        $mensaje = "<div class='alert alert-success'>✅ Nuevo Sector de Cultivo guardado correctamente.</div>";
    } catch (PDOException $e) {
        $mensaje = "<div class='alert alert-danger'>❌ Error al guardar: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Sector de Cultivo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 450px; width: 100%; border-radius: 12px; border: 2px solid #ddd; }
        .form-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .header-section { background: #2d5a27; color: white; padding: 20px; border-radius: 15px 15px 0 0; margin: -30px -30px 25px -30px; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="form-card">
                <div class="header-section">
                    <h3 class="mb-0">🌱 Definir Nuevo Sector de Cultivo</h3>
                    <p class="mb-0 opacity-75">Asigna una parcela física a un sector de producción</p>
                </div>

                <?php echo $mensaje; ?>

                <form method="POST">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <label class="form-label fw-bold text-success">1. Seleccionar Parcela de Origen (Geometría)</label>
                            <select id="selector_parcela" class="form-select form-select-lg border-success" required>
                                <option value="">-- Elige una parcela de la tabla 'parcelas' --</option>
                                <?php foreach($fuente_parcelas as $p): ?>
                                    <option value='<?= htmlspecialchars($p['coordenadas_geojson'], ENT_QUOTES) ?>'>
                                        <?= htmlspecialchars($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Al seleccionar una, se copiarán sus coordenadas automáticamente.</div>
                        </div>

                        <div class="col-12 mb-4">
                            <div id="map"></div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Código Identificador</label>
                            <input type="text" name="codigo" class="form-control" placeholder="Ej: SEC-001" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Nombre del Sector de Cultivo</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Sector Olivos Jóvenes" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Superficie Efectiva (Ha)</label>
                            <input type="number" step="0.0001" name="superficie" class="form-control" required placeholder="0.0000">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Fecha de Inicio/Creación</label>
                            <input type="date" name="fecha_creacion" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="activo" id="activo" checked>
                                <label class="form-check-label fw-bold" for="activo">Sector en Producción (Activo)</label>
                            </div>
                        </div>

                        <div class="col-12 mb-4">
                            <label class="form-label small fw-bold text-muted">GeoJSON Copiado de Parcela</label>
                            <textarea name="coordenadas_geojson" id="geojson_field" class="form-control bg-light" rows="2" readonly required placeholder="Selecciona una parcela arriba para rellenar este campo"></textarea>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">Volver</button>
                        <button type="submit" name="guardar_sector" class="btn btn-success px-5 fw-bold">Guardar en sectores_cultivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Iniciar Mapa centrado en una vista general
    var map = L.map('map').setView([41.341, 1.523], 14);

    // Capa Satélite
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Esri Satellite'
    }).addTo(map);

    var layerParcela = L.geoJSON().addTo(map);

    // Lógica para cuando el usuario elige una parcela del desplegable
    document.getElementById('selector_parcela').addEventListener('change', function() {
        const geojsonString = this.value;
        const textarea = document.getElementById('geojson_field');
        
        // Limpiar lo anterior
        layerParcela.clearLayers();
        textarea.value = "";

        if (geojsonString && geojsonString !== "") {
            try {
                // 1. Copiar el valor al textarea para que PHP lo reciba
                textarea.value = geojsonString;

                // 2. Parsear y dibujar en el mapa
                const geoData = JSON.parse(geojsonString);
                layerParcela = L.geoJSON(geoData, {
                    style: { color: '#2ecc71', weight: 4, fillOpacity: 0.3 }
                }).addTo(map);

                // 3. Hacer zoom a la parcela seleccionada
                map.fitBounds(layerParcela.getBounds(), { padding: [30, 30] });
            } catch (e) {
                console.error("Error al leer coordenadas:", e);
                alert("Las coordenadas de esta parcela no tienen un formato válido.");
            }
        }
    });
</script>

</body>
</html>