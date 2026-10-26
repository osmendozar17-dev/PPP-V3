<?php

function obtenerDatosCreditosMalla($escuela) {
    $escuela_normalizada = mb_strtoupper(trim((string)$escuela), "UTF-8");

    if (str_contains($escuela_normalizada, "INFORM")) {
        $total = 208;
        $nombre = "Ingeniería Informática";
    } elseif (str_contains($escuela_normalizada, "TELE")) {
        $total = 224;
        $nombre = "Ingeniería de Telecomunicaciones";
    } elseif (str_contains($escuela_normalizada, "ELECT")) {
        $total = 205;
        $nombre = "Ingeniería Electrónica";
    } elseif (str_contains($escuela_normalizada, "MECA")) {
        $total = 220;
        $nombre = "Ingeniería Mecatrónica";
    } else {
        $total = 208;
        $nombre = "Ingeniería";
    }

    return [
        "total" => $total,
        "minimo" => (int) ceil($total * 0.80),
        "carrera" => $nombre
    ];
}

?>