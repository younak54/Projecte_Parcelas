<?php
// Detectar módulo actual desde la URL
$script_actual = $_SERVER['SCRIPT_NAME'];
$modulo_activo = 'inicio'; // por defecto

if (strpos($script_actual, '/personal/') !== false) {
    $modulo_activo = 'Personal';
} elseif (strpos($script_actual, '/parcelas/') !== false) {
    $modulo_activo = 'Parcelas';
} elseif (strpos($script_actual, '/dashboard') !== false) {
    $modulo_activo = 'dashboard';
} elseif (strpos($script_actual, '/configuracion') !== false) {
    $modulo_activo = 'configuracion';
}
?>
<link rel="stylesheet" href="/pro/css/menu.css">
<nav class="menu-superior">
    <div class="logo-sistema">🌾 AgriManager</div>
    
    <ul class="menu-enlaces">
        <li>
            <a href="/pro/index.php" class="<?= $modulo_activo === 'inicio' ? 'activo' : '' ?>">
                <i>🏠</i> <span>Inicio</span>
            </a>
        </li>
        <li>
            <a href="/pro/RRHH/menuP.php" class="<?= $modulo_activo === 'Personal' ? 'activo' : '' ?>">
                <i>👥</i> <span>Gestión Personal</span>
            </a>
        </li>
        <li>
            <a href="/pro/Parcelas/index.php" class="<?= $modulo_activo === 'Parcelas' ? 'activo' : '' ?>">
                <i>🌳</i> <span>Gestión Parcelas</span>
            </a>
        </li>
        <li>
            <a href="#" onclick="alert('Dashboard en desarrollo'); return false;" class="<?= $modulo_activo === 'dashboard' ? 'activo' : '' ?>">
                <i>📊</i> <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="#" onclick="alert('Configuración en desarrollo'); return false;" class="<?= $modulo_activo === 'configuracion' ? 'activo' : '' ?>">
                <i>⚙️</i> <span>Configuración</span>
            </a>
        </li>
    </ul>
    
    <div class="usuario-menu">
        <span>👨‍🌾</span>
        <span>Usuario</span>
    </div>
</nav>