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
require_once "../creditos_config.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    die("ID de solicitud no especificado.");
}

$solicitud_id = intval($_GET["id"]);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_ocracc"])) {
    $informe_ocracc_numero = trim($_POST["informe_ocracc_numero"] ?? "");
    $informe_ocracc_fecha = !empty($_POST["informe_ocracc_fecha"]) ? trim($_POST["informe_ocracc_fecha"]) : null;

    $sql_ocr = "UPDATE solicitudes_carta SET informe_ocracc_numero = ?, informe_ocracc_fecha = ? WHERE id = ?";
    $stmt_ocr = $conexion->prepare($sql_ocr);
    if ($stmt_ocr) {
        $stmt_ocr->bind_param("ssi", $informe_ocracc_numero, $informe_ocracc_fecha, $solicitud_id);
        $stmt_ocr->execute();
        $stmt_ocr->close();
        header("Location: ver_solicitud.php?id=" . $solicitud_id . "&ocracc=guardado");
        exit;
    }
}

function mostrarValor($valor, $texto_default = "No registrado") {
    if ($valor === null || trim((string)$valor) === "") {
        return htmlspecialchars($texto_default);
    }

    return htmlspecialchars($valor);
}

function obtenerClaseEstado($estado) {
    $estado = strtolower(trim($estado));

    if ($estado === "pendiente") {
        return "pendiente";
    }

    if ($estado === "aprobado") {
        return "aprobado";
    }

    if ($estado === "observado") {
        return "observado";
    }

    if ($estado === "rechazado") {
        return "rechazado";
    }

    return "pendiente";
}

function mostrarBotonDocumento($rutaBD, $textoBoton) {
    if (empty($rutaBD)) {
        echo "<span class='doc-no-disponible'>Archivo no disponible</span>";
        return;
    }

    $rutaBD = str_replace("\\", "/", $rutaBD);
    $rutaFisica = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rutaBD);
    $rutaWeb = "../" . $rutaBD;

    if (!file_exists($rutaFisica)) {
        echo "<span class='doc-no-disponible'>Archivo no encontrado</span>";
        return;
    }

    echo "<a href='" . htmlspecialchars($rutaWeb) . "' target='_blank'>" . htmlspecialchars($textoBoton) . "</a>";
}

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

            archivo_fut,
            archivo_voucher,
            archivo_record,
            archivo_matricula,
            archivo_foto_carnet,
            archivo_fut_generado,
            archivo_datos_empresa_generado,
            archivo_pdf_consolidado,

            archivo_carta_presentacion,
            fecha_generacion_carta,
            numero_carta_presentacion,

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

$sql_cursos = "SELECT 
                    cm.codigo,
                    cm.nombre,
                    cm.ciclo,
                    cm.tipo,
                    cm.grupo_electivo,
                    sc.creditos
               FROM solicitud_cursos sc
               INNER JOIN cursos_malla cm ON sc.curso_id = cm.id
               WHERE sc.solicitud_id = ?
               ORDER BY cm.ciclo, cm.codigo";

$stmt_cursos = $conexion->prepare($sql_cursos);

if (!$stmt_cursos) {
    die("Error al preparar consulta de cursos: " . $conexion->error);
}

$stmt_cursos->bind_param("i", $solicitud_id);
$stmt_cursos->execute();
$resultado_cursos = $stmt_cursos->get_result();

$total_creditos = 0;
$cursos = [];

while ($fila = $resultado_cursos->fetch_assoc()) {
    $cursos[] = $fila;
    $total_creditos += intval($fila["creditos"]);
}

$stmt_cursos->close();

$sql_historial = "SELECT 
                    accion,
                    descripcion,
                    fecha
                  FROM historial_solicitudes
                  WHERE solicitud_id = ?
                  ORDER BY fecha ASC";

$stmt_historial = $conexion->prepare($sql_historial);

if (!$stmt_historial) {
    die("Error al preparar historial: " . $conexion->error);
}

$stmt_historial->bind_param("i", $solicitud_id);
$stmt_historial->execute();
$resultado_historial = $stmt_historial->get_result();

$historial = [];

while ($fila = $resultado_historial->fetch_assoc()) {
    $historial[] = $fila;
}

$stmt_historial->close();

$estadoClase = obtenerClaseEstado($solicitud["estado"]);

$tipoCarta = trim($solicitud["tipo_carta"] ?? "");

if ($tipoCarta === "") {
    if (intval($solicitud["cumple_creditos"]) === 1) {
        $tipoCarta = "Presentacion PPP";
    } else {
        $tipoCarta = "Simple";
    }
}

$tipoClase = ($tipoCarta === "Presentacion PPP") ? "tipo-ppp" : "tipo-simple";

$datos_creditos_malla = obtenerDatosCreditosMalla($solicitud["escuela"] ?? "");

$total_creditos_malla = $datos_creditos_malla["total"];
$creditos_minimos_malla = $datos_creditos_malla["minimo"];
$nombre_carrera_malla = $datos_creditos_malla["carrera"];

$mensaje_actualizacion = "";

if (isset($_GET["actualizado"]) && $_GET["actualizado"] == "1") {
    $mensaje_actualizacion = "Estado actualizado correctamente.";
}

$mensaje_carta = "";

if (isset($_GET["carta"]) && $_GET["carta"] === "generada") {
    $mensaje_carta = "Carta de presentación generada y enviada a Decanato correctamente.";
}

$mensaje_simple = "";

if (isset($_GET["simple"]) && $_GET["simple"] === "generada") {
    $mensaje_simple = "Carta Simple generada y enviada al estudiante correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Solicitud - Sistema PPP</title>
    <style>
        :root {
            --fiei-vino: #721224;
            --fiei-vino-hover: #580c1b;
            --fiei-vino-light: #fbf2f4;
            --fiei-vino-border: #f0d0d7;
            --unfv-naranja: #E65100;
            --unfv-naranja-hover: #BF360C;
            --unfv-naranja-soft: #fff3e0;
            --unfv-naranja-border: #ffe0b2;
            --texto-principal: #1e293b;
            --texto-secundario: #64748b;
            --borde-suave: #cbd5e1;
            --fondo-pagina: #f1f5f9;
            --blanco: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            background: var(--fondo-pagina);
            color: var(--texto-principal);
            margin: 0;
            padding: 24px 15px;
        }

        .contenedor {
            width: 95%;
            max-width: 1100px;
            margin: 15px auto;
            background: var(--blanco);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.07), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            border-top: 5px solid var(--fiei-vino);
        }

        .barra-superior {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .usuario {
            font-size: 14px;
            color: var(--texto-secundario);
        }

        .usuario b {
            color: var(--fiei-vino);
            font-weight: 600;
        }

        .acciones-superiores a {
            display: inline-block;
            margin-left: 8px;
            padding: 8.5px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            color: white;
            transition: all 0.15s ease;
        }

        .btn-volver {
            background: var(--fiei-vino);
        }

        .btn-volver:hover {
            background: var(--fiei-vino-hover);
            transform: translateY(-1px);
        }

        .btn-cerrar {
            background: #334155;
        }

        .btn-cerrar:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }

        h1 {
            text-align: center;
            color: var(--fiei-vino);
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 6px 0;
            letter-spacing: -0.3px;
        }

        h2 {
            text-align: center;
            font-size: 15px;
            font-weight: 500;
            color: var(--texto-secundario);
            margin: 0 0 24px 0;
        }

        .alerta-exito {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #059669;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
        }

        .bloque {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 22px;
            background: #fafbfc;
        }

        .bloque h3 {
            margin-top: 0;
            margin-bottom: 16px;
            color: var(--fiei-vino);
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 10px;
            font-size: 17.5px;
            font-weight: 700;
        }

        .grid-datos {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .dato {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 11px 14px;
            border-radius: 7px;
            font-size: 13.5px;
            line-height: 1.45;
        }

        .dato b {
            color: #334155;
            font-weight: 600;
        }

        .estado {
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
            letter-spacing: 0.2px;
        }

        .pendiente {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .aprobado {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .observado {
            background: var(--unfv-naranja-soft);
            color: #9a3412;
            border: 1px solid var(--unfv-naranja-border);
        }

        .rechazado {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .tipo {
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
        }

        .tipo-simple {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .tipo-ppp {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .cumple-si {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
        }

        .cumple-no {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
        }

        .nota-simple {
            background: var(--unfv-naranja-soft);
            color: #9a3412;
            border: 1px solid var(--unfv-naranja-border);
            border-left: 4px solid var(--unfv-naranja);
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .documentos {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .documentos a {
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            color: white;
            padding: 10px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(114, 18, 36, 0.2);
            transition: all 0.15s ease;
        }

        .documentos a:hover {
            background: linear-gradient(180deg, var(--fiei-vino-hover) 0%, #4a0915 100%);
            box-shadow: 0 4px 8px rgba(114, 18, 36, 0.25);
            transform: translateY(-1px);
        }

        .doc-no-disponible {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 9px 14px;
            border-radius: 7px;
            font-size: 13px;
            display: inline-block;
        }

        .nt-registrado {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #059669;
            color: #065f46;
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .nt-pendiente {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            color: #92400e;
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        th {
            background: var(--fiei-vino);
            color: white;
            padding: 11px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12.5px;
            letter-spacing: 0.3px;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            color: var(--texto-principal);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .resumen-creditos {
            margin-top: 14px;
            background: var(--unfv-naranja-soft);
            border: 1px solid var(--unfv-naranja-border);
            color: #9a3412;
            padding: 12px;
            border-radius: 7px;
            font-weight: 600;
            text-align: center;
            font-size: 13.5px;
        }

        .badge-electivo {
            display: inline-block;
            background: var(--unfv-naranja-soft);
            color: #9a3412;
            border: 1px solid var(--unfv-naranja-border);
            padding: 2px 7px;
            border-radius: 5px;
            font-size: 11.5px;
            font-weight: 600;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 13.5px;
            margin-bottom: 6px;
            color: #334155;
        }

        select, textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1.5px solid var(--borde-suave);
            border-radius: 7px;
            font-size: 14px;
            box-sizing: border-box;
            margin-bottom: 16px;
            font-family: inherit;
            background: #ffffff;
            color: var(--texto-principal);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        select:focus, textarea:focus {
            border-color: var(--fiei-vino);
            box-shadow: 0 0 0 3px rgba(114, 18, 36, 0.12);
            outline: none;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 13px;
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            color: white;
            border: none;
            font-size: 15px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 4px rgba(114, 18, 36, 0.2);
        }

        button:hover {
            background: linear-gradient(180deg, var(--fiei-vino-hover) 0%, #4a0915 100%);
            box-shadow: 0 4px 8px rgba(114, 18, 36, 0.25);
            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(0);
        }

        .vacio {
            text-align: center;
            padding: 18px;
            color: var(--texto-secundario);
            background: white;
            border-radius: 7px;
            font-size: 13.5px;
        }

        @media (max-width: 800px) {
            .grid-datos {
                grid-template-columns: 1fr;
            }

            .barra-superior {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .contenedor {
                overflow-x: auto;
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>

<div class="contenedor">

    <div class="barra-superior">
        <div class="usuario">
            Sesión activa:
            <b><?php echo htmlspecialchars(($_SESSION["nombres"] ?? "") . " " . ($_SESSION["apellidos"] ?? "")); ?></b>
            |
            Rol:
            <b><?php echo htmlspecialchars($_SESSION["rol"] ?? ""); ?></b>
        </div>

        <div class="acciones-superiores">
            <a class="btn-volver" href="panel.php">Volver al panel</a>
            <a class="btn-cerrar" href="../logout.php">Cerrar sesión</a>
        </div>
    </div>

    <h1>Detalle de Solicitud</h1>
    <h2>Carta de Presentación PPP</h2>

    <?php if (!empty($mensaje_actualizacion)): ?>
        <div class="alerta-exito">
            <?php echo htmlspecialchars($mensaje_actualizacion); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensaje_carta)): ?>
        <div class="alerta-exito">
            <?php echo htmlspecialchars($mensaje_carta); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensaje_simple)): ?>
        <div class="alerta-exito">
            <?php echo htmlspecialchars($mensaje_simple); ?>
        </div>
    <?php endif; ?>

    <div class="bloque">
        <h3>Datos del estudiante</h3>

        <div class="grid-datos">
            <div class="dato"><b>ID solicitud:</b> <?php echo htmlspecialchars($solicitud["id"]); ?></div>
            <div class="dato">
                <b>Estado:</b>
                <span class="estado <?php echo htmlspecialchars($estadoClase); ?>">
                    <?php echo htmlspecialchars($solicitud["estado"]); ?>
                </span>
            </div>

            <div class="dato"><b>Nombres:</b> <?php echo htmlspecialchars($solicitud["nombres"]); ?></div>
            <div class="dato"><b>Apellidos:</b> <?php echo htmlspecialchars($solicitud["apellidos"]); ?></div>

            <div class="dato"><b>DNI:</b> <?php echo htmlspecialchars($solicitud["dni"]); ?></div>
            <div class="dato"><b>Código estudiante:</b> <?php echo htmlspecialchars($solicitud["codigo_estudiante"]); ?></div>

            <div class="dato"><b>Correo institucional:</b> <?php echo htmlspecialchars($solicitud["correo_institucional"]); ?></div>
            <div class="dato"><b>Escuela:</b> <?php echo htmlspecialchars($solicitud["escuela"]); ?></div>

            <div class="dato"><b>Código de liquidación:</b> <?php echo htmlspecialchars($solicitud["codigo_liquidacion"]); ?></div>
            <div class="dato"><b>Fecha de solicitud:</b> <?php echo htmlspecialchars($solicitud["fecha_solicitud"]); ?></div>

            <div class="dato"><b>Fecha de revisión:</b> <?php echo mostrarValor($solicitud["fecha_revision"] ?? "", "Sin revisar"); ?></div>
            <div class="dato"><b>Observación:</b> <?php echo mostrarValor($solicitud["observacion"] ?? "", "Sin observación"); ?></div>
        </div>
    </div>

    <div class="bloque">
        <h3>Trámite UNFV, PDF consolidado y cartas generadas</h3>

        <?php if ($tipoCarta === "Presentacion PPP" && intval($solicitud["cumple_creditos"]) === 1): ?>
            <?php if (!empty($solicitud["numero_tramite"])): ?>
                <div class="nt-registrado">
                    <b>Número de Trámite UNFV:</b>
                    <?php echo htmlspecialchars($solicitud["numero_tramite"]); ?><br>
                    <b>Fecha de registro del NT:</b>
                    <?php echo mostrarValor($solicitud["fecha_registro_nt"] ?? "", "Sin fecha registrada"); ?>
                </div>
            <?php else: ?>
                <div class="nt-pendiente">
                    El estudiante aún no ha registrado el Número de Trámite UNFV.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="nt-pendiente">
                Esta solicitud es <b>Carta Simple</b>. No requiere Número de Trámite UNFV ni envío a Decanato.
            </div>
        <?php endif; ?>

        <div class="documentos">
            <?php mostrarBotonDocumento($solicitud["archivo_pdf_consolidado"] ?? "", "Descargar PDF consolidado"); ?>

            <?php if (!empty($solicitud["archivo_carta_presentacion"])): ?>
                <?php mostrarBotonDocumento($solicitud["archivo_carta_presentacion"], "Ver carta de presentación generada"); ?>
            <?php endif; ?>

            <?php if (!empty($solicitud["archivo_carta_simple"])): ?>
                <?php mostrarBotonDocumento($solicitud["archivo_carta_simple"], "Ver Carta Simple generada"); ?>
            <?php endif; ?>

            <?php if (
                $tipoCarta === "Presentacion PPP" &&
                intval($solicitud["cumple_creditos"]) === 1 &&
                !empty($solicitud["numero_tramite"]) &&
                !empty($solicitud["razon_social_empresa"]) &&
                !empty($solicitud["responsable_empresa"])
            ): ?>
                <a 
                    href="generar_enviar_carta_decanato.php?id=<?php echo intval($solicitud["id"]); ?>"
                    onclick="return confirm('¿Desea generar la carta de presentación y enviarla a Decanato?');"
                >
                    Generar y enviar a Decanato
                </a>
            <?php endif; ?>

            <?php if (
                $tipoCarta === "Simple" &&
                intval($solicitud["cumple_creditos"]) === 0
            ): ?>
                <a 
                    href="generar_enviar_carta_simple.php?id=<?php echo intval($solicitud["id"]); ?>"
                    onclick="return confirm('¿Desea generar la Carta Simple y enviarla al estudiante?');"
                >
                    Generar y enviar Carta Simple
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($solicitud["numero_carta_presentacion"])): ?>
            <br>
            <div class="dato">
                <b>Número de carta de presentación generada:</b>
                <?php echo mostrarValor($solicitud["numero_carta_presentacion"] ?? "", "No generado"); ?>
                <br>
                <b>Fecha de generación:</b>
                <?php echo mostrarValor($solicitud["fecha_generacion_carta"] ?? "", "Sin fecha"); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($solicitud["numero_carta_simple"])): ?>
            <br>
            <div class="dato">
                <b>Número de Carta Simple:</b>
                <?php echo mostrarValor($solicitud["numero_carta_simple"] ?? "", "No generado"); ?>
                <br>
                <b>Fecha de generación:</b>
                <?php echo mostrarValor($solicitud["fecha_generacion_carta_simple"] ?? "", "Sin fecha"); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="bloque">
        <h3>Datos del FUT generado</h3>

        <div class="grid-datos">
            <div class="dato"><b>Facultad:</b> <?php echo mostrarValor($solicitud["fut_facultad"] ?? ""); ?></div>
            <div class="dato"><b>Tipo de programa:</b> <?php echo mostrarValor($solicitud["fut_tipo_programa"] ?? ""); ?></div>

            <div class="dato"><b>Trámite:</b> <?php echo mostrarValor($solicitud["fut_tramite"] ?? ""); ?></div>
            <div class="dato"><b>Dependencia:</b> <?php echo mostrarValor($solicitud["fut_dependencia"] ?? ""); ?></div>

            <div class="dato"><b>Tipo de solicitante:</b> <?php echo mostrarValor($solicitud["fut_tipo_solicitante"] ?? ""); ?></div>
            <div class="dato"><b>Distrito:</b> <?php echo mostrarValor($solicitud["fut_distrito"] ?? ""); ?></div>

            <div class="dato"><b>Dirección domiciliaria:</b> <?php echo mostrarValor($solicitud["fut_direccion"] ?? ""); ?></div>
            <div class="dato"><b>Teléfono fijo:</b> <?php echo mostrarValor($solicitud["fut_telefono_fijo"] ?? ""); ?></div>

            <div class="dato"><b>Celular:</b> <?php echo mostrarValor($solicitud["fut_celular"] ?? ""); ?></div>
            <div class="dato"><b>Correo FUT:</b> <?php echo mostrarValor($solicitud["fut_correo"] ?? ""); ?></div>

            <div class="dato"><b>Total folios:</b> <?php echo mostrarValor($solicitud["fut_total_folios"] ?? ""); ?></div>
            <div class="dato"><b>Lugar y fecha:</b> <?php echo mostrarValor($solicitud["fut_lugar_fecha"] ?? ""); ?></div>
        </div>

        <br>

        <div class="dato">
            <b>Fundamentación:</b><br>
            <?php echo nl2br(mostrarValor($solicitud["fut_fundamentacion"] ?? "", "No registrado")); ?>
        </div>

        <br>

        <div class="dato">
            <b>Documentos adjuntos declarados:</b><br>
            <?php echo nl2br(mostrarValor($solicitud["fut_documentos_adjuntos"] ?? "", "No registrado")); ?>
        </div>
    </div>

    <div class="bloque">
        <h3>Evaluación académica de créditos</h3>

        <div class="grid-datos">
            <div class="dato">
                <b>Tipo de carta:</b>
                <span class="tipo <?php echo htmlspecialchars($tipoClase); ?>">
                    <?php echo htmlspecialchars($tipoCarta); ?>
                </span>
            </div>

            <div class="dato">
                <b>Total de créditos aprobados:</b>
                <?php echo intval($solicitud["total_creditos_aprobados"]); ?> créditos
            </div>

            <div class="dato">
                <b>Créditos mínimos requeridos:</b>
                <?php echo intval($creditos_minimos_malla); ?> créditos de <?php echo intval($total_creditos_malla); ?><br>
                <b>Carrera:</b> <?php echo htmlspecialchars($nombre_carrera_malla); ?>
            </div>

            <div class="dato">
                <b>Cumple el 80%:</b>
                <?php if (intval($solicitud["cumple_creditos"]) === 1): ?>
                    <span class="cumple-si">Sí cumple</span>
                <?php else: ?>
                    <span class="cumple-no">No cumple</span>
                <?php endif; ?>
            </div>

            <div class="dato">
                <b>Duración de prácticas:</b>
                <?php echo intval($solicitud["duracion_meses"] ?? 6); ?> meses<br>
                <b>Periodo de prácticas:</b>
                <?php if (!empty($solicitud["fecha_inicio_practicas"]) && !empty($solicitud["fecha_fin_practicas"])): ?>
                    <?php echo date("d/m/Y", strtotime($solicitud["fecha_inicio_practicas"])) . " al " . date("d/m/Y", strtotime($solicitud["fecha_fin_practicas"])); ?>
                <?php else: ?>
                    <span style="color:#888;">No registrado</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bloque">
        <h3>Informe OCRACC (Oficina de Créditos)</h3>
        <?php if (isset($_GET["ocracc"]) && $_GET["ocracc"] === "guardado"): ?>
            <div class="alerta-exito">
                Datos de Informe OCRACC guardados correctamente.
            </div>
        <?php endif; ?>
        <form method="POST" action="ver_solicitud.php?id=<?php echo $solicitud_id; ?>">
            <input type="hidden" name="guardar_ocracc" value="1">
            <div class="grid-datos">
                <div class="grupo">
                    <label for="informe_ocracc_numero"><b>Número de Informe OCRACC:</b></label>
                    <input type="text" name="informe_ocracc_numero" id="informe_ocracc_numero"
                           value="<?php echo htmlspecialchars($solicitud["informe_ocracc_numero"] ?? ""); ?>"
                           placeholder="Ej. Informe N° 045-2026-OCRACC-FIEI">
                </div>
                <div class="grupo">
                    <label for="informe_ocracc_fecha"><b>Fecha del Informe OCRACC:</b></label>
                    <input type="date" name="informe_ocracc_fecha" id="informe_ocracc_fecha"
                           value="<?php echo htmlspecialchars($solicitud["informe_ocracc_fecha"] ?? ""); ?>">
                </div>
            </div>
            <button type="submit" style="max-width: 250px; margin-top: 10px;">Guardar Datos OCRACC</button>
        </form>
    </div>

    <div class="bloque">
        <h3>Datos de empresa</h3>

        <?php if ($tipoCarta === "Presentacion PPP" && intval($solicitud["cumple_creditos"]) === 1): ?>
            <div class="grid-datos">
                <div class="dato"><b>RUC:</b> <?php echo mostrarValor($solicitud["ruc_empresa"] ?? ""); ?></div>
                <div class="dato"><b>Razón social:</b> <?php echo mostrarValor($solicitud["razon_social_empresa"] ?? "", "Pendiente"); ?></div>

                <div class="dato"><b>Estado del RUC:</b> <?php echo mostrarValor($solicitud["estado_ruc"] ?? "", "Pendiente"); ?></div>
                <div class="dato"><b>Condición del RUC:</b> <?php echo mostrarValor($solicitud["condicion_ruc"] ?? "", "Pendiente"); ?></div>

                <div class="dato"><b>Representante de empresa:</b> <?php echo mostrarValor($solicitud["responsable_empresa"] ?? ""); ?></div>
                <div class="dato"><b>Cargo del representante:</b> <?php echo mostrarValor($solicitud["cargo_responsable"] ?? ""); ?></div>

                <div class="dato"><b>DNI del representante:</b> <?php echo mostrarValor($solicitud["representante_dni"] ?? ""); ?></div>
                <div class="dato"><b>Teléfono del representante:</b> <?php echo mostrarValor($solicitud["representante_telefono"] ?? ""); ?></div>

                <div class="dato"><b>Email del representante:</b> <?php echo mostrarValor($solicitud["representante_email"] ?? ""); ?></div>
                <div class="dato"><b>Dirección de empresa:</b> <?php echo mostrarValor($solicitud["direccion_empresa"] ?? ""); ?></div>

                <div class="dato"><b>DPTO. / PROV. / DISTRITO:</b> <?php echo mostrarValor($solicitud["ubicacion_empresa"] ?? ""); ?></div>
            </div>
        <?php else: ?>
            <div class="nota-simple">
                Esta solicitud fue registrada como <b>Carta Simple</b>, porque el estudiante no cumple con el mínimo de créditos requeridos para Carta de Presentación PPP.
                Por ello no se solicitaron datos de empresa.
            </div>
        <?php endif; ?>
    </div>

    <div class="bloque">
        <h3>Documentos generados y adjuntos</h3>

        <div class="documentos">
            <?php mostrarBotonDocumento($solicitud["archivo_fut_generado"] ?? $solicitud["archivo_fut"] ?? "", "Ver FUT generado"); ?>

            <?php if (!empty($solicitud["archivo_datos_empresa_generado"])): ?>
                <?php mostrarBotonDocumento($solicitud["archivo_datos_empresa_generado"], "Ver datos de empresa generado"); ?>
            <?php endif; ?>

            <?php mostrarBotonDocumento($solicitud["archivo_pdf_consolidado"] ?? "", "Descargar PDF consolidado"); ?>

            <?php if (!empty($solicitud["archivo_carta_presentacion"])): ?>
                <?php mostrarBotonDocumento($solicitud["archivo_carta_presentacion"], "Ver carta de presentación"); ?>
            <?php endif; ?>

            <?php if (!empty($solicitud["archivo_carta_simple"])): ?>
                <?php mostrarBotonDocumento($solicitud["archivo_carta_simple"], "Ver Carta Simple"); ?>
            <?php endif; ?>

            <?php mostrarBotonDocumento($solicitud["archivo_foto_carnet"] ?? "", "Ver foto carnet"); ?>
            <?php mostrarBotonDocumento($solicitud["archivo_voucher"] ?? "", "Ver voucher"); ?>
            <?php mostrarBotonDocumento($solicitud["archivo_record"] ?? "", "Ver récord académico"); ?>
            <?php if (!empty($solicitud["archivo_matricula"])): ?>
                <?php mostrarBotonDocumento($solicitud["archivo_matricula"], "Ver ficha de matrícula"); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bloque">
        <h3>Cursos aprobados asociados a la solicitud</h3>

        <?php if (count($cursos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Ciclo</th>
                        <th>Código</th>
                        <th>Curso</th>
                        <th>Tipo</th>
                        <th>Grupo electivo</th>
                        <th>Créditos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $curso): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($curso["ciclo"]); ?></td>
                            <td><?php echo htmlspecialchars($curso["codigo"]); ?></td>
                            <td><?php echo htmlspecialchars($curso["nombre"]); ?></td>
                            <td><?php echo htmlspecialchars($curso["tipo"]); ?></td>
                            <td>
                                <?php if (!empty($curso["grupo_electivo"])): ?>
                                    <span class="badge-electivo">
                                        <?php echo htmlspecialchars($curso["grupo_electivo"]); ?>
                                    </span>
                                <?php else: ?>
                                    No aplica
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($curso["creditos"]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="resumen-creditos">
                Total de créditos aprobados en esta solicitud: <?php echo $total_creditos; ?>
            </div>
        <?php else: ?>
            <div class="vacio">
                No se registraron cursos aprobados para esta solicitud.
            </div>
        <?php endif; ?>
    </div>

    <div class="bloque">
        <h3>Historial de la solicitud</h3>

        <?php if (count($historial) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item["fecha"]); ?></td>
                            <td><?php echo htmlspecialchars($item["accion"]); ?></td>
                            <td><?php echo htmlspecialchars($item["descripcion"]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="vacio">
                No hay historial registrado para esta solicitud.
            </div>
        <?php endif; ?>
    </div>

    <div class="bloque">
        <h3>Revisión de la solicitud</h3>

        <form action="actualizar_estado.php" method="POST">
            <input type="hidden" name="solicitud_id" value="<?php echo intval($solicitud["id"]); ?>">

            <label for="estado">Estado de la solicitud</label>
            <select name="estado" id="estado" required>
                <option value="Pendiente" <?php echo $solicitud["estado"] === "Pendiente" ? "selected" : ""; ?>>Pendiente</option>
                <option value="Observado" <?php echo $solicitud["estado"] === "Observado" ? "selected" : ""; ?>>Observado</option>
                <option value="Aprobado" <?php echo $solicitud["estado"] === "Aprobado" ? "selected" : ""; ?>>Aprobado</option>
                <option value="Rechazado" <?php echo $solicitud["estado"] === "Rechazado" ? "selected" : ""; ?>>Rechazado</option>
            </select>

            <label for="observacion">Observación</label>
            <textarea name="observacion" id="observacion" placeholder="Ingrese una observación si corresponde..."><?php echo htmlspecialchars($solicitud["observacion"] ?? ""); ?></textarea>

            <button type="submit">Actualizar estado</button>
        </form>
    </div>

</div>

</body>
</html>

<?php
$conexion->close();
?>