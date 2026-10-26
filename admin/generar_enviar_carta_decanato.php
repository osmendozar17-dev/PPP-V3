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

function limpiarTextoCarta($valor) {
    return trim((string)($valor ?? ""));
}

function textoMayusculaCarta($valor) {
    return mb_strtoupper(limpiarTextoCarta($valor), "UTF-8");
}

function crearDirectorioCartaSiNoExiste($ruta) {
    if (!is_dir($ruta)) {
        mkdir($ruta, 0775, true);
    }
}

function numeroRomanoCiclo($numero) {
    $romanos = [
        1 => "I",
        2 => "II",
        3 => "III",
        4 => "IV",
        5 => "V",
        6 => "VI",
        7 => "VII",
        8 => "VIII",
        9 => "IX",
        10 => "X"
    ];

    return $romanos[intval($numero)] ?? strval($numero);
}

function nombreCarreraPorEscuela($escuela) {
    $escuela_normalizada = mb_strtoupper(trim($escuela), "UTF-8");

    if (str_contains($escuela_normalizada, "INFORM")) {
        return "Ingeniería Informática";
    }

    if (str_contains($escuela_normalizada, "TELE")) {
        return "Ingeniería de Telecomunicaciones";
    }

    if (str_contains($escuela_normalizada, "ELECT")) {
        return "Ingeniería Electrónica";
    }

    if (str_contains($escuela_normalizada, "MECA")) {
        return "Ingeniería Mecatrónica";
    }

    return "Ingeniería";
}

function fechaLargaEspanol($fecha = null) {
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

function rutaFisicaDesdeBDCarta($rutaBD) {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rutaBD);
}

function rutaBDDesdeFisicaCarta($rutaFisica) {
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    $relativa = str_replace($base, "", $rutaFisica);
    return str_replace(DIRECTORY_SEPARATOR, "/", $relativa);
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
            duracion_meses,
            fecha_inicio_practicas,
            fecha_fin_practicas,
            informe_ocracc_numero,
            informe_ocracc_fecha,

            ruc_empresa,
            razon_social_empresa,
            estado_ruc,
            condicion_ruc,
            responsable_empresa,
            cargo_responsable,
            representante_dni,
            representante_telefono,
            representante_email,
            direccion_empresa,
            ubicacion_empresa,

            numero_tramite,
            fecha_registro_nt,

            archivo_pdf_consolidado,
            archivo_carta_presentacion,
            fecha_generacion_carta,
            numero_carta_presentacion,

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

$tipo_carta = limpiarTextoCarta($solicitud["tipo_carta"] ?? "");

if ($tipo_carta === "") {
    $tipo_carta = intval($solicitud["cumple_creditos"]) === 1 ? "Presentacion PPP" : "Simple";
}

if ($tipo_carta !== "Presentacion PPP" || intval($solicitud["cumple_creditos"]) !== 1) {
    die("No se puede generar la carta para Decanato porque esta solicitud no corresponde a Carta de Presentación PPP.");
}

if (empty($solicitud["ruc_empresa"]) || empty($solicitud["razon_social_empresa"])) {
    die("No se puede generar la carta porque faltan los datos de la empresa.");
}

if (empty($solicitud["responsable_empresa"])) {
    die("No se puede generar la carta porque falta el representante de la empresa.");
}

if (empty($solicitud["numero_tramite"])) {
    die("No se puede generar la carta porque el estudiante aún no registró el Número de Trámite UNFV.");
}

/* ============================================================
   OBTENER CICLO MÁXIMO APROBADO
============================================================ */

$sql_ciclo = "SELECT MAX(cm.ciclo) AS ciclo_maximo
              FROM solicitud_cursos sc
              INNER JOIN cursos_malla cm ON sc.curso_id = cm.id
              WHERE sc.solicitud_id = ?";

$stmt_ciclo = $conexion->prepare($sql_ciclo);

if (!$stmt_ciclo) {
    die("Error al preparar consulta de ciclo: " . $conexion->error);
}

$stmt_ciclo->bind_param("i", $solicitud_id);
$stmt_ciclo->execute();
$resultado_ciclo = $stmt_ciclo->get_result();
$fila_ciclo = $resultado_ciclo->fetch_assoc();
$stmt_ciclo->close();

$ciclo_culminado = intval($fila_ciclo["ciclo_maximo"] ?? 0);

if ($ciclo_culminado <= 0) {
    die("No se puede generar la carta porque no hay cursos aprobados asociados a la solicitud.");
}

$ciclo_matricula = $ciclo_culminado + 1;

if ($ciclo_matricula > 10) {
    $ciclo_matricula = 10;
}

/* ============================================================
   DATOS PARA LA CARTA
============================================================ */

$anio_carta = date("Y");
$numero_carta = str_pad($solicitud_id, 3, "0", STR_PAD_LEFT) . "-" . $anio_carta . "-OPPP-FIEI-UNFV";

$representante = textoMayusculaCarta($solicitud["responsable_empresa"]);
$empresa = textoMayusculaCarta($solicitud["razon_social_empresa"]);

$alumno = textoMayusculaCarta($solicitud["apellidos"] . " " . $solicitud["nombres"]);
$codigo_estudiante = limpiarTextoCarta($solicitud["codigo_estudiante"]);
$nt = limpiarTextoCarta($solicitud["numero_tramite"]);
$duracion_practicas_val = intval($solicitud["duracion_meses"] ?? 6);
$duracion_texto = sprintf("%02d", $duracion_practicas_val) . " " . ($duracion_practicas_val === 1 ? "mes" : "meses");

$periodo_fechas_texto = "";
if (!empty($solicitud["fecha_inicio_practicas"]) && !empty($solicitud["fecha_fin_practicas"])) {
    $f_inicio = date("d/m/Y", strtotime($solicitud["fecha_inicio_practicas"]));
    $f_fin = date("d/m/Y", strtotime($solicitud["fecha_fin_practicas"]));
    $periodo_fechas_texto = " (del " . $f_inicio . " al " . $f_fin . ")";
}

$ocracc_texto = "";
if (!empty($solicitud["informe_ocracc_numero"])) {
    $ocracc_fecha_str = "";
    if (!empty($solicitud["informe_ocracc_fecha"])) {
        $ocracc_fecha_str = " de fecha " . date("d/m/Y", strtotime($solicitud["informe_ocracc_fecha"]));
    }
    $ocracc_texto = " según " . htmlspecialchars($solicitud["informe_ocracc_numero"]) . $ocracc_fecha_str . ",";
}

$carrera = nombreCarreraPorEscuela($solicitud["escuela"]);
$ciclo_culminado_romano = numeroRomanoCiclo($ciclo_culminado);
$ciclo_matricula_romano = numeroRomanoCiclo($ciclo_matricula);

$fecha_generacion = new DateTime();
$fecha_carta = fechaLargaEspanol($fecha_generacion);

/* ============================================================
   RUTAS
============================================================ */

$plantilla = dirname(__DIR__) . DIRECTORY_SEPARATOR . "plantillas" . DIRECTORY_SEPARATOR . "carta_presentacion_base.jpg";

if (!file_exists($plantilla)) {
    die("No se encontró la plantilla de carta en: plantillas/carta_presentacion_base.jpg");
}

if (!empty($solicitud["archivo_pdf_consolidado"])) {
    $rutaConsolidadoFisica = rutaFisicaDesdeBDCarta($solicitud["archivo_pdf_consolidado"]);
    $carpetaSolicitudFisica = dirname($rutaConsolidadoFisica);
} else {
    $carpetaSolicitudFisica = dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "cartas_decanato" . DIRECTORY_SEPARATOR . "solicitud_" . $solicitud_id;
}

crearDirectorioCartaSiNoExiste($carpetaSolicitudFisica);

$nombreArchivoCarta = "carta_presentacion_decanato_" . $solicitud_id . ".pdf";
$rutaCartaFisica = $carpetaSolicitudFisica . DIRECTORY_SEPARATOR . $nombreArchivoCarta;
$rutaCartaBD = rutaBDDesdeFisicaCarta($rutaCartaFisica);

/* ============================================================
   GENERAR PDF CON PLANTILLA DE FONDO
============================================================ */

$pdf = new TCPDF("P", "mm", "A4", true, "UTF-8", false);

$pdf->SetCreator("Sistema PPP - FIEI UNFV");
$pdf->SetAuthor("FIEI UNFV");
$pdf->SetTitle("Carta de Presentación PPP");
$pdf->SetSubject("Carta de Presentación PPP");
$pdf->SetKeywords("PPP, Carta, Decanato, FIEI, UNFV");

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

/*
   Plantilla como fondo.
   A4 vertical: 210mm x 297mm.
*/
$pdf->Image($plantilla, 0, 0, 210, 297, "", "", "", false, 300, "", false, false, 0);

/*
   Cubrir zonas donde se escribirá información variable.
   Se usan rectángulos blancos amplios para eliminar cualquier texto,
   letra o palabra de ejemplo que venga en la plantilla.
*/
$pdf->SetFillColor(255, 255, 255);

/* Número de carta */
$pdf->Rect(35, 37, 145, 10, "F");

/* Fecha */
$pdf->Rect(105, 47, 90, 9, "F");

/* Datos del señor, representante y empresa */
$pdf->Rect(28, 56, 165, 32, "F");

/* Presente, saludo y cuerpo principal */
$pdf->Rect(28, 88, 158, 88, "F");

/* Espacio antes de firmas, por si queda texto duplicado */
$pdf->Rect(28, 176, 158, 10, "F");

/* NT inferior */
$pdf->Rect(28, 250, 95, 10, "F");

/* ============================================================
   ESCRIBIR TEXTO SOBRE LA PLANTILLA
============================================================ */

$pdf->SetTextColor(0, 0, 0);

/* Número de carta */
$pdf->SetFont("times", "BIU", 12);
$pdf->SetXY(40, 38);
$pdf->Cell(130, 7, "CARTA DE PRESENTACIÓN Nº " . $numero_carta, 0, 1, "C");

/* Fecha */
$pdf->SetFont("times", "BI", 11);
$pdf->SetXY(112, 48);
$pdf->Cell(70, 6, "Breña, " . $fecha_carta, 0, 1, "L");

/* Señor y empresa */
$pdf->SetFont("times", "BI", 11);

$pdf->SetXY(32, 58);
$pdf->Cell(0, 6, "Señor", 0, 1, "L");

$pdf->SetXY(32, 68);
$pdf->MultiCell(150, 5, $representante, 0, "L", false);

$pdf->SetXY(32, 76);
$pdf->Cell(0, 5, "Representante de la empresa", 0, 1, "L");

$pdf->SetXY(32, 82);
$pdf->MultiCell(150, 5, "EMPRESA: " . $empresa, 0, "L", false);

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
Es grato dirigirme a usted, para expresar mi cordial saludo y a la vez presentar al alumno(a) 
<b>' . htmlspecialchars($alumno) . '</b>, con Código de Matrícula Nº 
<b>' . htmlspecialchars($codigo_estudiante) . '</b>, quien ha culminado el 
<b>' . htmlspecialchars($ciclo_culminado_romano) . ' Ciclo</b> de la 
<b>' . htmlspecialchars($carrera) . '</b>, y para poderse matricular en todos sus créditos correspondientes al 
<b>' . htmlspecialchars($ciclo_matricula_romano) . ' ciclo</b> de su carrera requiere realizar sus 
<b>Prácticas Pre Profesionales</b> en la empresa que usted dirige, por un período de ' . htmlspecialchars($duracion_texto) . $periodo_fechas_texto . $ocracc_texto . '; según lo dispuesto en la Ley Nº 28518 Ley sobre Modalidades Formativas Profesionales, por lo que agradeceré se sirva brindar las facilidades que el caso requiera.
</p>

<p>
Sin otro particular, es propicia la ocasión para expresar a usted las muestras de mi especial consideración.
<br>
Atentamente,
</p>
';

$pdf->writeHTMLCell(154, 0, 30, 88, $html_cuerpo, 0, 1, false, true, "J", true);

/* NT */
$pdf->SetFont("times", "I", 11);
$pdf->SetXY(31, 252);
$pdf->Cell(90, 6, "NT. " . $nt, 0, 1, "L");

/* Guardar PDF */
$pdf->Output($rutaCartaFisica, "F");

/* ============================================================
   ACTUALIZAR BASE DE DATOS
============================================================ */

$sql_update = "UPDATE solicitudes_carta
               SET archivo_carta_presentacion = ?,
                   fecha_generacion_carta = NOW(),
                   numero_carta_presentacion = ?
               WHERE id = ?";

$stmt_update = $conexion->prepare($sql_update);

if (!$stmt_update) {
    die("Error al preparar actualización de carta: " . $conexion->error);
}

$stmt_update->bind_param("ssi", $rutaCartaBD, $numero_carta, $solicitud_id);

if (!$stmt_update->execute()) {
    die("Error al guardar datos de la carta: " . $stmt_update->error);
}

$stmt_update->close();

/* ============================================================
   REGISTRAR HISTORIAL
============================================================ */

$accion = "Carta de presentación generada";
$descripcion = "El ingeniero generó la carta de presentación Nº " . $numero_carta . " y la envió a Decanato.";

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
   ENVIAR CORREO A DECANATO
============================================================ */

$datos_correo = [
    "solicitud_id" => $solicitud_id,
    "numero_carta" => $numero_carta,
    "nombres" => $solicitud["nombres"],
    "apellidos" => $solicitud["apellidos"],
    "codigo_estudiante" => $solicitud["codigo_estudiante"],
    "correo_institucional" => $solicitud["correo_institucional"],
    "escuela" => $solicitud["escuela"],
    "tipo_carta" => $tipo_carta,
    "numero_tramite" => $nt,
    "ruc_empresa" => $solicitud["ruc_empresa"],
    "razon_social_empresa" => $solicitud["razon_social_empresa"],
    "responsable_empresa" => $solicitud["responsable_empresa"],
    "ciclo_culminado" => $ciclo_culminado_romano,
    "ciclo_matricula" => $ciclo_matricula_romano,
    "carrera" => $carrera
];

$resultado_correo = true;

if (function_exists("enviarCorreoCartaDecanato")) {
    $resultado_correo = enviarCorreoCartaDecanato($datos_correo, $rutaCartaFisica);
}

/*
   Si aún no existe la función en enviar_correo.php, no rompemos el flujo.
   En el siguiente paso la agregamos.
*/
header("Location: ver_solicitud.php?id=" . $solicitud_id . "&carta=generada");
exit;
?>