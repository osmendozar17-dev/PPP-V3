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

$usuario_id = $_SESSION["usuario_id"];

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

function recortarTexto($texto, $limite = 80) {
    $texto = trim((string) $texto);

    if ($texto === "") {
        return "Sin observación";
    }

    if (mb_strlen($texto, "UTF-8") <= $limite) {
        return $texto;
    }

    return mb_substr($texto, 0, $limite, "UTF-8") . "...";
}

function archivoExisteParaDescarga($rutaBD) {
    if (empty($rutaBD)) {
        return false;
    }

    $rutaBD = str_replace("\\", "/", $rutaBD);
    $rutaFisica = __DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rutaBD);

    return file_exists($rutaFisica);
}

$sql = "SELECT 
            id,
            nombres,
            apellidos,
            codigo_estudiante,
            correo_institucional,
            escuela,
            codigo_liquidacion,
            total_creditos_aprobados,
            cumple_creditos,
            tipo_carta,
            numero_tramite,
            fecha_registro_nt,
            archivo_pdf_consolidado,
            estado,
            observacion,
            fecha_solicitud,
            fecha_revision
        FROM solicitudes_carta
        WHERE usuario_id = ?
        ORDER BY fecha_solicitud DESC";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar consulta: " . $conexion->error);
}

$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

$solicitudes = [];

$total_solicitudes = 0;
$total_pendientes = 0;
$total_observadas = 0;
$total_aprobadas = 0;
$total_rechazadas = 0;

while ($fila = $resultado->fetch_assoc()) {
    $solicitudes[] = $fila;
    $total_solicitudes++;

    if ($fila["estado"] === "Pendiente") {
        $total_pendientes++;
    } elseif ($fila["estado"] === "Observado") {
        $total_observadas++;
    } elseif ($fila["estado"] === "Aprobado") {
        $total_aprobadas++;
    } elseif ($fila["estado"] === "Rechazado") {
        $total_rechazadas++;
    }
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Solicitudes - Sistema PPP</title>
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
            width: 98%;
            max-width: 1450px;
            margin: 15px auto;
            background: var(--blanco);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.07), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            border-top: 5px solid var(--fiei-vino);
            overflow-x: auto;
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

        .btn-nueva {
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            box-shadow: 0 2px 4px rgba(114, 18, 36, 0.2);
        }

        .btn-nueva:hover {
            background: linear-gradient(180deg, var(--fiei-vino-hover) 0%, #4a0915 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(114, 18, 36, 0.25);
        }

        .btn-cerrar {
            background: #334155;
        }

        .btn-cerrar:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }

        .btn-detalle {
            background: var(--fiei-vino);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
            transition: all 0.15s ease;
        }

        .btn-detalle:hover {
            background: var(--fiei-vino-hover);
            transform: translateY(-1px);
        }

        .btn-descargar {
            background: #059669;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
            margin-top: 6px;
            transition: all 0.15s ease;
        }

        .btn-descargar:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .pdf-no-disponible {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            display: inline-block;
            white-space: nowrap;
            margin-top: 6px;
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

        .nota {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
            border-left: 4px solid #0284c7;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .nota-observado {
            background: var(--unfv-naranja-soft);
            color: #9a3412;
            border: 1px solid var(--unfv-naranja-border);
            border-left: 4px solid var(--unfv-naranja);
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 13.5px;
            font-weight: 600;
            line-height: 1.5;
        }

        .tarjetas-resumen {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .tarjeta {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.15s ease;
        }

        .tarjeta:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }

        .tarjeta b {
            display: block;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--fiei-vino);
        }

        .tarjeta span {
            font-size: 12.5px;
            color: var(--texto-secundario);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            min-width: 1250px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        th {
            background: var(--fiei-vino);
            color: white;
            padding: 11px 12px;
            text-align: left;
            white-space: nowrap;
            font-weight: 600;
            font-size: 12.5px;
            letter-spacing: 0.3px;
        }

        td {
            padding: 11px 12px;
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

        .fila-observada {
            background: #fffbeb !important;
        }

        .estado {
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
            white-space: nowrap;
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
            white-space: nowrap;
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
            white-space: nowrap;
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
            white-space: nowrap;
        }

        .nt-registrado {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
            white-space: nowrap;
        }

        .nt-pendiente {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
            white-space: nowrap;
        }

        .observacion-corta {
            max-width: 280px;
            line-height: 1.45;
        }

        .texto-observado {
            color: #9a3412;
            font-weight: 600;
        }

        .vacio {
            text-align: center;
            padding: 28px;
            color: var(--texto-secundario);
            font-size: 14px;
        }

        small {
            color: var(--texto-secundario);
        }

        @media (max-width: 900px) {
            .contenedor {
                padding: 20px 15px;
            }

            .barra-superior {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .tarjetas-resumen {
                grid-template-columns: repeat(2, 1fr);
            }

            table {
                font-size: 12px;
            }
        }

        @media (max-width: 520px) {
            .tarjetas-resumen {
                grid-template-columns: 1fr;
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
            <a class="btn-nueva" href="solicitud.php">Nueva solicitud</a>
            <a class="btn-cerrar" href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <h1>Mis Solicitudes</h1>
    <h2>Cartas de Presentación PPP</h2>

    <div class="nota">
        Aquí puede revisar el estado de sus solicitudes enviadas. También podrá ver si su solicitud fue registrada como Carta Simple o Carta de Presentación PPP según el cumplimiento del 80% de créditos aprobados. Si ya registró el Número de Trámite UNFV, aparecerá en la tabla. Desde esta vista también puede descargar el PDF consolidado generado.
    </div>

    <?php if ($total_observadas > 0): ?>
        <div class="nota-observado">
            Tiene <?php echo $total_observadas; ?> solicitud(es) observada(s). Revise el detalle para conocer la observación del responsable.
        </div>
    <?php endif; ?>

    <div class="tarjetas-resumen">
        <div class="tarjeta">
            <b><?php echo $total_solicitudes; ?></b>
            <span>Total</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $total_pendientes; ?></b>
            <span>Pendientes</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $total_observadas; ?></b>
            <span>Observadas</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $total_aprobadas; ?></b>
            <span>Aprobadas</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $total_rechazadas; ?></b>
            <span>Rechazadas</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Código estudiante</th>
                <th>Escuela</th>
                <th>Liquidación</th>
                <th>Tipo carta</th>
                <th>Créditos</th>
                <th>Cumple 80%</th>
                <th>Estado</th>
                <th>N° Trámite UNFV</th>
                <th>Observación</th>
                <th>Fecha solicitud</th>
                <th>Fecha revisión</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($solicitudes) > 0): ?>
                <?php foreach ($solicitudes as $fila): ?>
                    <?php 
                        $estadoClase = obtenerClaseEstado($fila["estado"]);
                        $claseFila = ($fila["estado"] === "Observado") ? "fila-observada" : "";

                        $tipoCarta = trim($fila["tipo_carta"] ?? "");

                        if ($tipoCarta === "") {
                            if (intval($fila["cumple_creditos"]) === 1) {
                                $tipoCarta = "Presentacion PPP";
                            } else {
                                $tipoCarta = "Simple";
                            }
                        }

                        $tipoClase = ($tipoCarta === "Presentacion PPP") ? "tipo-ppp" : "tipo-simple";
                        $observacion = recortarTexto($fila["observacion"] ?? "");

                        $rutaPdf = str_replace("\\", "/", $fila["archivo_pdf_consolidado"] ?? "");
                        $pdfDisponible = archivoExisteParaDescarga($rutaPdf);
                    ?>
                    <tr class="<?php echo htmlspecialchars($claseFila); ?>">
                        <td><?php echo htmlspecialchars($fila["id"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["codigo_estudiante"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["escuela"]); ?></td>
                        <td><?php echo htmlspecialchars($fila["codigo_liquidacion"]); ?></td>

                        <td>
                            <span class="tipo <?php echo htmlspecialchars($tipoClase); ?>">
                                <?php echo htmlspecialchars($tipoCarta); ?>
                            </span>
                        </td>

                        <td>
                            <b><?php echo intval($fila["total_creditos_aprobados"]); ?></b> créditos
                        </td>

                        <td>
                            <?php if (intval($fila["cumple_creditos"]) === 1): ?>
                                <span class="cumple-si">Sí cumple</span>
                            <?php else: ?>
                                <span class="cumple-no">No cumple</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="estado <?php echo htmlspecialchars($estadoClase); ?>">
                                <?php echo htmlspecialchars($fila["estado"]); ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!empty($fila["numero_tramite"])): ?>
                                <span class="nt-registrado">
                                    <?php echo htmlspecialchars($fila["numero_tramite"]); ?>
                                </span>
                                <br>
                                <small>
                                    Registrado:
                                    <?php 
                                        echo !empty($fila["fecha_registro_nt"]) 
                                            ? htmlspecialchars($fila["fecha_registro_nt"]) 
                                            : "Sin fecha"; 
                                    ?>
                                </small>
                            <?php else: ?>
                                <span class="nt-pendiente">Pendiente</span>
                            <?php endif; ?>
                        </td>

                        <td class="observacion-corta">
                            <?php if ($fila["estado"] === "Observado" && !empty($fila["observacion"])): ?>
                                <span class="texto-observado">
                                    <?php echo htmlspecialchars($observacion); ?>
                                </span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($observacion); ?>
                            <?php endif; ?>
                        </td>

                        <td><?php echo htmlspecialchars($fila["fecha_solicitud"]); ?></td>

                        <td>
                            <?php 
                                echo !empty($fila["fecha_revision"]) 
                                    ? htmlspecialchars($fila["fecha_revision"]) 
                                    : "Sin revisar"; 
                            ?>
                        </td>

                        <td>
                            <a class="btn-detalle" href="ver_mi_solicitud.php?id=<?php echo intval($fila["id"]); ?>">
                                Ver detalle
                            </a>

                            <br>

                            <?php if ($pdfDisponible): ?>
                                <a 
                                    class="btn-descargar" 
                                    href="<?php echo htmlspecialchars($rutaPdf); ?>" 
                                    target="_blank"
                                >
                                    Descargar PDF
                                </a>
                            <?php else: ?>
                                <span class="pdf-no-disponible">PDF no disponible</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="13" class="vacio">Todavía no tienes solicitudes registradas.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</body>
</html>

<?php
$conexion->close();
?>