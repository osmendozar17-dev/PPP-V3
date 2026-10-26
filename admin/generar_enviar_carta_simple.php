<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login.php?sesion=expirada");
    exit;
}

if ($_SESSION["rol"] === "Estudiante") {
    header("Location: ../solicitud.php");
    exit;
}

require_once "../vendor/autoload.php";
include "../conexion.php";
include "../enviar_correo.php";
require_once "../creditos_config.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    die("ID de solicitud no especificado.");
}

$solicitud_id = intval($_GET["id"]);

if ($solicitud_id <= 0) {
    die("ID de solicitud inválido.");
}

/* ============================================================
   FUNCIONES AUXILIARES
============================================================ */

function limpiarTextoCartaSimple($valor) {
    return trim((string)($valor ?? ""));
}

function textoMayusculaCartaSimple($valor) {
    return mb_strtoupper(limpiarTextoCartaSimple($valor), "UTF-8");
}

function crearDirectorioCartaSimpleSiNoExiste($ruta) {
    if (!is_dir($ruta)) {
        mkdir($ruta, 0775, true);
    }
}

function fechaLargaEspanolCartaSimple($fecha = null) {
    if ($fecha === null) {
        $fecha = new DateTime();
    }

    $dias = [
        "Sunday" => "domingo",
        "Monday" => "lunes",
        "Tuesday" => "martes",
        "Wednesday" => "miércoles",
        "Thursday" => "jueves",
        "Friday" => "viernes",
        "Saturday" => "sábado"
    ];

    $meses = [
        "January" => "Enero",
        "February" => "Febrero",
        "March" => "Marzo",
        "April" => "Abril",
        "May" => "Mayo",
        "June" => "Junio",
        "July" => "Julio",
        "August" => "Agosto",
        "September" => "Septiembre",
        "October" => "Octubre",
        "November" => "Noviembre",
        "December" => "Diciembre"
    ];

    $diaSemana = $dias[$fecha->format("l")] ?? $fecha->format("l");
    $dia = $fecha->format("d");
    $mes = $meses[$fecha->format("F")] ?? $fecha->format("F");
    $anio = $fecha->format("Y");

    return $diaSemana . ", " . $dia . " de " . $mes . " de " . $anio;
}

function rutaFisicaDesdeBDCartaSimple($rutaBD) {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rutaBD);
}

function rutaBDDesdeFisicaCartaSimple($rutaFisica) {
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    $relativa = str_replace($base, "", $rutaFisica);
    return str_replace(DIRECTORY_SEPARATOR, "/", $relativa);
}

function nombreResponsableSistemaSimple() {
    $config_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . "correo_config.php";

    if (file_exists($config_path)) {
        $config = require $config_path;
        return trim($config["nombre_responsable"] ?? "Responsable de Prácticas Preprofesionales");
    }

    return "Responsable de Prácticas Preprofesionales";
}

/* ============================================================
   OBTENER DATOS DE LA SOLICITUD
============================================================ */

$sql = "SELECT 
            id,
            usuario_id,
            nombres,
            apellidos,
            dni,
            codigo_estudiante,
            correo_institucional,
            escuela,
            codigo_liquidacion,

            total_creditos_aprobados,
            cumple_creditos,
            tipo_carta,

            archivo_pdf_consolidado,
            archivo_carta_simple,
            fecha_generacion_carta_simple,
            numero_carta_simple,

            estado,
            observacion,
            fecha_solicitud,
            fecha_revision
        FROM solicitudes_carta
        WHERE id = ?
        LIMIT 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar consulta: " . $conexion->error);
}

$stmt->bind_param("i", $solicitud_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    die("Solicitud no encontrada.");
}

$solicitud = $resultado->fetch_assoc();
$stmt->close();

/* ============================================================
   VALIDACIONES
============================================================ */

$tipo_carta = limpiarTextoCartaSimple($solicitud["tipo_carta"] ?? "");

if ($tipo_carta === "") {
    $tipo_carta = intval($solicitud["cumple_creditos"]) === 1 ? "Presentacion PPP" : "Simple";
}

if ($tipo_carta !== "Simple" || intval($solicitud["cumple_creditos"]) === 1) {
    die("No se puede generar Carta Simple porque esta solicitud corresponde a Carta de Presentación PPP.");
}

/* ============================================================
   DATOS DINÁMICOS DE CRÉDITOS Y CARRERA
============================================================ */

$datos_creditos_malla = obtenerDatosCreditosMalla($solicitud["escuela"] ?? "");

$total_creditos_malla = intval($datos_creditos_malla["total"]);
$creditos_minimos_malla = intval($datos_creditos_malla["minimo"]);
$nombre_carrera = $datos_creditos_malla["carrera"];

$total_creditos_aprobados = intval($solicitud["total_creditos_aprobados"] ?? 0);

/* ============================================================
   DATOS PARA LA CARTA SIMPLE
============================================================ */

$anio_carta = date("Y");
$numero_carta_simple = "CS-" . str_pad($solicitud_id, 3, "0", STR_PAD_LEFT) . "-" . $anio_carta . "-OPPP-FIEI-UNFV";

$alumno = textoMayusculaCartaSimple($solicitud["apellidos"] . " " . $solicitud["nombres"]);
$codigo_estudiante = limpiarTextoCartaSimple($solicitud["codigo_estudiante"]);
$correo_estudiante = limpiarTextoCartaSimple($solicitud["correo_institucional"]);
$escuela = limpiarTextoCartaSimple($solicitud["escuela"]);
$responsable = nombreResponsableSistemaSimple();

$fecha_generacion = new DateTime();
$fecha_carta = fechaLargaEspanolCartaSimple($fecha_generacion);

/* ============================================================
   RUTAS
============================================================ */

$plantilla = dirname(__DIR__) . DIRECTORY_SEPARATOR . "plantillas" . DIRECTORY_SEPARATOR . "carta_simple_base.jpg";

if (!file_exists($plantilla)) {
    die("No se encontró la plantilla de carta simple en: plantillas/carta_simple_base.jpg");
}

if (!empty($solicitud["archivo_pdf_consolidado"])) {
    $rutaConsolidadoFisica = rutaFisicaDesdeBDCartaSimple($solicitud["archivo_pdf_consolidado"]);
    $carpetaSolicitudFisica = dirname($rutaConsolidadoFisica);
} else {
    $carpetaSolicitudFisica = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "cartas_simples" . DIRECTORY_SEPARATOR . "solicitud_" . $solicitud_id;
}

crearDirectorioCartaSimpleSiNoExiste($carpetaSolicitudFisica);

$nombreArchivoCartaSimple = "carta_simple_" . $solicitud_id . ".pdf";
$rutaCartaSimpleFisica = $carpetaSolicitudFisica . DIRECTORY_SEPARATOR . $nombreArchivoCartaSimple;
$rutaCartaSimpleBD = rutaBDDesdeFisicaCartaSimple($rutaCartaSimpleFisica);

/* ============================================================
   GENERAR PDF CON PLANTILLA
============================================================ */

$pdf = new TCPDF("P", "mm", "A4", true, "UTF-8", false);

$pdf->SetCreator("Sistema PPP - FIEI UNFV");
$pdf->SetAuthor("FIEI UNFV");
$pdf->SetTitle("Carta Simple");
$pdf->SetSubject("Carta Simple");
$pdf->SetKeywords("PPP, Carta Simple, FIEI, UNFV");

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

$pdf->Image($plantilla, 0, 0, 210, 297, "", "", "", false, 300, "", false, false, 0);

/*
   Cubrir zonas variables de la plantilla.
*/
$pdf->SetFillColor(255, 255, 255);

/* Título */
$pdf->Rect(35, 37, 145, 10, "F");

/* Fecha */
$pdf->Rect(105, 47, 90, 9, "F");

/* Datos destinatario */
$pdf->Rect(28, 56, 165, 32, "F");

/* Cuerpo */
$pdf->Rect(28, 88, 158, 100, "F");

/* NT o zona inferior */
$pdf->Rect(28, 250, 95, 10, "F");

/* ============================================================
   ESCRIBIR TEXTO
============================================================ */

$pdf->SetTextColor(0, 0, 0);

/* Número de carta */
$pdf->SetFont("times", "BIU", 12);
$pdf->SetXY(40, 38);
$pdf->Cell(130, 7, "CARTA SIMPLE Nº " . $numero_carta_simple, 0, 1, "C");

/* Fecha */
$pdf->SetFont("times", "BI", 11);
$pdf->SetXY(112, 48);
$pdf->Cell(70, 6, "Breña, " . $fecha_carta, 0, 1, "L");

/* Destinatario */
$pdf->SetFont("times", "BI", 11);
$pdf->SetXY(32, 58);
$pdf->Cell(0, 6, "Alumno(a):", 0, 1, "L");

$pdf->SetXY(32, 66);
$pdf->MultiCell(150, 5, $alumno, 0, "L", false);

$pdf->SetXY(32, 76);
$pdf->Cell(0, 5, "Escuela Profesional: " . $escuela, 0, 1, "L");

/* Cuerpo */
$pdf->SetFont("times", "I", 11);

$html_cuerpo = '
<style>
    p {
        text-align: justify;
        line-height: 1.55;
        font-style: italic;
        font-size: 11.5pt;
    }
    b {
        font-weight: bold;
        font-style: italic;
    }
</style>

<p><u>Presente.-</u></p>

<p>De mi especial consideración:</p>

<p>
Es grato dirigirme a usted para dejar constancia que el alumno(a) 
<b>' . htmlspecialchars($alumno) . '</b>, con Código de Matrícula Nº 
<b>' . htmlspecialchars($codigo_estudiante) . '</b>, perteneciente a la carrera de 
<b>' . htmlspecialchars($nombre_carrera) . '</b>, registra actualmente 
<b>' . htmlspecialchars($total_creditos_aprobados) . ' créditos aprobados</b>.
</p>

<p>
Se precisa que el estudiante aún no cumple con el requisito mínimo del 80% de créditos aprobados requerido para la Carta de Presentación de Prácticas Preprofesionales, siendo el mínimo de 
<b>' . htmlspecialchars($creditos_minimos_malla) . ' créditos</b> de un total de 
<b>' . htmlspecialchars($total_creditos_malla) . ' créditos</b>.
</p>

<p>
Sin embargo, la presente Carta Simple se emite para que pueda iniciar actividades de acercamiento, apoyo o experiencia formativa inicial.
</p>

<p>
Atentamente,
</p>
';

$pdf->writeHTMLCell(154, 0, 30, 88, $html_cuerpo, 0, 1, false, true, "J", true);

/* Responsable */
$pdf->SetFont("times", "BI", 11);
$pdf->SetXY(65, 205);
$pdf->Cell(80, 6, $responsable, 0, 1, "C");

$pdf->SetFont("times", "I", 10);
$pdf->SetXY(65, 211);
$pdf->Cell(80, 5, "Responsable de Prácticas Preprofesionales", 0, 1, "C");

/* Código inferior */
$pdf->SetFont("times", "I", 10);
$pdf->SetXY(31, 252);
$pdf->Cell(100, 6, "Solicitud ID: " . $solicitud_id, 0, 1, "L");

/* Guardar */
$pdf->Output($rutaCartaSimpleFisica, "F");

/* ============================================================
   ACTUALIZAR BASE DE DATOS
============================================================ */

$sql_update = "UPDATE solicitudes_carta
               SET archivo_carta_simple = ?,
                   fecha_generacion_carta_simple = NOW(),
                   numero_carta_simple = ?
               WHERE id = ?";

$stmt_update = $conexion->prepare($sql_update);

if (!$stmt_update) {
    die("Error al preparar actualización de Carta Simple: " . $conexion->error);
}

$stmt_update->bind_param("ssi", $rutaCartaSimpleBD, $numero_carta_simple, $solicitud_id);

if (!$stmt_update->execute()) {
    die("Error al guardar datos de Carta Simple: " . $stmt_update->error);
}

$stmt_update->close();

/* ============================================================
   REGISTRAR HISTORIAL
============================================================ */

$accion = "Carta Simple generada";
$descripcion = "El ingeniero generó la Carta Simple Nº " . $numero_carta_simple . " y la envió al estudiante.";

$sql_historial = "INSERT INTO historial_solicitudes (
                    solicitud_id,
                    accion,
                    descripcion
                  ) VALUES (?, ?, ?)";

$stmt_historial = $conexion->prepare($sql_historial);

if ($stmt_historial) {
    $stmt_historial->bind_param("iss", $solicitud_id, $accion, $descripcion);
    $stmt_historial->execute();
    $stmt_historial->close();
}

/* ============================================================
   ENVIAR CORREO AL ESTUDIANTE
============================================================ */

$datos_correo = [
    "solicitud_id" => $solicitud_id,
    "numero_carta_simple" => $numero_carta_simple,
    "nombres" => $solicitud["nombres"],
    "apellidos" => $solicitud["apellidos"],
    "codigo_estudiante" => $codigo_estudiante,
    "correo_institucional" => $correo_estudiante,
    "escuela" => $escuela,
    "carrera" => $nombre_carrera,
    "total_creditos_aprobados" => $total_creditos_aprobados,
    "creditos_minimos" => $creditos_minimos_malla,
    "total_creditos_malla" => $total_creditos_malla,
    "tipo_carta" => "Simple"
];

$resultado_correo = true;

if (function_exists("enviarCorreoCartaSimpleEstudiante")) {
    $resultado_correo = enviarCorreoCartaSimpleEstudiante($datos_correo, $rutaCartaSimpleFisica);
}

header("Location: ver_solicitud.php?id=" . $solicitud_id . "&simple=generada");
exit;
?>