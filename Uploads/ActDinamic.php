<?php
require '../db.php';

//Configuracion

$table = "treballadors"; // <-- Tabla BD
$idField = "id_treballador"; 
$id = $_GET["id"] ?? null;

if (!$id) die("❌ Falta el ID");

//1.-Obtener estructura de la tabla
function getTableFields($pdo, $table){
    $stmt = $pdo->query("DESCRIBE $table");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//2.-Obtener datos actuales del registro
function getRecord($pdo, $table, $idField, $id){
    $stmt = $pdo->prepare("Select * FROM $table WHERE $idField = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

//3.-Update dinamico
function updateRecord($pdo, $table, $idField, $id, $postData){
    $fields = [];
    $values = [];

    foreach ($postData as $field => $value){
        if($field == $idField) continue;
        $field[] = "$field = ?";
        $values[] = $value;
    }

    $values[] = $id;

    $sql = "UPDATE $table SET" . implode(", ", $fields) . " WHERE $idField = ?";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute($values);
}

//4.-Procesar actualizacio
$fields = getTableFields($pdo, $table);
$record = getRecord($pdo, $table, $idField, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (updateRecord($pdo, $table, $idField, $id, $_POST)) {
        echo "<p style='color:green;'>✔️ Actualizado correctamente</p>";
        $record = getRecord($pdo, $table, $idField, $id); // refrescar datos
    } else {
        echo "<p style='color:red;'>❌ Error al actualizar</p>";
    }
}
/* ============================================================
   5. Función para crear el formulario dinámico
   ============================================================ */
   function renderDynamicInput($field, $value) {

    $name = $field["Field"];
    $type = $field["Type"];
    $null = $field["Null"];

    // Ocultar autoincrement
    if ($field["Extra"] == "auto_increment") return;

    echo "<label><strong>$name</strong></label><br>";

    // Campos tipo TEXT
    if (strpos($type, "text") !== false) {
        echo "<textarea name='$name' rows='3'>$value</textarea><br><br>";
        return;
    }

    // Campos ENUM
    if (strpos($type, "enum") !== false) {
        preg_match("/enum\((.*)\)/", $type, $matches);
        $options = str_getcsv(str_replace("'", "", $matches[1]));

        echo "<select name='$name'>";
        foreach ($options as $opt) {
            $selected = ($value == $opt) ? "selected" : "";
            echo "<option value='$opt' $selected>$opt</option>";
        }
        echo "</select><br><br>";
        return;
    }

    // Detectar DATE
    if (strpos($type, "date") !== false) {
        echo "<input type='date' name='$name' value='$value'><br><br>";
        return;
    }

    // Detectar TIME
    if (strpos($type, "time") !== false) {
        echo "<input type='time' name='$name' value='$value'><br><br>";
        return;
    }

    // Detectar números
    if (preg_match('/int|decimal|float|double/', $type)) {
        echo "<input type='number' step='any' name='$name' value='$value'><br><br>";
        return;
    }

    // tinyint(1) → checkbox booleano
    if ($type === "tinyint(1)") {
        $checked = $value ? "checked" : "";
        echo "<input type='checkbox' name='$name' value='1' $checked> Activo<br><br>";
        return;
    }

    // Por defecto → texto
    echo "<input type='text' name='$name' value='$value'><br><br>";
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Editar dinámico</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        form { max-width: 600px; padding: 20px; background: #f5f5f5; border-radius: 8px; }
        input, select, textarea {
            width: 100%; padding: 7px; margin-top: 5px;
            border: 1px solid #ccc; border-radius: 5px;
        }
        button {
            padding: 10px 15px; border: none; border-radius: 5px;
            background: #1a73e8; color: white; cursor: pointer;
        }
        button:hover { background: #135bb5; }
    </style>
</head>
<body>

<h1>✏️ Editar registro dinámico</h1>

<form method="POST">

    <?php foreach ($fields as $field): ?>
        <?php renderDynamicInput($field, $record[$field["Field"]] ?? ""); ?>
    <?php endforeach; ?>

    <button type="submit">💾 Guardar cambios</button>
</form>

</body>
</html>


?>
