<?php
include "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombres = trim($_POST["nombres"]);
    $apellidos = trim($_POST["apellidos"]);
    $dni = trim($_POST["dni"]);
    $codigo_estudiante = trim($_POST["codigo_estudiante"]);
    $correo = trim($_POST["correo"]);
    $correo_personal = isset($_POST["correo_personal"]) ? trim($_POST["correo_personal"]) : "";
    $escuela = trim($_POST["escuela"]);
    $password = $_POST["password"];
    $confirmar_password = $_POST["confirmar_password"];

    if (
        empty($nombres) ||
        empty($apellidos) ||
        empty($dni) ||
        empty($codigo_estudiante) ||
        empty($correo) ||
        empty($escuela) ||
        empty($password) ||
        empty($confirmar_password)
    ) {
        $mensaje = "Todos los campos son obligatorios.";
    } elseif (!preg_match("/^[0-9]{8}$/", $dni)) {
        $mensaje = "El DNI debe tener exactamente 8 dígitos.";
    } elseif (!str_ends_with($correo, "@unfv.edu.pe")) {
        $mensaje = "El correo debe ser institucional y terminar en @unfv.edu.pe.";
    } elseif ($correo !== $codigo_estudiante . "@unfv.edu.pe") {
        $mensaje = "El correo institucional debe coincidir con el código de estudiante.";
    } elseif (!empty($correo_personal) && !filter_var($correo_personal, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "El correo personal ingresado no tiene un formato válido.";
    } elseif ($password !== $confirmar_password) {
        $mensaje = "Las contraseñas no coinciden.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO usuarios (
                    nombres,
                    apellidos,
                    dni,
                    codigo_estudiante,
                    correo,
                    correo_personal,
                    escuela,
                    password_hash,
                    rol
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Estudiante')";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            $mensaje = "Error al preparar la consulta: " . $conexion->error;
        } else {
            $stmt->bind_param(
                "ssssssss",
                $nombres,
                $apellidos,
                $dni,
                $codigo_estudiante,
                $correo,
                $correo_personal,
                $escuela,
                $password_hash
            );

            if ($stmt->execute()) {
                header("Location: login.php?registro=exitoso");
                exit;
            } else {
                if ($conexion->errno == 1062) {
                    $mensaje = "El correo ya se encuentra registrado.";
                } else {
                    $mensaje = "Error al registrar usuario: " . $stmt->error;
                }
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
    <title>Registro de Estudiante - Sistema PPP | UNFV FIEI</title>
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
            padding: 28px 15px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .contenedor {
            width: 100%;
            max-width: 540px;
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

        input, select {
            width: 100%;
            padding: 11px 13px;
            border: 1.5px solid var(--borde-suave);
            border-radius: 7px;
            font-size: 14.5px;
            box-sizing: border-box;
            margin-bottom: 16px;
            background: #ffffff;
            color: var(--texto-principal);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            font-family: inherit;
        }

        input:focus, select:focus {
            border-color: var(--fiei-vino);
            box-shadow: 0 0 0 3px rgba(114, 18, 36, 0.12);
            outline: none;
        }

        select {
            cursor: pointer;
        }

        button {
            width: 100%;
            padding: 13px;
            background: linear-gradient(180deg, var(--fiei-vino) 0%, #631020 100%);
            color: white;
            border: none;
            font-size: 15.5px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-top: 6px;
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
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
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
                margin: 15px auto;
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

    <h1>Registro de Estudiante</h1>
    <h2>Sistema de Carta de Presentación PPP</h2>
    <div class="divisor-encabezado"></div>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php" onsubmit="return confirmarRegistro();">
        <label>Nombres</label>
        <input type="text" name="nombres" placeholder="Ingrese sus nombres completos" required>

        <label>Apellidos</label>
        <input type="text" name="apellidos" placeholder="Ingrese sus apellidos completos" required>

        <label>DNI</label>
        <input type="text" name="dni" id="dni" maxlength="8" placeholder="8 dígitos" required>

        <label>Código de estudiante</label>
        <input type="text" name="codigo_estudiante" id="codigo_estudiante" placeholder="Ej: 2023020371" required>

        <label>Correo institucional</label>
        <input type="email" name="correo" id="correo" placeholder="codigo@unfv.edu.pe" required>

        <label>Correo personal / de respaldo (Opcional)</label>
        <input type="email" name="correo_personal" id="correo_personal" placeholder="ejemplo@gmail.com">

        <label>Escuela profesional</label>
        <select name="escuela" required>
            <option value="">Seleccione una escuela</option>
            <option value="Ingeniería Informática">Ingeniería Informática</option>
            <option value="Ingeniería Electrónica">Ingeniería Electrónica</option>
            <option value="Ingeniería Mecatrónica">Ingeniería Mecatrónica</option>
            <option value="Ingeniería de Telecomunicaciones">Ingeniería de Telecomunicaciones</option>
        </select>

        <label>Contraseña</label>
        <input type="password" name="password" placeholder="Defina una contraseña segura" required>

        <label>Confirmar contraseña</label>
        <input type="password" name="confirmar_password" placeholder="Repita la contraseña" required>

        <button type="submit">Completar Registro</button>
    </form>

    <div class="link">
        ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
    </div>
</div>

<script>
    const codigoInput = document.getElementById("codigo_estudiante");
    const correoInput = document.getElementById("correo");
    const dniInput = document.getElementById("dni");

    codigoInput.addEventListener("input", function () {
        let codigo = codigoInput.value.trim();

        if (codigo.length > 0) {
            correoInput.value = codigo + "@unfv.edu.pe";
        } else {
            correoInput.value = "";
        }
    });

    dniInput.addEventListener("input", function () {
        dniInput.value = dniInput.value.replace(/[^0-9]/g, "");
    });

    function confirmarRegistro() {
        const nombres = document.querySelector('input[name="nombres"]').value.trim();
        const apellidos = document.querySelector('input[name="apellidos"]').value.trim();
        const dni = document.querySelector('input[name="dni"]').value.trim();
        const codigo = document.querySelector('input[name="codigo_estudiante"]').value.trim();
        const correo = document.querySelector('input[name="correo"]').value.trim();
        const correo_personal = document.querySelector('input[name="correo_personal"]').value.trim();
        const escuela = document.querySelector('select[name="escuela"]').value.trim();

        if (
            nombres === "" ||
            apellidos === "" ||
            dni === "" ||
            codigo === "" ||
            correo === "" ||
            escuela === ""
        ) {
            alert("Complete todos los datos antes de registrarse.");
            return false;
        }

        let mensaje =
            "Corrobore que los datos ingresados sean correctos:\n\n" +
            "Nombres: " + nombres + "\n" +
            "Apellidos: " + apellidos + "\n" +
            "DNI: " + dni + "\n" +
            "Código de estudiante: " + codigo + "\n" +
            "Correo institucional: " + correo + "\n";

        if (correo_personal !== "") {
            mensaje += "Correo personal: " + correo_personal + "\n";
        }

        mensaje += "Escuela profesional: " + escuela + "\n\n" +
                   "¿Los datos son correctos?";

        return confirm(mensaje);
    }
</script>

</body>
</html>