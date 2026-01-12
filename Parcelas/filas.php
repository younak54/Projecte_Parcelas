<?php
// 1. Configuración de errores y sesión (Debe ir al principio)
session_start();
require '../db.php';

$mensaje = "";

// 2. Recuperar mensaje de la sesión (Patrón PRG)
if (isset($_SESSION['mensaje_flash'])) {
    $mensaje = $_SESSION['mensaje_flash'];
    unset($_SESSION['mensaje_flash']);
}

// 3. Lógica de guardado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_fila'])) {
    try {
        $sector_id = $_POST['sector_id'];
        $variedad_id = $_POST['variedad_id'];
        $arboles = !empty($_POST['numero_arboles']) ? $_POST['numero_arboles'] : null;
        
        $geojson_data = json_decode($_POST['coordenadas_geojson'], true);

        if ($geojson_data && isset($geojson_data['features'])) {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO filas_arboles (sector_id, numero_fila, coordenadas_geojson, numero_arboles, variedad_id) 
                                   VALUES (:s, :n, :g, :a, :v)");

            foreach ($geojson_data['features'] as $index => $feature) {
                $num_fila_auto = (int)$_POST['numero_fila_inicio'] + $index;
                
                $stmt->execute([
                    ':s' => $sector_id,
                    ':n' => $num_fila_auto,
                    ':g' => json_encode($feature),
                    ':a' => $arboles,
                    ':v' => $variedad_id
                ]);
            }
            
            $pdo->commit();
            $_SESSION['mensaje_flash'] = "<div class='alert alert-success'>✅ Se han guardado ".count($geojson_data['features'])." filas correctamente.</div>";
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit(); 
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['mensaje_flash'] = "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 4. Carga de datos para el formulario
try {
    // IMPORTANTE: Verifica si tu columna es 'activo' o 'activa'
    $sectores = $pdo->query("SELECT id, nombre, coordenadas_geojson FROM sectores_cultivo WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
    $variedades = $pdo->query("SELECT id, nombre FROM variedades")->fetchAll(PDO::FETCH_ASSOC);
    $filas_existentes = $pdo->query("SELECT * FROM filas_arboles")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dibujado Múltiple de Filas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    <style>
        #map { height: 650px; width: 100%; border-radius: 12px; }
        .sidebar { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-3">
            <div class="sidebar">
                <h5 class="text-primary mb-3">Modo Dibujo Múltiple</h5>
                <?= $mensaje ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Sector</label>
                        <select name="sector_id" id="select_sector" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach($sectores as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= $s['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nº de la primera fila dibujada</label>
                        <input type="number" name="numero_fila_inicio" class="form-control" value="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Variedad</label>
                        <select name="variedad_id" class="form-select">
                            <?php foreach($variedades as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= $v['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Árboles (aprox por fila)</label>
                        <input type="number" name="numero_arboles" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="small text-muted">Datos acumulados:</label>
                        <textarea name="coordenadas_geojson" id="geojson_input" class="form-control bg-light" rows="4" readonly required></textarea>
                    </div>

                    <button type="submit" name="guardar_fila" class="btn btn-primary w-100 fw-bold">Guardar TODAS las filas</button>
                    <button type="button" onclick="location.reload()" class="btn btn-outline-secondary w-100 mt-2 btn-sm">Limpiar Dibujo</button>
                </form>
            </div>
        </div>
        <div class="col-lg-9"><div id="map"></div></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
    var map = L.map('map').setView([41.629752, 0.891348], 16);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}').addTo(map);

    var sectorLayer = L.layerGroup().addTo(map);
    var drawnItems = new L.FeatureGroup().addTo(map);

    var drawControl = new L.Control.Draw({
        draw: { polyline: { shapeOptions: { color: '#39ff14' } }, polygon:false, circle:false, marker:false, rectangle:false },
        edit: { featureGroup: drawnItems }
    });
    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function (e) {
        drawnItems.addLayer(e.layer);
        actualizarTextarea();
    });

    map.on(L.Draw.Event.EDITED, actualizarTextarea);
    map.on(L.Draw.Event.DELETED, actualizarTextarea);

    function actualizarTextarea() {
        var data = drawnItems.toGeoJSON();
        document.getElementById('geojson_input').value = JSON.stringify(data);
    }

    const listaSectores = <?= json_encode($sectores) ?>;

    document.getElementById('select_sector').addEventListener('change', function() {
        const id = this.value;
        const sector = listaSectores.find(s => s.id == id);
        sectorLayer.clearLayers();
        if (sector && sector.coordenadas_geojson) {
            const poly = L.geoJSON(JSON.parse(sector.coordenadas_geojson), {style:{color:'white', weight:1, fillOpacity:0.1}}).addTo(sectorLayer);
            map.fitBounds(poly.getBounds());
        }
    });

    // Cargar existentes
    <?php foreach($filas_existentes as $fila): ?>
        try { 
            L.geoJSON(<?= $fila['coordenadas_geojson'] ?>, {
                style: {color:'#ffcc00', weight:2}
            }).addTo(map); 
        } catch(e){}
    <?php endforeach; ?>
</script>
</body>
</html>