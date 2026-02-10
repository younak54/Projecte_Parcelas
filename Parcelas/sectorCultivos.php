<?php
session_start();
require '../db.php'; 

// --- 1. PROCESAR ACCIONES ---

// A. Guardar Nuevo Sector
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_sector'])) {
    try {
        $sql = "INSERT INTO sectores_cultivo (codigo, nombre, coordenadas_geojson, superficie_efectiva_ha, fecha_creacion, activo, parcela_id) 
                VALUES (:codigo, :nombre, :geojson, :superficie, :fecha, :activo, :parcela_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo'     => $_POST['codigo'],
            ':nombre'     => $_POST['nombre'],
            ':geojson'    => $_POST['coordenadas_geojson'],
            ':superficie' => $_POST['superficie'],
            ':fecha'      => $_POST['fecha_creacion'],
            ':activo'     => isset($_POST['activo']) ? 1 : 0,
            ':parcela_id' => $_POST['parcela_id']
        ]);
        $_SESSION['mensaje_flash'] = "<div class='alert alert-success'>✅ Sector guardado correctamente.</div>";
        header("Location: " . $_SERVER['PHP_SELF']); exit();
    } catch (PDOException $e) { $mensaje = "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>"; }
}

// B. Actualizar Sector (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_sector') {
    try {
        $sql = "UPDATE sectores_cultivo SET coordenadas_geojson = :geojson, superficie_efectiva_ha = :superficie WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':geojson' => $_POST['geojson'], ':superficie' => $_POST['superficie'], ':id' => $_POST['id']]);
        echo json_encode(['status' => 'success']); exit();
    } catch (PDOException $e) { echo json_encode(['status' => 'error']); exit(); }
}

// C. Eliminar Sector (AJAX) - CORREGIDO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_sector') {
    try {
        $sql = "DELETE FROM sectores_cultivo WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $_POST['id']]); // Aquí pasamos el ID real
        echo json_encode(['status' => 'success']); exit();
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit();
    }
}

if (isset($_SESSION['mensaje_flash'])) { $mensaje = $_SESSION['mensaje_flash']; unset($_SESSION['mensaje_flash']); }

// --- 2. CARGAR Y AGRUPAR DATOS ---
try {
    $parcelas = $pdo->query("SELECT id, nombre, coordenadas_geojson FROM parcelas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $sectores_raw = $pdo->query("SELECT * FROM sectores_cultivo ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

    $sectores_por_parcela = [];
    foreach ($sectores_raw as $s) {
        $p_id = $s['parcela_id'];
        if ($p_id) { $sectores_por_parcela[$p_id][] = $s; }
    }
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Sectores Geográficos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    <style>
        body { background-color: #f4f7f6; }
        #map_dibujo, #map_visor { height: 450px; width: 100%; border-radius: 12px; border: 2px solid #ddd; }
        .form-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .header-section { background: white; color: white; padding: 20px; border-radius: 15px 15px 0 0; margin: -30px -30px 25px -30px; }
        .parcela-row { background: #e8f5e9; font-weight: bold; color: #1b5e20; }
        .sector-row { padding-left: 40px !important; font-size: 0.95em; }
        .btn-focus { cursor: pointer; color: #1976d2; text-decoration: none; font-weight: bold; }
        .mb-0{color: black;}
    </style>
</head>
<body>

<div class="container py-5">
    <div class="form-card">
        <div class="header-section">
            <h3 class="mb-0">🌱 Definir Nuevo Sector</h3>
        </div>
        <?= $mensaje ?? '' ?>
        <form method="POST">
            <div class="row mt-4">
                <div class="col-md-4">
                    <label class="fw-bold text-success">1. Seleccionar Parcela Padre</label>
                    <select name="parcela_id" id="selector_parcela" class="form-select mb-3" required>
                        <option value="">-- Seleccionar Parcela --</option>
                        <?php foreach($parcelas as $p): ?>
                            <option value="<?= $p['id'] ?>" data-geojson='<?= htmlspecialchars($p['coordenadas_geojson'], ENT_QUOTES) ?>'>
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="bg-light p-3 rounded border">
                        <input type="text" name="codigo" class="form-control mb-2" placeholder="Código" required>
                        <input type="text" name="nombre" class="form-control mb-2" placeholder="Nombre" required>
                        <div class="input-group mb-2">
                            <input type="number" step="0.0001" name="superficie" id="superficie_input" class="form-control" required>
                            <span class="input-group-text">Ha</span>
                        </div>
                        <input type="date" name="fecha_creacion" class="form-control mb-2" value="<?= date('Y-m-d') ?>">
                        <div class="form-check form-switch bg-light p-3 rounded border mb-2">
                            <input class="form-check-input ms-0" type="checkbox" name="activo" id="check_activo" value="1" checked>
                            <label class="form-check-label fw-bold ms-2" for="check_activo">
                                Estado: <span id="status_text" class="text-success">Activo</span>
                            </label>
                        </div>
                    </div>
                    <textarea name="coordenadas_geojson" id="geojson_field" style="display:none;" required></textarea>
                    <button type="submit" name="guardar_sector" class="btn btn-success w-100 mt-4 fw-bold shadow-sm">Guardar Nuevo Sector</button>
                </div>
                <div class="col-md-8"><div id="map_dibujo"></div></div>
            </div>
        </form>
    </div>

    <div class="form-card">
        <div class="header-section" ><h3 class="mb-0">🗺️ Visor y Editor</h3></div>
        <div id="map_visor" class="mt-4"></div>
    </div>

    <div class="form-card">
        <div class="header-section" style="background: #444;"><h3 class="mb-0">📋 Inventario</h3></div>
        <div class="table-responsive mt-3">
            <table class="table table-hover border align-middle">
                <thead class="table-dark">
                    <tr><th>Nombre</th><th>Código</th><th>Superficie</th><th class="text-center">Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($parcelas as $p): ?>
                        <tr class="parcela-row">
                            <td colspan="3">📍 <?= htmlspecialchars($p['nombre']) ?></td>
                            <td class="text-center"><span class="btn-focus" onclick="enfocarElemento('parcela', <?= $p['id'] ?>)">🔍 Ver</span></td>
                        </tr>
                        <?php if (isset($sectores_por_parcela[$p['id']])): ?>
                            <?php foreach ($sectores_por_parcela[$p['id']] as $s): ?>
                                <tr>
                                    <td class="sector-row">↳ Sector: <?= htmlspecialchars($s['nombre']) ?></td>
                                    <td><code><?= htmlspecialchars($s['codigo']) ?></code></td>
                                    <td><?= $s['superficie_efectiva_ha'] ?> Ha</td>
                                    <td class="text-center">
                                        <span class="btn-focus me-3" onclick="enfocarElemento('sector', <?= $s['id'] ?>)">🎯 Enfocar</span>
                                        <a href="#" class="text-danger fw-bold" onclick="borrarSector(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>')">🗑️ Borrar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
    const tileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    const capasParcelas = {};
    const capasSectores = {};

    // --- MAPA 1 ---
    var mapDibujo = L.map('map_dibujo').setView([41.6297, 0.8955], 15);
    L.tileLayer(tileUrl).addTo(mapDibujo);
    var layerRef = L.geoJSON().addTo(mapDibujo);
    var drawnItemsNew = new L.FeatureGroup().addTo(mapDibujo);
    new L.Control.Draw({ edit: { featureGroup: drawnItemsNew }, draw: { polygon: true, polyline: false, circle: false, marker: false, rectangle: true } }).addTo(mapDibujo);

    document.getElementById('selector_parcela').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (option.dataset.geojson) {
            layerRef.clearLayers();
            const geo = JSON.parse(option.dataset.geojson);
            layerRef = L.geoJSON(geo, { style: { color: '#3498db', weight: 2, fillOpacity: 0.1, dashArray: '5,10' } }).addTo(mapDibujo);
            mapDibujo.fitBounds(layerRef.getBounds());
        }
    });

    mapDibujo.on(L.Draw.Event.CREATED, (e) => {
        drawnItemsNew.clearLayers();
        drawnItemsNew.addLayer(e.layer);
        document.getElementById('geojson_field').value = JSON.stringify(e.layer.toGeoJSON());
        let area = L.GeometryUtil.geodesicArea(e.layer.getLatLngs()[0]);
        document.getElementById('superficie_input').value = (area / 10000).toFixed(4);
    });

    // --- MAPA 2 ---
    var mapVisor = L.map('map_visor').setView([41.6297, 0.8955], 15);
    L.tileLayer(tileUrl).addTo(mapVisor);
    var drawnItemsVisor = new L.FeatureGroup().addTo(mapVisor);
    new L.Control.Draw({ edit: { featureGroup: drawnItemsVisor, remove: false }, draw: false }).addTo(mapVisor);

    const parcelasJS = <?= json_encode($parcelas) ?>;
    parcelasJS.forEach(p => {
        capasParcelas[p.id] = L.geoJSON(JSON.parse(p.coordenadas_geojson), { style: { color: 'white', weight: 1, fillOpacity: 0.05, interactive: false } }).addTo(mapVisor);
    });

    const sectoresJS = <?= json_encode($sectores_raw) ?>;
    sectoresJS.forEach(s => {
        if (s.coordenadas_geojson) {
            let g = L.geoJSON(JSON.parse(s.coordenadas_geojson), { style: { color: s.activo == 1 ? '#2ecc71' : '#e74c3c', weight: 2, fillOpacity: 0.4 } });
            g.eachLayer(layer => {
                layer.db_id = s.id;
                layer.db_nombre = s.nombre;
                layer.db_codigo = s.codigo;
                layer.bindPopup(`<strong>${s.nombre}</strong><br>Área: ${s.superficie_efectiva_ha} Ha`);
                drawnItemsVisor.addLayer(layer);
                capasSectores[s.id] = layer;
            });
        }
    });

    function enfocarElemento(tipo, id) {
        const capa = (tipo === 'sector') ? capasSectores[id] : capasParcelas[id];
        if (capa) { mapVisor.fitBounds(capa.getBounds(), { padding: [50, 50] }); if(tipo === 'sector') capa.openPopup(); }
    }

    // CORRECCIÓN: Coma en lugar de punto en los parámetros
    function borrarSector(id, nombre) {
        if (confirm(`¿Estás seguro de que quieres eliminar el sector "${nombre}"?`)) {
            let formData = new FormData();
            formData.append('action', 'delete_sector');
            formData.append('id', id);

            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            }).catch(err => console.error('Error:', err));
        }
    }

    mapVisor.on(L.Draw.Event.EDITED, function (e) {
        e.layers.eachLayer(function (layer) {
            let area = L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]);
            let formData = new FormData();
            formData.append('action', 'update_sector');
            formData.append('id', layer.db_id);
            formData.append('geojson', JSON.stringify(layer.toGeoJSON()));
            formData.append('superficie', (area / 10000).toFixed(4));
            fetch(window.location.href, { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.status === 'success') alert('Actualizado'); });
        });
    });

    document.getElementById('check_activo').addEventListener('change', function() {
        const statusText = document.getElementById('status_text');
        if(this.checked) {
            statusText.innerText = "Activo";
            statusText.className = "text-success";
        } else {
            statusText.innerText = "Desactivado";
            statusText.className = "text-danger";
        }
    });
</script>
</body>
</html> 