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

function obtenerTipoCarta($tipoCarta, $cumpleCreditos) {
    $tipoCarta = trim((string) $tipoCarta);

    if ($tipoCarta !== "") {
        return $tipoCarta;
    }

    if (intval($cumpleCreditos) === 1) {
        return "Presentacion PPP";
    }

    return "Simple";
}

function textoSeguro($valor, $default = "No registrado") {
    $valor = trim((string) $valor);

    if ($valor === "") {
        return htmlspecialchars($default);
    }

    return htmlspecialchars($valor);
}

$filtro_estado = trim($_GET["estado"] ?? "");
$filtro_escuela = trim($_GET["escuela"] ?? "");
$filtro_tipo = trim($_GET["tipo_carta"] ?? "");
$busqueda = trim($_GET["buscar"] ?? "");

$condiciones = [];
$tipos = "";
$parametros = [];

if ($filtro_estado !== "") {
    $condiciones[] = "estado = ?";
    $tipos .= "s";
    $parametros[] = $filtro_estado;
}

if ($filtro_escuela !== "") {
    $condiciones[] = "escuela = ?";
    $tipos .= "s";
    $parametros[] = $filtro_escuela;
}

if ($filtro_tipo !== "") {
    $condiciones[] = "tipo_carta = ?";
    $tipos .= "s";
    $parametros[] = $filtro_tipo;
}

if ($busqueda !== "") {
    $condiciones[] = "(
        nombres LIKE ? OR
        apellidos LIKE ? OR
        dni LIKE ? OR
        codigo_estudiante LIKE ? OR
        correo_institucional LIKE ? OR
        codigo_liquidacion LIKE ? OR
        ruc_empresa LIKE ? OR
        razon_social_empresa LIKE ? OR
        numero_tramite LIKE ?
    )";

    $buscar_like = "%" . $busqueda . "%";

    for ($i = 0; $i < 9; $i++) {
        $tipos .= "s";
        $parametros[] = $buscar_like;
    }
}

$where = "";

if (count($condiciones) > 0) {
    $where = "WHERE " . implode(" AND ", $condiciones);
}

$sql = "SELECT 
            id,
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
            responsable_empresa,
            cargo_responsable,
            numero_tramite,
            fecha_registro_nt,
            estado,
            fecha_solicitud,
            fecha_revision
        FROM solicitudes_carta
        $where
        ORDER BY fecha_solicitud DESC";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error al preparar consulta: " . $conexion->error);
}

if (!empty($parametros)) {
    $stmt->bind_param($tipos, ...$parametros);
}

$stmt->execute();
$resultado = $stmt->get_result();

$solicitudes = [];

while ($fila = $resultado->fetch_assoc()) {
    $solicitudes[] = $fila;
}

$stmt->close();

/* Resumen general sin filtros */
$sql_resumen = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes,
                    SUM(CASE WHEN estado = 'Observado' THEN 1 ELSE 0 END) AS observadas,
                    SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) AS aprobadas,
                    SUM(CASE WHEN estado = 'Rechazado' THEN 1 ELSE 0 END) AS rechazadas,
                    SUM(CASE WHEN tipo_carta = 'Simple' THEN 1 ELSE 0 END) AS simples,
                    SUM(CASE WHEN tipo_carta = 'Presentacion PPP' THEN 1 ELSE 0 END) AS ppp,
                    SUM(CASE WHEN numero_tramite IS NOT NULL AND numero_tramite <> '' THEN 1 ELSE 0 END) AS nt_registrados
                FROM solicitudes_carta";

$resultado_resumen = $conexion->query($sql_resumen);
$resumen = $resultado_resumen->fetch_assoc();

$total = intval($resumen["total"] ?? 0);
$pendientes = intval($resumen["pendientes"] ?? 0);
$observadas = intval($resumen["observadas"] ?? 0);
$aprobadas = intval($resumen["aprobadas"] ?? 0);
$rechazadas = intval($resumen["rechazadas"] ?? 0);
$simples = intval($resumen["simples"] ?? 0);
$ppp = intval($resumen["ppp"] ?? 0);
$nt_registrados = intval($resumen["nt_registrados"] ?? 0);

/* Escuelas para filtro */
$sql_escuelas = "SELECT DISTINCT escuela FROM solicitudes_carta WHERE escuela IS NOT NULL AND escuela <> '' ORDER BY escuela ASC";
$resultado_escuelas = $conexion->query($sql_escuelas);

$escuelas = [];

if ($resultado_escuelas) {
    while ($fila_escuela = $resultado_escuelas->fetch_assoc()) {
        $escuelas[] = $fila_escuela["escuela"];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Ingeniero - Sistema PPP</title>
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
            max-width: 1550px;
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

        .btn-cerrar {
            background: #334155;
            color: white;
            padding: 8.5px 16px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.15s ease;
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

        .resumen {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-left: 4px solid #0284c7;
            color: #0369a1;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .tarjetas {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .tarjeta {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px 10px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.15s ease;
        }

        .tarjeta:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
        }

        .tarjeta b {
            display: block;
            font-size: 23px;
            color: var(--fiei-vino);
            font-weight: 700;
            margin-bottom: 3px;
        }

        .tarjeta span {
            font-size: 11.5px;
            color: var(--texto-secundario);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .filtros {
            background: #fafbfc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 24px;
        }

        .filtros form {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            display: block;
            margin-bottom: 5px;
        }

        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--borde-suave);
            border-radius: 7px;
            font-size: 13.5px;
            box-sizing: border-box;
            background: #ffffff;
            color: var(--texto-principal);
            font-family: inherit;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input:focus, select:focus {
            border-color: var(--fiei-vino);
            box-shadow: 0 0 0 3px rgba(114, 18, 36, 0.12);
            outline: none;
        }

        .btn-filtrar,
        .btn-limpiar {
            padding: 10px 18px;
            border-radius: 7px;
            text-decoration: none;
            border: none;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            color: white;
            text-align: center;
            height: 40px;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .btn-filtrar {
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            box-shadow: 0 2px 4px rgba(114, 18, 36, 0.2);
        }

        .btn-filtrar:hover {
            background: linear-gradient(180deg, var(--fiei-vino-hover) 0%, #4a0915 100%);
            box-shadow: 0 4px 8px rgba(114, 18, 36, 0.25);
            transform: translateY(-1px);
        }

        .btn-limpiar {
            background: #475569;
        }

        .btn-limpiar:hover {
            background: #334155;
            transform: translateY(-1px);
        }

        .info-resultados {
            font-size: 13.5px;
            margin-bottom: 12px;
            color: var(--texto-secundario);
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            min-width: 1500px;
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

        .fila-pendiente {
            background: #fffdf5;
        }

        .fila-observada {
            background: #fffbeb !important;
        }

        .estado {
            padding: 4px 8px;
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
            padding: 4px 8px;
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

        .empresa {
            font-size: 12.5px;
            color: #334155;
            line-height: 1.5;
            min-width: 180px;
        }

        .empresa b {
            color: var(--fiei-vino);
            font-weight: 600;
        }

        .nt-box {
            font-size: 12px;
            line-height: 1.5;
            min-width: 150px;
        }

        .nt-registrado {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 4px;
        }

        .nt-pendiente {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }

        .btn-ver {
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            color: white;
            padding: 6.5px 12px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
            transition: all 0.15s ease;
            box-shadow: 0 1px 3px rgba(114, 18, 36, 0.2);
        }

        .btn-ver:hover {
            background: linear-gradient(180deg, var(--fiei-vino-hover) 0%, #4a0915 100%);
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(114, 18, 36, 0.25);
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

        @media (max-width: 1350px) {
            .tarjetas {
                grid-template-columns: repeat(4, 1fr);
            }

            .filtros form {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .contenedor {
                padding: 20px 15px;
            }

            .barra-superior {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }

            .filtros form {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
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

        <a class="btn-cerrar" href="../logout.php">Cerrar sesión</a>
    </div>

    <h1>Panel del Ingeniero</h1>
    <h2>Solicitudes de Carta de Presentación PPP</h2>

    <div class="resumen">
        Desde este panel podrá visualizar las solicitudes enviadas por los estudiantes, revisar si cumplen el 80% de créditos, diferenciar entre Carta Simple y Carta de Presentación PPP, revisar el Número de Trámite UNFV y actualizar el estado de cada solicitud.
    </div>

    <div class="tarjetas">
        <div class="tarjeta">
            <b><?php echo $total; ?></b>
            <span>Total</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $pendientes; ?></b>
            <span>Pendientes</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $observadas; ?></b>
            <span>Observadas</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $aprobadas; ?></b>
            <span>Aprobadas</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $rechazadas; ?></b>
            <span>Rechazadas</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $simples; ?></b>
            <span>Cartas simples</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $ppp; ?></b>
            <span>Cartas PPP</span>
        </div>

        <div class="tarjeta">
            <b><?php echo $nt_registrados; ?></b>
            <span>NT registrados</span>
        </div>
    </div>

    <div class="filtros">
        <form method="GET" action="panel.php">
            <div>
                <label for="buscar">Buscar</label>
                <input 
                    type="text" 
                    name="buscar" 
                    id="buscar" 
                    placeholder="Nombre, DNI, código, RUC, empresa, NT..."
                    value="<?php echo htmlspecialchars($busqueda); ?>"
                >
            </div>

            <div>
                <label for="estado">Estado</label>
                <select name="estado" id="estado">
                    <option value="">Todos</option>
                    <option value="Pendiente" <?php echo $filtro_estado === "Pendiente" ? "selected" : ""; ?>>Pendiente</option>
                    <option value="Observado" <?php echo $filtro_estado === "Observado" ? "selected" : ""; ?>>Observado</option>
                    <option value="Aprobado" <?php echo $filtro_estado === "Aprobado" ? "selected" : ""; ?>>Aprobado</option>
                    <option value="Rechazado" <?php echo $filtro_estado === "Rechazado" ? "selected" : ""; ?>>Rechazado</option>
                </select>
            </div>

            <div>
                <label for="escuela">Escuela</label>
                <select name="escuela" id="escuela">
                    <option value="">Todas</option>
                    <?php foreach ($escuelas as $escuela): ?>
                        <option value="<?php echo htmlspecialchars($escuela); ?>" <?php echo $filtro_escuela === $escuela ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($escuela); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="tipo_carta">Tipo de carta</label>
                <select name="tipo_carta" id="tipo_carta">
                    <option value="">Todas</option>
                    <option value="Simple" <?php echo $filtro_tipo === "Simple" ? "selected" : ""; ?>>Simple</option>
                    <option value="Presentacion PPP" <?php echo $filtro_tipo === "Presentacion PPP" ? "selected" : ""; ?>>Presentación PPP</option>
                </select>
            </div>

            <button class="btn-filtrar" type="submit">Filtrar</button>
            <a class="btn-limpiar" href="panel.php">Limpiar</a>
        </form>
    </div>

    <div class="info-resultados">
        Mostrando <b><?php echo count($solicitudes); ?></b> solicitud(es).
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Estudiante</th>
                <th>DNI</th>
                <th>Código</th>
                <th>Escuela</th>
                <th>Liquidación</th>
                <th>Tipo carta</th>
                <th>Créditos</th>
                <th>Cumple 80%</th>
                <th>Empresa / RUC</th>
                <th>N° Trámite UNFV</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Revisión</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($solicitudes) > 0): ?>
                <?php foreach ($solicitudes as $fila): ?>
                    <?php 
                        $estadoClase = obtenerClaseEstado($fila["estado"]);
                        $tipoCarta = obtenerTipoCarta($fila["tipo_carta"] ?? "", $fila["cumple_creditos"] ?? 0);
                        $tipoClase = ($tipoCarta === "Presentacion PPP") ? "tipo-ppp" : "tipo-simple";

                        $claseFila = "";

                        if ($fila["estado"] === "Pendiente") {
                            $claseFila = "fila-pendiente";
                        }

                        if ($fila["estado"] === "Observado") {
                            $claseFila = "fila-observada";
                        }
                    ?>
                    <tr class="<?php echo htmlspecialchars($claseFila); ?>">
                        <td><?php echo intval($fila["id"]); ?></td>

                        <td>
                            <?php echo htmlspecialchars($fila["nombres"] . " " . $fila["apellidos"]); ?><br>
                            <small><?php echo htmlspecialchars($fila["correo_institucional"]); ?></small>
                        </td>

                        <td><?php echo htmlspecialchars($fila["dni"]); ?></td>
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
                            <?php if (!empty($fila["ruc_empresa"])): ?>
                                <div class="empresa">
                                    <b>RUC:</b> <?php echo htmlspecialchars($fila["ruc_empresa"]); ?><br>
                                    <b>Razón social:</b> <?php echo textoSeguro($fila["razon_social_empresa"] ?? "", "Pendiente"); ?><br>
                                    <b>Responsable:</b> <?php echo textoSeguro($fila["responsable_empresa"] ?? "", "No registrado"); ?><br>
                                    <b>Cargo:</b> <?php echo textoSeguro($fila["cargo_responsable"] ?? "", "No registrado"); ?>
                                </div>
                            <?php else: ?>
                                No aplica
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="nt-box">
                                <?php if (!empty($fila["numero_tramite"])): ?>
                                    <span class="nt-registrado">
                                        <?php echo htmlspecialchars($fila["numero_tramite"]); ?>
                                    </span><br>
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
                            </div>
                        </td>

                        <td>
                            <span class="estado <?php echo htmlspecialchars($estadoClase); ?>">
                                <?php echo htmlspecialchars($fila["estado"]); ?>
                            </span>
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
                            <a class="btn-ver" href="ver_solicitud.php?id=<?php echo intval($fila["id"]); ?>">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="15" class="vacio">No hay solicitudes registradas con los filtros seleccionados.</td>
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