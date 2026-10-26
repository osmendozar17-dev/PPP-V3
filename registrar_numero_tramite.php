<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php?sesion=expirada");
    exit;
}

if ($_SESSION["rol"] !== "Estudiante") {
    header("Location: admin/panel.php");
    exit;
}

include "conexion.php";
include "enviar_correo.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Acceso no permitido.");
}

$usuario_id = intval($_SESSION["usuario_id"]);
$solicitud_id = intval($_POST["solicitud_id"] ?? 0);
$numero_tramite = trim($_POST["numero_tramite"] ?? "");

if ($solicitud_id <= 0) {
    die("ID de solicitud inválido.");
}

if (empty($numero_tramite)) {
    die("Debe ingresar el Número de Trámite.");
}

/*
   Limpiamos el NT para evitar caracteres raros.
   Permitimos letras, números, guion, slash, punto y espacios.
*/
if (!preg_match("/^[a-zA-Z0-9\-\_\/\.\s]+$/", $numero_tramite)) {
    die("El Número de Trámite contiene caracteres no permitidos.");
}

/*
   Verificar que la solicitud pertenezca al estudiante.
*/
$sql = "SELECT 
            id,
            usuario_id,
            nombres,
            apellidos,
            dni,
            codigo_estudiante,
            correo_institucional,
            escuela,
            tipo_carta,
            total_creditos_aprobados,
            numero_tramite,
            archivo_pdf_consolidado,
            estado
        FROM solicitudes_carta
        WHERE id = ? AND usuario_id = ?
        LIMIT 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar consulta: " . $conexion->error);
}

$stmt->bind_param("ii", $solicitud_id, $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    die("Solicitud no encontrada o no pertenece a este estudiante.");
}

$solicitud = $resultado->fetch_assoc();
$stmt->close();

if (!empty($solicitud["numero_tramite"])) {
    header("Location: ver_mi_solicitud.php?id=" . $solicitud_id . "&nt=ok");
    exit;
}

/*
   Guardar NT.
*/
$sql_update = "UPDATE solicitudes_carta
               SET numero_tramite = ?,
                   fecha_registro_nt = NOW()
               WHERE id = ? AND usuario_id = ?";

$stmt_update = $conexion->prepare($sql_update);

if (!$stmt_update) {
    die("Error al preparar actualización del NT: " . $conexion->error);
}

$stmt_update->bind_param("sii", $numero_tramite, $solicitud_id, $usuario_id);

if (!$stmt_update->execute()) {
    die("Error al guardar el Número de Trámite: " . $stmt_update->error);
}

$stmt_update->close();

/*
   Registrar historial.
*/
$accion = "Número de Trámite registrado";
$descripcion = "El estudiante registró el Número de Trámite UNFV: " . $numero_tramite . ".";

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

/*
   Enviar correo al responsable/ingeniero.
*/
$datos_correo_nt = [
    "solicitud_id" => $solicitud["id"],
    "nombres" => $solicitud["nombres"],
    "apellidos" => $solicitud["apellidos"],
    "dni" => $solicitud["dni"],
    "codigo_estudiante" => $solicitud["codigo_estudiante"],
    "correo_institucional" => $solicitud["correo_institucional"],
    "escuela" => $solicitud["escuela"],
    "tipo_carta" => $solicitud["tipo_carta"],
    "total_creditos_aprobados" => $solicitud["total_creditos_aprobados"],
    "numero_tramite" => $numero_tramite,
    "archivo_pdf_consolidado" => $solicitud["archivo_pdf_consolidado"],
    "estado" => $solicitud["estado"]
];

if (function_exists("enviarCorreoNumeroTramite")) {
    enviarCorreoNumeroTramite($datos_correo_nt);
}

header("Location: ver_mi_solicitud.php?id=" . $solicitud_id . "&nt=ok");
exit;

$conexion->close();
?>