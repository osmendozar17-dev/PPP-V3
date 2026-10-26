<?php
session_start();

include "conexion.php";
include "enviar_correo.php";
include "generar_pdfs.php";
require_once "creditos_config.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php?sesion=expirada");
    exit;
}

if ($_SESSION["rol"] !== "Estudiante") {
    header("Location: admin/panel.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Acceso no permitido.");
}

$usuario_id = $_SESSION["usuario_id"];

/* ============================================================
   DATOS PRINCIPALES DEL ESTUDIANTE
============================================================ */

$nombres = trim($_POST["nombres"] ?? "");
$apellidos = trim($_POST["apellidos"] ?? "");
$dni = trim($_POST["dni"] ?? "");
$codigo_estudiante = trim($_POST["codigo_estudiante"] ?? "");
$correo_institucional = trim($_POST["correo_institucional"] ?? "");
$correo_personal = trim($_POST["correo_personal"] ?? "");
$escuela = trim($_POST["escuela"] ?? "");
$codigo_liquidacion = trim($_POST["codigo_liquidacion"] ?? "");
$duracion_meses = intval($_POST["duracion_meses"] ?? 6);
if ($duracion_meses < 1 || $duracion_meses > 6) {
    $duracion_meses = 6;
}
$fecha_inicio_practicas = !empty($_POST["fecha_inicio_practicas"]) ? trim($_POST["fecha_inicio_practicas"]) : null;
$fecha_fin_practicas = !empty($_POST["fecha_fin_practicas"]) ? trim($_POST["fecha_fin_practicas"]) : null;

// Actualizar el correo personal en la base de datos si el estudiante lo ingresa/modifica
if (!empty($correo_personal)) {
    if (filter_var($correo_personal, FILTER_VALIDATE_EMAIL)) {
        $sql_upd = "UPDATE usuarios SET correo_personal = ? WHERE id = ?";
        $stmt_upd = $conexion->prepare($sql_upd);
        if ($stmt_upd) {
            $stmt_upd->bind_param("si", $correo_personal, $usuario_id);
            $stmt_upd->execute();
            $stmt_upd->close();
            $_SESSION["correo_personal"] = $correo_personal;
        }
    }
} else {
    $sql_upd = "UPDATE usuarios SET correo_personal = NULL WHERE id = ?";
    $stmt_upd = $conexion->prepare($sql_upd);
    if ($stmt_upd) {
        $stmt_upd->bind_param("i", $usuario_id);
        $stmt_upd->execute();
        $stmt_upd->close();
        $_SESSION["correo_personal"] = "";
    }
}

$cursos_aprobados = $_POST["cursos_aprobados"] ?? [];

$total_creditos_aprobados = 0;
$cumple_creditos = 0;
$tipo_carta = "Simple";

/* ============================================================
   DATOS FUT
============================================================ */

$fut_facultad = trim($_POST["fut_facultad"] ?? "FACULTAD DE INGENIERÍA ELECTRÓNICA E INFORMÁTICA");
$fut_tipo_programa = trim($_POST["fut_tipo_programa"] ?? "");
$fut_tramite = trim($_POST["fut_tramite"] ?? "");
$fut_dependencia = trim($_POST["fut_dependencia"] ?? "");
$fut_tipo_solicitante = trim($_POST["fut_tipo_solicitante"] ?? "");
$fut_direccion = trim($_POST["fut_direccion"] ?? "");
$fut_distrito = trim($_POST["fut_distrito"] ?? "");
$fut_telefono_fijo = trim($_POST["fut_telefono_fijo"] ?? "");
$fut_celular = trim($_POST["fut_celular"] ?? "");
$fut_correo = trim($_POST["fut_correo"] ?? "");
$fut_fundamentacion = trim($_POST["fut_fundamentacion"] ?? "");
$fut_documentos_adjuntos = trim($_POST["fut_documentos_adjuntos"] ?? "");
$fut_total_folios = intval($_POST["fut_total_folios"] ?? 0);
$fut_lugar_fecha = trim($_POST["fut_lugar_fecha"] ?? "");

/* ============================================================
   DATOS EMPRESA
============================================================ */

$ruc_empresa = trim($_POST["ruc_empresa"] ?? "");
$razon_social_empresa = trim($_POST["razon_social_empresa"] ?? "");
$estado_ruc = trim($_POST["estado_ruc"] ?? "");
$condicion_ruc = trim($_POST["condicion_ruc"] ?? "");
$responsable_empresa = trim($_POST["responsable_empresa"] ?? "");
$cargo_responsable = trim($_POST["cargo_responsable"] ?? "");

$representante_dni = trim($_POST["representante_dni"] ?? "");
$representante_telefono = trim($_POST["representante_telefono"] ?? "");
$representante_email = trim($_POST["representante_email"] ?? "");
$direccion_empresa = trim($_POST["direccion_empresa"] ?? "");
$ubicacion_empresa = trim($_POST["ubicacion_empresa"] ?? "");

$datos_creditos_malla = obtenerDatosCreditosMalla($escuela);

$TOTAL_CREDITOS_MALLA = $datos_creditos_malla["total"];
$CREDITOS_MINIMOS = $datos_creditos_malla["minimo"];

/* ============================================================
   VALIDACIONES GENERALES
============================================================ */

if (
    empty($nombres) ||
    empty($apellidos) ||
    empty($dni) ||
    empty($codigo_estudiante) ||
    empty($correo_institucional) ||
    empty($escuela) ||
    empty($codigo_liquidacion)
) {
    die("Error: todos los campos principales son obligatorios.");
}

if (!preg_match("/^[0-9]{8}$/", $dni)) {
    die("Error: el DNI debe tener exactamente 8 dígitos.");
}

if (!str_ends_with($correo_institucional, "@unfv.edu.pe")) {
    die("Error: el correo institucional debe terminar en @unfv.edu.pe.");
}

if (
    empty($fut_facultad) ||
    empty($fut_tipo_programa) ||
    empty($fut_tramite) ||
    empty($fut_dependencia) ||
    empty($fut_tipo_solicitante) ||
    empty($fut_direccion) ||
    empty($fut_distrito) ||
    empty($fut_celular) ||
    empty($fut_correo) ||
    empty($fut_fundamentacion) ||
    empty($fut_documentos_adjuntos) ||
    empty($fut_lugar_fecha)
) {
    die("Error: todos los campos obligatorios del FUT deben completarse.");
}

if ($fut_total_folios <= 0) {
    die("Error: el total de folios debe ser mayor a cero.");
}

/* ============================================================
   FUNCIONES AUXILIARES
============================================================ */

function validarRucEmpresaBackend($ruc) {
    $config_path = __DIR__ . "/ruc_config.php";

    if (!file_exists($config_path)) {
        return [
            "ok" => false,
            "mensaje" => "No se encontró el archivo ruc_config.php."
        ];
    }

    $config = require $config_path;
    $token = trim($config["token"] ?? "");

    if ($token === "" || $token === "AQUI_COLOCA_TU_TOKEN" || $token === "AQUI_VA_TU_TOKEN_REAL") {
        return [
            "ok" => false,
            "mensaje" => "No se configuró el token de consulta RUC."
        ];
    }

    $url = "https://api.decolecta.com/v1/sunat/ruc?numero=" . urlencode($ruc);

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $token,
            "Accept: application/json"
        ]
    ]);

    $respuesta = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($respuesta === false || !empty($error)) {
        return [
            "ok" => false,
            "mensaje" => "No se pudo conectar con el servicio de consulta RUC."
        ];
    }

    $datos = json_decode($respuesta, true);

    if ($httpCode !== 200 || !is_array($datos)) {
        return [
            "ok" => false,
            "mensaje" => "No se encontró información válida para el RUC ingresado."
        ];
    }

    $razon_social = $datos["razon_social"] ?? $datos["razonSocial"] ?? "";
    $estado = $datos["estado"] ?? "";
    $condicion = $datos["condicion"] ?? "";

    if (empty($razon_social)) {
        return [
            "ok" => false,
            "mensaje" => "El RUC existe, pero no se obtuvo la razón social."
        ];
    }

    return [
        "ok" => true,
        "razon_social" => $razon_social,
        "estado" => $estado,
        "condicion" => $condicion
    ];
}

function obtenerCarpetaEscuela($escuela) {
    $escuela_normalizada = mb_strtoupper(trim($escuela), "UTF-8");

    if (str_contains($escuela_normalizada, "INFORM")) {
        return "INFORMATICA";
    }

    if (str_contains($escuela_normalizada, "TELE")) {
        return "TELECOMUNICACIONES";
    }

    if (str_contains($escuela_normalizada, "ELECT")) {
        return "ELECTRONICA";
    }

    if (str_contains($escuela_normalizada, "MECA")) {
        return "MECATRONICA";
    }

    return "OTRAS";
}

function crearCarpetaSiNoExisteLocal($ruta) {
    if (!is_dir($ruta)) {
        if (!mkdir($ruta, 0775, true)) {
            die("Error: no se pudo crear la carpeta " . $ruta);
        }
    }
}

function subirArchivoSolicitud($archivo, $carpetaDestinoFisica, $carpetaDestinoBD, $extensiones_permitidas) {
    if (!isset($_FILES[$archivo])) {
        die("Error: no se recibió el archivo " . $archivo);
    }

    if ($_FILES[$archivo]["error"] !== UPLOAD_ERR_OK) {
        die("Error: no se pudo subir el archivo " . $archivo . ". Código de error: " . $_FILES[$archivo]["error"]);
    }

    $nombreOriginal = $_FILES[$archivo]["name"];
    $archivoTemporal = $_FILES[$archivo]["tmp_name"];
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if (!in_array($extension, $extensiones_permitidas)) {
        die("Error: el archivo " . $nombreOriginal . " tiene un formato no permitido.");
    }

    $nombreSeguro = uniqid() . "_" . preg_replace("/[^a-zA-Z0-9._-]/", "_", $nombreOriginal);

    $rutaFisicaDestino = $carpetaDestinoFisica . $nombreSeguro;
    $rutaBD = $carpetaDestinoBD . $nombreSeguro;

    if (!move_uploaded_file($archivoTemporal, $rutaFisicaDestino)) {
        die("Error: no se pudo guardar el archivo " . $nombreOriginal . " en " . $rutaFisicaDestino);
    }

    return $rutaBD;
}

function rutaFisicaDesdeBD($rutaBD) {
    return __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rutaBD);
}

function rutaBDDesdeFisica($rutaFisica) {
    $base = __DIR__ . DIRECTORY_SEPARATOR;
    $relativa = str_replace($base, "", $rutaFisica);
    return str_replace(DIRECTORY_SEPARATOR, "/", $relativa);
}

/* ============================================================
   CALCULAR CRÉDITOS APROBADOS
============================================================ */

if (!empty($cursos_aprobados)) {
    $ids_limpios = [];

    foreach ($cursos_aprobados as $curso_id) {
        $curso_id = intval($curso_id);

        if ($curso_id > 0) {
            $ids_limpios[] = $curso_id;
        }
    }

    $ids_limpios = array_unique($ids_limpios);

    if (!empty($ids_limpios)) {
        $placeholders = implode(",", array_fill(0, count($ids_limpios), "?"));
        $tipos = str_repeat("i", count($ids_limpios));

        $sql_total_creditos = "SELECT COALESCE(SUM(creditos), 0) AS total
                               FROM cursos_malla
                               WHERE id IN ($placeholders)
                               AND escuela = ?";

        $stmt_total = $conexion->prepare($sql_total_creditos);

        if (!$stmt_total) {
            die("Error al preparar cálculo de créditos: " . $conexion->error);
        }

        $tipos .= "s";
        $params = array_merge($ids_limpios, [$escuela]);

        $stmt_total->bind_param($tipos, ...$params);
        $stmt_total->execute();
        $resultado_total = $stmt_total->get_result();

        if ($fila_total = $resultado_total->fetch_assoc()) {
            $total_creditos_aprobados = intval($fila_total["total"]);
        }

        $stmt_total->close();
    }
}

/* ============================================================
   VALIDAR TIPO DE CARTA
============================================================ */

if ($total_creditos_aprobados >= $CREDITOS_MINIMOS) {
    $cumple_creditos = 1;
    $tipo_carta = "Presentacion PPP";

    if (empty($ruc_empresa)) {
        die("Error: debe ingresar el RUC de la empresa.");
    }

    if (!preg_match("/^[0-9]{11}$/", $ruc_empresa)) {
        die("Error: el RUC debe tener exactamente 11 dígitos.");
    }

    if (!str_starts_with($ruc_empresa, "20")) {
        die("Error: solo se permite RUC de empresa que empiece con 20. No se admite RUC 10 de persona natural.");
    }

    $resultado_ruc = validarRucEmpresaBackend($ruc_empresa);

    if ($resultado_ruc["ok"] !== true) {
        die("Error: no se pudo validar el RUC de la empresa. " . $resultado_ruc["mensaje"]);
    }

    $razon_social_empresa = $resultado_ruc["razon_social"];
    $estado_ruc = $resultado_ruc["estado"];
    $condicion_ruc = $resultado_ruc["condicion"];

    if (strtoupper(trim($estado_ruc)) !== "ACTIVO") {
        die("Error: el RUC debe encontrarse en estado ACTIVO.");
    }

    if (strtoupper(trim($condicion_ruc)) !== "HABIDO") {
        die("Error: el RUC debe encontrarse en condición HABIDO.");
    }

    if (empty($responsable_empresa)) {
        die("Error: debe ingresar el nombre del representante de la empresa.");
    }

    if (empty($cargo_responsable)) {
        die("Error: debe ingresar el cargo del representante de la empresa.");
    }

    if (!preg_match("/^[0-9]{8}$/", $representante_dni)) {
        die("Error: debe ingresar un DNI válido del representante de la empresa.");
    }

    if (empty($representante_telefono)) {
        die("Error: debe ingresar el teléfono del representante de la empresa.");
    }

    if (empty($representante_email)) {
        die("Error: debe ingresar el email del representante de la empresa.");
    }

    if (empty($direccion_empresa)) {
        die("Error: debe ingresar la dirección de la empresa.");
    }

    if (empty($ubicacion_empresa)) {
        die("Error: debe ingresar DPTO. / PROV. / DISTRITO de la empresa.");
    }
} else {
    $cumple_creditos = 0;
    $tipo_carta = "Simple";

    $ruc_empresa = null;
    $razon_social_empresa = null;
    $estado_ruc = null;
    $condicion_ruc = null;
    $responsable_empresa = null;
    $cargo_responsable = null;
    $representante_dni = null;
    $representante_telefono = null;
    $representante_email = null;
    $direccion_empresa = null;
    $ubicacion_empresa = null;
}

/* ============================================================
   INSERTAR SOLICITUD PRIMERO, SIN RUTAS DE ARCHIVOS
============================================================ */

$ruta_archivo_fut = "";
$ruta_voucher = "";
$ruta_record = "";
$ruta_foto_carnet = "";
$ruta_fut_generado = "";
$ruta_datos_empresa_generado = "";
$ruta_pdf_consolidado = "";

$sql = "INSERT INTO solicitudes_carta (
            usuario_id,
            nombres,
            apellidos,
            dni,
            codigo_estudiante,
            correo_institucional,
            escuela,
            codigo_liquidacion,

            fut_facultad,
            fut_tipo_programa,
            fut_tramite,
            fut_dependencia,
            fut_tipo_solicitante,
            fut_direccion,
            fut_distrito,
            fut_telefono_fijo,
            fut_celular,
            fut_correo,
            fut_fundamentacion,
            fut_documentos_adjuntos,
            fut_total_folios,
            fut_lugar_fecha,

            total_creditos_aprobados,
            cumple_creditos,
            tipo_carta,
            duracion_meses,
            fecha_inicio_practicas,
            fecha_fin_practicas,

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

            archivo_fut,
            archivo_voucher,
            archivo_record,
            archivo_foto_carnet,
            archivo_fut_generado,
            archivo_datos_empresa_generado,
            archivo_pdf_consolidado,
            estado
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            'Pendiente'
        )";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . $conexion->error);
}

$tipos_insert = "i" . str_repeat("s", 19) . "isiisss" . str_repeat("s", 18);

$stmt->bind_param(
    $tipos_insert,
    $usuario_id,
    $nombres,
    $apellidos,
    $dni,
    $codigo_estudiante,
    $correo_institucional,
    $escuela,
    $codigo_liquidacion,

    $fut_facultad,
    $fut_tipo_programa,
    $fut_tramite,
    $fut_dependencia,
    $fut_tipo_solicitante,
    $fut_direccion,
    $fut_distrito,
    $fut_telefono_fijo,
    $fut_celular,
    $fut_correo,
    $fut_fundamentacion,
    $fut_documentos_adjuntos,
    $fut_total_folios,
    $fut_lugar_fecha,

    $total_creditos_aprobados,
    $cumple_creditos,
    $tipo_carta,
    $duracion_meses,
    $fecha_inicio_practicas,
    $fecha_fin_practicas,

    $ruc_empresa,
    $razon_social_empresa,
    $estado_ruc,
    $condicion_ruc,
    $responsable_empresa,
    $cargo_responsable,
    $representante_dni,
    $representante_telefono,
    $representante_email,
    $direccion_empresa,
    $ubicacion_empresa,

    $ruta_archivo_fut,
    $ruta_voucher,
    $ruta_record,
    $ruta_foto_carnet,
    $ruta_fut_generado,
    $ruta_datos_empresa_generado,
    $ruta_pdf_consolidado
);

if ($stmt->execute()) {
    $solicitud_id = $stmt->insert_id;

    /* ============================================================
       CREAR CARPETAS
    ============================================================ */

    $carpeta_escuela = obtenerCarpetaEscuela($escuela);
    $codigo_seguro = preg_replace("/[^a-zA-Z0-9_-]/", "_", $codigo_estudiante);

    $base_solicitud_bd = "uploads/" . $carpeta_escuela . "/" . $codigo_seguro . "/solicitud_" . $solicitud_id . "/";

    $base_solicitud_fisica = __DIR__ . DIRECTORY_SEPARATOR .
        "uploads" . DIRECTORY_SEPARATOR .
        $carpeta_escuela . DIRECTORY_SEPARATOR .
        $codigo_seguro . DIRECTORY_SEPARATOR .
        "solicitud_" . $solicitud_id . DIRECTORY_SEPARATOR;

    $carpeta_foto_bd = $base_solicitud_bd . "foto_carnet/";
    $carpeta_voucher_bd = $base_solicitud_bd . "voucher/";
    $carpeta_record_bd = $base_solicitud_bd . "record/";
    $carpeta_matricula_bd = $base_solicitud_bd . "matricula/";
    $carpeta_fut_generado_bd = $base_solicitud_bd . "fut_generado/";
    $carpeta_datos_empresa_bd = $base_solicitud_bd . "datos_empresa_generado/";
    $carpeta_consolidado_bd = $base_solicitud_bd . "consolidado/";

    $carpeta_foto_fisica = $base_solicitud_fisica . "foto_carnet" . DIRECTORY_SEPARATOR;
    $carpeta_voucher_fisica = $base_solicitud_fisica . "voucher" . DIRECTORY_SEPARATOR;
    $carpeta_record_fisica = $base_solicitud_fisica . "record" . DIRECTORY_SEPARATOR;
    $carpeta_matricula_fisica = $base_solicitud_fisica . "matricula" . DIRECTORY_SEPARATOR;
    $carpeta_fut_generado_fisica = $base_solicitud_fisica . "fut_generado" . DIRECTORY_SEPARATOR;
    $carpeta_datos_empresa_fisica = $base_solicitud_fisica . "datos_empresa_generado" . DIRECTORY_SEPARATOR;
    $carpeta_consolidado_fisica = $base_solicitud_fisica . "consolidado" . DIRECTORY_SEPARATOR;

    crearCarpetaSiNoExisteLocal($carpeta_foto_fisica);
    crearCarpetaSiNoExisteLocal($carpeta_voucher_fisica);
    crearCarpetaSiNoExisteLocal($carpeta_record_fisica);
    crearCarpetaSiNoExisteLocal($carpeta_matricula_fisica);
    crearCarpetaSiNoExisteLocal($carpeta_fut_generado_fisica);
    crearCarpetaSiNoExisteLocal($carpeta_datos_empresa_fisica);
    crearCarpetaSiNoExisteLocal($carpeta_consolidado_fisica);

    /* ============================================================
       SUBIR ARCHIVOS ADJUNTOS
    ============================================================ */

    $ruta_foto_carnet = subirArchivoSolicitud(
        "archivo_foto_carnet",
        $carpeta_foto_fisica,
        $carpeta_foto_bd,
        ["jpg", "jpeg", "png"]
    );

    $ruta_voucher = subirArchivoSolicitud(
        "archivo_voucher",
        $carpeta_voucher_fisica,
        $carpeta_voucher_bd,
        ["pdf", "jpg", "jpeg", "png"]
    );

    $ruta_record = subirArchivoSolicitud(
        "archivo_record",
        $carpeta_record_fisica,
        $carpeta_record_bd,
        ["pdf", "jpg", "jpeg", "png"]
    );

    $ruta_matricula = subirArchivoSolicitud(
        "archivo_matricula",
        $carpeta_matricula_fisica,
        $carpeta_matricula_bd,
        ["pdf", "jpg", "jpeg", "png"]
    );

    /* ============================================================
       GENERAR PDFs
    ============================================================ */

    $datos_pdf = [
        "nombres" => $nombres,
        "apellidos" => $apellidos,
        "dni" => $dni,
        "codigo_estudiante" => $codigo_estudiante,
        "correo_institucional" => $correo_institucional,
        "escuela" => $escuela,
        "codigo_liquidacion" => $codigo_liquidacion,

        "fut_facultad" => $fut_facultad,
        "fut_tipo_programa" => $fut_tipo_programa,
        "fut_tramite" => $fut_tramite,
        "fut_dependencia" => $fut_dependencia,
        "fut_tipo_solicitante" => $fut_tipo_solicitante,
        "fut_direccion" => $fut_direccion,
        "fut_distrito" => $fut_distrito,
        "fut_telefono_fijo" => $fut_telefono_fijo,
        "fut_celular" => $fut_celular,
        "fut_correo" => $fut_correo,
        "fut_fundamentacion" => $fut_fundamentacion,
        "fut_documentos_adjuntos" => $fut_documentos_adjuntos,
        "fut_total_folios" => $fut_total_folios,
        "fut_lugar_fecha" => $fut_lugar_fecha,

        "total_creditos_aprobados" => $total_creditos_aprobados,
        "cumple_creditos" => $cumple_creditos,
        "tipo_carta" => $tipo_carta,

        "ruc_empresa" => $ruc_empresa,
        "razon_social_empresa" => $razon_social_empresa,
        "estado_ruc" => $estado_ruc,
        "condicion_ruc" => $condicion_ruc,
        "responsable_empresa" => $responsable_empresa,
        "cargo_responsable" => $cargo_responsable,
        "representante_dni" => $representante_dni,
        "representante_telefono" => $representante_telefono,
        "representante_email" => $representante_email,
        "direccion_empresa" => $direccion_empresa,
        "ubicacion_empresa" => $ubicacion_empresa
    ];

    $ruta_fut_generado_fisica = $carpeta_fut_generado_fisica . "FUT_solicitud_" . $solicitud_id . ".pdf";
    generarFutPdf($datos_pdf, $ruta_fut_generado_fisica);
    $ruta_fut_generado = rutaBDDesdeFisica($ruta_fut_generado_fisica);

    if ($cumple_creditos == 1) {
        $ruta_datos_empresa_generado_fisica = $carpeta_datos_empresa_fisica . "DATOS_EMPRESA_solicitud_" . $solicitud_id . ".pdf";
        generarDatosEmpresaPdf($datos_pdf, $ruta_datos_empresa_generado_fisica);
        $ruta_datos_empresa_generado = rutaBDDesdeFisica($ruta_datos_empresa_generado_fisica);
    } else {
        $ruta_datos_empresa_generado = "";
        $ruta_datos_empresa_generado_fisica = "";
    }

    $ruta_pdf_consolidado_fisica = $carpeta_consolidado_fisica . "CONSOLIDADO_solicitud_" . $solicitud_id . ".pdf";

    generarPdfConsolidado(
        [
            "fut_generado" => $ruta_fut_generado_fisica,
            "datos_empresa_generado" => $ruta_datos_empresa_generado_fisica,
            "foto_carnet" => rutaFisicaDesdeBD($ruta_foto_carnet),
            "voucher" => rutaFisicaDesdeBD($ruta_voucher),
            "record" => rutaFisicaDesdeBD($ruta_record),
            "matricula" => !empty($ruta_matricula) ? rutaFisicaDesdeBD($ruta_matricula) : ""
        ],
        $ruta_pdf_consolidado_fisica
    );

    $ruta_pdf_consolidado = rutaBDDesdeFisica($ruta_pdf_consolidado_fisica);

    /*
       Para mantener compatibilidad con las vistas anteriores,
       archivo_fut apuntará al FUT generado automáticamente.
     */
    $ruta_archivo_fut = $ruta_fut_generado;

    /* ============================================================
       ACTUALIZAR RUTAS DE ARCHIVOS EN LA SOLICITUD
    ============================================================ */

    $sql_actualizar_archivos = "UPDATE solicitudes_carta
                                SET archivo_fut = ?,
                                    archivo_voucher = ?,
                                    archivo_record = ?,
                                    archivo_matricula = ?,
                                    archivo_foto_carnet = ?,
                                    archivo_fut_generado = ?,
                                    archivo_datos_empresa_generado = ?,
                                    archivo_pdf_consolidado = ?
                                WHERE id = ?";

    $stmt_actualizar_archivos = $conexion->prepare($sql_actualizar_archivos);

    if (!$stmt_actualizar_archivos) {
        die("Error al preparar actualización de archivos: " . $conexion->error);
    }

    $stmt_actualizar_archivos->bind_param(
        "ssssssssi",
        $ruta_archivo_fut,
        $ruta_voucher,
        $ruta_record,
        $ruta_matricula,
        $ruta_foto_carnet,
        $ruta_fut_generado,
        $ruta_datos_empresa_generado,
        $ruta_pdf_consolidado,
        $solicitud_id
    );

    if (!$stmt_actualizar_archivos->execute()) {
        die("Error al actualizar rutas de archivos: " . $stmt_actualizar_archivos->error);
    }

    $stmt_actualizar_archivos->close();

    /* ============================================================
       REGISTRAR HISTORIAL DE SOLICITUD
    ============================================================ */

    $accion = "Solicitud registrada";
    $descripcion = "El estudiante registró una nueva solicitud de carta de presentación. ";
    $descripcion .= "Tipo de carta: " . $tipo_carta . ". ";
    $descripcion .= "Créditos aprobados: " . $total_creditos_aprobados . ". ";
    $descripcion .= "Se generó el FUT y el PDF consolidado.";

    if ($cumple_creditos == 1) {
        $descripcion .= " Cumple con el requisito mínimo del 80%.";
        $descripcion .= " Empresa: " . $razon_social_empresa . ".";
    } else {
        $descripcion .= " No cumple con el requisito mínimo del 80%; se registra como Carta Simple.";
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

    /* ============================================================
       GUARDAR CURSOS APROBADOS
    ============================================================ */

    if (!empty($cursos_aprobados)) {
        $sql_curso_aprobado = "INSERT IGNORE INTO usuario_cursos_aprobados (
                                    usuario_id,
                                    curso_id
                                ) VALUES (?, ?)";

        $stmt_curso_aprobado = $conexion->prepare($sql_curso_aprobado);

        $sql_solicitud_curso = "INSERT INTO solicitud_cursos (
                                    solicitud_id,
                                    curso_id,
                                    creditos
                                ) VALUES (?, ?, ?)";

        $stmt_solicitud_curso = $conexion->prepare($sql_solicitud_curso);

        $sql_creditos = "SELECT creditos 
                         FROM cursos_malla 
                         WHERE id = ? 
                         LIMIT 1";

        $stmt_creditos = $conexion->prepare($sql_creditos);

        foreach ($cursos_aprobados as $curso_id) {
            $curso_id = intval($curso_id);

            if ($curso_id > 0) {
                if ($stmt_curso_aprobado) {
                    $stmt_curso_aprobado->bind_param(
                        "ii",
                        $usuario_id,
                        $curso_id
                    );

                    $stmt_curso_aprobado->execute();
                }

                $creditos = 0;

                if ($stmt_creditos) {
                    $stmt_creditos->bind_param("i", $curso_id);
                    $stmt_creditos->execute();

                    $resultado_creditos = $stmt_creditos->get_result();

                    if ($fila_creditos = $resultado_creditos->fetch_assoc()) {
                        $creditos = intval($fila_creditos["creditos"]);
                    }
                }

                if ($stmt_solicitud_curso && $creditos > 0) {
                    $stmt_solicitud_curso->bind_param(
                        "iii",
                        $solicitud_id,
                        $curso_id,
                        $creditos
                    );

                    $stmt_solicitud_curso->execute();
                }
            }
        }

        if ($stmt_curso_aprobado) {
            $stmt_curso_aprobado->close();
        }

        if ($stmt_solicitud_curso) {
            $stmt_solicitud_curso->close();
        }

        if ($stmt_creditos) {
            $stmt_creditos->close();
        }
    }

    /* ============================================================
       ENVIAR CORREO AL RESPONSABLE
    ============================================================ */

    $datos_correo = [
        "nombres" => $nombres,
        "apellidos" => $apellidos,
        "dni" => $dni,
        "codigo_estudiante" => $codigo_estudiante,
        "correo_institucional" => $correo_institucional,
        "escuela" => $escuela,
        "codigo_liquidacion" => $codigo_liquidacion,
        "total_creditos_aprobados" => $total_creditos_aprobados,
        "cumple_creditos" => $cumple_creditos,
        "tipo_carta" => $tipo_carta,
        "ruc_empresa" => $ruc_empresa,
        "razon_social_empresa" => $razon_social_empresa,
        "estado_ruc" => $estado_ruc,
        "condicion_ruc" => $condicion_ruc,
        "responsable_empresa" => $responsable_empresa,
        "cargo_responsable" => $cargo_responsable,
        "representante_dni" => $representante_dni,
        "representante_telefono" => $representante_telefono,
        "representante_email" => $representante_email,
        "direccion_empresa" => $direccion_empresa,
        "ubicacion_empresa" => $ubicacion_empresa,
        "archivo_pdf_consolidado" => $ruta_pdf_consolidado,
        "archivo_matricula" => $ruta_matricula
    ];

    $archivos_correo = [
        [
            "ruta" => $ruta_pdf_consolidado_fisica,
            "nombre" => "CONSOLIDADO_solicitud_" . $solicitud_id . "_" . $codigo_estudiante . ".pdf"
        ]
    ];

    $resultado_correo = enviarCorreoNuevaSolicitud($datos_correo, $solicitud_id, $archivos_correo);

    if ($resultado_correo === true) {
        $mensaje_correo = "Notificación enviada correctamente al responsable con el PDF consolidado adjunto.";
    } else {
        $mensaje_correo = $resultado_correo;
    }

    $cantidad_cursos_aprobados = count($cursos_aprobados);

    /* ============================================================
       CONFIGURACIÓN URL TRÁMITE UNFV
    ============================================================ */

    $url_tramite_unfv = "#";

    if (file_exists(__DIR__ . "/tramite_config.php")) {
        $tramite_config = require __DIR__ . "/tramite_config.php";
        $url_tramite_unfv = $tramite_config["url_tramite_unfv"] ?? "#";
    }

    /* ============================================================
       PANTALLA DE CONFIRMACIÓN
    ============================================================ */

    echo "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <title>Solicitud registrada</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f6f9;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 25px;
            }

            .mensaje {
                background: white;
                padding: 35px;
                border-radius: 10px;
                box-shadow: 0 0 12px rgba(0,0,0,0.15);
                text-align: center;
                max-width: 780px;
            }

            h1 {
                color: #0a7c35;
            }

            p {
                font-size: 16px;
                color: #333;
            }

            .resumen {
                background: #fff3cd;
                border: 1px solid #ffeeba;
                color: #856404;
                padding: 12px;
                border-radius: 6px;
                margin-top: 15px;
                text-align: left;
            }

            .aviso-tramite {
                background: #e8f4ff;
                border: 1px solid #b8daff;
                color: #08415c;
                padding: 14px;
                border-radius: 6px;
                margin-top: 18px;
                text-align: left;
            }

            .aviso-tramite ol {
                margin-top: 8px;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                margin-left: 5px;
                margin-right: 5px;
                padding: 10px 18px;
                background: #8B0000;
                color: white;
                text-decoration: none;
                border-radius: 6px;
            }

            a:hover {
                background: #a40000;
            }

            .btn-secundario {
                background: #333;
            }

            .btn-secundario:hover {
                background: #555;
            }
        </style>
    </head>
    <body>
        <div class='mensaje'>
            <h1>Solicitud registrada correctamente</h1>
            <p>Tu solicitud fue enviada y quedó en estado <b>Pendiente</b>.</p>
            <p>Código de solicitud: <b>" . htmlspecialchars($solicitud_id) . "</b></p>

            <div class='resumen'>
                <p><b>Tipo de carta:</b> " . htmlspecialchars($tipo_carta) . "</p>
                <p><b>Créditos aprobados:</b> " . htmlspecialchars($total_creditos_aprobados) . "</p>
                <p><b>Créditos mínimos requeridos para PPP:</b> " . htmlspecialchars($CREDITOS_MINIMOS) . " de " . htmlspecialchars($TOTAL_CREDITOS_MALLA) . "</p>
                <p><b>Cursos aprobados registrados:</b> " . htmlspecialchars($cantidad_cursos_aprobados) . "</p>
                <p><b>PDF consolidado:</b> generado correctamente.</p>";

    if ($cumple_creditos == 1) {
        echo "
                <p><b>Empresa:</b> " . htmlspecialchars($razon_social_empresa) . "</p>
                <p><b>RUC:</b> " . htmlspecialchars($ruc_empresa) . "</p>
                <p><b>Estado / Condición:</b> " . htmlspecialchars($estado_ruc) . " / " . htmlspecialchars($condicion_ruc) . "</p>";
    }

    echo "
            </div>

            <div class='aviso-tramite'>
                <b>Siguiente paso en Trámite UNFV:</b>
                <ol>
                    <li>Descargue el PDF consolidado generado por el sistema.</li>
                    <li>Ingrese a la plataforma de Trámite UNFV.</li>
                    <li>Registre su trámite adjuntando el PDF consolidado.</li>
                    <li>Anote el Número de Trámite (NT) que le genere la plataforma.</li>
                    <li>Regrese a este sistema y registre el NT desde <b>Mis solicitudes → Ver detalle</b>.</li>
                </ol>
            </div>

            <p>" . htmlspecialchars($mensaje_correo) . "</p>

            <a href='" . htmlspecialchars($ruta_pdf_consolidado) . "' target='_blank'>Descargar PDF consolidado</a>
            <a href='" . htmlspecialchars($url_tramite_unfv) . "' target='_blank'>Ir a Trámite UNFV</a>
            <a class='btn-secundario' href='mis_solicitudes.php'>Ver mis solicitudes</a>
        </div>
    </body>
    </html>
    ";
} else {
    echo "Error al guardar la solicitud: " . $stmt->error;
}

$stmt->close();
$conexion->close();
?>