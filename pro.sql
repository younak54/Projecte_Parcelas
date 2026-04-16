-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-04-2026 a las 13:05:40
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `pro`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertes`
--

CREATE TABLE `alertes` (
  `id_alerta` int(11) NOT NULL,
  `id_treballador` int(11) DEFAULT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `taula_referencia` varchar(50) DEFAULT NULL,
  `tipus_alerta` enum('VENCIMENT_DOCUMENT','VENCIMENT_CONTRAT','VENCIMENT_CERTIFICACIO','TRACTAMENT_PENDENT','PLAGA_DETECTADA','ESTOC_MINIM','COSECHA_PREVISTA') NOT NULL,
  `data_generacio` date NOT NULL,
  `data_venciment` date NOT NULL,
  `missatge` text NOT NULL,
  `urgencia` enum('BAIXA','MITJA','ALTA','CRITICA') DEFAULT 'MITJA',
  `resolta` tinyint(1) DEFAULT 0,
  `id_usuari_resolucio` int(11) DEFAULT NULL,
  `data_resolucio` timestamp NULL DEFAULT NULL,
  `observacions_resolucio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alertes`
--

INSERT INTO `alertes` (`id_alerta`, `id_treballador`, `id_referencia`, `taula_referencia`, `tipus_alerta`, `data_generacio`, `data_venciment`, `missatge`, `urgencia`, `resolta`, `id_usuari_resolucio`, `data_resolucio`, `observacions_resolucio`, `created_at`) VALUES
(1, 1, 101, 'documentacio', 'VENCIMENT_DOCUMENT', '2025-11-29', '2025-12-02', 'Document d\'identitat del treballador venç demà. Cal renovar immediatament.', 'CRITICA', 0, NULL, NULL, NULL, '2025-12-01 09:09:40'),
(2, 6, 201, 'monitoratge_plagues', 'PLAGA_DETECTADA', '2025-12-01', '2025-12-04', 'Pulgó verd detectat al sector SEC-001. Requereix tractament urgent en 72h.', 'ALTA', 0, NULL, NULL, NULL, '2025-12-01 09:09:50'),
(4, 1, 401, 'registre_collites', 'COSECHA_PREVISTA', '2025-11-21', '2025-11-29', 'Cosecha de Golden Delicious completada.', 'BAIXA', 1, NULL, NULL, NULL, '2025-12-01 09:10:03'),
(5, 6, 501, 'tasques', 'TRACTAMENT_PENDENT', '2025-11-30', '2025-12-03', 'Tractament preventiu de fong pendent al sector SEC-002. Finestra òptima propera.', 'ALTA', 0, NULL, NULL, NULL, '2025-12-01 09:10:10'),
(8, 16, 3, 'documentacio', 'VENCIMENT_DOCUMENT', '2025-12-01', '2025-12-03', 'Document \'Certificado Manejo Fitosanitario\' vence en 2 dies', 'CRITICA', 0, NULL, NULL, NULL, '2025-12-01 09:42:45'),
(9, NULL, 1, 'stock_herbicidas', 'ESTOC_MINIM', '2025-12-01', '2025-12-08', 'Stock baix: TEST GLIFOSAT (3.50 uds)', 'ALTA', 0, NULL, NULL, NULL, '2025-12-01 09:42:45');

--
-- Disparadores `alertes`
--
DELIMITER $$
CREATE TRIGGER `trg_alertes_resolta_to_historial` AFTER UPDATE ON `alertes` FOR EACH ROW BEGIN
  IF OLD.resolta = 0 AND NEW.resolta = 1 THEN
    INSERT INTO alertes_historial (
      id_alerta_original, 
      tipus_alerta, 
      data_generacio, 
      id_usuari_resolucio,
      accio_realitzada
    ) VALUES (
      OLD.id_alerta,
      OLD.tipus_alerta,
      OLD.created_at,
      (SELECT id_user FROM users_app WHERE actiu = 1 LIMIT 1), -- S'hauria de passar l'usuari real
      CONCAT('Alerta resolta: ', OLD.missatge)
    );
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertes_historial`
--

CREATE TABLE `alertes_historial` (
  `id_historial` int(11) NOT NULL,
  `id_alerta_original` int(11) NOT NULL,
  `tipus_alerta` varchar(100) NOT NULL,
  `data_generacio` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_resolucio` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_usuari_resolucio` int(11) NOT NULL,
  `accio_realitzada` text NOT NULL,
  `temps_resposta_hores` decimal(10,2) GENERATED ALWAYS AS (timestampdiff(SECOND,`data_generacio`,`data_resolucio`) / 3600.0) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertes_sistema`
--

CREATE TABLE `alertes_sistema` (
  `id_alerta` int(11) NOT NULL,
  `tipus_alerta` enum('VENCIMENT_CONTRAT','VENCIMENT_CERTIFICACIO','VENCIMENT_PRODUCTE','TRACTAMENT_PENDENT','ESTOC_MINIM','PLAGA_DETECTADA') NOT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `taula_referencia` varchar(50) DEFAULT NULL,
  `missatge` text NOT NULL,
  `data_generacio` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_venciment` date DEFAULT NULL,
  `urgencia` enum('BAIXA','MITJA','ALTA','CRITICA') DEFAULT 'MITJA',
  `resolta` tinyint(1) DEFAULT 0,
  `id_destinatari` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alertes_sistema`
--

INSERT INTO `alertes_sistema` (`id_alerta`, `tipus_alerta`, `id_referencia`, `taula_referencia`, `missatge`, `data_generacio`, `data_venciment`, `urgencia`, `resolta`, `id_destinatari`) VALUES
(1, 'ESTOC_MINIM', 1, 'stock_herbicidas', 'Stock baix: TEST GLIFOSAT (3.50 uds)', '2026-03-14 16:15:57', '2026-03-21', 'ALTA', 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `analisis_muestras`
--

CREATE TABLE `analisis_muestras` (
  `id` int(11) NOT NULL,
  `id_parcela` int(11) DEFAULT NULL,
  `id_sector` int(11) DEFAULT NULL,
  `tipus_mostra` enum('SOL','AIGUA','FULLES') NOT NULL,
  `data_mostra` date NOT NULL,
  `parametres` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parametres`)),
  `resultats` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resultats`)),
  `laboratori` varchar(100) DEFAULT NULL,
  `observacions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aplicacions`
--

CREATE TABLE `aplicacions` (
  `id_aplicacio` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `fila_id` int(11) DEFAULT NULL,
  `id_producte` int(11) DEFAULT NULL,
  `id_fertilizant` int(11) DEFAULT NULL,
  `tipus_aplicacio` enum('FITOSANITARI','FERTILITZACIO') NOT NULL,
  `data_aplicacio` datetime NOT NULL,
  `superficie_tractada_ha` decimal(10,4) NOT NULL,
  `dosis_aplicada` decimal(10,2) NOT NULL,
  `volum_total_caldo` decimal(10,2) DEFAULT NULL,
  `metode_aplicacio` varchar(100) DEFAULT NULL,
  `condicions_clima` text DEFAULT NULL,
  `id_operari` int(11) NOT NULL,
  `observacions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `aplicacions`
--

INSERT INTO `aplicacions` (`id_aplicacio`, `id_sector`, `fila_id`, `id_producte`, `id_fertilizant`, `tipus_aplicacio`, `data_aplicacio`, `superficie_tractada_ha`, `dosis_aplicada`, `volum_total_caldo`, `metode_aplicacio`, `condicions_clima`, `id_operari`, `observacions`, `created_at`) VALUES
(1, 12, NULL, NULL, 3, 'FERTILITZACIO', '2026-01-20 10:06:00', 0.5000, 1.00, NULL, NULL, NULL, 5, NULL, '2026-01-20 09:07:43'),
(7, 13, NULL, NULL, 1, 'FERTILITZACIO', '2026-01-20 10:37:00', 0.3000, 1.00, 5.00, 'Atomizador', 'Bon temps', 6, '', '2026-01-20 09:38:17');

--
-- Disparadores `aplicacions`
--
DELIMITER $$
CREATE TRIGGER `trg_actualitzar_estoc_aplicacio` AFTER INSERT ON `aplicacions` FOR EACH ROW BEGIN
  -- Si és un producte fitosanitari
  IF NEW.id_producte IS NOT NULL THEN
    UPDATE stock_herbicidas s
    INNER JOIN productos_fitosanitarios p ON s.id = p.id
    SET s.cantidad_actual = s.cantidad_actual - NEW.dosis_aplicada
    WHERE p.id = NEW.id_producte;
  END IF;
  
  -- Si és un fertilitzant
  IF NEW.id_fertilizant IS NOT NULL THEN
    UPDATE fertilizantes f
    SET f.stock_id = f.stock_id - NEW.dosis_aplicada
    WHERE f.id = NEW.id_fertilizant;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `assignacions`
--

CREATE TABLE `assignacions` (
  `id_assignacio` int(11) NOT NULL,
  `id_tasca` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `data_assignacio` date NOT NULL,
  `hora_inici_real` datetime DEFAULT NULL,
  `hora_final_real` datetime DEFAULT NULL,
  `estat` enum('PENDENT','EN_CURS','FINALITZADA','ATURADA') DEFAULT 'PENDENT',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `assignacions`
--

INSERT INTO `assignacions` (`id_assignacio`, `id_tasca`, `id_treballador`, `data_assignacio`, `hora_inici_real`, `hora_final_real`, `estat`, `notes`, `created_at`) VALUES
(8, 8, 6, '2025-12-15', NULL, NULL, 'PENDENT', NULL, '2025-12-15 08:42:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_cambios`
--

CREATE TABLE `auditoria_cambios` (
  `id` int(11) NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `id_registro` int(11) DEFAULT NULL,
  `accion` enum('INSERT','UPDATE','DELETE') DEFAULT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anteriores`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendari_laboral`
--

CREATE TABLE `calendari_laboral` (
  `id_dia` int(11) NOT NULL,
  `data` date NOT NULL,
  `tipus` enum('FESTIU_NACIONAL','FESTIU_REGIONAL','FESTIU_LOCAL','LABORABLE') NOT NULL,
  `descripcio` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria_professional`
--

CREATE TABLE `categoria_professional` (
  `id_categoria` int(11) NOT NULL,
  `nom_categoria` varchar(100) NOT NULL,
  `descripcio` text DEFAULT NULL,
  `requisits_certificacio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificacions`
--

CREATE TABLE `certificacions` (
  `id_certificacio` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `tipus_certificacio` varchar(100) NOT NULL,
  `entitat_emissora` varchar(100) DEFAULT NULL,
  `data_obtencio` date NOT NULL,
  `data_caducitat` date DEFAULT NULL,
  `document_ruta` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contractes`
--

CREATE TABLE `contractes` (
  `id_contracte` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `tipus_contracte` enum('FIX','TEMPORAL','PRACTIQUES') NOT NULL,
  `data_inici` date NOT NULL,
  `data_final` date DEFAULT NULL,
  `salari_base` decimal(10,2) DEFAULT NULL,
  `hores_setmanals` decimal(5,2) DEFAULT NULL,
  `estat` enum('ACTIU','FINALITZAT','PRORROGAT') DEFAULT 'ACTIU',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `control_qualitat`
--

CREATE TABLE `control_qualitat` (
  `id` int(11) NOT NULL,
  `id_lot` varchar(50) NOT NULL,
  `data_control` datetime NOT NULL,
  `parametre` varchar(100) NOT NULL,
  `valor` decimal(8,2) DEFAULT NULL,
  `resultat` enum('APTA','NO_APTA','LIMIT') DEFAULT 'APTA',
  `observacions` text DEFAULT NULL,
  `id_operari` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costes_tasques`
--

CREATE TABLE `costes_tasques` (
  `id_cost` int(11) NOT NULL,
  `id_tasca` int(11) NOT NULL,
  `id_assignacio` int(11) DEFAULT NULL,
  `concepte` varchar(100) NOT NULL,
  `categoria` enum('MA_D_OBRA','FITOSANITARI','FERTILITZANT','MAQUINARIA','DESPLAÇAMENT','ALTRES') NOT NULL,
  `quantitat` decimal(10,2) DEFAULT NULL,
  `unitat` varchar(20) DEFAULT NULL,
  `cost_unitari` decimal(10,4) NOT NULL,
  `cost_total` decimal(12,2) GENERATED ALWAYS AS (`quantitat` * `cost_unitari`) STORED,
  `data_cost` date NOT NULL,
  `id_treballador` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cultivos`
--

CREATE TABLE `cultivos` (
  `id` int(11) NOT NULL,
  `nombre_comun` varchar(100) NOT NULL,
  `nombre_cientifico` varchar(150) DEFAULT NULL,
  `familia` varchar(100) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cultivos`
--

INSERT INTO `cultivos` (`id`, `nombre_comun`, `nombre_cientifico`, `familia`, `categoria`) VALUES
(1, 'Manzano', 'Malus domestica', 'Rosaceae', 'Fruta de pepita'),
(2, 'Cerezo', 'Prunus avium', 'Rosaceae', 'Fruta de hueso'),
(3, 'Albaricoquero', 'Prunus armeniaca', 'Rosaceae', 'Fruta de hueso'),
(4, 'Peras', 'Pyrus communis', 'Rosaceae', 'Frutal');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departaments`
--

CREATE TABLE `departaments` (
  `id_departament` int(11) NOT NULL,
  `nom_departament` varchar(100) NOT NULL,
  `descripcio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_responsable` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `departaments`
--

INSERT INTO `departaments` (`id_departament`, `nom_departament`, `descripcio`, `created_at`, `id_responsable`) VALUES
(2, 'Recursos Humanos', 'Gestiona el personal y contrataciones', '2025-11-25 08:15:09', 1),
(3, 'Marketing', 'Departamento encargado de la promoción y publicidad', '2025-11-25 08:16:53', NULL),
(4, 'Tecnología', 'Desarrollo y mantenimiento de sistemas', '2025-11-25 08:16:53', 5),
(5, 'Logística', 'Gestión de envíos y distribución', '2025-11-25 08:16:53', 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentacio`
--

CREATE TABLE `documentacio` (
  `id_document` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `tipus_document` varchar(100) NOT NULL,
  `nom_document` varchar(255) DEFAULT NULL,
  `ruta_arxiu` varchar(255) NOT NULL,
  `data_carrega` date NOT NULL,
  `data_venciment` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documentacio`
--

INSERT INTO `documentacio` (`id_document`, `id_treballador`, `tipus_document`, `nom_document`, `ruta_arxiu`, `data_carrega`, `data_venciment`, `created_at`) VALUES
(3, 16, 'Certificado Manejo Fitosanitario', 'certificado.pdf', '/docs/certificado.pdf', '2025-06-04', '2025-12-03', '2025-12-01 09:35:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_herbicida`
--

CREATE TABLE `documentos_herbicida` (
  `id` int(11) NOT NULL,
  `herbicida_id` int(11) NOT NULL,
  `tipo_documento` varchar(50) DEFAULT NULL,
  `archivo_url` varchar(255) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `descripcion` text DEFAULT NULL,
  `vigente` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_parcela`
--

CREATE TABLE `documentos_parcela` (
  `id` int(11) NOT NULL,
  `parcela_id` int(11) NOT NULL,
  `tipo_documento` varchar(50) DEFAULT NULL,
  `archivo_url` varchar(255) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp(),
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empreses`
--

CREATE TABLE `empreses` (
  `id_empresa` int(11) NOT NULL,
  `cif_nif` varchar(20) NOT NULL,
  `nom_empresa` varchar(100) NOT NULL,
  `tipus` enum('CLIENT','PROVEIDOR','AMBOS') DEFAULT 'CLIENT',
  `adreca` text DEFAULT NULL,
  `poblacio` varchar(100) DEFAULT NULL,
  `codi_postal` varchar(10) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `persona_contacte` varchar(100) DEFAULT NULL,
  `certificacions_requerides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`certificacions_requerides`)),
  `actiu` tinyint(1) DEFAULT 1,
  `data_alta` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empreses`
--

INSERT INTO `empreses` (`id_empresa`, `cif_nif`, `nom_empresa`, `tipus`, `adreca`, `poblacio`, `codi_postal`, `telefon`, `email`, `persona_contacte`, `certificacions_requerides`, `actiu`, `data_alta`) VALUES
(1, 'ESB12345678', 'Distribuciones Frutas SL', 'CLIENT', NULL, NULL, NULL, NULL, 'compras@distribuciones.com', NULL, '[\"GLOBALG.A.P.\", \"BRC\"]', 1, '2025-11-24 08:34:02'),
(2, 'ESZ87654321', 'Cooperativa Agrícola La Unió', 'CLIENT', NULL, NULL, NULL, NULL, 'exportacio@cooperativa.cat', NULL, '[\"ECOLOGIC\"]', 1, '2025-11-24 08:34:02'),
(3, 'ESP11223344', 'Fertilizantes del Vallès', 'PROVEIDOR', NULL, NULL, NULL, NULL, 'vendas@fertivalles.com', NULL, NULL, 1, '2025-11-24 08:34:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equips`
--

CREATE TABLE `equips` (
  `id_equip` int(11) NOT NULL,
  `nom_equip` varchar(100) NOT NULL,
  `descripcio` text DEFAULT NULL,
  `tipus` enum('PERMANENT','TEMPORAL') NOT NULL,
  `estat_actiu` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fertilizantes`
--

CREATE TABLE `fertilizantes` (
  `id` int(11) NOT NULL,
  `nombre_comercial` varchar(100) NOT NULL,
  `composicion_npk` varchar(20) DEFAULT NULL,
  `tipo` enum('NITROGENADO','FOSFATADO','POTASICO','MICRONUTRIENTES','ORGANICO') NOT NULL,
  `concentracion` decimal(10,2) DEFAULT NULL,
  `unidad` varchar(20) DEFAULT '%',
  `stock_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `fertilizantes`
--

INSERT INTO `fertilizantes` (`id`, `nombre_comercial`, `composicion_npk`, `tipo`, `concentracion`, `unidad`, `stock_id`, `activo`) VALUES
(1, 'Nitrofoska Special', '12-12-17', 'POTASICO', 12.00, '%Nitrógeno', NULL, 1),
(2, 'Nitrofoska Special', '12-12-17', 'POTASICO', 12.00, '%Nitrógeno', NULL, 1),
(3, 'Urea Granulada', '46-0-0', 'NITROGENADO', 46.00, '%Nitrógeno Amoniacal', NULL, 1),
(4, 'DAP', '18-46-0', 'FOSFATADO', 46.00, '%P2O5 (Fósforo)', NULL, 1),
(7, 'Quelato de Hierro EDDHA', '0-0-0', '', 6.00, '%ierro (Fe)', NULL, 0),
(8, 'Quelato de Hierro EDDHA', '0-0-0', 'MICRONUTRIENTES', 6.00, '%Hierro (Fe)', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `filas_arboles`
--

CREATE TABLE `filas_arboles` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `numero_fila` int(11) NOT NULL,
  `coordenadas_geojson` text DEFAULT NULL,
  `numero_arboles` int(11) DEFAULT NULL,
  `variedad_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formacio`
--

CREATE TABLE `formacio` (
  `id_formacio` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `tipus_formacio` enum('ACADEMICA','PROFESSIONAL') NOT NULL,
  `nivell` varchar(100) DEFAULT NULL,
  `titol` varchar(255) NOT NULL,
  `entitat` varchar(100) DEFAULT NULL,
  `any_obtencio` year(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fotos_parcela`
--

CREATE TABLE `fotos_parcela` (
  `id` int(11) NOT NULL,
  `parcela_id` int(11) NOT NULL,
  `foto_url` varchar(255) NOT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `fecha_foto` date DEFAULT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `herbicidas`
--

CREATE TABLE `herbicidas` (
  `id` int(11) NOT NULL,
  `nombre_comercial` varchar(100) NOT NULL,
  `principio_activo` varchar(150) NOT NULL,
  `codigo_registro` varchar(50) NOT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `tipo_hierba` enum('gramineas','dicotiledoneas','ambas') NOT NULL,
  `modo_accion` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `dosis_recomendada` varchar(50) DEFAULT NULL,
  `unidad_dosis` varchar(20) DEFAULT NULL,
  `toxicidad_clp` varchar(20) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `herbicidas`
--

INSERT INTO `herbicidas` (`id`, `nombre_comercial`, `principio_activo`, `codigo_registro`, `fabricante`, `tipo_hierba`, `modo_accion`, `descripcion`, `dosis_recomendada`, `unidad_dosis`, `toxicidad_clp`, `foto_url`, `activo`) VALUES
(1, 'TEST GLIFOSAT', 'Glifosato', 'TEST-001', NULL, 'ambas', NULL, NULL, NULL, NULL, NULL, NULL, 1),
(2, 'Roundup Ultimate', 'Glifosato48%', 'TEST-002', 'Vayer', 'ambas', 'Sistémico', 'Herbicida total para control de malezas perennes', '3.0 - 6.0', 'L/ha', 'Atencion', NULL, 1),
(3, 'Gramoxone 200SL', 'Paraquat 20%', 'TEST-003', 'Syngenta', 'ambas', 'Contacto', 'Desecante rápido de tejidos verdes', '1.5 - 3.0', 'L/ha', 'Peligro', NULL, 1),
(4, 'Starane 200', 'Fluroxipir 20%', 'TEST-004', 'Corteva', 'dicotiledoneas', 'Sistemico Hormonal', 'Especialista en hoja ancha (dicotiledoneas)', '0.75 - 1.0', 'L/ha', 'Atencion', NULL, 1),
(5, 'Dual Gold', 'S-metolacloro 96%', 'TEST-005', 'Syngenta', 'gramineas', 'Pre-emergencia', 'Control preventivo de gramíneas anuales', '1.0 - 1.2', 'L/ha', 'Atencion', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `herbicidas_tipos_hierba`
--

CREATE TABLE `herbicidas_tipos_hierba` (
  `id` int(11) NOT NULL,
  `herbicida_id` int(11) NOT NULL,
  `tipo_hierba_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_cultivos`
--

CREATE TABLE `historial_cultivos` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `variedad_id` int(11) NOT NULL,
  `fecha_plantacion` date NOT NULL,
  `fecha_arrancada` date DEFAULT NULL,
  `marco_plantacion` varchar(20) DEFAULT NULL,
  `numero_arboles_plantados` int(11) DEFAULT NULL,
  `arboles_fallados` int(11) DEFAULT 0,
  `origen_material` varchar(100) DEFAULT NULL,
  `sistema_formacion` varchar(50) DEFAULT NULL,
  `inversion_inicial` decimal(12,2) DEFAULT NULL,
  `rendimiento_kg_ha` decimal(10,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_cultivos`
--

INSERT INTO `historial_cultivos` (`id`, `sector_id`, `variedad_id`, `fecha_plantacion`, `fecha_arrancada`, `marco_plantacion`, `numero_arboles_plantados`, `arboles_fallados`, `origen_material`, `sistema_formacion`, `inversion_inicial`, `rendimiento_kg_ha`, `observaciones`) VALUES
(4, 11, 4, '2018-11-20', NULL, '5x4 m', 600, 0, 'Vivero Tarragona', 'Vaso', 9800.00, 12000.00, NULL),
(6, 11, 5, '2015-12-10', '2023-08-15', '6x5 m', 450, 12, 'Vivero Cooperativo', NULL, 7500.00, 16500.00, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horaris`
--

CREATE TABLE `horaris` (
  `id_horari` int(11) NOT NULL,
  `nom_horari` varchar(100) NOT NULL,
  `hores_entrada` time DEFAULT NULL,
  `hores_sortida` time DEFAULT NULL,
  `durada_pausa` decimal(4,2) DEFAULT NULL,
  `tipus` enum('FIX','VARIABLE','TEMPORADA') NOT NULL,
  `temporada_inici` date DEFAULT NULL,
  `temporada_final` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `horaris`
--

INSERT INTO `horaris` (`id_horari`, `nom_horari`, `hores_entrada`, `hores_sortida`, `durada_pausa`, `tipus`, `temporada_inici`, `temporada_final`) VALUES
(1, 'Turno Tarde', '14:00:00', '22:00:00', 1.00, 'FIX', NULL, NULL),
(2, 'Turno Partido', '09:00:00', '19:00:00', 2.00, 'FIX', NULL, NULL),
(3, 'Refuerzo Fines de Semana', NULL, NULL, NULL, 'VARIABLE', NULL, NULL),
(4, 'Jornada Mañana', '08:00:00', '16:00:00', 1.00, 'FIX', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencias_parcela`
--

CREATE TABLE `incidencias_parcela` (
  `id` int(11) NOT NULL,
  `parcela_id` int(11) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `gravedad` varchar(20) DEFAULT NULL,
  `ubicacion_geojson` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencies`
--

CREATE TABLE `incidencies` (
  `id_incidencia` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `data_incidencia` date NOT NULL,
  `tipus_incidencia` varchar(100) DEFAULT NULL,
  `descripcio` text DEFAULT NULL,
  `id_assignacio` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lectures_sensors`
--

CREATE TABLE `lectures_sensors` (
  `id` int(11) NOT NULL,
  `id_sensor` varchar(50) NOT NULL,
  `data_lectura` datetime NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `unitat` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `limites_legales_aplicacion`
--

CREATE TABLE `limites_legales_aplicacion` (
  `id` int(11) NOT NULL,
  `herbicida_id` int(11) NOT NULL,
  `cultivo_id` int(11) DEFAULT NULL,
  `dosis_maxima_ha` decimal(10,2) DEFAULT NULL,
  `numero_maximo_aplicaciones` int(11) DEFAULT NULL,
  `intervalo_seguridad_dias` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lotes_herbicidas`
--

CREATE TABLE `lotes_herbicidas` (
  `id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `numero_lote` varchar(50) NOT NULL,
  `fecha_caducidad` date NOT NULL,
  `cantidad_inicial` decimal(10,2) NOT NULL,
  `cantidad_actual` decimal(10,2) NOT NULL,
  `fecha_recepcion` date DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lots_produccio`
--

CREATE TABLE `lots_produccio` (
  `id_lot` varchar(50) NOT NULL,
  `id_registre_collita` int(11) NOT NULL,
  `data_creacio` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantitat_total_kg` decimal(10,2) NOT NULL,
  `destinacio` varchar(100) DEFAULT NULL,
  `client` varchar(100) DEFAULT NULL,
  `id_empresa_client` int(11) DEFAULT NULL,
  `ubicacio_magatzem` varchar(100) DEFAULT NULL,
  `estat` enum('EN_TRACTAMENT','EMMAGATZEMAT','EXPEDIT') DEFAULT 'EN_TRACTAMENT',
  `codigo_qr` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `maquinaria_agricola`
--

CREATE TABLE `maquinaria_agricola` (
  `id_maquinaria` int(11) NOT NULL,
  `nom_maquinaria` varchar(100) NOT NULL,
  `tipus` enum('TRACTOR','ATOMIZADOR','COSECHADORA','PODADORA','FURGONETA') NOT NULL,
  `numero_serie` varchar(50) DEFAULT NULL,
  `any_fabricacio` year(4) DEFAULT NULL,
  `data_adquisicio` date DEFAULT NULL,
  `cost_adquisicio` decimal(12,2) DEFAULT NULL,
  `estat` enum('OPERATIVA','MANTENIMENT','AVERIADA','BAIXA') DEFAULT 'OPERATIVA',
  `hores_us_acumulades` decimal(10,2) DEFAULT 0.00,
  `ubicacio_actual` varchar(100) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `actiu` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `maquinaria_manteniment`
--

CREATE TABLE `maquinaria_manteniment` (
  `id_manteniment` int(11) NOT NULL,
  `id_maquinaria` int(11) NOT NULL,
  `tipus_manteniment` enum('PREVENTIU','CORRECTIU','REVISIO') NOT NULL,
  `data_manteniment` date NOT NULL,
  `hores_maquinaria` decimal(10,2) DEFAULT NULL,
  `descripcio` text NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `id_tecnic` int(11) DEFAULT NULL,
  `proper_manteniment_hores` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `monitoratge_plagues`
--

CREATE TABLE `monitoratge_plagues` (
  `id` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `data_observacio` date NOT NULL,
  `tipus_plaga` varchar(100) NOT NULL,
  `nivell_incidencia` enum('BAIX','MITJA','ALT','CRITIC') DEFAULT 'BAIX',
  `llindar_intervencio` tinyint(1) DEFAULT 0,
  `coordenades_geojson` text DEFAULT NULL,
  `descripcio` text DEFAULT NULL,
  `tractament_recomanat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_stock`
--

CREATE TABLE `movimientos_stock` (
  `id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `observacions_sector`
--

CREATE TABLE `observacions_sector` (
  `id` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `data_observacio` date NOT NULL,
  `observacio` text NOT NULL,
  `id_treballador` int(11) DEFAULT NULL,
  `tipus` enum('GENERAL','FITOSANITARI','FENOLÒGIC') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagaments`
--

CREATE TABLE `pagaments` (
  `id_pagament` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `data_pagament` date NOT NULL,
  `periode_inici` date NOT NULL,
  `periode_final` date NOT NULL,
  `import_brut` decimal(10,2) NOT NULL,
  `import_net` decimal(10,2) NOT NULL,
  `tipus` enum('NOMINA','HORES_EXTRES','PRORROGA','INDEMNITZACIO') DEFAULT 'NOMINA',
  `document_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parcelas`
--

CREATE TABLE `parcelas` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `coordenadas_geojson` text NOT NULL,
  `superficie_total_ha` decimal(10,4) NOT NULL,
  `superficie_efectiva_ha` decimal(10,4) GENERATED ALWAYS AS (case when `infraestructuras` is null then `superficie_total_ha` else `superficie_total_ha` * 0.95 end) STORED,
  `tipo_suelo_id` int(11) DEFAULT NULL,
  `ph` decimal(3,1) DEFAULT NULL,
  `materia_organica` decimal(4,2) DEFAULT NULL,
  `pendiente` decimal(4,2) DEFAULT NULL,
  `orientacion` varchar(20) DEFAULT NULL,
  `infraestructuras` text DEFAULT NULL,
  `activa` tinyint(1) DEFAULT 1,
  `fecha_alta` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `parcelas`
--

INSERT INTO `parcelas` (`id`, `codigo`, `nombre`, `coordenadas_geojson`, `superficie_total_ha`, `tipo_suelo_id`, `ph`, `materia_organica`, `pendiente`, `orientacion`, `infraestructuras`, `activa`, `fecha_alta`) VALUES
(35, 'PAR-001', 'Parcela 1', '{\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[0.883082,41.627689],[0.883485,41.627288],[0.885008,41.628114],[0.884971,41.628692],[0.883082,41.627689]]]}}', 0.0000, 1, 6.0, 2.00, 1.00, 'EST', 'Campo', 1, '2026-01-13 09:03:17'),
(36, 'PAR-002', 'Parcela 2', '{\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[0.881178,41.627485],[0.881682,41.627228],[0.882085,41.626952],[0.882331,41.626783],[0.882433,41.626775],[0.882471,41.626851],[0.88239,41.627236],[0.882439,41.627517],[0.882541,41.627758],[0.882637,41.628002],[0.882267,41.628251],[0.881178,41.627485]]]}}', 0.0000, NULL, 7.0, 3.00, 3.50, 'SUD', 'Campo', 1, '2026-01-13 09:06:08'),
(37, 'PAR-003', 'Parcela 3', '{\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[0.882795,41.627834],[0.88271,41.627689],[0.882667,41.627585],[0.882645,41.627481],[0.882635,41.627377],[0.882645,41.627248],[0.882656,41.62712],[0.882677,41.626976],[0.882731,41.626831],[0.882774,41.626671],[0.882881,41.626527],[0.882967,41.626366],[0.883128,41.626222],[0.883732,41.625761],[0.884643,41.626222],[0.884531,41.626398],[0.884343,41.626587],[0.883833,41.627],[0.883662,41.627104],[0.883479,41.627184],[0.883286,41.62732],[0.883174,41.627445],[0.882795,41.627834]]]}}', 0.0000, NULL, 8.0, 1.00, 2.00, 'Norte', 'Camp', 1, '2026-01-13 09:08:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plazos_seguridad`
--

CREATE TABLE `plazos_seguridad` (
  `id` int(11) NOT NULL,
  `herbicida_id` int(11) NOT NULL,
  `tipo_plazo` enum('ingreso_personal','cosecha','aplicacion_cultivo') NOT NULL,
  `dias_plazo` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presupostos_campanya`
--

CREATE TABLE `presupostos_campanya` (
  `id_presupost` int(11) NOT NULL,
  `any_campanya` year(4) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `concepte` varchar(100) NOT NULL,
  `categoria` enum('MA_D_OBRA','FITOSANITARIS','FERTILITZANTS','MAQUINARIA','ALTRES') NOT NULL,
  `pressupostat` decimal(12,2) NOT NULL,
  `moneda` varchar(3) DEFAULT 'EUR',
  `data_creacio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_fitosanitarios`
--

CREATE TABLE `productos_fitosanitarios` (
  `id` int(11) NOT NULL,
  `nombre_comercial` varchar(100) NOT NULL,
  `principio_activo` varchar(150) NOT NULL,
  `codigo_registro` varchar(50) NOT NULL,
  `fabricante` varchar(100) DEFAULT NULL,
  `tipo_producto` enum('HERBICIDA','FUNGICIDA','INSECTICIDA','ACARICIDA','BACTERICIDA','NEMATICIDA') NOT NULL,
  `dosis_recomendada_ha` decimal(10,2) DEFAULT NULL,
  `unidad_dosis` varchar(20) DEFAULT 'ml/L',
  `periodo_carencia_dias` int(11) DEFAULT NULL,
  `nivel_toxicidad` varchar(20) DEFAULT NULL,
  `fitos_para_produccion_ecologica` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `cif` varchar(20) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `nombre`, `cif`, `contacto`, `email`, `telefono`) VALUES
(1, 'ElectroServicios SL', 'C87654321', 'Ana Robles', 'ana@electroservicios.com', '934567890'),
(2, 'Distribuciones Martínez', 'F11223344', 'Jorge Martínez', 'ventas@dmartinez.com', '955667788'),
(3, 'TecnoPlus Europa', 'A99887766', 'María López', 'ml@tecnoplus.eu', '600112233');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registre_collites`
--

CREATE TABLE `registre_collites` (
  `id` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `data_collita` date NOT NULL,
  `hora_inici` datetime DEFAULT NULL,
  `hora_final` datetime DEFAULT NULL,
  `quantitat_kg` decimal(10,2) NOT NULL,
  `unitat` enum('KG','CAIXES','BINS') DEFAULT 'KG',
  `varietat_id` int(11) NOT NULL,
  `equip_collita` varchar(255) DEFAULT NULL,
  `condicions_clima` text DEFAULT NULL,
  `grau_maduracio` varchar(50) DEFAULT NULL,
  `incidencies` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registre_hores`
--

CREATE TABLE `registre_hores` (
  `id_registre` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `id_assignacio` int(11) DEFAULT NULL,
  `data` date NOT NULL,
  `hora_inici` datetime NOT NULL,
  `hora_final` datetime DEFAULT NULL,
  `ubicacio` varchar(100) DEFAULT NULL,
  `pausa_durada` decimal(5,2) DEFAULT 0.00,
  `incidencies_observacions` text DEFAULT NULL,
  `validat` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registro_fertilizacions`
--

CREATE TABLE `registro_fertilizacions` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `fertilizante_id` int(11) NOT NULL,
  `data_abonat` date NOT NULL,
  `quantitat` decimal(10,2) DEFAULT NULL,
  `observacions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registro_tractaments`
--

CREATE TABLE `registro_tractaments` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `herbicida_id` int(11) NOT NULL,
  `data_aplicacio` date NOT NULL,
  `dosi` decimal(10,2) DEFAULT NULL,
  `unitat` varchar(20) DEFAULT NULL,
  `observacions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sectores_cultivo`
--

CREATE TABLE `sectores_cultivo` (
  `id` int(11) NOT NULL,
  `parcela_id` int(11) DEFAULT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `coordenadas_geojson` text DEFAULT NULL,
  `superficie_efectiva_ha` decimal(10,4) DEFAULT NULL,
  `fecha_creacion` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sectores_cultivo`
--

INSERT INTO `sectores_cultivo` (`id`, `parcela_id`, `codigo`, `nombre`, `coordenadas_geojson`, `superficie_efectiva_ha`, `fecha_creacion`, `activo`) VALUES
(11, 36, 'PAR-2-SEC-1', 'Cerezos', '{\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[0.882645,41.628002],[0.881661,41.627235],[0.881167,41.627483],[0.882262,41.628257],[0.882645,41.628002]]]}}', 0.5552, '2026-01-13', 1),
(12, 35, 'PAR-1-SEC-1', 'Manzanas', '{\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[0.883093,41.627688],[0.88349,41.627283],[0.884308,41.627729],[0.883874,41.628122],[0.883093,41.627688]]]}}', 0.4651, '2026-01-19', 1),
(13, 35, 'PAR-1-SEC-2', 'Manzanas', '{\"type\":\"Feature\",\"properties\":{},\"geometry\":{\"type\":\"Polygon\",\"coordinates\":[[[0.883871,41.628121],[0.884316,41.627732],[0.885008,41.628129],[0.884971,41.628698],[0.883871,41.628121]]]}}', 0.5065, '2026-01-19', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sectores_parcelas`
--

CREATE TABLE `sectores_parcelas` (
  `id` int(11) NOT NULL,
  `sector_id` int(11) NOT NULL,
  `parcela_id` int(11) NOT NULL,
  `porcentaje_superficie` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguiment_fenologic`
--

CREATE TABLE `seguiment_fenologic` (
  `id` int(11) NOT NULL,
  `id_sector` int(11) NOT NULL,
  `data_observacio` date NOT NULL,
  `estat_fenologic` enum('DORMANCIA','FLORACIO','QUALLAT','DESENVOLUPAMENT','MADURACIO') NOT NULL,
  `intensitat` enum('BAIXA','MITJA','ALTA') DEFAULT 'MITJA',
  `observacions` text DEFAULT NULL,
  `id_treballador` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sensors`
--

CREATE TABLE `sensors` (
  `id_sensor` varchar(50) NOT NULL,
  `tipus_sensor` enum('HUMITAT_SOL','CONDUCTIVITAT','TEMPERATURA','PLUVIOMETRE','TRAMPA_ELECTRONICA') NOT NULL,
  `id_sector` int(11) DEFAULT NULL,
  `id_parcela` int(11) DEFAULT NULL,
  `ubicacio_geojson` text DEFAULT NULL,
  `estat` enum('ACTIU','INACTIU','MANTENIMENT') DEFAULT 'ACTIU',
  `data_instalacio` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_herbicidas`
--

CREATE TABLE `stock_herbicidas` (
  `id` int(11) NOT NULL,
  `herbicida_id` int(11) NOT NULL,
  `ubicacion_almacen` varchar(100) DEFAULT NULL,
  `cantidad_actual` decimal(10,2) NOT NULL,
  `unidad` varchar(20) NOT NULL,
  `stock_minimo` decimal(10,2) DEFAULT 0.00,
  `fecha_ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `stock_herbicidas`
--

INSERT INTO `stock_herbicidas` (`id`, `herbicida_id`, `ubicacion_almacen`, `cantidad_actual`, `unidad`, `stock_minimo`, `fecha_ultima_actualizacion`) VALUES
(1, 1, 'Almacén Principal', 3.50, 'L', 5.00, '2025-12-01 09:35:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasques`
--

CREATE TABLE `tasques` (
  `id_tasca` int(11) NOT NULL,
  `id_sector` int(11) DEFAULT NULL,
  `tipus_tasca` varchar(100) NOT NULL,
  `descripcio` text DEFAULT NULL,
  `parcel_la_zona` varchar(100) DEFAULT NULL,
  `data_inici_finestra` date NOT NULL,
  `data_final_finestra` date NOT NULL,
  `interval_dies_optims` int(11) DEFAULT NULL,
  `durada_estimada` decimal(8,2) DEFAULT NULL,
  `requisits_personal_nombre` int(11) DEFAULT NULL,
  `requisits_qualificacio` text DEFAULT NULL,
  `cultivar_estat_fenologic` varchar(100) DEFAULT NULL,
  `equipament_necessari` text DEFAULT NULL,
  `id_tasca_precedent` int(11) DEFAULT NULL,
  `estat` enum('PENDENT','ASSIGNADA','EN_CURS','FINALITZADA','CANCEL·LADA') DEFAULT 'PENDENT',
  `prioritat` enum('BAIXA','MITJA','ALTA','URGENT') DEFAULT 'MITJA',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tasques`
--

INSERT INTO `tasques` (`id_tasca`, `id_sector`, `tipus_tasca`, `descripcio`, `parcel_la_zona`, `data_inici_finestra`, `data_final_finestra`, `interval_dies_optims`, `durada_estimada`, `requisits_personal_nombre`, `requisits_qualificacio`, `cultivar_estat_fenologic`, `equipament_necessari`, `id_tasca_precedent`, `estat`, `prioritat`, `created_at`) VALUES
(1, NULL, 'Collita', 'S\'ha de collir tot', NULL, '2025-12-02', '2025-12-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PENDENT', 'MITJA', '2025-12-02 09:43:58'),
(3, NULL, 'Collita', 'S\'ha de collir tot', NULL, '2025-12-02', '2025-12-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PENDENT', 'MITJA', '2025-12-02 09:50:09'),
(4, NULL, 'Collita', 'S\'ha de collir tot', NULL, '2025-12-02', '2025-12-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PENDENT', 'MITJA', '2025-12-02 09:50:18'),
(5, NULL, 'Collita', 'S\'ha de collir tot', NULL, '2025-12-02', '2025-12-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PENDENT', 'MITJA', '2025-12-02 09:52:00'),
(6, NULL, 'Collita', 'S\'ha de collir tot', NULL, '2025-12-02', '2025-12-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PENDENT', 'MITJA', '2025-12-02 09:52:36'),
(7, NULL, 'Collita', 'S\'ha de collir tot', NULL, '2025-12-02', '2025-12-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PENDENT', 'MITJA', '2025-12-02 09:55:30'),
(8, NULL, 'Collita', '', NULL, '2025-12-15', '2025-12-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PENDENT', 'URGENT', '2025-12-15 08:42:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasques_maquinaria`
--

CREATE TABLE `tasques_maquinaria` (
  `id` int(11) NOT NULL,
  `id_tasca` int(11) NOT NULL,
  `id_maquinaria` int(11) NOT NULL,
  `hores_us` decimal(5,2) DEFAULT NULL,
  `cost_hora` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasques_material`
--

CREATE TABLE `tasques_material` (
  `id` int(11) NOT NULL,
  `id_tasca` int(11) NOT NULL,
  `material` varchar(100) NOT NULL,
  `quantitat_necessaria` decimal(10,2) DEFAULT NULL,
  `unitat` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_hierba`
--

CREATE TABLE `tipos_hierba` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_suelo`
--

CREATE TABLE `tipos_suelo` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ph_min` decimal(3,1) DEFAULT NULL,
  `ph_max` decimal(3,1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_suelo`
--

INSERT INTO `tipos_suelo` (`id`, `tipo`, `descripcion`, `ph_min`, `ph_max`) VALUES
(1, 'Franco-arenoso', 'Suelo bien drenado, ideal para frutales', 6.0, 7.5),
(2, 'Arcilloso', 'Retiene humedad, riesgo de encharcamiento', 5.5, 7.0),
(3, 'Calcáreo', 'Alcalino, limita absorción de nutrientes', 7.5, 8.5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `treballadors`
--

CREATE TABLE `treballadors` (
  `id_treballador` int(11) NOT NULL,
  `nom_complet` varchar(200) NOT NULL,
  `fotografia` varchar(255) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `document_identitat` varchar(50) NOT NULL,
  `tipus_document` enum('DNI','NIE','PASSAPORT') NOT NULL,
  `data_naixement` date NOT NULL,
  `lloc_naixement` varchar(100) DEFAULT NULL,
  `nacionalitat` varchar(50) NOT NULL,
  `situacio_residencia` varchar(100) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adreca` text DEFAULT NULL,
  `contacte_emergencia` text DEFAULT NULL,
  `iban_bancari` varchar(34) DEFAULT NULL,
  `numero_seguretat_social` varchar(20) NOT NULL,
  `tipus_permis_treball` varchar(50) DEFAULT NULL,
  `estat_actiu` tinyint(1) DEFAULT 1,
  `data_incorporacio` date NOT NULL,
  `data_finalitzacio` date DEFAULT NULL,
  `id_departament` int(11) DEFAULT NULL,
  `id_equip` int(11) DEFAULT NULL,
  `id_horari` int(11) DEFAULT NULL COMMENT 'ID del horario asignado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `historial_laboral_previ` text DEFAULT NULL,
  `idiomes` varchar(255) DEFAULT NULL,
  `habilitats` text DEFAULT NULL,
  `certificacions_addicionals` text DEFAULT NULL,
  `rol` enum('ADMIN','MANAGER','TREBALLADOR','SUPERVISOR') NOT NULL DEFAULT 'TREBALLADOR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `treballadors`
--

INSERT INTO `treballadors` (`id_treballador`, `nom_complet`, `fotografia`, `foto_url`, `document_identitat`, `tipus_document`, `data_naixement`, `lloc_naixement`, `nacionalitat`, `situacio_residencia`, `telefon`, `email`, `adreca`, `contacte_emergencia`, `iban_bancari`, `numero_seguretat_social`, `tipus_permis_treball`, `estat_actiu`, `data_incorporacio`, `data_finalitzacio`, `id_departament`, `id_equip`, `id_horari`, `created_at`, `updated_at`, `historial_laboral_previ`, `idiomes`, `habilitats`, `certificacions_addicionals`, `rol`) VALUES
(1, 'younes add sss', NULL, NULL, '12345678A', 'DNI', '2025-05-05', 'Barcelona', 'Española', NULL, '123456789', 'juan@personal.com', 'C/mayor 5, 4-3', NULL, NULL, '25/5215222', NULL, 1, '2025-11-24', NULL, NULL, NULL, 4, '2025-11-24 09:00:09', '2025-11-25 10:01:23', NULL, NULL, NULL, NULL, 'TREBALLADOR'),
(5, 'younes asd', NULL, NULL, '87654321B', 'DNI', '0000-00-00', 'Barcelona', '', NULL, NULL, NULL, NULL, NULL, NULL, '25/521452', NULL, 0, '2025-11-24', NULL, NULL, NULL, 1, '2025-11-24 09:30:45', '2025-11-25 11:15:30', NULL, NULL, NULL, NULL, 'TREBALLADOR'),
(6, 'Jordi Tarrago', NULL, NULL, '12345684D', 'DNI', '2025-05-05', 'Barcelona', 'Romano', NULL, '153246251', NULL, NULL, NULL, NULL, '25/525252', 'Si', 1, '2025-11-24', NULL, NULL, NULL, 2, '2025-11-24 12:28:05', '2025-11-25 10:01:23', NULL, NULL, NULL, NULL, 'TREBALLADOR'),
(16, 'Automàtic Test', NULL, NULL, 'AUT001', 'DNI', '1990-01-01', 'Barcelona', 'Española', NULL, '666555444', 'auto@test.com', NULL, NULL, NULL, '', NULL, 1, '2025-09-02', NULL, NULL, NULL, NULL, '2025-12-01 09:35:25', '2025-12-01 09:35:25', NULL, NULL, NULL, NULL, 'TREBALLADOR');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users_app`
--

CREATE TABLE `users_app` (
  `id_user` int(11) NOT NULL,
  `id_treballador` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `rol` enum('ADMIN','RRHH','CAPS_DE_CAMPO','OPERARI','VISITANT') NOT NULL,
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permisos`)),
  `actiu` tinyint(1) DEFAULT 1,
  `ultim_acces` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vacances_permisos`
--

CREATE TABLE `vacances_permisos` (
  `id_absencia` int(11) NOT NULL,
  `id_treballador` int(11) NOT NULL,
  `tipus_absencia` enum('VACANCES','PERMIS','BAIXA_MALALTIA','BAIXA_ACCIDENT','PERMIS_RETRIBUIT') NOT NULL,
  `data_inici` date NOT NULL,
  `data_final` date NOT NULL,
  `dies` decimal(5,2) DEFAULT NULL,
  `estat` enum('APROVAT','PENDENT','CANCEL·LAT') DEFAULT 'PENDENT',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `variedades`
--

CREATE TABLE `variedades` (
  `id` int(11) NOT NULL,
  `cultivo_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `necesidades_hidricas` varchar(50) DEFAULT NULL,
  `horas_frio` int(11) DEFAULT NULL,
  `resistencia_malezas` varchar(50) DEFAULT NULL,
  `productividad_media_kg_ha` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `variedades`
--

INSERT INTO `variedades` (`id`, `cultivo_id`, `nombre`, `descripcion`, `necesidades_hidricas`, `horas_frio`, `resistencia_malezas`, `productividad_media_kg_ha`) VALUES
(1, 1, '(Manzana)Golden Delicious', 'Variedad muy popular', 'Media', 1100, NULL, 45000.00),
(2, 1, '(Manzana)Fuji', 'Dulce y crujiente', 'Alta', 1200, NULL, 50000.00),
(3, 2, '(Cerezo)Bing', 'Cerezo de mesa', 'Media', 800, NULL, 15000.00),
(4, 3, '(Albaricoquero)Moniquí', 'Pulpa blanca, extremadamente dulce y tierna. Muy apreciado para consumo en fresco.', 'Alta', 600, 'Media', 12000.00),
(5, 3, '(Albaricoquero)Bulida', 'Fruto grande, piel amarilla y carne firme. Variedad muy resistente y apta para conserva e industria.', 'Media', 350, 'Alta', 18000.00),
(6, 3, '(Albaricoquero)Galta Roja', 'El clásico de mercado: piel naranja con chapa rojiza. Sabor dulce y equilibrado.', 'Media', 450, 'Media', 15000.00),
(7, 3, '(Albaricoquero)Canino', 'Árbol muy vigoroso y productivo. Frutos de buen tamaño y excelente resistencia al transporte.', 'Media', 500, 'Alta', 20000.00),
(8, 3, '(Albaricoquero)Nancy', 'Variedad de maduración media con frutos muy grandes y aroma intenso. Ideal para postres.', 'Alta', 750, 'Baja', 14000.00),
(9, 3, '(Albaricoquero)Paviot', 'Variedad tardía de frutos gigantes y color naranja cobrizo. Muy azucarado.', 'Alta', 800, 'Media', 13000.00),
(10, 3, '(Albaricoquero)Currot', 'La variedad más precoz (principios de mayo). Fruto pequeño y sabor algo ácido.', 'Baja', 300, 'Alta', 10000.00);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estat_economic_sector`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_estat_economic_sector` (
`any_campanya` year(4)
,`id_sector` int(11)
,`sector_nom` varchar(100)
,`categoria` enum('MA_D_OBRA','FITOSANITARIS','FERTILITZANTS','MAQUINARIA','ALTRES')
,`concepte` varchar(100)
,`pressupostat` decimal(12,2)
,`cost_real` decimal(34,2)
,`diferencia` decimal(35,2)
,`percentatge_execucio` decimal(40,2)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estat_economic_sector`
--
DROP TABLE IF EXISTS `v_estat_economic_sector`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estat_economic_sector`  AS SELECT `p`.`any_campanya` AS `any_campanya`, `p`.`id_sector` AS `id_sector`, `s`.`nombre` AS `sector_nom`, `p`.`categoria` AS `categoria`, `p`.`concepte` AS `concepte`, `p`.`pressupostat` AS `pressupostat`, coalesce(sum(`c`.`cost_total`),0) AS `cost_real`, `p`.`pressupostat`- coalesce(sum(`c`.`cost_total`),0) AS `diferencia`, round(coalesce(sum(`c`.`cost_total`),0) / `p`.`pressupostat` * 100,2) AS `percentatge_execucio` FROM ((`presupostos_campanya` `p` left join `sectores_cultivo` `s` on(`p`.`id_sector` = `s`.`id`)) left join `costes_tasques` `c` on(`p`.`id_sector` = `c`.`id_tasca` and `c`.`categoria` = `p`.`categoria` and year(`c`.`data_cost`) = `p`.`any_campanya`)) GROUP BY `p`.`id_presupost` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertes`
--
ALTER TABLE `alertes`
  ADD PRIMARY KEY (`id_alerta`),
  ADD KEY `idx_venciment` (`data_venciment`),
  ADD KEY `idx_resolta` (`resolta`),
  ADD KEY `id_treballador` (`id_treballador`),
  ADD KEY `idx_alertes_urgents` (`resolta`,`data_venciment`),
  ADD KEY `idx_usuari_resolucio` (`id_usuari_resolucio`);

--
-- Indices de la tabla `alertes_historial`
--
ALTER TABLE `alertes_historial`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_usuari` (`id_usuari_resolucio`),
  ADD KEY `idx_data_resolucio` (`data_resolucio`);

--
-- Indices de la tabla `alertes_sistema`
--
ALTER TABLE `alertes_sistema`
  ADD PRIMARY KEY (`id_alerta`),
  ADD KEY `idx_venciment` (`data_venciment`),
  ADD KEY `idx_resolta` (`resolta`),
  ADD KEY `idx_urgencia` (`urgencia`);

--
-- Indices de la tabla `analisis_muestras`
--
ALTER TABLE `analisis_muestras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_parcela` (`id_parcela`),
  ADD KEY `id_sector` (`id_sector`);

--
-- Indices de la tabla `aplicacions`
--
ALTER TABLE `aplicacions`
  ADD PRIMARY KEY (`id_aplicacio`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `id_producte` (`id_producte`),
  ADD KEY `id_fertilizant` (`id_fertilizant`),
  ADD KEY `id_operari` (`id_operari`),
  ADD KEY `fk_aplicacions_fila` (`fila_id`);

--
-- Indices de la tabla `assignacions`
--
ALTER TABLE `assignacions`
  ADD PRIMARY KEY (`id_assignacio`),
  ADD KEY `idx_tasca` (`id_tasca`),
  ADD KEY `idx_treballador` (`id_treballador`);

--
-- Indices de la tabla `auditoria_cambios`
--
ALTER TABLE `auditoria_cambios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `calendari_laboral`
--
ALTER TABLE `calendari_laboral`
  ADD PRIMARY KEY (`id_dia`),
  ADD UNIQUE KEY `data` (`data`),
  ADD KEY `idx_data` (`data`);

--
-- Indices de la tabla `categoria_professional`
--
ALTER TABLE `categoria_professional`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nom_categoria` (`nom_categoria`);

--
-- Indices de la tabla `certificacions`
--
ALTER TABLE `certificacions`
  ADD PRIMARY KEY (`id_certificacio`),
  ADD KEY `idx_caducitat` (`data_caducitat`),
  ADD KEY `id_treballador` (`id_treballador`),
  ADD KEY `idx_cert_caducitat` (`data_caducitat`,`id_treballador`);

--
-- Indices de la tabla `contractes`
--
ALTER TABLE `contractes`
  ADD PRIMARY KEY (`id_contracte`),
  ADD KEY `id_treballador` (`id_treballador`);

--
-- Indices de la tabla `control_qualitat`
--
ALTER TABLE `control_qualitat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_lot` (`id_lot`),
  ADD KEY `id_operari` (`id_operari`);

--
-- Indices de la tabla `costes_tasques`
--
ALTER TABLE `costes_tasques`
  ADD PRIMARY KEY (`id_cost`),
  ADD KEY `idx_tasca` (`id_tasca`),
  ADD KEY `idx_categoria` (`categoria`),
  ADD KEY `idx_data_cost` (`data_cost`),
  ADD KEY `fk_cost_assignacio` (`id_assignacio`);

--
-- Indices de la tabla `cultivos`
--
ALTER TABLE `cultivos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `departaments`
--
ALTER TABLE `departaments`
  ADD PRIMARY KEY (`id_departament`),
  ADD UNIQUE KEY `nom_departament` (`nom_departament`),
  ADD KEY `id_responsable` (`id_responsable`);

--
-- Indices de la tabla `documentacio`
--
ALTER TABLE `documentacio`
  ADD PRIMARY KEY (`id_document`),
  ADD KEY `idx_venciment` (`data_venciment`),
  ADD KEY `id_treballador` (`id_treballador`),
  ADD KEY `idx_documentacio_venciment` (`data_venciment`,`id_treballador`);

--
-- Indices de la tabla `documentos_herbicida`
--
ALTER TABLE `documentos_herbicida`
  ADD PRIMARY KEY (`id`),
  ADD KEY `herbicida_id` (`herbicida_id`);

--
-- Indices de la tabla `documentos_parcela`
--
ALTER TABLE `documentos_parcela`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcela_id` (`parcela_id`);

--
-- Indices de la tabla `empreses`
--
ALTER TABLE `empreses`
  ADD PRIMARY KEY (`id_empresa`),
  ADD UNIQUE KEY `cif_nif` (`cif_nif`),
  ADD KEY `idx_tipus` (`tipus`),
  ADD KEY `idx_actiu` (`actiu`);

--
-- Indices de la tabla `equips`
--
ALTER TABLE `equips`
  ADD PRIMARY KEY (`id_equip`);

--
-- Indices de la tabla `fertilizantes`
--
ALTER TABLE `fertilizantes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `filas_arboles`
--
ALTER TABLE `filas_arboles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sector_id` (`sector_id`),
  ADD KEY `variedad_id` (`variedad_id`);

--
-- Indices de la tabla `formacio`
--
ALTER TABLE `formacio`
  ADD PRIMARY KEY (`id_formacio`),
  ADD KEY `id_treballador` (`id_treballador`);

--
-- Indices de la tabla `fotos_parcela`
--
ALTER TABLE `fotos_parcela`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcela_id` (`parcela_id`);

--
-- Indices de la tabla `herbicidas`
--
ALTER TABLE `herbicidas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_registro` (`codigo_registro`);

--
-- Indices de la tabla `herbicidas_tipos_hierba`
--
ALTER TABLE `herbicidas_tipos_hierba`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_herbicida_tipo` (`herbicida_id`,`tipo_hierba_id`),
  ADD KEY `tipo_hierba_id` (`tipo_hierba_id`);

--
-- Indices de la tabla `historial_cultivos`
--
ALTER TABLE `historial_cultivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sector_id` (`sector_id`),
  ADD KEY `variedad_id` (`variedad_id`);

--
-- Indices de la tabla `horaris`
--
ALTER TABLE `horaris`
  ADD PRIMARY KEY (`id_horari`);

--
-- Indices de la tabla `incidencias_parcela`
--
ALTER TABLE `incidencias_parcela`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parcela_id` (`parcela_id`);

--
-- Indices de la tabla `incidencies`
--
ALTER TABLE `incidencies`
  ADD PRIMARY KEY (`id_incidencia`),
  ADD KEY `id_treballador` (`id_treballador`),
  ADD KEY `id_assignacio` (`id_assignacio`);

--
-- Indices de la tabla `lectures_sensors`
--
ALTER TABLE `lectures_sensors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_sensor` (`id_sensor`),
  ADD KEY `idx_data_lectura` (`data_lectura`);

--
-- Indices de la tabla `limites_legales_aplicacion`
--
ALTER TABLE `limites_legales_aplicacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `herbicida_id` (`herbicida_id`),
  ADD KEY `cultivo_id` (`cultivo_id`);

--
-- Indices de la tabla `lotes_herbicidas`
--
ALTER TABLE `lotes_herbicidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`);

--
-- Indices de la tabla `lots_produccio`
--
ALTER TABLE `lots_produccio`
  ADD PRIMARY KEY (`id_lot`),
  ADD KEY `id_registre_collita` (`id_registre_collita`),
  ADD KEY `idx_empresa_client` (`id_empresa_client`);

--
-- Indices de la tabla `maquinaria_agricola`
--
ALTER TABLE `maquinaria_agricola`
  ADD PRIMARY KEY (`id_maquinaria`),
  ADD UNIQUE KEY `numero_serie` (`numero_serie`),
  ADD KEY `idx_tipus` (`tipus`),
  ADD KEY `idx_estat` (`estat`);

--
-- Indices de la tabla `maquinaria_manteniment`
--
ALTER TABLE `maquinaria_manteniment`
  ADD PRIMARY KEY (`id_manteniment`),
  ADD KEY `idx_maquinaria` (`id_maquinaria`),
  ADD KEY `idx_data` (`data_manteniment`),
  ADD KEY `fk_manteniment_tecnic` (`id_tecnic`);

--
-- Indices de la tabla `monitoratge_plagues`
--
ALTER TABLE `monitoratge_plagues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `idx_data_plaga` (`data_observacio`,`tipus_plaga`);

--
-- Indices de la tabla `movimientos_stock`
--
ALTER TABLE `movimientos_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`);

--
-- Indices de la tabla `observacions_sector`
--
ALTER TABLE `observacions_sector`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `id_treballador` (`id_treballador`);

--
-- Indices de la tabla `pagaments`
--
ALTER TABLE `pagaments`
  ADD PRIMARY KEY (`id_pagament`),
  ADD KEY `id_treballador` (`id_treballador`),
  ADD KEY `idx_periode` (`periode_inici`,`periode_final`);

--
-- Indices de la tabla `parcelas`
--
ALTER TABLE `parcelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `tipo_suelo_id` (`tipo_suelo_id`);

--
-- Indices de la tabla `plazos_seguridad`
--
ALTER TABLE `plazos_seguridad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `herbicida_id` (`herbicida_id`);

--
-- Indices de la tabla `presupostos_campanya`
--
ALTER TABLE `presupostos_campanya`
  ADD PRIMARY KEY (`id_presupost`),
  ADD UNIQUE KEY `unique_sector_concepte` (`any_campanya`,`id_sector`,`concepte`),
  ADD KEY `idx_campanya` (`any_campanya`),
  ADD KEY `fk_presupost_sector` (`id_sector`);

--
-- Indices de la tabla `productos_fitosanitarios`
--
ALTER TABLE `productos_fitosanitarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_registro` (`codigo_registro`),
  ADD KEY `idx_tipo_producto` (`tipo_producto`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`),
  ADD UNIQUE KEY `cif` (`cif`);

--
-- Indices de la tabla `registre_collites`
--
ALTER TABLE `registre_collites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `varietat_id` (`varietat_id`);

--
-- Indices de la tabla `registre_hores`
--
ALTER TABLE `registre_hores`
  ADD PRIMARY KEY (`id_registre`),
  ADD KEY `idx_data` (`data`),
  ADD KEY `idx_treballador_data` (`id_treballador`,`data`),
  ADD KEY `id_assignacio` (`id_assignacio`),
  ADD KEY `idx_registre_mensual` (`id_treballador`,`data`,`hora_inici`);

--
-- Indices de la tabla `registro_fertilizacions`
--
ALTER TABLE `registro_fertilizacions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sector_id` (`sector_id`),
  ADD KEY `fertilizante_id` (`fertilizante_id`);

--
-- Indices de la tabla `registro_tractaments`
--
ALTER TABLE `registro_tractaments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sector_id` (`sector_id`),
  ADD KEY `herbicida_id` (`herbicida_id`);

--
-- Indices de la tabla `sectores_cultivo`
--
ALTER TABLE `sectores_cultivo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `sectores_parcelas`
--
ALTER TABLE `sectores_parcelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sector_parcela` (`sector_id`,`parcela_id`),
  ADD KEY `parcela_id` (`parcela_id`);

--
-- Indices de la tabla `seguiment_fenologic`
--
ALTER TABLE `seguiment_fenologic`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `idx_fenologic` (`data_observacio`,`estat_fenologic`);

--
-- Indices de la tabla `sensors`
--
ALTER TABLE `sensors`
  ADD PRIMARY KEY (`id_sensor`),
  ADD KEY `id_sector` (`id_sector`),
  ADD KEY `id_parcela` (`id_parcela`);

--
-- Indices de la tabla `stock_herbicidas`
--
ALTER TABLE `stock_herbicidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `herbicida_id` (`herbicida_id`);

--
-- Indices de la tabla `tasques`
--
ALTER TABLE `tasques`
  ADD PRIMARY KEY (`id_tasca`),
  ADD KEY `id_tasca_precedent` (`id_tasca_precedent`),
  ADD KEY `idx_tasques_planificacio` (`estat`,`data_inici_finestra`,`data_final_finestra`),
  ADD KEY `idx_sector` (`id_sector`);

--
-- Indices de la tabla `tasques_maquinaria`
--
ALTER TABLE `tasques_maquinaria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tasca_maquinaria` (`id_tasca`,`id_maquinaria`),
  ADD KEY `fk_tasca_maquinaria_maquinaria` (`id_maquinaria`);

--
-- Indices de la tabla `tasques_material`
--
ALTER TABLE `tasques_material`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_tasca` (`id_tasca`);

--
-- Indices de la tabla `tipos_hierba`
--
ALTER TABLE `tipos_hierba`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo` (`tipo`);

--
-- Indices de la tabla `tipos_suelo`
--
ALTER TABLE `tipos_suelo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipo` (`tipo`);

--
-- Indices de la tabla `treballadors`
--
ALTER TABLE `treballadors`
  ADD PRIMARY KEY (`id_treballador`),
  ADD UNIQUE KEY `document_identitat` (`document_identitat`),
  ADD UNIQUE KEY `numero_seguretat_social` (`numero_seguretat_social`),
  ADD KEY `idx_document` (`document_identitat`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_estat` (`estat_actiu`),
  ADD KEY `id_departament` (`id_departament`),
  ADD KEY `id_equip` (`id_equip`),
  ADD KEY `idx_treballadors_actius` (`estat_actiu`,`nom_complet`),
  ADD KEY `fk_treballadors_horaris` (`id_horari`);

--
-- Indices de la tabla `users_app`
--
ALTER TABLE `users_app`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_treballador` (`id_treballador`);

--
-- Indices de la tabla `vacances_permisos`
--
ALTER TABLE `vacances_permisos`
  ADD PRIMARY KEY (`id_absencia`),
  ADD KEY `idx_dates` (`data_inici`,`data_final`),
  ADD KEY `id_treballador` (`id_treballador`);

--
-- Indices de la tabla `variedades`
--
ALTER TABLE `variedades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cultivo_id` (`cultivo_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertes`
--
ALTER TABLE `alertes`
  MODIFY `id_alerta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `alertes_historial`
--
ALTER TABLE `alertes_historial`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `alertes_sistema`
--
ALTER TABLE `alertes_sistema`
  MODIFY `id_alerta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `analisis_muestras`
--
ALTER TABLE `analisis_muestras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `aplicacions`
--
ALTER TABLE `aplicacions`
  MODIFY `id_aplicacio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `assignacions`
--
ALTER TABLE `assignacions`
  MODIFY `id_assignacio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `auditoria_cambios`
--
ALTER TABLE `auditoria_cambios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calendari_laboral`
--
ALTER TABLE `calendari_laboral`
  MODIFY `id_dia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categoria_professional`
--
ALTER TABLE `categoria_professional`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `certificacions`
--
ALTER TABLE `certificacions`
  MODIFY `id_certificacio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contractes`
--
ALTER TABLE `contractes`
  MODIFY `id_contracte` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `control_qualitat`
--
ALTER TABLE `control_qualitat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `costes_tasques`
--
ALTER TABLE `costes_tasques`
  MODIFY `id_cost` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cultivos`
--
ALTER TABLE `cultivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `departaments`
--
ALTER TABLE `departaments`
  MODIFY `id_departament` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `documentacio`
--
ALTER TABLE `documentacio`
  MODIFY `id_document` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `documentos_herbicida`
--
ALTER TABLE `documentos_herbicida`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documentos_parcela`
--
ALTER TABLE `documentos_parcela`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empreses`
--
ALTER TABLE `empreses`
  MODIFY `id_empresa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `equips`
--
ALTER TABLE `equips`
  MODIFY `id_equip` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fertilizantes`
--
ALTER TABLE `fertilizantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `filas_arboles`
--
ALTER TABLE `filas_arboles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `formacio`
--
ALTER TABLE `formacio`
  MODIFY `id_formacio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fotos_parcela`
--
ALTER TABLE `fotos_parcela`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `herbicidas`
--
ALTER TABLE `herbicidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `herbicidas_tipos_hierba`
--
ALTER TABLE `herbicidas_tipos_hierba`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_cultivos`
--
ALTER TABLE `historial_cultivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `horaris`
--
ALTER TABLE `horaris`
  MODIFY `id_horari` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `incidencias_parcela`
--
ALTER TABLE `incidencias_parcela`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `incidencies`
--
ALTER TABLE `incidencies`
  MODIFY `id_incidencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lectures_sensors`
--
ALTER TABLE `lectures_sensors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `limites_legales_aplicacion`
--
ALTER TABLE `limites_legales_aplicacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lotes_herbicidas`
--
ALTER TABLE `lotes_herbicidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `maquinaria_agricola`
--
ALTER TABLE `maquinaria_agricola`
  MODIFY `id_maquinaria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `maquinaria_manteniment`
--
ALTER TABLE `maquinaria_manteniment`
  MODIFY `id_manteniment` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `monitoratge_plagues`
--
ALTER TABLE `monitoratge_plagues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimientos_stock`
--
ALTER TABLE `movimientos_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `observacions_sector`
--
ALTER TABLE `observacions_sector`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagaments`
--
ALTER TABLE `pagaments`
  MODIFY `id_pagament` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `parcelas`
--
ALTER TABLE `parcelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `plazos_seguridad`
--
ALTER TABLE `plazos_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `presupostos_campanya`
--
ALTER TABLE `presupostos_campanya`
  MODIFY `id_presupost` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos_fitosanitarios`
--
ALTER TABLE `productos_fitosanitarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `registre_collites`
--
ALTER TABLE `registre_collites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `registre_hores`
--
ALTER TABLE `registre_hores`
  MODIFY `id_registre` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `registro_fertilizacions`
--
ALTER TABLE `registro_fertilizacions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `registro_tractaments`
--
ALTER TABLE `registro_tractaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sectores_cultivo`
--
ALTER TABLE `sectores_cultivo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `sectores_parcelas`
--
ALTER TABLE `sectores_parcelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `seguiment_fenologic`
--
ALTER TABLE `seguiment_fenologic`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `stock_herbicidas`
--
ALTER TABLE `stock_herbicidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tasques`
--
ALTER TABLE `tasques`
  MODIFY `id_tasca` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `tasques_maquinaria`
--
ALTER TABLE `tasques_maquinaria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tasques_material`
--
ALTER TABLE `tasques_material`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_hierba`
--
ALTER TABLE `tipos_hierba`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_suelo`
--
ALTER TABLE `tipos_suelo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `treballadors`
--
ALTER TABLE `treballadors`
  MODIFY `id_treballador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `users_app`
--
ALTER TABLE `users_app`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `vacances_permisos`
--
ALTER TABLE `vacances_permisos`
  MODIFY `id_absencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `variedades`
--
ALTER TABLE `variedades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertes`
--
ALTER TABLE `alertes`
  ADD CONSTRAINT `alertes_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alerta_usuari_resolucio` FOREIGN KEY (`id_usuari_resolucio`) REFERENCES `users_app` (`id_user`) ON DELETE SET NULL;

--
-- Filtros para la tabla `alertes_historial`
--
ALTER TABLE `alertes_historial`
  ADD CONSTRAINT `fk_historial_usuari` FOREIGN KEY (`id_usuari_resolucio`) REFERENCES `users_app` (`id_user`) ON DELETE CASCADE;

--
-- Filtros para la tabla `aplicacions`
--
ALTER TABLE `aplicacions`
  ADD CONSTRAINT `fk_aplicacions_fertilizant` FOREIGN KEY (`id_fertilizant`) REFERENCES `fertilizantes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_aplicacions_fila` FOREIGN KEY (`fila_id`) REFERENCES `filas_arboles` (`id`),
  ADD CONSTRAINT `fk_aplicacions_operari` FOREIGN KEY (`id_operari`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aplicacions_producte` FOREIGN KEY (`id_producte`) REFERENCES `productos_fitosanitarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_aplicacions_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `assignacions`
--
ALTER TABLE `assignacions`
  ADD CONSTRAINT `assignacions_ibfk_1` FOREIGN KEY (`id_tasca`) REFERENCES `tasques` (`id_tasca`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignacions_ibfk_2` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE;

--
-- Filtros para la tabla `certificacions`
--
ALTER TABLE `certificacions`
  ADD CONSTRAINT `certificacions_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE;

--
-- Filtros para la tabla `contractes`
--
ALTER TABLE `contractes`
  ADD CONSTRAINT `contractes_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE;

--
-- Filtros para la tabla `control_qualitat`
--
ALTER TABLE `control_qualitat`
  ADD CONSTRAINT `fk_controlqualitat_lot` FOREIGN KEY (`id_lot`) REFERENCES `lots_produccio` (`id_lot`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_controlqualitat_operari` FOREIGN KEY (`id_operari`) REFERENCES `treballadors` (`id_treballador`) ON DELETE SET NULL;

--
-- Filtros para la tabla `costes_tasques`
--
ALTER TABLE `costes_tasques`
  ADD CONSTRAINT `fk_cost_assignacio` FOREIGN KEY (`id_assignacio`) REFERENCES `assignacions` (`id_assignacio`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cost_tasca` FOREIGN KEY (`id_tasca`) REFERENCES `tasques` (`id_tasca`) ON DELETE CASCADE;

--
-- Filtros para la tabla `departaments`
--
ALTER TABLE `departaments`
  ADD CONSTRAINT `departaments_ibfk_1` FOREIGN KEY (`id_responsable`) REFERENCES `treballadors` (`id_treballador`) ON DELETE SET NULL;

--
-- Filtros para la tabla `documentacio`
--
ALTER TABLE `documentacio`
  ADD CONSTRAINT `documentacio_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE;

--
-- Filtros para la tabla `documentos_herbicida`
--
ALTER TABLE `documentos_herbicida`
  ADD CONSTRAINT `documentos_herbicida_ibfk_1` FOREIGN KEY (`herbicida_id`) REFERENCES `herbicidas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `documentos_parcela`
--
ALTER TABLE `documentos_parcela`
  ADD CONSTRAINT `documentos_parcela_ibfk_1` FOREIGN KEY (`parcela_id`) REFERENCES `parcelas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `filas_arboles`
--
ALTER TABLE `filas_arboles`
  ADD CONSTRAINT `filas_arboles_ibfk_1` FOREIGN KEY (`sector_id`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `filas_arboles_ibfk_2` FOREIGN KEY (`variedad_id`) REFERENCES `variedades` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `formacio`
--
ALTER TABLE `formacio`
  ADD CONSTRAINT `formacio_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fotos_parcela`
--
ALTER TABLE `fotos_parcela`
  ADD CONSTRAINT `fotos_parcela_ibfk_1` FOREIGN KEY (`parcela_id`) REFERENCES `parcelas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `herbicidas_tipos_hierba`
--
ALTER TABLE `herbicidas_tipos_hierba`
  ADD CONSTRAINT `herbicidas_tipos_hierba_ibfk_1` FOREIGN KEY (`herbicida_id`) REFERENCES `herbicidas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `herbicidas_tipos_hierba_ibfk_2` FOREIGN KEY (`tipo_hierba_id`) REFERENCES `tipos_hierba` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_cultivos`
--
ALTER TABLE `historial_cultivos`
  ADD CONSTRAINT `historial_cultivos_ibfk_1` FOREIGN KEY (`sector_id`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_cultivos_ibfk_2` FOREIGN KEY (`variedad_id`) REFERENCES `variedades` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencias_parcela`
--
ALTER TABLE `incidencias_parcela`
  ADD CONSTRAINT `incidencias_parcela_ibfk_1` FOREIGN KEY (`parcela_id`) REFERENCES `parcelas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencies`
--
ALTER TABLE `incidencies`
  ADD CONSTRAINT `incidencies_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE,
  ADD CONSTRAINT `incidencies_ibfk_2` FOREIGN KEY (`id_assignacio`) REFERENCES `assignacions` (`id_assignacio`) ON DELETE SET NULL;

--
-- Filtros para la tabla `lectures_sensors`
--
ALTER TABLE `lectures_sensors`
  ADD CONSTRAINT `fk_lectura_sensor` FOREIGN KEY (`id_sensor`) REFERENCES `sensors` (`id_sensor`) ON DELETE CASCADE;

--
-- Filtros para la tabla `limites_legales_aplicacion`
--
ALTER TABLE `limites_legales_aplicacion`
  ADD CONSTRAINT `limites_legales_aplicacion_ibfk_1` FOREIGN KEY (`herbicida_id`) REFERENCES `herbicidas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `limites_legales_aplicacion_ibfk_2` FOREIGN KEY (`cultivo_id`) REFERENCES `cultivos` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `lotes_herbicidas`
--
ALTER TABLE `lotes_herbicidas`
  ADD CONSTRAINT `lotes_herbicidas_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `stock_herbicidas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `lots_produccio`
--
ALTER TABLE `lots_produccio`
  ADD CONSTRAINT `fk_lot_client` FOREIGN KEY (`id_empresa_client`) REFERENCES `empreses` (`id_empresa`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lot_collita` FOREIGN KEY (`id_registre_collita`) REFERENCES `registre_collites` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `maquinaria_manteniment`
--
ALTER TABLE `maquinaria_manteniment`
  ADD CONSTRAINT `fk_manteniment_maquinaria` FOREIGN KEY (`id_maquinaria`) REFERENCES `maquinaria_agricola` (`id_maquinaria`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_manteniment_tecnic` FOREIGN KEY (`id_tecnic`) REFERENCES `treballadors` (`id_treballador`) ON DELETE SET NULL;

--
-- Filtros para la tabla `monitoratge_plagues`
--
ALTER TABLE `monitoratge_plagues`
  ADD CONSTRAINT `fk_monitoratge_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `movimientos_stock`
--
ALTER TABLE `movimientos_stock`
  ADD CONSTRAINT `movimientos_stock_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `stock_herbicidas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `observacions_sector`
--
ALTER TABLE `observacions_sector`
  ADD CONSTRAINT `observacions_sector_ibfk_1` FOREIGN KEY (`id_sector`) REFERENCES `sectores_cultivo` (`id`),
  ADD CONSTRAINT `observacions_sector_ibfk_2` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`);

--
-- Filtros para la tabla `pagaments`
--
ALTER TABLE `pagaments`
  ADD CONSTRAINT `fk_pagament_treballador` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE;

--
-- Filtros para la tabla `parcelas`
--
ALTER TABLE `parcelas`
  ADD CONSTRAINT `parcelas_ibfk_1` FOREIGN KEY (`tipo_suelo_id`) REFERENCES `tipos_suelo` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `plazos_seguridad`
--
ALTER TABLE `plazos_seguridad`
  ADD CONSTRAINT `plazos_seguridad_ibfk_1` FOREIGN KEY (`herbicida_id`) REFERENCES `herbicidas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `presupostos_campanya`
--
ALTER TABLE `presupostos_campanya`
  ADD CONSTRAINT `fk_presupost_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `registre_collites`
--
ALTER TABLE `registre_collites`
  ADD CONSTRAINT `fk_collita_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_collita_varietat` FOREIGN KEY (`varietat_id`) REFERENCES `variedades` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `registre_hores`
--
ALTER TABLE `registre_hores`
  ADD CONSTRAINT `registre_hores_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE,
  ADD CONSTRAINT `registre_hores_ibfk_2` FOREIGN KEY (`id_assignacio`) REFERENCES `assignacions` (`id_assignacio`) ON DELETE SET NULL;

--
-- Filtros para la tabla `registro_fertilizacions`
--
ALTER TABLE `registro_fertilizacions`
  ADD CONSTRAINT `registro_fertilizacions_ibfk_1` FOREIGN KEY (`sector_id`) REFERENCES `sectores_cultivo` (`id`),
  ADD CONSTRAINT `registro_fertilizacions_ibfk_2` FOREIGN KEY (`fertilizante_id`) REFERENCES `fertilizantes` (`id`);

--
-- Filtros para la tabla `registro_tractaments`
--
ALTER TABLE `registro_tractaments`
  ADD CONSTRAINT `registro_tractaments_ibfk_1` FOREIGN KEY (`sector_id`) REFERENCES `sectores_cultivo` (`id`),
  ADD CONSTRAINT `registro_tractaments_ibfk_2` FOREIGN KEY (`herbicida_id`) REFERENCES `herbicidas` (`id`);

--
-- Filtros para la tabla `sectores_parcelas`
--
ALTER TABLE `sectores_parcelas`
  ADD CONSTRAINT `sectores_parcelas_ibfk_1` FOREIGN KEY (`sector_id`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sectores_parcelas_ibfk_2` FOREIGN KEY (`parcela_id`) REFERENCES `parcelas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `seguiment_fenologic`
--
ALTER TABLE `seguiment_fenologic`
  ADD CONSTRAINT `fk_fenologic_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores_cultivo` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `stock_herbicidas`
--
ALTER TABLE `stock_herbicidas`
  ADD CONSTRAINT `stock_herbicidas_ibfk_1` FOREIGN KEY (`herbicida_id`) REFERENCES `herbicidas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tasques`
--
ALTER TABLE `tasques`
  ADD CONSTRAINT `fk_tasques_sector` FOREIGN KEY (`id_sector`) REFERENCES `sectores_cultivo` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasques_ibfk_1` FOREIGN KEY (`id_tasca_precedent`) REFERENCES `tasques` (`id_tasca`) ON DELETE SET NULL;

--
-- Filtros para la tabla `tasques_maquinaria`
--
ALTER TABLE `tasques_maquinaria`
  ADD CONSTRAINT `fk_tasca_maquinaria_maquinaria` FOREIGN KEY (`id_maquinaria`) REFERENCES `maquinaria_agricola` (`id_maquinaria`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tasca_maquinaria_tasca` FOREIGN KEY (`id_tasca`) REFERENCES `tasques` (`id_tasca`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tasques_material`
--
ALTER TABLE `tasques_material`
  ADD CONSTRAINT `fk_material_tasca` FOREIGN KEY (`id_tasca`) REFERENCES `tasques` (`id_tasca`) ON DELETE CASCADE;

--
-- Filtros para la tabla `treballadors`
--
ALTER TABLE `treballadors`
  ADD CONSTRAINT `fk_treballadors_horaris` FOREIGN KEY (`id_horari`) REFERENCES `horaris` (`id_horari`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `treballadors_ibfk_1` FOREIGN KEY (`id_departament`) REFERENCES `departaments` (`id_departament`) ON DELETE SET NULL,
  ADD CONSTRAINT `treballadors_ibfk_2` FOREIGN KEY (`id_equip`) REFERENCES `equips` (`id_equip`) ON DELETE SET NULL;

--
-- Filtros para la tabla `users_app`
--
ALTER TABLE `users_app`
  ADD CONSTRAINT `fk_user_treballador` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE SET NULL;

--
-- Filtros para la tabla `vacances_permisos`
--
ALTER TABLE `vacances_permisos`
  ADD CONSTRAINT `vacances_permisos_ibfk_1` FOREIGN KEY (`id_treballador`) REFERENCES `treballadors` (`id_treballador`) ON DELETE CASCADE;

--
-- Filtros para la tabla `variedades`
--
ALTER TABLE `variedades`
  ADD CONSTRAINT `variedades_ibfk_1` FOREIGN KEY (`cultivo_id`) REFERENCES `cultivos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
