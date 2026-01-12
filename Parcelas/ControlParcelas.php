<?php
require '../db.php';

// Función para calcular superficie aproximada desde GeoJSON
function calcularSuperficieHectareas($geojson) {
    $data = is_array($geojson) ? $geojson : json_decode($geojson, true);
    if (!$data) return 0;

    $areaTotalM2 = 0;

    // 1. Extraer todos los Polígonos según el tipo de GeoJSON
    $polygons = [];
    if ($data['type'] === 'FeatureCollection') {
        foreach ($data['features'] as $f) {
            if ($f['geometry']['type'] === 'Polygon') $polygons[] = $f['geometry']['coordinates'];
            if ($f['geometry']['type'] === 'MultiPolygon') {
                foreach ($f['geometry']['coordinates'] as $p) $polygons[] = $p;
            }
        }
    } elseif ($data['type'] === 'MultiPolygon') {
        $polygons = $data['coordinates'];
    } elseif ($data['type'] === 'Polygon') {
        $polygons = [$data['coordinates']];
    }

    // 2. Calcular el área de cada polígono
    foreach ($polygons as $rings) {
        // El primer anillo (index 0) es el exterior
        $areaTotalM2 += calcularAreaAnillo($rings[0]);
        
        // Los siguientes anillos (si existen) son huecos, se restan
        for ($i = 1; $i < count($rings); $i++) {
            $areaTotalM2 -= calcularAreaAnillo($rings[$i]);
        }
    }

    // Convertir de m² a Hectáreas (1 Ha = 10,000 m²)
    return round(abs($areaTotalM2) / 10000, 4);
}

/**
 * Calcula el área de un anillo (array de puntos) en metros cuadrados
 * usando la fórmula de área esférica (Spherical Geometry).
 */
function calcularAreaAnillo($coords) {
    $radius = 6378137; // Radio de la Tierra en metros
    $area = 0;
    $len = count($coords);

    if ($len < 3) return 0;

    for ($i = 0; $i < $len; $i++) {
        $p1 = $coords[$i];
        $p2 = $coords[($i + 1) % $len]; // El siguiente punto (cerrando el ciclo)

        // Convertir a radianes y aplicar fórmula esférica
        $area += deg2rad($p2[0] - $p1[0]) * (2 + sin(deg2rad($p1[1])) + sin(deg2rad($p2[1])));
    }

    return ($area * $radius * $radius) / 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recoger datos del formulario
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $coordenadas_geojson = trim($_POST['coordenadas_geojson'] ?? null);
        $tipo_suelo_id = $_POST['tipo_suelo_id'] ?: null;
        $ph = $_POST['ph'] ?: null;
        $materia_organica = $_POST['materia_organica'] ?: null;
        $pendiente = $_POST['pendiente'] ?: null;
        $orientacion = trim($_POST['orientacion'] ?? null);
        $infraestructuras = trim($_POST['infraestructuras'] ?? null);

        // Calcular superficie si no viene del formulario
        if (empty($_POST['superficie_total_ha']) || $_POST['superficie_total_ha'] == '0.0000') {
            $superficie_total_ha = !empty($coordenadas_geojson) 
                ? calcularSuperficieHectareas($coordenadas_geojson) 
                : null;
        } else {
            $superficie_total_ha = $_POST['superficie_total_ha'];
        }

        // Convertir cadenas vacías a NULL
        foreach (['coordenadas_geojson', 'orientacion', 'infraestructuras'] as $k) {
            if ($$k === '') $$k = null;
        }

        // INSERT con ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO parcelas 
                    (codigo, nombre, coordenadas_geojson, superficie_total_ha, tipo_suelo_id, 
                     ph, materia_organica, pendiente, orientacion, infraestructuras, fecha_alta)
                VALUES
                    (:codigo, :nombre, :coordenadas_geojson, :superficie_total_ha, :tipo_suelo_id, 
                     :ph, :materia_organica, :pendiente, :orientacion, :infraestructuras, NOW())
                ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre),
                    coordenadas_geojson = VALUES(coordenadas_geojson),
                    superficie_total_ha = VALUES(superficie_total_ha),
                    tipo_suelo_id = VALUES(tipo_suelo_id),
                    ph = VALUES(ph),
                    materia_organica = VALUES(materia_organica),
                    pendiente = VALUES(pendiente),
                    orientacion = VALUES(orientacion),
                    infraestructuras = VALUES(infraestructuras)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':coordenadas_geojson' => $coordenadas_geojson,
            ':superficie_total_ha' => $superficie_total_ha,
            ':tipo_suelo_id' => $tipo_suelo_id,
            ':ph' => $ph,
            ':materia_organica' => $materia_organica,
            ':pendiente' => $pendiente,
            ':orientacion' => $orientacion,
            ':infraestructuras' => $infraestructuras
        ]);

        header("Location: index.php?status=success");
        exit;

    } catch (PDOException $e) {
        // Mostrar error para depuración
        echo "<h2>Error en la base de datos:</h2>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        echo "<h3>Datos enviados:</h3>";
        echo "<pre>" . print_r($_POST, true) . "</pre>";
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}