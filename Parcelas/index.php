<?php
require '../db.php';

// 1. Solo obtenemos las parcelas (eliminamos la consulta de sectores)
$parcelas = $pdo->query("SELECT * FROM parcelas WHERE activa = TRUE ORDER BY codigo")->fetchAll(PDO::FETCH_ASSOC);

// 2. Tipos de suelo para el select del formulario
$tipos_suelo = $pdo->query("SELECT * FROM tipos_suelo ORDER BY tipo")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🌳 Gestión de Parcelas y Cultivos</title>
    <link rel="stylesheet" href="../css/parcela.css">
    <link rel="stylesheet" href="../menu.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    
    <style>
        html { scroll-behavior: smooth; } /* Desplazamiento suave al pulsar */
        
        #map { 
            height: 600px; 
            margin-bottom: 20px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
        }
        
        /* --- ESTILOS DEL POPUP (PARA EVITAR SOLAPAMIENTOS) --- */
        .popup-container {
            min-width: 160px;
            padding: 5px;
        }

        .popup-titulo {
            display: block;
            color: #2d5a27;
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 8px;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
        }

        .popup-dato {
            display: block;
            font-size: 0.9em;
            color: #555;
            margin-bottom: 3px;
        }

        .btn-detalle-mapa {
            display: block;
            margin-top: 12px;
            padding: 8px;
            background: #2d5a27;
            color: white !important;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .btn-detalle-mapa:hover {
            background: #1e3d1a;
        }

        /* Efecto para resaltar la tarjeta al llegar */
        .card-highlight {
            animation: focus-card 2s ease;
            border: 2px solid #2d5a27 !important;
        }

        @keyframes focus-card {
            0% { background-color: #f1f8e9; transform: scale(1.02); }
            100% { background-color: white; transform: scale(1); }
        }
    </style>
</head>
<body>
    <?php include '../menu.php'; ?>
    <h1>🌳 Gestión de Parcelas y Cultivos</h1>

    <div id="map-container">
        <div id="map"></div>
    </div>

    <h2>➕ Nueva Parcela / Editar Parcela</h2>
    <form id="form-principal" method="POST" action="ControlParcelas.php" enctype="multipart/form-data">
        
        <h3>📍 Ubicación y Código</h3>
        <label>Código Parcela:</label>
        <input type="text" name="codigo" required placeholder="PAR-001" maxlength="20">
        
        <label>Nombre Descriptivo:</label>
        <input type="text" name="nombre" required placeholder="Sector Norte - Manzanos" maxlength="100">
        
        <label>Coordenadas (GeoJSON):</label>
        <textarea name="coordenadas_geojson" rows="4" placeholder='{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,1],[0,0]]]}'></textarea>
        <small>Dibuja el polígono en el mapa o pega GeoJSON</small>
        
        <label>Subir archivo KML/GeoJSON:</label>
        <input type="file" name="archivo_geo" accept=".kml,.geojson,.json">

        <h3>🌱 Características del Suelo</h3>
        <label>Tipo de Suelo:</label>
        <select name="tipo_suelo_id">
            <option value="">-- Seleccionar --</option>
            <?php foreach ($tipos_suelo as $ts): ?>
                <option value="<?= $ts['id'] ?>"><?= $ts['tipo'] ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>pH del Suelo:</label>
        <input type="number" name="ph" step="0.1" min="0" max="14" placeholder="6.5">
        
        <label>% Materia Orgánica:</label>
        <input type="number" name="materia_organica" step="0.01" min="0" max="100" placeholder="2.5">
        
        <label>Pendiente (%):</label>
        <input type="number" name="pendiente" step="0.01" placeholder="3.5">
        
        <label>Orientación:</label>
        <input type="text" name="orientacion" placeholder="Norte" maxlength="20">

        <h3>🏗️ Infraestructura</h3>
        <label>Infraestructura (JSON):</label>
        <textarea name="infraestructuras" rows="3" placeholder='{"riego":"goteo","caminos":"asfaltado","vallas":"electrificada"}'></textarea>

        <button type="submit">💾 Guardar Parcela</button>
    </form>

    <h2>📦 Parcelas Registradas</h2>
    <div class="cards-container">
        <?php foreach ($parcelas as $par): ?>
            <div class="card" id="parcela-<?= $par['id'] ?>">
                <h3>📍 <?= htmlspecialchars($par['nombre']) ?></h3>
                <p><strong>Código:</strong> <?= htmlspecialchars($par['codigo']) ?></p>
                <p><strong>Superficie:</strong> <?= $par['superficie_total_ha'] ? $par['superficie_total_ha'] . ' ha' : 'No calculada' ?></p>
                
                <div class="card-section">
                    <p><strong>Tipo Suelo:</strong> <?= $par['tipo_suelo_id'] ? 'Asignado' : 'Sin asignar' ?></p>
                    <p><strong>pH:</strong> <?= $par['ph'] ?? 'No registrado' ?></p>
                    <p><strong>Pendiente:</strong> <?= $par['pendiente'] ? $par['pendiente'] . '%' : 'Plana' ?></p>
                </div>

                <div class="card-actions">
                    <form method="POST" action="EliminarParcela.php" style="display:inline;" 
                          onsubmit="return confirm('⚠️ ¿Desactivar parcela?');">
                        <input type="hidden" name="id" value="<?= $par['id'] ?>">
                        <button type="submit" class="btn-borrar">🗑️ Borrar</button>
                    </form>
                    
                    <form method="GET" action="ActParcela.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $par['id'] ?>">
                        <button type="submit" class="btn-actualizar">✏️ Editar</button>
                    </form>

                    <form method="GET" action="DetalleParcela.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $par['id'] ?>">
                        <button type="submit" class="btn-detalle">📋 Detalle</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script>
    // Inicializar mapa satelital
    const map = L.map('map').setView([41.6297, 0.8955], 15);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Esri Satellite'
    }).addTo(map);

    const drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    function scrollAParcela(id) {
        const target = document.getElementById('parcela-' + id);
        if (target) {
            document.querySelectorAll('.card').forEach(c => c.classList.remove('card-highlight'));
            target.classList.add('card-highlight');
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    const parcelas = <?= json_encode($parcelas) ?>;
    
    parcelas.forEach(p => {
        if (p.coordenadas_geojson) {
            try {
                const geojson = JSON.parse(p.coordenadas_geojson);
                const layer = L.geoJSON(geojson, {
                    style: { color: '#e67e22', weight: 3, opacity: 0.8, fillOpacity: 0.3 }
                });

                // --- POPUP ACTUALIZADO SIN SOLAPAMIENTOS ---
                const popupContenido = `
                    <div class="popup-container">
                        <span class="popup-titulo">📍 ${p.nombre}</span>
                        <span class="popup-dato"><strong>Código:</strong> ${p.codigo}</span>
                        <span class="popup-dato"><strong>Superficie:</strong> ${p.superficie_total_ha || '---'} ha</span>
                        <a href="javascript:void(0)" onclick="scrollAParcela(${p.id})" class="btn-detalle-mapa">
                            🔍 Ver detalles en lista
                        </a>
                    </div>
                `;
                
                layer.bindPopup(popupContenido);
                layer.addTo(map);
            } catch(e) {
                console.error("Error en datos de parcela:", e);
            }
        }
    });

    if (parcelas.length > 0) {
        try {
            const firstBounds = L.geoJSON(JSON.parse(parcelas[0].coordenadas_geojson)).getBounds();
            map.fitBounds(firstBounds, { padding: [50, 50] });
        } catch(e) {}
    }

    const drawControl = new L.Control.Draw({
        edit: { featureGroup: drawnItems },
        draw: { polygon: true, polyline: false, rectangle: false, circle: false, marker: false, circlemarker: false }
    });
    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function(e) {
        drawnItems.clearLayers();
        drawnItems.addLayer(e.layer);
        document.querySelector('textarea[name="coordenadas_geojson"]').value = JSON.stringify(e.layer.toGeoJSON());
    });
</script>
</body>
</html>