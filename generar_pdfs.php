<?php

require_once __DIR__ . "/vendor/autoload.php";

use setasign\Fpdi\Tcpdf\Fpdi;

function textoPdf($valor) {
    return trim((string)($valor ?? ""));
}

function crearDirectorioSiNoExiste($ruta) {
    if (!is_dir($ruta)) {
        mkdir($ruta, 0775, true);
    }
}

function crearPdfBase() {
    $pdf = new Fpdi("P", "mm", "A4", true, "UTF-8", false);

    $pdf->SetCreator("Sistema PPP - FIEI UNFV");
    $pdf->SetAuthor("FIEI UNFV");
    $pdf->SetTitle("Documento generado por Sistema PPP");
    $pdf->SetSubject("Solicitud PPP");
    $pdf->SetKeywords("FUT, PPP, UNFV, FIEI");

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetMargins(12, 10, 12);
    $pdf->SetAutoPageBreak(true, 12);

    return $pdf;
}

function checkMarca($valor, $opcion) {
    return textoPdf($valor) === $opcion ? "X" : "";
}

/* ============================================================
   GENERAR FUT PDF
============================================================ */

function generarFutPdf($datos, $rutaDestino) {
    $directorio = dirname($rutaDestino);
    crearDirectorioSiNoExiste($directorio);

    $pdf = crearPdfBase();
    $pdf->AddPage();

    $pdf->SetFont("helvetica", "B", 9);
    $pdf->Cell(0, 5, "Universidad Nacional Federico Villarreal", 0, 1, "L");

    $pdf->SetFont("helvetica", "", 8);
    $pdf->Cell(0, 4, "FACULTAD DE INGENIERÍA ELECTRÓNICA E INFORMÁTICA", 0, 1, "L");

    $pdf->Ln(3);

    $pdf->SetFont("helvetica", "", 11);
    $pdf->Cell(0, 6, "Facultad: FACULTAD DE INGENIERÍA ELECTRÓNICA E INFORMÁTICA", 0, 1, "C");

    $pdf->SetFont("helvetica", "B", 15);
    $pdf->Cell(0, 8, "FORMULARIO ÚNICO DE TRÁMITE", 0, 1, "C");

    $pdf->SetFont("helvetica", "", 9);
    $pdf->Cell(0, 5, "(Gratuito)", 0, 1, "C");

    $pdf->Ln(3);

    $tipoPrograma = textoPdf($datos["fut_tipo_programa"] ?? "");
    $tramite = textoPdf($datos["fut_tramite"] ?? "");
    $dependencia = textoPdf($datos["fut_dependencia"] ?? "");
    $tipoSolicitante = textoPdf($datos["fut_tipo_solicitante"] ?? "");

    $pdf->SetFont("helvetica", "", 9);

    $html = '
    <table border="1" cellpadding="4">
        <tr>
            <td width="25%">
                <b>Pre Grado</b> ( ' . checkMarca($tipoPrograma, "Pregrado") . ' )<br>
                <b>Segunda Especialidad</b> ( ' . checkMarca($tipoPrograma, "Segunda Especialidad") . ' )
            </td>
            <td width="20%"><b>N°:</b></td>
            <td width="55%"><b>Trámite:</b> ' . htmlspecialchars($tramite) . '</td>
        </tr>
        <tr>
            <td colspan="3" align="center"><b>S U M I L L A</b></td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $html = '
    <table border="1" cellpadding="4">
        <tr>
            <td width="65%">
                <b>DEPENDENCIA DE LA FACULTAD A QUIEN SE DIRIGE:</b><br><br>
                ' . htmlspecialchars($dependencia) . '
            </td>
            <td width="35%">
                <b>SOLICITANTE:</b><br>
                Estudiante ( ' . checkMarca($tipoSolicitante, "Estudiante") . ' ) &nbsp;&nbsp;
                Egresado ( ' . checkMarca($tipoSolicitante, "Egresado") . ' )<br>
                Apoderado ( ' . checkMarca($tipoSolicitante, "Apoderado") . ' )
            </td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $apellidosNombres = textoPdf(($datos["apellidos"] ?? "") . " " . ($datos["nombres"] ?? ""));
    $codigo = textoPdf($datos["codigo_estudiante"] ?? "");
    $dni = textoPdf($datos["dni"] ?? "");
    $direccion = textoPdf($datos["fut_direccion"] ?? "");
    $distrito = textoPdf($datos["fut_distrito"] ?? "");
    $telefonoFijo = textoPdf($datos["fut_telefono_fijo"] ?? "");
    $celular = textoPdf($datos["fut_celular"] ?? "");
    $correo = textoPdf($datos["fut_correo"] ?? "");
    $fundamentacion = textoPdf($datos["fut_fundamentacion"] ?? "");
    $documentosAdjuntos = textoPdf($datos["fut_documentos_adjuntos"] ?? "");
    $totalFolios = textoPdf($datos["fut_total_folios"] ?? "");
    $lugarFecha = textoPdf($datos["fut_lugar_fecha"] ?? "");

    $pdf->SetFont("helvetica", "B", 9);
    $pdf->Cell(0, 5, "DATOS DEL ESTUDIANTE:", 0, 1, "L");

    $html = '
    <table border="1" cellpadding="4">
        <tr style="background-color:#dddddd;">
            <td width="80%"><b>Apellidos y Nombres:</b></td>
            <td width="20%"><b>Código:</b></td>
        </tr>
        <tr>
            <td>' . htmlspecialchars($apellidosNombres) . '</td>
            <td>' . htmlspecialchars($codigo) . '</td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $html = '
    <table border="1" cellpadding="4">
        <tr style="background-color:#dddddd;">
            <td width="22%"><b>DNI / Pasaporte / Carné Extranjería</b></td>
            <td width="58%"><b>DIRECCIÓN DOMICILIARIA:</b></td>
            <td width="20%"><b>Distrito:</b></td>
        </tr>
        <tr>
            <td>' . htmlspecialchars($dni) . '</td>
            <td>' . htmlspecialchars($direccion) . '</td>
            <td>' . htmlspecialchars($distrito) . '</td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $html = '
    <table border="1" cellpadding="4">
        <tr style="background-color:#dddddd;">
            <td width="20%"><b>Teléfono fijo:</b></td>
            <td width="20%"><b>Celular:</b></td>
            <td width="60%"><b>Correo Electrónico:</b></td>
        </tr>
        <tr>
            <td>' . htmlspecialchars($telefonoFijo) . '</td>
            <td>' . htmlspecialchars($celular) . '</td>
            <td>' . htmlspecialchars($correo) . '</td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $html = '
    <table border="1" cellpadding="4">
        <tr style="background-color:#dddddd;">
            <td><b>Fundamentación de la Solicitud:</b></td>
        </tr>
        <tr>
            <td height="70">' . nl2br(htmlspecialchars($fundamentacion)) . '</td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $html = '
    <table border="1" cellpadding="4">
        <tr style="background-color:#dddddd;">
            <td width="78%"><b>Documentos que se adjunta:</b></td>
            <td width="22%"><b>Total folios:</b></td>
        </tr>
        <tr>
            <td height="50">' . nl2br(htmlspecialchars($documentosAdjuntos)) . '</td>
            <td>' . htmlspecialchars($totalFolios) . '</td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $html = '
    <table border="1" cellpadding="4">
        <tr style="background-color:#dddddd;">
            <td width="65%"><b>Lugar y Fecha:</b></td>
            <td width="35%"><b>Firma y Post Firma del Solicitante:</b></td>
        </tr>
        <tr>
            <td height="40">' . htmlspecialchars($lugarFecha) . '</td>
            <td></td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $pdf->Ln(2);
    $pdf->SetFont("helvetica", "B", 8);
    $pdf->Cell(0, 4, "FAC-UNFV-001", 0, 1, "L");

    $pdf->SetFont("helvetica", "", 7);
    $pdf->Cell(0, 4, "Jr. Carlos Gonzales 285 - Maranga - San Miguel | Teléfono: 748-0888", 0, 1, "C");

    $pdf->Output($rutaDestino, "F");

    return $rutaDestino;
}

/* ============================================================
   GENERAR DATOS DE EMPRESA PDF
============================================================ */

function generarDatosEmpresaPdf($datos, $rutaDestino) {
    $directorio = dirname($rutaDestino);
    crearDirectorioSiNoExiste($directorio);

    $pdf = crearPdfBase();
    $pdf->AddPage();

    $pdf->SetFont("helvetica", "B", 13);
    $pdf->Cell(0, 8, "FORMATO 01", 0, 1, "C");

    $pdf->SetFont("helvetica", "B", 15);
    $pdf->Cell(0, 8, "FICHA DE DATOS DE LA EMPRESA", 0, 1, "C");

    $pdf->Ln(6);

    $nombreAlumno = textoPdf(($datos["nombres"] ?? "") . " " . ($datos["apellidos"] ?? ""));
    $codigoAlumno = textoPdf($datos["codigo_estudiante"] ?? "");
    $rucEmpresa = textoPdf($datos["ruc_empresa"] ?? "");
    $nombreEmpresa = textoPdf($datos["razon_social_empresa"] ?? "");
    $representante = textoPdf($datos["responsable_empresa"] ?? "");
    $cargo = textoPdf($datos["cargo_responsable"] ?? "");
    $dniRepresentante = textoPdf($datos["representante_dni"] ?? "");
    $telefonoRepresentante = textoPdf($datos["representante_telefono"] ?? "");
    $emailRepresentante = textoPdf($datos["representante_email"] ?? "");
    $direccionEmpresa = textoPdf($datos["direccion_empresa"] ?? "");
    $ubicacionEmpresa = textoPdf($datos["ubicacion_empresa"] ?? "");

    $duracionMeses = intval($datos["duracion_meses"] ?? 6);
    $fechaInicioStr = !empty($datos["fecha_inicio_practicas"]) ? date("d/m/Y", strtotime($datos["fecha_inicio_practicas"])) : "";
    $fechaFinStr = !empty($datos["fecha_fin_practicas"]) ? date("d/m/Y", strtotime($datos["fecha_fin_practicas"])) : "";
    $periodoPracticas = ($fechaInicioStr && $fechaFinStr) ? "$fechaInicioStr al $fechaFinStr" : "No especificado";

    $pdf->SetFont("helvetica", "", 11);

    $html = '
    <table cellpadding="5">
        <tr>
            <td width="35%"><b>NOMBRE DEL ALUMNO:</b></td>
            <td width="65%" style="border-bottom:1px solid #000;">' . htmlspecialchars($nombreAlumno) . '</td>
        </tr>
        <tr>
            <td><b>CÓDIGO DEL ALUMNO:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($codigoAlumno) . '</td>
        </tr>
        <tr>
            <td><b>RUC DE LA EMPRESA:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($rucEmpresa) . '</td>
        </tr>
        <tr>
            <td><b>NOMBRE DE LA EMPRESA:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($nombreEmpresa) . '</td>
        </tr>
        <tr>
            <td><b>REPRESENTANTE DE LA EMPRESA:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($representante) . '</td>
        </tr>
        <tr>
            <td></td>
            <td><small>(a quien se le dirige la carta de presentación)</small></td>
        </tr>
        <tr>
            <td><b>CARGO DEL REPRESENTANTE:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($cargo) . '</td>
        </tr>
        <tr>
            <td><b>DNI DEL REPRESENTANTE:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($dniRepresentante) . '</td>
        </tr>
        <tr>
            <td><b>TELÉFONO DEL REPRESENTANTE:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($telefonoRepresentante) . '</td>
        </tr>
        <tr>
            <td><b>EMAIL DEL REPRESENTANTE:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($emailRepresentante) . '</td>
        </tr>
        <tr>
            <td><b>DIRECCIÓN DE LA EMPRESA:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($direccionEmpresa) . '</td>
        </tr>
        <tr>
            <td><b>DPTO. / PROV. / DISTRITO:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($ubicacionEmpresa) . '</td>
        </tr>
        <tr>
            <td><b>DURACIÓN DE PRÁCTICAS:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($duracionMeses) . ' meses</td>
        </tr>
        <tr>
            <td><b>PERIODO DE PRÁCTICAS:</b></td>
            <td style="border-bottom:1px solid #000;">' . htmlspecialchars($periodoPracticas) . '</td>
        </tr>
    </table>
    ';

    $pdf->writeHTML($html, true, false, true, false, "");

    $pdf->Ln(15);

    $pdf->SetFont("helvetica", "B", 10);
    $pdf->Cell(0, 6, "OFICINA DE TUTORÍA Y PRÁCTICAS PRE PROFESIONALES", 0, 1, "C");

    $pdf->SetFont("helvetica", "", 9);
    $pdf->Cell(0, 5, "FIEI - FACULTAD DE INGENIERÍA ELECTRÓNICA E INFORMÁTICA", 0, 1, "C");
    $pdf->Cell(0, 5, "Dirección: Jr. Iquique 127 - Breña", 0, 1, "C");
    $pdf->Cell(0, 5, "Teléfono: 748-0888 Anexo 9872", 0, 1, "C");
    $pdf->Cell(0, 5, "Web: www.unfv.edu.pe/facultades/fiei", 0, 1, "C");

    $pdf->Output($rutaDestino, "F");

    return $rutaDestino;
}

/* ============================================================
   AGREGAR PDF EXISTENTE AL CONSOLIDADO
============================================================ */

function agregarPdfExistente($pdf, $rutaPdf) {
    if (!file_exists($rutaPdf)) {
        return;
    }

    $cantidadPaginas = $pdf->setSourceFile($rutaPdf);

    for ($pagina = 1; $pagina <= $cantidadPaginas; $pagina++) {
        $templateId = $pdf->importPage($pagina);
        $tamano = $pdf->getTemplateSize($templateId);

        $orientacion = ($tamano["width"] > $tamano["height"]) ? "L" : "P";

        $pdf->AddPage($orientacion, [$tamano["width"], $tamano["height"]]);
        $pdf->useTemplate($templateId);
    }
}

/* ============================================================
   AGREGAR IMAGEN AL CONSOLIDADO
============================================================ */

function agregarImagenComoPagina($pdf, $rutaImagen, $titulo = "") {
    if (!file_exists($rutaImagen)) {
        return;
    }

    $info = getimagesize($rutaImagen);

    if (!$info) {
        return;
    }

    $anchoImg = $info[0];
    $altoImg = $info[1];

    // Si la imagen es horizontal, se crea página horizontal.
    // Si es vertical, se crea página vertical.
    $orientacion = ($anchoImg > $altoImg) ? "L" : "P";

    $pdf->AddPage($orientacion, "A4");

    if ($orientacion === "L") {
        $anchoPagina = 297;
        $altoPagina = 210;
    } else {
        $anchoPagina = 210;
        $altoPagina = 297;
    }

    if (!empty($titulo)) {
        $pdf->SetFont("helvetica", "B", 13);
        $pdf->Cell(0, 8, $titulo, 0, 1, "C");
        $pdf->Ln(4);
    }

    $margen = 12;
    $espacioTitulo = !empty($titulo) ? 25 : 10;

    $anchoMax = $anchoPagina - ($margen * 2);
    $altoMax = $altoPagina - $espacioTitulo - $margen;

    $ratio = min($anchoMax / $anchoImg, $altoMax / $altoImg);

    $nuevoAncho = $anchoImg * $ratio;
    $nuevoAlto = $altoImg * $ratio;

    $x = ($anchoPagina - $nuevoAncho) / 2;
    $y = !empty($titulo) ? 28 : (($altoPagina - $nuevoAlto) / 2);

    $pdf->Image($rutaImagen, $x, $y, $nuevoAncho, $nuevoAlto);
}

/* ============================================================
   AGREGAR ARCHIVO PDF O IMAGEN AL CONSOLIDADO
============================================================ */

function agregarArchivoAlConsolidado($pdf, $rutaArchivo, $tituloImagen = "") {
    if (!file_exists($rutaArchivo)) {
        return;
    }

    $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));

    if ($extension === "pdf") {
        agregarPdfExistente($pdf, $rutaArchivo);
        return;
    }

    if (in_array($extension, ["jpg", "jpeg", "png"])) {
        agregarImagenComoPagina($pdf, $rutaArchivo, $tituloImagen);
        return;
    }
}

/* ============================================================
   GENERAR PDF CONSOLIDADO
============================================================ */

function generarPdfConsolidado($rutas, $rutaDestino) {
    $directorio = dirname($rutaDestino);
    crearDirectorioSiNoExiste($directorio);

    $pdf = crearPdfBase();

    if (!empty($rutas["fut_generado"])) {
        agregarArchivoAlConsolidado($pdf, $rutas["fut_generado"]);
    }

    if (!empty($rutas["datos_empresa_generado"]) && file_exists($rutas["datos_empresa_generado"])) {
        agregarArchivoAlConsolidado($pdf, $rutas["datos_empresa_generado"]);
    }

    if (!empty($rutas["foto_carnet"])) {
        agregarArchivoAlConsolidado($pdf, $rutas["foto_carnet"], "Foto tamaño carnet");
    }

    if (!empty($rutas["voucher"])) {
        agregarArchivoAlConsolidado($pdf, $rutas["voucher"], "Voucher de pago");
    }

    if (!empty($rutas["record"])) {
        agregarArchivoAlConsolidado($pdf, $rutas["record"], "Récord académico");
    }

    if (!empty($rutas["matricula"]) && file_exists($rutas["matricula"])) {
        agregarArchivoAlConsolidado($pdf, $rutas["matricula"], "Ficha de matrícula");
    }

    $pdf->Output($rutaDestino, "F");

    return $rutaDestino;
}
?>