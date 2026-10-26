<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/vendor/autoload.php";

function limpiarCorreoHtml($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function obtenerCorreoPersonalPorCodigo($codigo_estudiante) {
    global $conexion;
    if (empty($codigo_estudiante)) {
        return null;
    }
    
    if (!isset($conexion) || !$conexion) {
        include_once __DIR__ . "/conexion.php";
    }
    
    global $conexion;
    if (!$conexion) {
        return null;
    }

    $sql = "SELECT correo_personal FROM usuarios WHERE codigo_estudiante = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $codigo_estudiante);
        $stmt->execute();
        $res = $stmt->get_result();
        $correo_personal = null;
        if ($row = $res->fetch_assoc()) {
            $correo_personal = !empty($row["correo_personal"]) ? trim($row["correo_personal"]) : null;
        }
        $stmt->close();
        return $correo_personal;
    }
    return null;
}

function enviarCorreoNuevaSolicitud($datos, $solicitud_id, $archivos = []) {
    $config = require __DIR__ . "/correo_config.php";

    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $config["username"];
        $mail->Password = $config["password"];
        $mail->SMTPSecure = $config["secure"];
        $mail->Port = $config["port"];

        // Configuración general
        $mail->CharSet = "UTF-8";
        $mail->setFrom($config["username"], "Sistema PPP - FIEI UNFV");
        $mail->addAddress($config["correo_responsable"], $config["nombre_responsable"]);

        // Adjuntar documentos enviados por el estudiante
        foreach ($archivos as $archivo) {
            if (
                isset($archivo["ruta"]) &&
                isset($archivo["nombre"]) &&
                file_exists($archivo["ruta"])
            ) {
                $mail->addAttachment($archivo["ruta"], $archivo["nombre"]);
            }
        }

        $tipo_carta = limpiarCorreoHtml($datos["tipo_carta"] ?? "No especificado");
        $creditos = limpiarCorreoHtml($datos["total_creditos_aprobados"] ?? "0");
        $cumple_creditos = intval($datos["cumple_creditos"] ?? 0);

        $empresa_html = "";

        if ($cumple_creditos === 1) {
            $empresa_html = "
                <h3>Datos de empresa</h3>
                <p><b>RUC:</b> " . limpiarCorreoHtml($datos["ruc_empresa"] ?? "") . "</p>
                <p><b>Razón social:</b> " . limpiarCorreoHtml($datos["razon_social_empresa"] ?? "") . "</p>
                <p><b>Estado RUC:</b> " . limpiarCorreoHtml($datos["estado_ruc"] ?? "") . "</p>
                <p><b>Condición RUC:</b> " . limpiarCorreoHtml($datos["condicion_ruc"] ?? "") . "</p>
                <p><b>Responsable:</b> " . limpiarCorreoHtml($datos["responsable_empresa"] ?? "") . "</p>
                <p><b>Cargo:</b> " . limpiarCorreoHtml($datos["cargo_responsable"] ?? "") . "</p>
            ";
        }

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = "Nueva solicitud de Carta de Presentación PPP";

        $mail->Body = "
            <h2>Nueva solicitud registrada</h2>

            <p>Se ha registrado una nueva solicitud en el Sistema PPP - FIEI UNFV.</p>

            <h3>Datos del estudiante</h3>

            <p><b>Código de solicitud:</b> " . limpiarCorreoHtml($solicitud_id) . "</p>
            <p><b>Nombres:</b> " . limpiarCorreoHtml($datos["nombres"] ?? "") . "</p>
            <p><b>Apellidos:</b> " . limpiarCorreoHtml($datos["apellidos"] ?? "") . "</p>
            <p><b>DNI:</b> " . limpiarCorreoHtml($datos["dni"] ?? "") . "</p>
            <p><b>Código de estudiante:</b> " . limpiarCorreoHtml($datos["codigo_estudiante"] ?? "") . "</p>
            <p><b>Correo institucional:</b> " . limpiarCorreoHtml($datos["correo_institucional"] ?? "") . "</p>
            <p><b>Escuela profesional:</b> " . limpiarCorreoHtml($datos["escuela"] ?? "") . "</p>
            <p><b>Código de liquidación:</b> " . limpiarCorreoHtml($datos["codigo_liquidacion"] ?? "") . "</p>

            <h3>Evaluación académica</h3>
            <p><b>Tipo de carta:</b> {$tipo_carta}</p>
            <p><b>Créditos aprobados:</b> {$creditos}</p>

            {$empresa_html}

            <h3>Documentos adjuntos</h3>
            <ul>
                <li>FUT (Generado)</li>
                <li>Voucher de pago</li>
                <li>Récord académico</li>
                ' . (!empty($datos["archivo_matricula"]) ? '<li>Ficha de matrícula</li>' : '') . '
            </ul>

            <p>La solicitud se encuentra en estado <b>Pendiente</b> para su revisión.</p>
        ";

        $mail->AltBody = "Nueva solicitud registrada. Código de solicitud: {$solicitud_id}. Tipo de carta: {$tipo_carta}. Créditos aprobados: {$creditos}.";

        $mail->send();

        return true;

    } catch (Exception $e) {
        return "Error al enviar correo: " . $mail->ErrorInfo;
    }
}

function enviarCorreoCambioEstado($datos) {
    $config = require __DIR__ . "/correo_config.php";

    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $config["username"];
        $mail->Password = $config["password"];
        $mail->SMTPSecure = $config["secure"];
        $mail->Port = $config["port"];

        // Configuración general
        $mail->CharSet = "UTF-8";
        $mail->setFrom($config["username"], "Sistema PPP - FIEI UNFV");

        $correo_estudiante = $datos["correo_institucional"] ?? "";
        $nombre_estudiante = trim(($datos["nombres"] ?? "") . " " . ($datos["apellidos"] ?? ""));

        if (empty($correo_estudiante)) {
            return "Error: no se encontró el correo institucional del estudiante.";
        }

        $mail->addAddress($correo_estudiante, $nombre_estudiante);

        // Copia de respaldo al correo personal si existe
        $correo_personal = obtenerCorreoPersonalPorCodigo($datos["codigo_estudiante"] ?? "");
        if (!empty($correo_personal) && filter_var($correo_personal, FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($correo_personal, $nombre_estudiante);
        }

        $estado = $datos["estado"] ?? "";
        $estado_anterior = $datos["estado_anterior"] ?? "";
        $observacion = trim($datos["observacion"] ?? "");

        $mensaje_estado = "";

        if ($estado === "Aprobado") {
            $mensaje_estado = "
                <p>Su solicitud ha sido <b>aprobada</b>. Puede revisar el estado ingresando nuevamente al sistema.</p>
            ";
        } elseif ($estado === "Observado") {
            $mensaje_estado = "
                <p>Su solicitud ha sido <b>observada</b>. Revise la observación indicada para realizar las correcciones necesarias.</p>
            ";
        } elseif ($estado === "Rechazado") {
            $mensaje_estado = "
                <p>Su solicitud ha sido <b>rechazada</b>. Revise la observación registrada para conocer el motivo.</p>
            ";
        } else {
            $mensaje_estado = "
                <p>Su solicitud fue actualizada y actualmente se encuentra en estado <b>" . limpiarCorreoHtml($estado) . "</b>.</p>
            ";
        }

        $observacion_html = "";

        if (!empty($observacion)) {
            $observacion_html = "
                <h3>Observación registrada</h3>
                <p>" . nl2br(limpiarCorreoHtml($observacion)) . "</p>
            ";
        }

        $empresa_html = "";

        if (!empty($datos["razon_social_empresa"])) {
            $empresa_html = "
                <p><b>Empresa:</b> " . limpiarCorreoHtml($datos["razon_social_empresa"]) . "</p>
            ";
        }

        $mail->isHTML(true);
        $mail->Subject = "Actualización de solicitud PPP - Estado: " . $estado;

        $mail->Body = "
            <h2>Actualización de solicitud</h2>

            <p>Estimado(a) <b>" . limpiarCorreoHtml($nombre_estudiante) . "</b>,</p>

            {$mensaje_estado}

            <h3>Detalle de la solicitud</h3>

            <p><b>Código de solicitud:</b> " . limpiarCorreoHtml($datos["solicitud_id"] ?? "") . "</p>
            <p><b>Estado anterior:</b> " . limpiarCorreoHtml($estado_anterior) . "</p>
            <p><b>Estado actual:</b> " . limpiarCorreoHtml($estado) . "</p>
            <p><b>Tipo de carta:</b> " . limpiarCorreoHtml($datos["tipo_carta"] ?? "No especificado") . "</p>
            <p><b>Código de estudiante:</b> " . limpiarCorreoHtml($datos["codigo_estudiante"] ?? "") . "</p>
            <p><b>Escuela profesional:</b> " . limpiarCorreoHtml($datos["escuela"] ?? "") . "</p>
            <p><b>Créditos aprobados:</b> " . limpiarCorreoHtml($datos["total_creditos_aprobados"] ?? "0") . "</p>
            {$empresa_html}

            {$observacion_html}

            <p>Puede revisar el detalle ingresando nuevamente al Sistema PPP - FIEI UNFV.</p>
        ";

        $mail->AltBody = "Su solicitud PPP fue actualizada. Estado actual: {$estado}. Observación: {$observacion}";

        $mail->send();

        return true;

    } catch (Exception $e) {
        return "Error al enviar correo de actualización: " . $mail->ErrorInfo;
    }
}

function enviarCorreoNumeroTramite($datos) {
    $config = require __DIR__ . "/correo_config.php";

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $config["username"];
        $mail->Password = $config["password"];
        $mail->SMTPSecure = $config["secure"];
        $mail->Port = $config["port"];

        $mail->CharSet = "UTF-8";
        $mail->setFrom($config["username"], "Sistema PPP - FIEI UNFV");
        $mail->addAddress($config["correo_responsable"], $config["nombre_responsable"]);

        $mail->isHTML(true);
        $mail->Subject = "Número de Trámite UNFV registrado - Solicitud #" . ($datos["solicitud_id"] ?? "");

        $archivoConsolidado = trim($datos["archivo_pdf_consolidado"] ?? "");
        $rutaConsolidadoFisica = "";

        if (!empty($archivoConsolidado)) {
            $rutaConsolidadoFisica = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $archivoConsolidado);

            if (file_exists($rutaConsolidadoFisica)) {
                $mail->addAttachment(
                    $rutaConsolidadoFisica,
                    "CONSOLIDADO_solicitud_" . ($datos["solicitud_id"] ?? "") . ".pdf"
                );
            }
        }

        $mail->Body = "
            <h2>Número de Trámite UNFV registrado</h2>

            <p>El estudiante registró el Número de Trámite UNFV para su solicitud.</p>

            <h3>Datos de la solicitud</h3>

            <p><b>Código de solicitud:</b> " . limpiarCorreoHtml($datos["solicitud_id"] ?? "") . "</p>
            <p><b>Número de Trámite:</b> " . limpiarCorreoHtml($datos["numero_tramite"] ?? "") . "</p>
            <p><b>Estado actual de la solicitud:</b> " . limpiarCorreoHtml($datos["estado"] ?? "") . "</p>

            <h3>Datos del estudiante</h3>

            <p><b>Estudiante:</b> " . limpiarCorreoHtml(($datos["nombres"] ?? "") . " " . ($datos["apellidos"] ?? "")) . "</p>
            <p><b>DNI:</b> " . limpiarCorreoHtml($datos["dni"] ?? "") . "</p>
            <p><b>Código de estudiante:</b> " . limpiarCorreoHtml($datos["codigo_estudiante"] ?? "") . "</p>
            <p><b>Correo institucional:</b> " . limpiarCorreoHtml($datos["correo_institucional"] ?? "") . "</p>
            <p><b>Escuela:</b> " . limpiarCorreoHtml($datos["escuela"] ?? "") . "</p>

            <h3>Evaluación académica</h3>

            <p><b>Tipo de carta:</b> " . limpiarCorreoHtml($datos["tipo_carta"] ?? "") . "</p>
            <p><b>Créditos aprobados:</b> " . limpiarCorreoHtml($datos["total_creditos_aprobados"] ?? "") . "</p>

            <p>El PDF consolidado se adjunta a este correo si se encuentra disponible.</p>
        ";

        $mail->AltBody = "El estudiante registró el Número de Trámite UNFV: " . ($datos["numero_tramite"] ?? "");

        $mail->send();

        return true;

    } catch (Exception $e) {
        return "Error al enviar correo de Número de Trámite: " . $mail->ErrorInfo;
    }
}

function enviarCorreoCartaDecanato($datos, $rutaCartaFisica) {
    $config = require __DIR__ . "/correo_config.php";

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $config["username"];
        $mail->Password = $config["password"];
        $mail->SMTPSecure = $config["secure"];
        $mail->Port = $config["port"];

        $mail->CharSet = "UTF-8";
        $mail->setFrom($config["username"], "Sistema PPP - FIEI UNFV");

        $correo_decanato = trim($config["correo_decanato"] ?? "");
        $nombre_decanato = trim($config["nombre_decanato"] ?? "Decanato FIEI UNFV");

        if (empty($correo_decanato)) {
            return "Error: no se configuró el correo de Decanato.";
        }

        $mail->addAddress($correo_decanato, $nombre_decanato);

        if (!empty($config["correo_responsable"])) {
            $mail->addCC(
                $config["correo_responsable"],
                $config["nombre_responsable"] ?? "Responsable Sistema PPP"
            );
        }

        if (!file_exists($rutaCartaFisica)) {
            return "Error: no se encontró el archivo de carta generado.";
        }

        $numero_carta = limpiarCorreoHtml($datos["numero_carta"] ?? "");
        $solicitud_id = limpiarCorreoHtml($datos["solicitud_id"] ?? "");
        $nombre_estudiante = limpiarCorreoHtml(trim(($datos["apellidos"] ?? "") . " " . ($datos["nombres"] ?? "")));
        $codigo_estudiante = limpiarCorreoHtml($datos["codigo_estudiante"] ?? "");
        $correo_institucional = limpiarCorreoHtml($datos["correo_institucional"] ?? "");
        $escuela = limpiarCorreoHtml($datos["escuela"] ?? "");
        $tipo_carta = limpiarCorreoHtml($datos["tipo_carta"] ?? "");
        $numero_tramite = limpiarCorreoHtml($datos["numero_tramite"] ?? "");
        $ruc_empresa = limpiarCorreoHtml($datos["ruc_empresa"] ?? "");
        $razon_social_empresa = limpiarCorreoHtml($datos["razon_social_empresa"] ?? "");
        $responsable_empresa = limpiarCorreoHtml($datos["responsable_empresa"] ?? "");
        $ciclo_culminado = limpiarCorreoHtml($datos["ciclo_culminado"] ?? "");
        $ciclo_matricula = limpiarCorreoHtml($datos["ciclo_matricula"] ?? "");
        $carrera = limpiarCorreoHtml($datos["carrera"] ?? "");

        $mail->addAttachment(
            $rutaCartaFisica,
            "Carta_Presentacion_" . ($datos["numero_carta"] ?? "PPP") . ".pdf"
        );

        $mail->isHTML(true);
        $mail->Subject = "Carta de Presentación PPP para Decanato - " . ($datos["numero_carta"] ?? "");

        $mail->Body = "
            <h2>Carta de Presentación PPP generada</h2>

            <p>Se remite la carta de presentación generada desde el Sistema PPP - FIEI UNFV para su revisión y trámite correspondiente.</p>

            <h3>Datos de la carta</h3>
            <p><b>Número de carta:</b> {$numero_carta}</p>
            <p><b>Código de solicitud:</b> {$solicitud_id}</p>
            <p><b>Número de Trámite UNFV:</b> {$numero_tramite}</p>

            <h3>Datos del estudiante</h3>
            <p><b>Estudiante:</b> {$nombre_estudiante}</p>
            <p><b>Código de estudiante:</b> {$codigo_estudiante}</p>
            <p><b>Correo institucional:</b> {$correo_institucional}</p>
            <p><b>Escuela profesional:</b> {$escuela}</p>
            <p><b>Carrera:</b> {$carrera}</p>
            <p><b>Ciclo culminado:</b> {$ciclo_culminado}</p>
            <p><b>Ciclo a matricular:</b> {$ciclo_matricula}</p>

            <h3>Datos de la empresa</h3>
            <p><b>RUC:</b> {$ruc_empresa}</p>
            <p><b>Razón social:</b> {$razon_social_empresa}</p>
            <p><b>Representante:</b> {$responsable_empresa}</p>

            <h3>Tipo de solicitud</h3>
            <p><b>Tipo de carta:</b> {$tipo_carta}</p>

            <p>Se adjunta el PDF de la carta de presentación generado por el sistema.</p>
        ";

        $mail->AltBody = "Carta de Presentación PPP generada. Número de carta: "
            . ($datos["numero_carta"] ?? "")
            . ". Estudiante: "
            . trim(($datos["apellidos"] ?? "") . " " . ($datos["nombres"] ?? ""))
            . ". Número de Trámite UNFV: "
            . ($datos["numero_tramite"] ?? "");

        $mail->send();

        return true;

    } catch (Exception $e) {
        return "Error al enviar correo de carta a Decanato: " . $mail->ErrorInfo;
    }
}

function enviarCorreoCartaSimpleEstudiante($datos, $rutaCartaSimpleFisica) {
    $config = require __DIR__ . "/correo_config.php";

    $mail = new PHPMailer(true);

    try {
        /* ============================================================
           CONFIGURACIÓN SMTP
        ============================================================ */

        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $config["username"];
        $mail->Password = $config["password"];
        $mail->SMTPSecure = $config["secure"];
        $mail->Port = $config["port"];

        $mail->CharSet = "UTF-8";
        $mail->setFrom($config["username"], "Sistema PPP - FIEI UNFV");

        /* ============================================================
           DESTINATARIO: ESTUDIANTE
        ============================================================ */

        $correo_estudiante = trim($datos["correo_institucional"] ?? "");
        $nombre_estudiante = trim(($datos["nombres"] ?? "") . " " . ($datos["apellidos"] ?? ""));

        if (empty($correo_estudiante)) {
            return "Error: no se encontró el correo institucional del estudiante.";
        }

        if (!filter_var($correo_estudiante, FILTER_VALIDATE_EMAIL)) {
            return "Error: el correo institucional del estudiante no tiene un formato válido.";
        }

        $mail->addAddress($correo_estudiante, $nombre_estudiante);

        // Copia de respaldo al correo personal si existe
        $correo_personal = obtenerCorreoPersonalPorCodigo($datos["codigo_estudiante"] ?? "");
        if (!empty($correo_personal) && filter_var($correo_personal, FILTER_VALIDATE_EMAIL)) {
            $mail->addCC($correo_personal, $nombre_estudiante);
        }

        /*
           Copia al responsable/ingeniero para evidencia.
        */
        if (!empty($config["correo_responsable"]) && filter_var($config["correo_responsable"], FILTER_VALIDATE_EMAIL)) {
            $mail->addCC(
                $config["correo_responsable"],
                $config["nombre_responsable"] ?? "Responsable Sistema PPP"
            );
        }

        /* ============================================================
           VALIDAR PDF
        ============================================================ */

        if (empty($rutaCartaSimpleFisica) || !file_exists($rutaCartaSimpleFisica)) {
            return "Error: no se encontró el PDF de Carta Simple generado.";
        }

        /* ============================================================
           DATOS LIMPIOS
        ============================================================ */

        $numero_carta_simple = limpiarCorreoHtml($datos["numero_carta_simple"] ?? "");
        $solicitud_id = limpiarCorreoHtml($datos["solicitud_id"] ?? "");
        $codigo_estudiante = limpiarCorreoHtml($datos["codigo_estudiante"] ?? "");
        $escuela = limpiarCorreoHtml($datos["escuela"] ?? "");
        $carrera = limpiarCorreoHtml($datos["carrera"] ?? "");
        $total_creditos_aprobados = limpiarCorreoHtml($datos["total_creditos_aprobados"] ?? "0");
        $creditos_minimos = limpiarCorreoHtml($datos["creditos_minimos"] ?? "0");
        $total_creditos_malla = limpiarCorreoHtml($datos["total_creditos_malla"] ?? "0");

        $nombre_estudiante_html = limpiarCorreoHtml($nombre_estudiante);

        /* ============================================================
           ADJUNTAR CARTA SIMPLE
        ============================================================ */

        $nombreAdjunto = "Carta_Simple";

        if (!empty($datos["numero_carta_simple"])) {
            $nombreAdjunto .= "_" . preg_replace("/[^a-zA-Z0-9_\-]/", "_", $datos["numero_carta_simple"]);
        }

        $nombreAdjunto .= ".pdf";

        $mail->addAttachment($rutaCartaSimpleFisica, $nombreAdjunto);

        /* ============================================================
           CONTENIDO DEL CORREO
        ============================================================ */

        $mail->isHTML(true);
        $mail->Subject = "Carta Simple generada - Sistema PPP FIEI UNFV";

        $mail->Body = "
            <h2>Carta Simple generada</h2>

            <p>Estimado(a) estudiante <b>{$nombre_estudiante_html}</b>,</p>

            <p>
                Se le remite adjunta su <b>Carta Simple</b>, generada desde el 
                <b>Sistema PPP - FIEI UNFV</b>.
            </p>

            <p>
                Esta carta se emite debido a que, según la evaluación académica registrada en el sistema,
                usted aún no cumple con el requisito mínimo del 80% de créditos aprobados requerido para la
                emisión de la Carta de Presentación de Prácticas Preprofesionales.
            </p>

            <h3>Datos de la solicitud</h3>
            <p><b>Número de carta:</b> {$numero_carta_simple}</p>
            <p><b>Código de solicitud:</b> {$solicitud_id}</p>
            <p><b>Código de estudiante:</b> {$codigo_estudiante}</p>
            <p><b>Escuela profesional:</b> {$escuela}</p>
            <p><b>Carrera:</b> {$carrera}</p>

            <h3>Evaluación de créditos</h3>
            <p><b>Créditos aprobados:</b> {$total_creditos_aprobados}</p>
            <p><b>Créditos mínimos requeridos para PPP:</b> {$creditos_minimos} de {$total_creditos_malla}</p>

            <p>
                La Carta Simple se adjunta en formato PDF para que pueda utilizarla como constancia de acercamiento,
                apoyo o experiencia formativa inicial.
            </p>

            <p>Atentamente,<br>
            Sistema PPP - FIEI UNFV</p>
        ";

        $mail->AltBody = "Carta Simple generada. "
            . "Número de carta: " . ($datos["numero_carta_simple"] ?? "")
            . ". Solicitud: " . ($datos["solicitud_id"] ?? "")
            . ". Estudiante: " . $nombre_estudiante
            . ". Código: " . ($datos["codigo_estudiante"] ?? "")
            . ". Créditos aprobados: " . ($datos["total_creditos_aprobados"] ?? "0")
            . ". Créditos mínimos requeridos: " . ($datos["creditos_minimos"] ?? "0")
            . " de " . ($datos["total_creditos_malla"] ?? "0");

        $mail->send();

        return true;

    } catch (Exception $e) {
        return "Error al enviar Carta Simple al estudiante: " . $mail->ErrorInfo;
    }
}
?>