<?php
require '../db.php';

if (!isset($_GET['id'])) { die("Parcela no especificada"); }

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM parcelas WHERE id = ?");
$stmt->execute([$id]);
$parcela = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parcela) { die("Parcela no encontrada"); }
$tipos_suelo = $pdo->query("SELECT * FROM tipos_suelo ORDER BY tipo")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Parcela: <?= htmlspecialchars($parcela['nombre']) ?></title>
    <link rel="stylesheet" href="../css/parcela.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    
    <style>
        #map { height: 500px; width: 100%; border-radius: 12px; border: 2px solid #2d5a27; margin-bottom: 20px; }
        .map-controls { background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 10px; border-left: 5px solid #2d5a27; }
    </style>
</head>
<body>
    <h1>✏️ Editar Parcela: <?= htmlspecialchars($parcela['nombre']) ?></h1>

    <div id="map"></div>

    <form id="form-principal" method="POST" action="ControlParcelas.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $parcela['id'] ?>">

        <div class="seccion">
            <h3>📍 Ubicación y Geometría</h3>
            <label>Código Parcela:</label>
            <input type="text" name="codigo" required value="<?= htmlspecialchars($parcela['codigo']) ?>">

            <label>Nombre:</label>
            <input type="text" name="nombre" required value="<?= htmlspecialchars($parcela['nombre']) ?>">

            <label>Coordenadas (GeoJSON):</label>
            <textarea name="coordenadas_geojson" id="geojson_input" rows="4" readonly required><?= htmlspecialchars($parcela['coordenadas_geojson']) ?></textarea>
        </div>

        <div class="seccion">
            <h3>🌱 Datos de Campo</h3>
            <label>Tipo de Suelo:</label>
            <select name="tipo_suelo_id">
                <?php foreach ($tipos_suelo as $ts): ?>
                    <option value="<?= $ts['id'] ?>" <?= $ts['id'] == $parcela['tipo_suelo_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ts['tipo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label>pH:</label>
            <input type="number" name="ph" step="0.1" value="<?= htmlspecialchars($parcela['ph']) ?>">
        </div>

        <button type="submit" style="background:#2d5a27; color:white; padding:15px; width:100%; cursor:pointer;">
            💾 GUARDAR CAMBIOS ACTUALIZADOS
        </button>
    </form>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

    <script>
        // 1. Iniciar mapa con capa Satélite
        var map = L.map('map').setView([0, 0], 2);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}').addTo(map);

        // 2. Grupo donde vive la parcela editable
        var drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        // 3. CARGAR PARCELA ACTUAL DESDE EL TEXTAREA
        var geojsonTexto = document.getElementById('geojson_input').value;
        
        if (geojsonTexto) {
            try {
                var geojsonData = JSON.parse(geojsonTexto);
                var capaActual = L.geoJSON(geojsonData);
                
                // Pasamos la parcela al grupo de edición
                capaActual.eachLayer(function(layer) {
                    drawnItems.addLayer(layer);
                });

                // Zoom automático a la parcela
                map.fitBounds(drawnItems.getBounds(), { padding: [50, 50] });
            } catch (e) {
                console.error("Error al cargar la parcela existente:", e);
            }
        }

        // 4. Configurar herramientas de dibujo y edición
        var drawControl = new L.Control.Draw({
            edit: {
                featureGroup: drawnItems, // Esto permite editar lo que ya está en el mapa
                remove: true
            },
            draw: {
                polygon: { shapeOptions: { color: '#2d5a27' } },
                polyline: false, circle: false, marker: false, rectangle: true
            }
        });
        map.addControl(drawControl);

        // 5. FUNCIÓN PARA ACTUALIZAR EL FORMULARIO
        function actualizarInput() {
            var data = drawnItems.toGeoJSON();
            // Convertimos la colección de vuelta a String para el input
            document.getElementById('geojson_input').value = JSON.stringify(data);
        }

        // EVENTOS
        map.on(L.Draw.Event.CREATED, function (e) {
            drawnItems.addLayer(e.layer);
            actualizarInput();
        });

        map.on(L.Draw.Event.EDITED, function () {
            actualizarInput();
        });

        map.on(L.Draw.Event.DELETED, function () {
            actualizarInput();
        });
    </script>
</body>
</html>