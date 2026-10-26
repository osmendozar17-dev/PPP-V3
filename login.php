<?php
session_start();
include "conexion.php";

$mensaje = "";
$correo = "";

if (isset($_GET["registro"]) && $_GET["registro"] === "exitoso") {
    $mensaje = "Registro exitoso. Ahora puedes iniciar sesión.";
}

if (isset($_GET["sesion"]) && $_GET["sesion"] === "expirada") {
    $mensaje = "Tu sesión expiró o no has iniciado sesión. Ingresa nuevamente para enviar la solicitud.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = trim($_POST["correo"]);
    $password = $_POST["password"];

    if (empty($correo) || empty($password)) {
        $mensaje = "Ingrese su correo y contraseña.";
    } else {
        $sql = "SELECT 
                    id,
                    nombres,
                    apellidos,
                    dni,
                    codigo_estudiante,
                    correo,
                    correo_personal,
                    escuela,
                    password_hash,
                    rol,
                    activo,
                    intentos_fallidos,
                    bloqueado_hasta
                FROM usuarios
                WHERE correo = ?
                LIMIT 1";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            $mensaje = "Error al preparar la consulta: " . $conexion->error;
        } else {
            $stmt->bind_param("s", $correo);
            $stmt->execute();

            $resultado = $stmt->get_result();

            if ($resultado->num_rows === 1) {
                $usuario = $resultado->fetch_assoc();

                $ahora = time();
                $bloqueadoHasta = !empty($usuario["bloqueado_hasta"]) ? strtotime($usuario["bloqueado_hasta"]) : 0;

                if ($bloqueadoHasta > $ahora) {
                    $minutosRestantes = ceil(($bloqueadoHasta - $ahora) / 60);
                    $mensaje = "Tu cuenta se encuentra bloqueada por exceder 5 intentos fallidos de contraseña. Intenta nuevamente en $minutosRestantes minuto(s).";
                } elseif ($usuario["activo"] != 1) {
                    $mensaje = "El usuario se encuentra desactivado.";
                } elseif (password_verify($password, $usuario["password_hash"])) {
                    // Restablecer contador e historial de bloqueo tras inicio exitoso
                    $upd = "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?";
                    $stmt_upd = $conexion->prepare($upd);
                    if ($stmt_upd) {
                        $stmt_upd->bind_param("i", $usuario["id"]);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }

                    $_SESSION["usuario_id"] = $usuario["id"];
                    $_SESSION["nombres"] = $usuario["nombres"];
                    $_SESSION["apellidos"] = $usuario["apellidos"];
                    $_SESSION["dni"] = $usuario["dni"];
                    $_SESSION["codigo_estudiante"] = $usuario["codigo_estudiante"];
                    $_SESSION["correo"] = $usuario["correo"];
                    $_SESSION["correo_personal"] = $usuario["correo_personal"];
                    $_SESSION["escuela"] = $usuario["escuela"];
                    $_SESSION["rol"] = $usuario["rol"];

                    if ($usuario["rol"] === "Estudiante") {
                        header("Location: solicitud.php");
                        exit;
                    } else {
                        header("Location: admin/panel.php");
                        exit;
                    }
                } else {
                    // Contraseña incorrecta: incrementar reintentos
                    $nuevosIntentos = intval($usuario["intentos_fallidos"]) + 1;

                    if ($nuevosIntentos >= 5) {
                        $tiempoBloqueo = date("Y-m-d H:i:s", strtotime("+10 minutes"));
                        $upd = "UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?";
                        $stmt_upd = $conexion->prepare($upd);
                        if ($stmt_upd) {
                            $stmt_upd->bind_param("isi", $nuevosIntentos, $tiempoBloqueo, $usuario["id"]);
                            $stmt_upd->execute();
                            $stmt_upd->close();
                        }
                        $mensaje = "Has superado los 5 intentos fallidos de contraseña. Tu cuenta ha sido bloqueada durante 10 minutos.";
                    } else {
                        $intentosRestantes = 5 - $nuevosIntentos;
                        $upd = "UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?";
                        $stmt_upd = $conexion->prepare($upd);
                        if ($stmt_upd) {
                            $stmt_upd->bind_param("ii", $nuevosIntentos, $usuario["id"]);
                            $stmt_upd->execute();
                            $stmt_upd->close();
                        }
                        $mensaje = "Contraseña incorrecta. Te quedan $intentosRestantes intento(s) antes del bloqueo.";
                    }
                }
            } else {
                $mensaje = "El correo no se encuentra registrado.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema PPP | UNFV FIEI</title>
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
            padding: 20px 15px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .contenedor {
            width: 100%;
            max-width: 440px;
            background: var(--blanco);
            padding: 38px 34px 34px 34px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            border-top: 5px solid var(--fiei-vino);
            position: relative;
        }

        .marca-institucional {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .insignia-unfv {
            background: var(--unfv-naranja-soft);
            color: var(--unfv-naranja);
            border: 1px solid var(--unfv-naranja-border);
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .insignia-fiei {
            background: var(--fiei-vino-light);
            color: var(--fiei-vino);
            border: 1px solid var(--fiei-vino-border);
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        h1 {
            text-align: center;
            color: var(--fiei-vino);
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 6px 0;
            letter-spacing: -0.3px;
        }

        h2 {
            text-align: center;
            font-size: 14.5px;
            font-weight: 500;
            color: var(--texto-secundario);
            margin: 0 0 24px 0;
            line-height: 1.4;
        }

        .divisor-encabezado {
            height: 1px;
            background: #e2e8f0;
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 13.5px;
            margin-bottom: 6px;
            color: #334155;
        }

        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            padding: 11px 13px;
            border: 1.5px solid var(--borde-suave);
            border-radius: 7px;
            font-size: 14.5px;
            box-sizing: border-box;
            margin-bottom: 16px;
            color: var(--texto-principal);
            background: #ffffff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input[type="email"]:focus, input[type="password"]:focus, input[type="text"]:focus {
            border-color: var(--fiei-vino);
            box-shadow: 0 0 0 3px rgba(114, 18, 36, 0.12);
            outline: none;
        }

        .mostrar-password {
            margin-top: -6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .mostrar-password label {
            font-weight: 500;
            font-size: 13.5px;
            color: var(--texto-secundario);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 0;
            user-select: none;
        }

        .mostrar-password input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin: 0;
            cursor: pointer;
            accent-color: var(--fiei-vino);
        }

        button {
            width: 100%;
            padding: 12.5px;
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

        .mensaje {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
            padding: 11px 14px;
            border-radius: 7px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 13.5px;
            line-height: 1.45;
        }

        .link {
            text-align: center;
            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: var(--texto-secundario);
        }

        .link a {
            color: var(--fiei-vino);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .link a:hover {
            color: var(--unfv-naranja);
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .contenedor {
                padding: 26px 20px 22px 20px;
                margin: 20px auto;
            }
            h1 {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>

<div class="contenedor">
    <div class="marca-institucional">
        <span class="insignia-unfv">UNFV</span>
        <span class="insignia-fiei">FIEI</span>
    </div>

    <h1>Iniciar Sesión</h1>
    <h2>Sistema de Carta de Presentación PPP</h2>
    <div class="divisor-encabezado"></div>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label for="correo">Correo institucional</label>
        <input type="email" name="correo" id="correo" value="<?php echo htmlspecialchars($correo); ?>" placeholder="ejemplo@unfv.edu.pe" required>

        <label for="password">Contraseña</label>
        <input type="password" name="password" id="password" placeholder="••••••••" required>

        <div class="mostrar-password">
            <label><input type="checkbox" id="verPassword" onclick="togglePassword()"> Mostrar contraseña</label>
        </div>

        <button type="submit">Ingresar al Sistema</button>
    </form>

    <div class="link">
        ¿Eres estudiante? <a href="register.php">Regístrate aquí</a>
    </div>
</div>

<script>
    function togglePassword() {
        const pwd = document.getElementById("password");
        if (pwd.type === "password") {
            pwd.type = "text";
        } else {
            pwd.type = "password";
        }
    }
</script>

</body>
</html>