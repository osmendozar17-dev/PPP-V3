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

include "../conexion.php";
include "../enviar_correo.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Acceso no permitido.");
}

$solicitud_id = intval($_POST["solicitud_id"] ?? 0);
$estado = trim($_POST["estado"] ?? "");
$observacion = trim($_POST["observacion"] ?? "");

$estados_permitidos = ["Pendiente", "Observado", "Aprobado", "Rechazado"];

if ($solicitud_id <= 0) {
    die("ID de solicitud inválido.");
}

if (!in_array($estado, $estados_permitidos)) {
    die("Estado no permitido.");
}

/*
   Si la solicitud será observada o rechazada,
   es recomendable exigir una observación.
*/
if (($estado === "Observado" || $estado === "Rechazado") && empty($observacion)) {
    die("Debe ingresar una observación cuando el estado sea Observado o Rechazado.");
}

/*
   Primero obtenemos los datos actuales de la solicitud.
   Esto permite registrar mejor el historial y enviar correo.
*/
$sql_solicitud = "SELECT 
                    id,
                    nombres,
                    apellidos,
                    codigo_estudiante,
                    correo_institucional,
                    escuela,
                    estado,
                    tipo_carta,
                    total_creditos_aprobados,
                    ruc_empresa,
                    razon_social_empresa
                  FROM solicitudes_carta
                  WHERE id = ?
                  LIMIT 1";

$stmt_solicitud = $conexion->prepare($sql_solicitud);

if (!$stmt_solicitud) {
    die("Error al preparar consulta de solicitud: " . $conexion->error);
}

$stmt_solicitud->bind_param("i", $solicitud_id);
$stmt_solicitud->execute();
$resultado_solicitud = $stmt_solicitud->get_result();

if ($resultado_solicitud->num_rows !== 1) {
    die("Solicitud no encontrada.");
}

$solicitud = $resultado_solicitud->fetch_assoc();
$stmt_solicitud->close();

$estado_anterior = $solicitud["estado"];

/*
   Actualizar estado de la solicitud.
*/
$sql = "UPDATE solicitudes_carta
        SET estado = ?,
            observacion = ?,
            fecha_revision = NOW()
        WHERE id = ?";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar la actualización: " . $conexion->error);
}

$stmt->bind_param("ssi", $estado, $observacion, $solicitud_id);

if ($stmt->execute()) {

    /*
       Registrar historial.
    */
    $accion = "Estado actualizado";
    $descripcion = "El ingeniero actualizó la solicitud del estado " . $estado_anterior . " al estado: " . $estado . ".";

    if (!empty($observacion)) {
        $descripcion .= " Observación: " . $observacion;
    }

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
       Preparar datos para correo al estudiante.
    */
    $datos_correo_estado = [
        "solicitud_id" => $solicitud["id"],
        "nombres" => $solicitud["nombres"],
        "apellidos" => $solicitud["apellidos"],
        "codigo_estudiante" => $solicitud["codigo_estudiante"],
        "correo_institucional" => $solicitud["correo_institucional"],
        "escuela" => $solicitud["escuela"],
        "estado_anterior" => $estado_anterior,
        "estado" => $estado,
        "observacion" => $observacion,
        "tipo_carta" => $solicitud["tipo_carta"],
        "total_creditos_aprobados" => $solicitud["total_creditos_aprobados"],
        "ruc_empresa" => $solicitud["ruc_empresa"],
        "razon_social_empresa" => $solicitud["razon_social_empresa"]
    ];

    /*
       Enviar correo si existe la función en enviar_correo.php.
       Esto evita que el sistema se rompa si la función aún no está creada.
    */
    if (function_exists("enviarCorreoCambioEstado")) {
        enviarCorreoCambioEstado($datos_correo_estado);
    }

    header("Location: ver_solicitud.php?id=" . $solicitud_id . "&actualizado=1");
    exit;

} else {
    echo "Error al actualizar la solicitud: " . $stmt->error;
}

$stmt->close();
$conexion->close();
?>