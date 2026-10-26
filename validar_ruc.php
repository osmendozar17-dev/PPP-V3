<?php
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "Método no permitido."
    ]);
    exit;
}

$ruc = trim($_POST["ruc"] ?? "");

if (!preg_match("/^[0-9]{11}$/", $ruc)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "El RUC debe tener exactamente 11 dígitos."
    ]);
    exit;
}

if (!str_starts_with($ruc, "20")) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "Solo se permite RUC de empresa que empiece con 20. No se admite RUC 10 de persona natural."
    ]);
    exit;
}

$config_path = __DIR__ . "/ruc_config.php";

if (!file_exists($config_path)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se encontró el archivo ruc_config.php."
    ]);
    exit;
}

$config = require $config_path;
$token = trim($config["token"] ?? "");

if ($token === "" || $token === "AQUI_COLOCA_TU_TOKEN" || $token === "AQUI_VA_TU_TOKEN_REAL") {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se configuró el token de consulta RUC."
    ]);
    exit;
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
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo conectar con el servicio de consulta RUC.",
        "detalle" => $error
    ]);
    exit;
}

$datos = json_decode($respuesta, true);

if ($httpCode !== 200 || !is_array($datos)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se encontró información válida para el RUC ingresado.",
        "codigo_http" => $httpCode,
        "respuesta" => $respuesta
    ]);
    exit;
}

$razon_social = $datos["razon_social"] ?? $datos["razonSocial"] ?? "";
$estado = $datos["estado"] ?? "";
$condicion = $datos["condicion"] ?? "";

if (empty($razon_social)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "El RUC existe, pero no se obtuvo la razón social.",
        "respuesta" => $datos
    ]);
    exit;
}

echo json_encode([
    "ok" => true,
    "mensaje" => "RUC validado correctamente.",
    "ruc" => $ruc,
    "razon_social" => $razon_social,
    "estado" => $estado,
    "condicion" => $condicion
]);