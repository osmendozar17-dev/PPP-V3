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
require_once "creditos_config.php";

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    die("ID de solicitud no especificado.");
}

$solicitud_id = intval($_GET["id"]);
$usuario_id = $_SESSION["usuario_id"];

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
    $rutaFisica = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rutaBD);
    $rutaWeb = $rutaBD;

    if (!file_exists($rutaFisica)) {
        echo "<span class='doc-no-disponible'>Archivo no encontrado</span>";
        return;
    }

    echo "<a href='" . htmlspecialchars($rutaWeb) . "' target='_blank'>" . htmlspecialchars($textoBoton) . "</a>";
}

$mensaje_nt = "";

if (isset($_GET["nt"]) && $_GET["nt"] === "registrado") {
    $mensaje_nt = "Número de Trámite UNFV registrado correctamente. Se notificó al ingeniero responsable.";
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

            total_creditos_aprobados,
            cumple_creditos,
            tipo_carta,

            ruc_empresa,
            razon_social_empresa,
            estado_ruc,
            condicion_ruc,
            responsable_empresa,
            cargo_responsable,

            numero_tramite,
            fecha_registro_nt,

            archivo_fut,
            archivo_voucher,
            archivo_record,
            archivo_foto_carnet,
            archivo_fut_generado,
            archivo_datos_empresa_generado,
            archivo_pdf_consolidado,

            estado,
            observacion,
            fecha_solicitud,
            fecha_revision
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

$cursos = [];
$total_creditos = 0;

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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de mi Solicitud - Sistema PPP</title>
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
            max-width: 1050px;
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

        .acciones a {
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

        .observacion {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 14px;
            border-radius: 7px;
            color: #334155;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .observacion-alerta {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            color: #92400e;
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

        .bloque-nt {
            background: #f0f9ff;
            border: 1.5px solid #bae6fd;
            color: #0369a1;
            padding: 18px;
            border-radius: 9px;
            margin-top: 16px;
        }

        .bloque-nt h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--fiei-vino);
            font-size: 16px;
            font-weight: 700;
        }

        .bloque-nt label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #334155;
            font-size: 13.5px;
        }

        .bloque-nt input {
            width: 100%;
            padding: 11px 13px;
            border: 1.5px solid #bae6fd;
            border-radius: 7px;
            font-size: 14.5px;
            box-sizing: border-box;
            margin-bottom: 14px;
            background: #ffffff;
            font-family: inherit;
        }

        .bloque-nt input:focus {
            border-color: var(--fiei-vino);
            box-shadow: 0 0 0 3px rgba(114, 18, 36, 0.12);
            outline: none;
        }

        .bloque-nt button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            color: white;
            border: none;
            font-size: 14.5px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.15s ease;
            box-shadow: 0 2px 4px rgba(114, 18, 36, 0.2);
        }

        .bloque-nt button:hover {
            background: linear-gradient(180deg, var(--fiei-vino-hover) 0%, #4a0915 100%);
            transform: translateY(-1px);
        }

        .nt-registrado {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #059669;
            color: #065f46;
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-top: 16px;
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
        </div>

        <div class="acciones">
            <a class="btn-volver" href="mis_solicitudes.php">Volver a mis solicitudes</a>
            <a class="btn-cerrar" href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <h1>Detalle de mi Solicitud</h1>
    <h2>Carta de Presentación PPP</h2>

    <?php if (!empty($mensaje_nt)): ?>
        <div class="alerta-exito">
            <?php echo htmlspecialchars($mensaje_nt); ?>
        </div>
    <?php endif; ?>

    <div class="bloque">
        <h3>Estado de la solicitud</h3>

        <div class="grid-datos">
            <div class="dato"><b>ID solicitud:</b> <?php echo htmlspecialchars($solicitud["id"]); ?></div>
            <div class="dato">
                <b>Estado:</b>
                <span class="estado <?php echo htmlspecialchars($estadoClase); ?>">
                    <?php echo htmlspecialchars($solicitud["estado"]); ?>
                </span>
            </div>

            <div class="dato"><b>Fecha de solicitud:</b> <?php echo htmlspecialchars($solicitud["fecha_solicitud"]); ?></div>
            <div class="dato"><b>Fecha de revisión:</b> <?php echo mostrarValor($solicitud["fecha_revision"] ?? "", "Sin revisar"); ?></div>
        </div>
    </div>

    <div class="bloque">
        <h3>Trámite UNFV y PDF consolidado</h3>

        <div class="documentos">
            <?php mostrarBotonDocumento($solicitud["archivo_pdf_consolidado"] ?? "", "Descargar PDF consolidado"); ?>
        </div>

        <?php if (empty($solicitud["numero_tramite"])): ?>
            <div class="bloque-nt">
                <h4>Registrar Número de Trámite UNFV</h4>

                <p>
                    Después de descargar el PDF consolidado y registrarlo en la plataforma de Trámite UNFV,
                    copie aquí el Número de Trámite generado para que el ingeniero pueda continuar con la revisión.
                </p>

                <form action="registrar_numero_tramite.php" method="POST" onsubmit="return confirm('¿Desea registrar este Número de Trámite UNFV?');">
                    <input type="hidden" name="solicitud_id" value="<?php echo intval($solicitud["id"]); ?>">

                    <label for="numero_tramite">Número de Trámite UNFV</label>
                    <input 
                        type="text" 
                        name="numero_tramite" 
                        id="numero_tramite" 
                        placeholder="Ejemplo: NT-2026-000123"
                        required
                    >

                    <button type="submit">Registrar Número de Trámite</button>
                </form>
            </div>
        <?php else: ?>
            <div class="nt-registrado">
                <b>Número de Trámite UNFV registrado:</b>
                <?php echo htmlspecialchars($solicitud["numero_tramite"]); ?><br>

                <b>Fecha de registro:</b>
                <?php 
                    echo !empty($solicitud["fecha_registro_nt"]) 
                        ? htmlspecialchars($solicitud["fecha_registro_nt"]) 
                        : "Sin fecha registrada"; 
                ?>
            </div>
        <?php endif; ?>
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
        </div>
    </div>

    <div class="bloque">
        <h3>Datos registrados</h3>

        <div class="grid-datos">
            <div class="dato"><b>Nombres:</b> <?php echo htmlspecialchars($solicitud["nombres"]); ?></div>
            <div class="dato"><b>Apellidos:</b> <?php echo htmlspecialchars($solicitud["apellidos"]); ?></div>

            <div class="dato"><b>DNI:</b> <?php echo htmlspecialchars($solicitud["dni"]); ?></div>
            <div class="dato"><b>Código estudiante:</b> <?php echo htmlspecialchars($solicitud["codigo_estudiante"]); ?></div>

            <div class="dato"><b>Correo institucional:</b> <?php echo htmlspecialchars($solicitud["correo_institucional"]); ?></div>
            <div class="dato"><b>Escuela:</b> <?php echo htmlspecialchars($solicitud["escuela"]); ?></div>

            <div class="dato"><b>Código de liquidación:</b> <?php echo htmlspecialchars($solicitud["codigo_liquidacion"]); ?></div>
        </div>
    </div>

    <div class="bloque">
        <h3>Datos de empresa</h3>

        <?php if ($tipoCarta === "Presentacion PPP" && intval($solicitud["cumple_creditos"]) === 1): ?>
            <div class="grid-datos">
                <div class="dato"><b>RUC:</b> <?php echo mostrarValor($solicitud["ruc_empresa"] ?? ""); ?></div>
                <div class="dato"><b>Razón social:</b> <?php echo mostrarValor($solicitud["razon_social_empresa"] ?? "", "Pendiente"); ?></div>

                <div class="dato"><b>Estado del RUC:</b> <?php echo mostrarValor($solicitud["estado_ruc"] ?? "", "Pendiente"); ?></div>
                <div class="dato"><b>Condición del RUC:</b> <?php echo mostrarValor($solicitud["condicion_ruc"] ?? "", "Pendiente"); ?></div>

                <div class="dato"><b>Responsable de empresa:</b> <?php echo mostrarValor($solicitud["responsable_empresa"] ?? ""); ?></div>
                <div class="dato"><b>Cargo del responsable:</b> <?php echo mostrarValor($solicitud["cargo_responsable"] ?? ""); ?></div>
            </div>
        <?php else: ?>
            <div class="nota-simple">
                Esta solicitud fue registrada como <b>Carta Simple</b>, porque no cumple con el mínimo de créditos requeridos para Carta de Presentación PPP.
                Por ello no se solicitaron datos de empresa.
            </div>
        <?php endif; ?>
    </div>

    <div class="bloque">
        <h3>Observación del responsable</h3>

        <div class="observacion <?php echo !empty($solicitud["observacion"]) ? "observacion-alerta" : ""; ?>">
            <?php 
                echo !empty($solicitud["observacion"]) 
                    ? nl2br(htmlspecialchars($solicitud["observacion"])) 
                    : "Sin observación registrada."; 
            ?>
        </div>
    </div>

    <div class="bloque">
        <h3>Documentos enviados</h3>

        <div class="documentos">
            <?php mostrarBotonDocumento($solicitud["archivo_fut_generado"] ?? $solicitud["archivo_fut"] ?? "", "Ver FUT generado"); ?>
            <?php mostrarBotonDocumento($solicitud["archivo_pdf_consolidado"] ?? "", "Descargar PDF consolidado"); ?>

            <?php if (!empty($solicitud["archivo_datos_empresa_generado"])): ?>
                <?php mostrarBotonDocumento($solicitud["archivo_datos_empresa_generado"], "Ver datos de empresa generado"); ?>
            <?php endif; ?>

            <?php mostrarBotonDocumento($solicitud["archivo_foto_carnet"] ?? "", "Ver foto carnet"); ?>
            <?php mostrarBotonDocumento($solicitud["archivo_voucher"] ?? "", "Ver voucher"); ?>
            <?php mostrarBotonDocumento($solicitud["archivo_record"] ?? "", "Ver récord académico"); ?>
        </div>
    </div>

    <div class="bloque">
        <h3>Cursos aprobados registrados en esta solicitud</h3>

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

</div>

</body>
</html>

<?php
$conexion->close();
?>