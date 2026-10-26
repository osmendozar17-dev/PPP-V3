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

$nombres_sesion = $_SESSION["nombres"] ?? "";
$apellidos_sesion = $_SESSION["apellidos"] ?? "";
$dni_sesion = $_SESSION["dni"] ?? "";
$codigo_sesion = $_SESSION["codigo_estudiante"] ?? "";
$correo_sesion = $_SESSION["correo"] ?? "";
$escuela_sesion = $_SESSION["escuela"] ?? "";

$datos_creditos_malla = obtenerDatosCreditosMalla($escuela_sesion);

$total_creditos_malla = $datos_creditos_malla["total"];
$creditos_minimos_malla = $datos_creditos_malla["minimo"];
$nombre_carrera_malla = $datos_creditos_malla["carrera"];

$facultad_fiei = "FACULTAD DE INGENIERÍA ELECTRÓNICA E INFORMÁTICA";

$cursos = [];
$requisitos = [];
$cursos_aprobados_guardados = [];

$sql_cursos = "SELECT id, escuela, ciclo, codigo, nombre, creditos, tipo, grupo_electivo
               FROM cursos_malla
               WHERE escuela = ? AND activo = 1
               ORDER BY ciclo, codigo";

$stmt_cursos = $conexion->prepare($sql_cursos);
$stmt_cursos->bind_param("s", $escuela_sesion);
$stmt_cursos->execute();
$resultado_cursos = $stmt_cursos->get_result();

while ($fila = $resultado_cursos->fetch_assoc()) {
    $cursos[] = $fila;
}

$stmt_cursos->close();

$sql_requisitos = "SELECT 
                        c.codigo AS codigo_curso,
                        r.codigo_requisito
                   FROM cursos_requisitos r
                   INNER JOIN cursos_malla c ON r.curso_id = c.id
                   WHERE c.escuela = ?";

$stmt_req = $conexion->prepare($sql_requisitos);
$stmt_req->bind_param("s", $escuela_sesion);
$stmt_req->execute();
$resultado_req = $stmt_req->get_result();

while ($fila = $resultado_req->fetch_assoc()) {
    $codigoCurso = $fila["codigo_curso"];
    $codigoReq = $fila["codigo_requisito"];

    if (!isset($requisitos[$codigoCurso])) {
        $requisitos[$codigoCurso] = [];
    }

    $requisitos[$codigoCurso][] = $codigoReq;
}

$stmt_req->close();

$sql_aprobados = "SELECT c.codigo
                  FROM usuario_cursos_aprobados uca
                  INNER JOIN cursos_malla c ON uca.curso_id = c.id
                  WHERE uca.usuario_id = ?";

$stmt_aprobados = $conexion->prepare($sql_aprobados);
$stmt_aprobados->bind_param("i", $_SESSION["usuario_id"]);
$stmt_aprobados->execute();
$resultado_aprobados = $stmt_aprobados->get_result();

while ($fila = $resultado_aprobados->fetch_assoc()) {
    $cursos_aprobados_guardados[] = $fila["codigo"];
}

$stmt_aprobados->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Carta de Presentación PPP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
            width: 100%;
            max-width: 1100px;
            margin: 15px auto;
            background: var(--blanco);
            padding: 34px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.07), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
            border-top: 5px solid var(--fiei-vino);
        }

        .barra-superior {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
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
            padding: 8px 15px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            display: inline-block;
            margin-left: 6px;
            transition: all 0.15s ease;
        }

        .btn-cerrar:hover {
            background: #1e293b;
            color: white;
            transform: translateY(-1px);
        }

        .btn-cerrar[href*="mis_solicitudes"] {
            background: var(--fiei-vino);
        }

        .btn-cerrar[href*="mis_solicitudes"]:hover {
            background: var(--fiei-vino-hover);
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

        h3 {
            color: var(--fiei-vino);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.2px;
        }

        .grupo {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 13.5px;
            margin-bottom: 6px;
            color: #334155;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1.5px solid var(--borde-suave);
            border-radius: 7px;
            font-size: 14.5px;
            box-sizing: border-box;
            font-family: inherit;
            color: var(--texto-principal);
            background: #ffffff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--fiei-vino);
            box-shadow: 0 0 0 3px rgba(114, 18, 36, 0.12);
            outline: none;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        input[readonly], textarea[readonly] {
            background: #f8fafc;
            color: #64748b;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }

        input[type="file"] {
            background: #ffffff;
            cursor: pointer;
            padding: 8px 12px;
        }

        .fila {
            display: flex;
            gap: 16px;
        }

        .fila .grupo {
            flex: 1;
        }

        .nota {
            background: var(--unfv-naranja-soft);
            color: #9a3412;
            border: 1px solid var(--unfv-naranja-border);
            border-left: 4px solid var(--unfv-naranja);
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .nota-datos {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
            border-left: 4px solid #0284c7;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .seccion-fut,
        .seccion-cursos,
        .seccion-adjuntos {
            margin-top: 26px;
            margin-bottom: 26px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fafbfc;
        }

        .seccion-fut h3,
        .seccion-cursos h3,
        .seccion-adjuntos h3 {
            color: var(--fiei-vino);
            margin-top: 0;
            margin-bottom: 16px;
            text-align: center;
            font-size: 18px;
            font-weight: 700;
        }

        .descripcion-cursos {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 13.5px;
            line-height: 1.45;
        }

        .sin-cursos {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px;
            border-radius: 7px;
            text-align: center;
            font-size: 14px;
        }

        .contenedor-ciclos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 15px;
        }

        .tarjeta-ciclo {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .tarjeta-ciclo:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.08);
            border-color: var(--fiei-vino);
        }

        .tarjeta-ciclo h4 {
            margin: 0 0 8px 0;
            color: var(--fiei-vino);
            font-size: 16.5px;
            font-weight: 700;
        }

        .tarjeta-ciclo p {
            margin: 5px 0;
            color: #475569;
            font-size: 13.5px;
        }

        .creditos-ciclo {
            background: var(--unfv-naranja-soft);
            border: 1px solid var(--unfv-naranja-border);
            color: #9a3412;
            padding: 7px 10px;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 10px;
            font-size: 13px;
        }

        .resumen-creditos {
            background: var(--unfv-naranja-soft);
            border: 1px solid var(--unfv-naranja-border);
            color: #9a3412;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            text-align: center;
            font-size: 14.5px;
        }

        .aviso-creditos {
            display: none;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 14px 16px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .aviso-creditos b {
            color: #991b1b;
        }

        .seccion-empresa {
            display: none;
            margin-top: 24px;
            margin-bottom: 26px;
            padding: 24px;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            background: #f8fbff;
        }

        .seccion-empresa h3 {
            color: var(--fiei-vino);
            margin-top: 0;
            margin-bottom: 18px;
            text-align: center;
            font-size: 18px;
            font-weight: 700;
        }

        .estado-ruc-info {
            background: var(--unfv-naranja-soft);
            color: #9a3412;
            border: 1px solid var(--unfv-naranja-border);
            padding: 11px 14px;
            border-radius: 7px;
            margin-bottom: 16px;
            font-size: 13.5px;
            display: none;
        }

        .modal-cursos {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background: rgba(15, 23, 42, 0.65);
            padding: 25px 0;
            backdrop-filter: blur(2px);
        }

        .modal-contenido {
            background: #ffffff;
            width: 90%;
            max-width: 850px;
            margin: 20px auto;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .modal-header {
            background: var(--fiei-vino);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 19px;
            font-weight: 700;
            color: white;
        }

        .btn-cerrar-modal {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 34px;
            height: 34px;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
            line-height: 32px;
            padding: 0;
            transition: all 0.15s ease;
        }

        .btn-cerrar-modal:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .curso-item {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 12px;
            background: #ffffff;
            transition: border-color 0.15s ease;
        }

        .curso-item:hover {
            border-color: #cbd5e1;
        }

        .curso-item.bloqueado {
            background: #f1f5f9;
            color: #94a3b8;
            opacity: 0.85;
            border-color: #e2e8f0;
        }

        .curso-item.electivo {
            border-left: 4px solid var(--fiei-vino);
        }

        .curso-nombre {
            font-weight: 600;
            font-size: 14.5px;
            margin-bottom: 5px;
            color: #1e293b;
        }

        .curso-detalle {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .curso-item.bloqueado .curso-detalle {
            color: #94a3b8;
        }

        .badge-electivo {
            display: inline-block;
            background: var(--unfv-naranja-soft);
            color: #9a3412;
            border: 1px solid var(--unfv-naranja-border);
            padding: 2px 7px;
            border-radius: 5px;
            font-size: 11.5px;
            margin-left: 6px;
            font-weight: 600;
        }

        .acciones-curso {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 13.5px;
        }

        .acciones-curso label {
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0;
            cursor: pointer;
            color: #334155;
        }

        .acciones-curso input {
            width: auto;
            margin-bottom: 0;
            accent-color: var(--fiei-vino);
        }

        .resumen-modal {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 11px 14px;
            border-radius: 7px;
            margin-bottom: 16px;
            font-size: 13.5px;
            font-weight: 600;
        }

        .archivo-contenedor {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .archivo-contenedor input[type="file"] {
            flex: 1;
        }

        .check-archivo {
            display: none;
            color: #059669;
            font-size: 22px;
            font-weight: bold;
        }

        .check-archivo.activo {
            display: inline-block;
        }

        button {
            width: 100%;
            padding: 13.5px;
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            color: white;
            border: none;
            font-size: 16px;
            border-radius: 8px;
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

        .pie {
            text-align: center;
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: var(--texto-secundario);
        }

        .inputs-cursos-aprobados {
            display: none;
        }

        @media (max-width: 768px) {
            .contenedor {
                padding: 22px 16px;
                margin: 10px auto;
            }

            .fila {
                flex-direction: column;
                gap: 0;
            }

            .barra-superior {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .archivo-contenedor {
                align-items: flex-start;
            }

            .acciones-curso {
                flex-direction: column;
                gap: 8px;
            }

            .modal-contenido {
                width: 95%;
                margin: 10px auto;
            }
        }
    </style>
</head>
<body>

<div class="contenedor">

    <div class="barra-superior">
        <div class="usuario">
            Sesión activa:
            <b><?php echo htmlspecialchars($nombres_sesion . " " . $apellidos_sesion); ?></b>
        </div>
        <div>
            <a class="btn-cerrar" href="mis_solicitudes.php">Mis solicitudes</a>
            <a class="btn-cerrar" href="logout.php">Cerrar sesión</a>
        </div>
    </div>

    <h1>Solicitud de Carta de Presentación</h1>
    <h2>Prácticas Preprofesionales - FIEI UNFV</h2>

    <div class="nota-datos">
        Los datos personales y académicos se cargan automáticamente según el registro del estudiante.
        Si algún dato no es correcto, cierre sesión y comuníquese con el responsable del sistema.
    </div>

    <div class="nota">
        Complete el Formulario Único de Trámite, seleccione sus cursos aprobados y adjunte la foto tamaño carnet, voucher de pago y récord académico.
        El sistema generará automáticamente el FUT, la ficha de datos de empresa si corresponde y el PDF consolidado final.
    </div>

    <form action="guardar_solicitud.php" method="POST" enctype="multipart/form-data" onsubmit="return validarEnvioSolicitud();">

        <div class="fila">
            <div class="grupo">
                <label for="nombres">Nombres</label>
                <input type="text" name="nombres" id="nombres"
                       value="<?php echo htmlspecialchars($nombres_sesion); ?>" readonly required>
            </div>

            <div class="grupo">
                <label for="apellidos">Apellidos</label>
                <input type="text" name="apellidos" id="apellidos"
                       value="<?php echo htmlspecialchars($apellidos_sesion); ?>" readonly required>
            </div>
        </div>

        <div class="fila">
            <div class="grupo">
                <label for="dni">DNI</label>
                <input type="text" name="dni" id="dni" maxlength="8"
                       value="<?php echo htmlspecialchars($dni_sesion); ?>" readonly required>
            </div>

            <div class="grupo">
                <label for="codigo_estudiante">Código de estudiante</label>
                <input type="text" name="codigo_estudiante" id="codigo_estudiante"
                       value="<?php echo htmlspecialchars($codigo_sesion); ?>" readonly required>
            </div>
        </div>

        <div class="grupo">
            <label for="correo_institucional">Correo institucional</label>
            <input type="email" name="correo_institucional" id="correo_institucional"
                   value="<?php echo htmlspecialchars($correo_sesion); ?>" readonly required>
        </div>

        <div class="grupo">
            <label for="correo_personal">Correo de respaldo (Personal - Opcional)</label>
            <input type="email" name="correo_personal" id="correo_personal"
                   value="<?php echo htmlspecialchars($_SESSION["correo_personal"] ?? ""); ?>"
                   placeholder="ejemplo@gmail.com">
        </div>

        <div class="grupo">
            <label for="escuela">Escuela profesional</label>
            <input type="text" name="escuela" id="escuela"
                   value="<?php echo htmlspecialchars($escuela_sesion); ?>" readonly required>
        </div>

        <div class="grupo">
            <label for="codigo_liquidacion">Código de liquidación</label>
            <input type="text" name="codigo_liquidacion" id="codigo_liquidacion" required>
        </div>

        <div class="grupo">
            <label for="duracion_meses">Duración de las prácticas</label>
            <select name="duracion_meses" id="duracion_meses" required>
                <option value="1">1 Mes</option>
                <option value="2">2 Meses</option>
                <option value="3">3 Meses</option>
                <option value="4">4 Meses</option>
                <option value="5">5 Meses</option>
                <option value="6" selected>6 Meses</option>
            </select>
        </div>

        <div class="fila">
            <div class="grupo">
                <label for="fecha_inicio_practicas">Fecha de inicio de prácticas</label>
                <input type="date" name="fecha_inicio_practicas" id="fecha_inicio_practicas" required>
            </div>

            <div class="grupo">
                <label for="fecha_fin_practicas">Fecha de término de prácticas</label>
                <input type="date" name="fecha_fin_practicas" id="fecha_fin_practicas" required>
            </div>
        </div>

        <div class="seccion-fut">
            <h3>Formulario Único de Trámite - FUT</h3>

            <div class="descripcion-cursos">
                Esta sección reemplaza al archivo FUT adjuntado. El sistema generará automáticamente el FUT en PDF con los datos registrados.
            </div>

            <div class="grupo">
                <label for="fut_facultad">Facultad</label>
                <input type="text" name="fut_facultad" id="fut_facultad"
                       value="<?php echo htmlspecialchars($facultad_fiei); ?>" readonly required>
            </div>

            <div class="grupo">
                <label>Apellidos y nombres</label>
                <input type="text"
                       value="<?php echo htmlspecialchars($apellidos_sesion . " " . $nombres_sesion); ?>" readonly>
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="fut_tipo_programa">Tipo de programa</label>
                    <select name="fut_tipo_programa" id="fut_tipo_programa" required>
                        <option value="">Seleccione una opción</option>
                        <option value="Pregrado">Pregrado</option>
                        <option value="Segunda Especialidad">Segunda Especialidad</option>
                    </select>
                </div>

                <div class="grupo">
                    <label for="fut_tramite">Trámite</label>
                    <select name="fut_tramite" id="fut_tramite" required>
                        <option value="">Primero seleccione el tipo de programa</option>
                    </select>
                </div>
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="fut_dependencia">Dependencia de la facultad a quien se dirige</label>
                    <input type="text" name="fut_dependencia" id="fut_dependencia"
                           placeholder="Ejemplo: Oficina de Tutoría y Prácticas Preprofesionales" required>
                </div>

                <div class="grupo">
                    <label for="fut_tipo_solicitante">Tipo de solicitante</label>
                    <select name="fut_tipo_solicitante" id="fut_tipo_solicitante" required>
                        <option value="">Seleccione una opción</option>
                        <option value="Estudiante">Estudiante</option>
                        <option value="Egresado">Egresado</option>
                        <option value="Apoderado">Apoderado</option>
                    </select>
                </div>
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="fut_direccion">Dirección domiciliaria</label>
                    <input type="text" name="fut_direccion" id="fut_direccion"
                           placeholder="Av. / Jr. / Calle / Mz. / Lt. / Urb. / N° / Dpto." required>
                </div>

                <div class="grupo">
                    <label for="fut_distrito">Distrito</label>
                    <input type="text" name="fut_distrito" id="fut_distrito" required>
                </div>
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="fut_telefono_fijo">Teléfono fijo</label>
                    <input type="text" name="fut_telefono_fijo" id="fut_telefono_fijo">
                </div>

                <div class="grupo">
                    <label for="fut_celular">Celular</label>
                    <input type="text" name="fut_celular" id="fut_celular" required>
                </div>

                <div class="grupo">
                    <label for="fut_correo">Correo electrónico para FUT</label>
                    <input type="email" name="fut_correo" id="fut_correo"
                           value="<?php echo htmlspecialchars($correo_sesion); ?>" required>
                </div>
            </div>

            <div class="grupo">
                <label for="fut_fundamentacion">Fundamentación de la solicitud</label>
                <textarea name="fut_fundamentacion" id="fut_fundamentacion" required
                          placeholder="Explique brevemente el motivo de su solicitud."></textarea>
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="fut_documentos_adjuntos">Documentos que se adjunta</label>
                    <textarea name="fut_documentos_adjuntos" id="fut_documentos_adjuntos" required>Foto tamaño carnet
Voucher de pago
Récord académico</textarea>
                </div>

                <div class="grupo">
                    <label for="fut_total_folios">Total de folios</label>
                    <input type="number" name="fut_total_folios" id="fut_total_folios" min="1" value="3" required>
                </div>
            </div>

            <div class="grupo">
                <label for="fut_lugar_fecha">Lugar y fecha</label>
                <input type="text" name="fut_lugar_fecha" id="fut_lugar_fecha"
                       placeholder="Ejemplo: Lima, 07 de junio de 2026" required>
            </div>
        </div>

        <div class="seccion-cursos">
            <h3>Cursos según malla curricular</h3>

            <div class="descripcion-cursos">
                Seleccione un ciclo para abrir sus cursos en una ventana emergente dentro de la página.
                Marque únicamente los cursos que ya tiene aprobados. Los créditos aprobados se mostrarán por ciclo y también en el total general.
                Los cursos electivos pueden marcarse individualmente; puede seleccionar uno o ambos si los aprobó y cumple sus requisitos.
            </div>

            <div id="contenedor-ciclos" class="contenedor-ciclos"></div>

            <div class="resumen-creditos">
                Total de créditos aprobados: <span id="total-creditos">0</span>
            </div>

            <div id="inputs-cursos-aprobados" class="inputs-cursos-aprobados"></div>

            <input type="hidden" name="total_creditos_aprobados" id="total_creditos_aprobados" value="0">
            <input type="hidden" name="cumple_creditos" id="cumple_creditos" value="0">
            <input type="hidden" name="tipo_carta" id="tipo_carta" value="Simple">

            <div id="aviso-creditos" class="aviso-creditos">
                <b>No cumple con el requisito mínimo de créditos.</b><br>
                Para solicitar la Carta de Presentación de Prácticas Preprofesionales debe contar como mínimo con el 80% de créditos aprobados.
                En esta malla de <b><?php echo htmlspecialchars($nombre_carrera_malla); ?></b>, el mínimo requerido es 
                <b><?php echo intval($creditos_minimos_malla); ?> créditos</b> 
                de un total de 
                <b><?php echo intval($total_creditos_malla); ?> créditos</b>.
                <br><br>
                Puede continuar, pero el sistema registrará la solicitud como <b>Carta Simple</b>.
            </div>
        </div>

        <div id="seccion-empresa" class="seccion-empresa">
            <h3>Datos de la empresa</h3>

            <div class="descripcion-cursos">
                Esta sección se habilita únicamente si cumple con el 80% de créditos aprobados.
                El sistema generará automáticamente la ficha de datos de empresa en PDF.
            </div>

            <div class="fila">
                <div class="grupo">
                    <label>Nombre del alumno</label>
                    <input type="text"
                           value="<?php echo htmlspecialchars($nombres_sesion . " " . $apellidos_sesion); ?>" readonly>
                </div>

                <div class="grupo">
                    <label>Código del alumno</label>
                    <input type="text"
                           value="<?php echo htmlspecialchars($codigo_sesion); ?>" readonly>
                </div>
            </div>

            <div id="estado-ruc-info" class="estado-ruc-info">
                Primero se hará una validación básica del RUC. Luego conectaremos la consulta automática con una API.
            </div>

            <div class="grupo">
                <label for="ruc_empresa">RUC de la empresa</label>
                <input 
                    type="text" 
                    name="ruc_empresa" 
                    id="ruc_empresa" 
                    maxlength="11" 
                    placeholder="Ejemplo: 20123456789"
                >
            </div>

            <div class="grupo">
                <label for="razon_social_empresa">Razón social / Nombre de empresa</label>
                <input 
                    type="text" 
                    name="razon_social_empresa" 
                    id="razon_social_empresa" 
                    readonly
                >
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="estado_ruc">Estado del RUC</label>
                    <input 
                        type="text" 
                        name="estado_ruc" 
                        id="estado_ruc" 
                        readonly
                    >
                </div>

                <div class="grupo">
                    <label for="condicion_ruc">Condición del RUC</label>
                    <input 
                        type="text" 
                        name="condicion_ruc" 
                        id="condicion_ruc" 
                        readonly
                    >
                </div>
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="responsable_empresa">Representante de la empresa</label>
                    <input 
                        type="text" 
                        name="responsable_empresa" 
                        id="responsable_empresa" 
                        placeholder="Ejemplo: Juan Pérez Ramírez"
                    >
                </div>

                <div class="grupo">
                    <label for="cargo_responsable">Cargo del representante</label>
                    <input 
                        type="text" 
                        name="cargo_responsable" 
                        id="cargo_responsable" 
                        placeholder="Ejemplo: Jefe de Recursos Humanos"
                    >
                </div>
            </div>

            <div class="fila">
                <div class="grupo">
                    <label for="representante_dni">DNI del representante de la empresa</label>
                    <input 
                        type="text" 
                        name="representante_dni" 
                        id="representante_dni" 
                        maxlength="8"
                        placeholder="Ejemplo: 12345678"
                    >
                </div>

                <div class="grupo">
                    <label for="representante_telefono">Teléfono del representante</label>
                    <input 
                        type="text" 
                        name="representante_telefono" 
                        id="representante_telefono"
                        placeholder="Ejemplo: 987654321"
                    >
                </div>
            </div>

            <div class="grupo">
                <label for="representante_email">Email del representante</label>
                <input 
                    type="email" 
                    name="representante_email" 
                    id="representante_email"
                    placeholder="Ejemplo: representante@empresa.com"
                >
            </div>

            <div class="grupo">
                <label for="direccion_empresa">Dirección de la empresa</label>
                <input 
                    type="text" 
                    name="direccion_empresa" 
                    id="direccion_empresa"
                    placeholder="Ejemplo: Av. Principal 123"
                >
            </div>

            <div class="grupo">
                <label for="ubicacion_empresa">DPTO. / PROV. / DISTRITO</label>
                <input 
                    type="text" 
                    name="ubicacion_empresa" 
                    id="ubicacion_empresa"
                    placeholder="Ejemplo: Lima / Lima / San Isidro"
                >
            </div>
        </div>

        <div class="seccion-adjuntos">
            <h3>Documentos adjuntos</h3>

            <div class="descripcion-cursos">
                El FUT ya no se adjunta manualmente. El sistema lo generará automáticamente en PDF.
                Ahora debe adjuntar foto tamaño carnet, voucher de pago y récord académico. La ficha de matrícula es opcional.
            </div>

            <div class="grupo">
                <label for="archivo_foto_carnet">Adjuntar foto tamaño carnet</label>
                <div class="archivo-contenedor">
                    <input type="file" name="archivo_foto_carnet" id="archivo_foto_carnet"
                           accept=".jpg,.jpeg,.png" required>
                    <span class="check-archivo" id="check_foto_carnet">✓</span>
                </div>
            </div>

            <div class="grupo">
                <label for="archivo_voucher">Adjuntar voucher de pago</label>
                <div class="archivo-contenedor">
                    <input type="file" name="archivo_voucher" id="archivo_voucher"
                           accept=".pdf,.jpg,.jpeg,.png" required>
                    <span class="check-archivo" id="check_voucher">✓</span>
                </div>
            </div>

            <div class="grupo">
                <label for="archivo_record">Adjuntar récord académico</label>
                <div class="archivo-contenedor">
                    <input type="file" name="archivo_record" id="archivo_record"
                           accept=".pdf,.jpg,.jpeg,.png" required>
                    <span class="check-archivo" id="check_record">✓</span>
                </div>
            </div>

            <div class="grupo">
                <label for="archivo_matricula">Adjuntar ficha de matrícula (Opcional)</label>
                <div class="archivo-contenedor">
                    <input type="file" name="archivo_matricula" id="archivo_matricula"
                           accept=".pdf,.jpg,.jpeg,.png">
                    <span class="check-archivo" id="check_matricula">✓</span>
                </div>
            </div>
        </div>

        <button type="submit">Enviar solicitud</button>
    </form>

    <div class="pie">
        Sistema de Gestión de Cartas de Presentación de Prácticas Preprofesionales
    </div>
</div>

<div id="modal-cursos" class="modal-cursos">
    <div class="modal-contenido">
        <div class="modal-header">
            <h3 id="modal-titulo">Cursos del ciclo</h3>
            <button type="button" class="btn-cerrar-modal" onclick="cerrarModalCursos()">×</button>
        </div>
        <div class="modal-body">
            <div id="modal-resumen-ciclo" class="resumen-modal"></div>
            <div id="modal-lista-cursos"></div>
        </div>
    </div>
</div>

<script>
    const cursosMalla = <?php echo json_encode($cursos, JSON_UNESCAPED_UNICODE); ?>;
    const requisitosCursos = <?php echo json_encode($requisitos, JSON_UNESCAPED_UNICODE); ?>;
    const cursosAprobadosGuardados = <?php echo json_encode($cursos_aprobados_guardados, JSON_UNESCAPED_UNICODE); ?>;

    let cursosAprobados = {};
    let cicloActualModal = null;
    let rucValidadoCorrectamente = false;

    const TOTAL_CREDITOS_MALLA = <?php echo intval($total_creditos_malla); ?>;
    const CREDITOS_MINIMOS = <?php echo intval($creditos_minimos_malla); ?>;

    const futTipoPrograma = document.getElementById("fut_tipo_programa");
    const futTramite = document.getElementById("fut_tramite");

    const tramitesPregrado = [
        "1. Boleta de notas",
        "2. Carta de presentación Práctica pre profesional",
        "3. Constancia de egresado",
        "4. Constancia de estudios",
        "5. Constancia de horas globales y crédito",
        "6. Constancia de matrícula",
        "7. Constancia de notas",
        "8. Constancia de notas de cursos de actualización",
        "9. Constancia de orden de mérito",
        "10. Convalidación de asignatura",
        "11. Duplicado carné de biblioteca especializada",
        "12. Examen de aplazado",
        "13. Matrícula extemporánea",
        "14. Obtención de grado de bachiller",
        "15. Obtención de título profesional",
        "16. Reactualización de matrícula",
        "17. Rectificación de matrícula",
        "18. Regularización académica",
        "19. Reprocesamiento de prácticas",
        "20. Reserva de matrícula",
        "21. Resolución de expedito para optar título profesional",
        "22. Retiro de matrícula por asignatura",
        "23. Subsanación de asignatura",
        "24. Tabla de equivalencias (sin costo)",
        "25. Curso de adelanto de asignatura",
        "26. Derecho de obtención de especialista",
        "27. Nivelación de asignatura",
        "28. Duplicado de diploma de grado o título",
        "29. Constancia de curso de actualización",
        "30. Constancia de práctica pre profesionales",
        "31. Constancia de tercio superior",
        "32. Plan de estudios",
        "33. Otros"
    ];

    const tramitesSegundaEspecialidad = [
        "34. Constancia de ingreso",
        "35. Constancia de estudios",
        "36. Constancia de matrícula",
        "37. Constancia de notas",
        "38. Constancia de orden de méritos",
        "39. Reprocesamiento de acta (sin costo)",
        "40. Reserva de matrícula",
        "41. Resolución de expedito para título profesional",
        "42. Retiro de matrícula",
        "43. Constancia de curso de actualización",
        "44. Curso de actualización",
        "45. Examen de aplazado por módulo",
        "46. Carpeta de inscripción",
        "47. Derecho de inscripción / matrícula",
        "48. Derecho académico (pensión total)",
        "49. Derecho de diploma",
        "50. Examen de aplazado por módulo",
        "51. Reserva de inscripción/matrícula",
        "52. Retiro de matrícula",
        "53. Constancia de egresado",
        "54. Constancia de estudios",
        "55. Carpeta de admisión",
        "56. Inscripción admisión segunda especialidad",
        "57. Derecho académico (pensión semestral)",
        "58. Derecho de examen de subsanación",
        "59. Derecho de matrícula semestral",
        "60. Matrícula extemporánea",
        "61. Reactualización de matrícula",
        "62. Certificado de estudios (por año)",
        "63. Aprobación de plan de tesis",
        "64. Asesoría de plan de tesis",
        "65. Asesoría de tesis",
        "66. Derecho de diploma",
        "67. Derecho de obtención del título de especialista",
        "68. Examen sustentación / Suficiencia título profesional",
        "69. Nombramiento de jurado de tesis",
        "70. Curso Taller de tesis para obtención del título profesional",
        "71. Derecho de asignatura convalidada",
        "72. Traslado de especialidad"
    ];

    function cargarTramitesFut() {
        const tipo = futTipoPrograma.value;

        futTramite.innerHTML = "";

        const opcionInicial = document.createElement("option");
        opcionInicial.value = "";
        opcionInicial.textContent = "Seleccione un trámite";
        futTramite.appendChild(opcionInicial);

        let listaTramites = [];

        if (tipo === "Pregrado") {
            listaTramites = tramitesPregrado;
        } else if (tipo === "Segunda Especialidad") {
            listaTramites = tramitesSegundaEspecialidad;
        } else {
            opcionInicial.textContent = "Primero seleccione el tipo de programa";
            return;
        }

        listaTramites.forEach(tramite => {
            const option = document.createElement("option");
            option.value = tramite;
            option.textContent = tramite;
            futTramite.appendChild(option);
        });
    }

    cursosAprobadosGuardados.forEach(codigo => {
        cursosAprobados[codigo] = true;
    });

    function activarCheckArchivo(inputId, checkId) {
        const input = document.getElementById(inputId);
        const check = document.getElementById(checkId);

        input.addEventListener("change", function () {
            if (input.files.length > 0) {
                check.classList.add("activo");
            } else {
                check.classList.remove("activo");
            }
        });
    }

    activarCheckArchivo("archivo_foto_carnet", "check_foto_carnet");
    activarCheckArchivo("archivo_voucher", "check_voucher");
    activarCheckArchivo("archivo_record", "check_record");
    activarCheckArchivo("archivo_matricula", "check_matricula");

    const contenedorCiclos = document.getElementById("contenedor-ciclos");
    const totalCreditosSpan = document.getElementById("total-creditos");
    const modalCursos = document.getElementById("modal-cursos");
    const modalTitulo = document.getElementById("modal-titulo");
    const modalListaCursos = document.getElementById("modal-lista-cursos");
    const modalResumenCiclo = document.getElementById("modal-resumen-ciclo");
    const inputsCursosAprobados = document.getElementById("inputs-cursos-aprobados");

    const avisoCreditos = document.getElementById("aviso-creditos");
    const seccionEmpresa = document.getElementById("seccion-empresa");

    const inputTotalCreditos = document.getElementById("total_creditos_aprobados");
    const inputCumpleCreditos = document.getElementById("cumple_creditos");
    const inputTipoCarta = document.getElementById("tipo_carta");

    const rucEmpresa = document.getElementById("ruc_empresa");
    const razonSocialEmpresa = document.getElementById("razon_social_empresa");
    const estadoRuc = document.getElementById("estado_ruc");
    const condicionRuc = document.getElementById("condicion_ruc");
    const responsableEmpresa = document.getElementById("responsable_empresa");
    const cargoResponsable = document.getElementById("cargo_responsable");
    const representanteDni = document.getElementById("representante_dni");
    const representanteTelefono = document.getElementById("representante_telefono");
    const representanteEmail = document.getElementById("representante_email");
    const direccionEmpresa = document.getElementById("direccion_empresa");
    const ubicacionEmpresa = document.getElementById("ubicacion_empresa");
    const estadoRucInfo = document.getElementById("estado-ruc-info");

    function obtenerCodigosAprobados() {
        return Object.keys(cursosAprobados).filter(codigo => cursosAprobados[codigo] === true);
    }

    function requisitosCumplidos(curso, aprobados) {
        const requisitos = requisitosCursos[curso.codigo] || [];

        if (requisitos.length === 0) {
            return true;
        }

        return requisitos.every(req => aprobados.includes(req));
    }

    function agruparCursosPorCiclo() {
        const cursosPorCiclo = {};

        cursosMalla.forEach(curso => {
            if (!cursosPorCiclo[curso.ciclo]) {
                cursosPorCiclo[curso.ciclo] = [];
            }

            cursosPorCiclo[curso.ciclo].push(curso);
        });

        return cursosPorCiclo;
    }

    function calcularCreditosCiclo(ciclo) {
        let total = 0;

        cursosMalla.forEach(curso => {
            if (parseInt(curso.ciclo) === parseInt(ciclo) && cursosAprobados[curso.codigo] === true) {
                total += parseInt(curso.creditos);
            }
        });

        return total;
    }

    function calcularCantidadAprobadosCiclo(ciclo) {
        let total = 0;

        cursosMalla.forEach(curso => {
            if (parseInt(curso.ciclo) === parseInt(ciclo) && cursosAprobados[curso.codigo] === true) {
                total++;
            }
        });

        return total;
    }

    function calcularTotalCreditosAprobados() {
        let total = 0;

        cursosMalla.forEach(curso => {
            if (cursosAprobados[curso.codigo] === true) {
                total += parseInt(curso.creditos);
            }
        });

        return total;
    }

    function actualizarCursoAprobado(codigo, aprobado) {
        if (aprobado) {
            cursosAprobados[codigo] = true;
        } else {
            delete cursosAprobados[codigo];
        }

        limpiarCursosDependientesBloqueados();
        actualizarVistas();
    }

    function limpiarCursosDependientesBloqueados() {
        let huboCambios = true;

        while (huboCambios) {
            huboCambios = false;
            const aprobados = obtenerCodigosAprobados();

            cursosMalla.forEach(curso => {
                const habilitado = requisitosCumplidos(curso, aprobados);

                if (!habilitado && cursosAprobados[curso.codigo]) {
                    delete cursosAprobados[curso.codigo];
                    huboCambios = true;
                }
            });
        }
    }

    function actualizarInputsOcultos() {
        inputsCursosAprobados.innerHTML = "";

        cursosMalla.forEach(curso => {
            if (cursosAprobados[curso.codigo] === true) {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "cursos_aprobados[]";
                input.value = curso.id;
                inputsCursosAprobados.appendChild(input);
            }
        });
    }

    function actualizarSeccionEmpresa() {
        const totalCreditos = calcularTotalCreditosAprobados();

        inputTotalCreditos.value = totalCreditos;

        if (totalCreditos >= CREDITOS_MINIMOS) {
            inputCumpleCreditos.value = "1";
            inputTipoCarta.value = "Presentacion PPP";

            avisoCreditos.style.display = "none";
            seccionEmpresa.style.display = "block";

            rucEmpresa.required = true;
            responsableEmpresa.required = true;
            cargoResponsable.required = true;
            representanteDni.required = true;
            representanteTelefono.required = true;
            representanteEmail.required = true;
            direccionEmpresa.required = true;
            ubicacionEmpresa.required = true;
        } else {
            inputCumpleCreditos.value = "0";
            inputTipoCarta.value = "Simple";

            avisoCreditos.style.display = "block";
            seccionEmpresa.style.display = "none";

            rucEmpresa.required = false;
            responsableEmpresa.required = false;
            cargoResponsable.required = false;
            representanteDni.required = false;
            representanteTelefono.required = false;
            representanteEmail.required = false;
            direccionEmpresa.required = false;
            ubicacionEmpresa.required = false;

            rucEmpresa.value = "";
            razonSocialEmpresa.value = "";
            estadoRuc.value = "";
            condicionRuc.value = "";
            responsableEmpresa.value = "";
            cargoResponsable.value = "";
            representanteDni.value = "";
            representanteTelefono.value = "";
            representanteEmail.value = "";
            direccionEmpresa.value = "";
            ubicacionEmpresa.value = "";
            estadoRucInfo.style.display = "none";
        }
    }

    function renderizarCiclos() {
        contenedorCiclos.innerHTML = "";

        if (cursosMalla.length === 0) {
            contenedorCiclos.innerHTML = `
                <div class="sin-cursos">
                    No se encontraron cursos registrados para la escuela:
                    <b><?php echo htmlspecialchars($escuela_sesion); ?></b>.
                    Verifique que la escuela del registro coincida con la escuela cargada en cursos_malla.
                </div>
            `;
            totalCreditosSpan.textContent = 0;
            return;
        }

        const cursosPorCiclo = agruparCursosPorCiclo();

        Object.keys(cursosPorCiclo).sort((a, b) => a - b).forEach(ciclo => {
            const totalCursos = cursosPorCiclo[ciclo].length;
            const aprobadosCiclo = calcularCantidadAprobadosCiclo(ciclo);
            const creditosCiclo = calcularCreditosCiclo(ciclo);

            const tarjeta = document.createElement("div");
            tarjeta.className = "tarjeta-ciclo";
            tarjeta.onclick = function () {
                abrirModalCursos(ciclo);
            };

            tarjeta.innerHTML = `
                <h4>${ciclo}° Ciclo</h4>
                <p>Cursos aprobados: <b>${aprobadosCiclo}</b> de ${totalCursos}</p>
                <div class="creditos-ciclo">
                    Créditos aprobados del ciclo: ${creditosCiclo}
                </div>
            `;

            contenedorCiclos.appendChild(tarjeta);
        });
    }

    function abrirModalCursos(ciclo) {
        cicloActualModal = ciclo;

        modalTitulo.textContent = "Cursos del " + ciclo + "° Ciclo";
        modalCursos.style.display = "block";

        renderizarCursosDelModal(ciclo);
    }

    function cerrarModalCursos() {
        modalCursos.style.display = "none";
        cicloActualModal = null;
        actualizarVistas();
    }

    function renderizarCursosDelModal(ciclo) {
        const aprobados = obtenerCodigosAprobados();
        const cursosCiclo = cursosMalla.filter(curso => parseInt(curso.ciclo) === parseInt(ciclo));

        modalListaCursos.innerHTML = "";

        modalResumenCiclo.textContent =
            "Créditos aprobados en este ciclo: " + calcularCreditosCiclo(ciclo) +
            " | Cursos aprobados: " + calcularCantidadAprobadosCiclo(ciclo) +
            " de " + cursosCiclo.length;

        cursosCiclo.forEach(curso => {
            const estaAprobado = cursosAprobados[curso.codigo] === true;
            const habilitado = requisitosCumplidos(curso, aprobados);
            const bloqueado = !habilitado;
            const requisitos = requisitosCursos[curso.codigo] || [];
            const esElectivo = curso.tipo === "Electivo";

            const item = document.createElement("div");
            item.className = "curso-item" + (bloqueado ? " bloqueado" : "") + (esElectivo ? " electivo" : "");

            const nombre = document.createElement("div");
            nombre.className = "curso-nombre";

            let badgeElectivo = "";

            if (esElectivo) {
                badgeElectivo = `<span class="badge-electivo">${curso.grupo_electivo || "Electivo"}</span>`;
            }

            nombre.innerHTML = curso.codigo + " - " + curso.nombre + badgeElectivo;

            const detalle = document.createElement("div");
            detalle.className = "curso-detalle";

            const textoReq = requisitos.length > 0
                ? " | Requisito(s): " + requisitos.join(", ")
                : " | Sin requisito";

            let textoElectivo = "";

            if (esElectivo) {
                textoElectivo = " | Puede marcarse individualmente si fue aprobado";
            }

            detalle.textContent = "Créditos: " + curso.creditos + " | Tipo: " + curso.tipo + textoReq + textoElectivo;

            const acciones = document.createElement("div");
            acciones.className = "acciones-curso";

            const labelAprobado = document.createElement("label");
            labelAprobado.innerHTML = `
                <input
                    type="checkbox"
                    class="check-aprobado"
                    data-codigo="${curso.codigo}"
                    data-creditos="${curso.creditos}"
                    value="${curso.id}"
                    ${estaAprobado ? "checked" : ""}
                    ${bloqueado ? "disabled" : ""}
                >
                Aprobado
            `;

            acciones.appendChild(labelAprobado);

            item.appendChild(nombre);
            item.appendChild(detalle);

            if (bloqueado) {
                const aviso = document.createElement("div");
                aviso.className = "curso-detalle";
                aviso.textContent = "Bloqueado: debe aprobar primero el/los requisito(s).";
                item.appendChild(aviso);
            }

            item.appendChild(acciones);
            modalListaCursos.appendChild(item);

            const checkAprobado = acciones.querySelector(".check-aprobado");

            checkAprobado.addEventListener("change", function () {
                actualizarCursoAprobado(curso.codigo, this.checked);
            });
        });
    }

    function actualizarVistas() {
        renderizarCiclos();

        const totalCreditos = calcularTotalCreditosAprobados();
        totalCreditosSpan.textContent = totalCreditos;

        actualizarInputsOcultos();
        actualizarSeccionEmpresa();

        if (cicloActualModal !== null) {
            renderizarCursosDelModal(cicloActualModal);
        }
    }

    rucEmpresa.addEventListener("input", function () {
        rucEmpresa.value = rucEmpresa.value.replace(/[^0-9]/g, "");

        rucValidadoCorrectamente = false;
        razonSocialEmpresa.value = "";
        estadoRuc.value = "";
        condicionRuc.value = "";
        estadoRucInfo.style.display = "none";
        estadoRucInfo.textContent = "";

        if (rucEmpresa.value.length === 11) {
            if (!rucEmpresa.value.startsWith("20")) {
                alert("Solo se permite RUC de empresa que empiece con 20. No se admite RUC 10 de persona natural.");
                rucEmpresa.value = "";
                return;
            }

            consultarRucEmpresa(rucEmpresa.value);
        }
    });

    representanteDni.addEventListener("input", function () {
        representanteDni.value = representanteDni.value.replace(/[^0-9]/g, "");
    });

    representanteTelefono.addEventListener("input", function () {
        representanteTelefono.value = representanteTelefono.value.replace(/[^0-9+ -]/g, "");
    });

    async function consultarRucEmpresa(ruc) {
        estadoRucInfo.style.display = "block";
        estadoRucInfo.textContent = "Consultando RUC en SUNAT...";

        razonSocialEmpresa.value = "";
        estadoRuc.value = "";
        condicionRuc.value = "";
        rucValidadoCorrectamente = false;

        try {
            const formData = new FormData();
            formData.append("ruc", ruc);

            const respuesta = await fetch("validar_ruc.php", {
                method: "POST",
                body: formData
            });

            const datos = await respuesta.json();

            if (datos.ok === true) {
                razonSocialEmpresa.value = datos.razon_social || "";
                estadoRuc.value = datos.estado || "";
                condicionRuc.value = datos.condicion || "";

                rucValidadoCorrectamente = true;

                estadoRucInfo.style.display = "block";
                estadoRucInfo.textContent = "RUC validado correctamente. La razón social fue completada automáticamente.";
            } else {
                razonSocialEmpresa.value = "";
                estadoRuc.value = "";
                condicionRuc.value = "";
                rucValidadoCorrectamente = false;

                estadoRucInfo.style.display = "block";
                estadoRucInfo.textContent = datos.mensaje || "No se pudo validar el RUC ingresado.";
            }
        } catch (error) {
            razonSocialEmpresa.value = "";
            estadoRuc.value = "";
            condicionRuc.value = "";
            rucValidadoCorrectamente = false;

            estadoRucInfo.style.display = "block";
            estadoRucInfo.textContent = "Error al consultar el RUC. Verifique la conexión o el archivo validar_ruc.php.";
        }
    }

    function validarEnvioSolicitud() {
        const fechaInicio = document.getElementById("fecha_inicio_practicas").value;
        const fechaFin = document.getElementById("fecha_fin_practicas").value;

        if (!fechaInicio || !fechaFin) {
            alert("Debe ingresar la fecha de inicio y término de las prácticas.");
            return false;
        }

        if (new Date(fechaFin) <= new Date(fechaInicio)) {
            alert("La fecha de término debe ser posterior a la fecha de inicio de las prácticas.");
            return false;
        }

        const totalCreditos = calcularTotalCreditosAprobados();

        inputTotalCreditos.value = totalCreditos;

        if (totalCreditos >= CREDITOS_MINIMOS) {
            if (rucEmpresa.value.trim().length !== 11 || !rucEmpresa.value.startsWith("20")) {
                alert("Debe ingresar un RUC válido de empresa que empiece con 20.");
                return false;
            }

            if (!rucValidadoCorrectamente || razonSocialEmpresa.value.trim() === "") {
                alert("Debe validar correctamente el RUC antes de enviar la solicitud.");
                return false;
            }

            if (estadoRuc.value.trim().toUpperCase() !== "ACTIVO") {
                alert("El RUC debe encontrarse en estado ACTIVO.");
                return false;
            }

            if (condicionRuc.value.trim().toUpperCase() !== "HABIDO") {
                alert("El RUC debe encontrarse en condición HABIDO.");
                return false;
            }

            if (responsableEmpresa.value.trim() === "") {
                alert("Debe ingresar el nombre del representante de la empresa.");
                return false;
            }

            if (cargoResponsable.value.trim() === "") {
                alert("Debe ingresar el cargo del representante de la empresa.");
                return false;
            }

            if (representanteDni.value.trim().length !== 8) {
                alert("Debe ingresar un DNI válido del representante de la empresa.");
                return false;
            }

            if (representanteTelefono.value.trim() === "") {
                alert("Debe ingresar el teléfono del representante de la empresa.");
                return false;
            }

            if (representanteEmail.value.trim() === "") {
                alert("Debe ingresar el email del representante de la empresa.");
                return false;
            }

            if (direccionEmpresa.value.trim() === "") {
                alert("Debe ingresar la dirección de la empresa.");
                return false;
            }

            if (ubicacionEmpresa.value.trim() === "") {
                alert("Debe ingresar DPTO. / PROV. / DISTRITO de la empresa.");
                return false;
            }
        }

        return true;
    }

    window.onclick = function(event) {
        if (event.target === modalCursos) {
            cerrarModalCursos();
        }
    };

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape" && modalCursos.style.display === "block") {
            cerrarModalCursos();
        }
    });

    futTipoPrograma.addEventListener("change", cargarTramitesFut);

    actualizarVistas();
</script>

</body>
</html>