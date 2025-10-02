<?php
session_start();
require_once __DIR__ . '/conexion.php';
require_once('fpdf/fpdf.php');

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('America/Mexico_City');

// Función para formatear montos
function formatMoney($amount) {
    return number_format($amount, 2);
}

// Obtener fecha del parámetro GET o usar la actual si no está definida
$fecha_corte = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_corte)) {
    die('Formato de fecha inválido');
}

// Obtener datos de ventas para el corte - ADAPTADO para nueva BD
$sql_ventas = "SELECT v.Id_Venta, 
               CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) as fechaVenta,
               v.montoVenta, v.MontoPago,
               s.Descripcion,
               GROUP_CONCAT(CONCAT(a.Nombre, ' (', va.cantidad, ' x $', a.Precio, ')') SEPARATOR ', ') AS productos,
               SUM(va.cantidad * a.Precio) as totalCalculado
               FROM venta v
               JOIN servicio s ON v.Id_Servicio = s.Id_Servicio
               JOIN ventaaccesorios va ON v.Id_Venta = va.Id_Venta
               JOIN accesorio a ON va.Id_Accesorios = a.Id_Accesorios
               WHERE CONCAT(v.AnioVenta, '-', LPAD(v.MesVenta, 2, '0'), '-', LPAD(v.DiaVenta, 2, '0')) = ?
               GROUP BY v.Id_Venta
               ORDER BY v.AnioVenta DESC, v.MesVenta DESC, v.DiaVenta DESC";

$stmt_ventas = $conexion->prepare($sql_ventas);
$stmt_ventas->bind_param("s", $fecha_corte);
$stmt_ventas->execute();
$result_ventas = $stmt_ventas->get_result();
$ventas_dia = $result_ventas->fetch_all(MYSQLI_ASSOC);

// Consulta para obtener servicios de impresión ENTREGADOS - ADAPTADO
$sql_impresion = "SELECT i.Impresion_Id, s.nombreServicio, i.NomDoc, i.Color, 
                         i.tipoDocumentp, i.numCopias, i.PrecioTotal, 
                         CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) as FechaEntrega,
                         i.MontoPago, s.Descripcion
                  FROM impresion i
                  JOIN servicio s ON i.Id_Servicio = s.Id_Servicio
                  WHERE i.Entregado = 1 AND CONCAT(i.AnioEntrega, '-', LPAD(i.MesEntrega, 2, '0'), '-', LPAD(i.DiaEntrega, 2, '0')) = ?
                  ORDER BY i.AnioEntrega DESC, i.MesEntrega DESC, i.DiaEntrega DESC";

$stmt_impresion = $conexion->prepare($sql_impresion);
$stmt_impresion->bind_param("s", $fecha_corte);
$stmt_impresion->execute();
$result_impresion = $stmt_impresion->get_result();
$impresiones_dia = $result_impresion->fetch_all(MYSQLI_ASSOC);

// Consulta para obtener servicios de escaneo ENTREGADOS - ADAPTADO
$sql_escaner = "SELECT e.Escanner_Id, s.nombreServicio, e.TipoDocumento, 
                       e.PrecioTotal, 
                       CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) as FechaEntrega,
                       e.MontoPago, s.Descripcion
                FROM escaner e
                JOIN servicio s ON e.Id_Servicio = s.Id_Servicio
                WHERE e.Entregado = 1 AND CONCAT(e.AnioEntrega, '-', LPAD(e.MesEntrega, 2, '0'), '-', LPAD(e.DiaEntrega, 2, '0')) = ?
                ORDER BY e.AnioEntrega DESC, e.MesEntrega DESC, e.DiaEntrega DESC";

$stmt_escaner = $conexion->prepare($sql_escaner);
$stmt_escaner->bind_param("s", $fecha_corte);
$stmt_escaner->execute();
$result_escaner = $stmt_escaner->get_result();
$escaneos_dia = $result_escaner->fetch_all(MYSQLI_ASSOC);

// Calcular totales
$total_ventas = 0;
$efectivo_total = 0;
$total_impresiones = 0;
$total_escaneos = 0;

foreach ($ventas_dia as $venta) {
    $total_ventas += $venta['totalCalculado'];
    $efectivo_total += $venta['MontoPago'];
}

foreach ($impresiones_dia as $impresion) {
    $total_impresiones += $impresion['PrecioTotal'];
    $efectivo_total += $impresion['MontoPago'];
}

foreach ($escaneos_dia as $escaneo) {
    $total_escaneos += $escaneo['PrecioTotal'];
    $efectivo_total += $escaneo['MontoPago'];
}

$total_servicios = $total_impresiones + $total_escaneos;
$total_general = $total_ventas + $total_servicios;
$diferencia = $efectivo_total - $total_general;

// Crear PDF
class PDF extends FPDF {
    function Header() {
        if (file_exists('logoCut.png')) {
            $this->Image('logoCut.png', 10, 8, 30);
        }
        $this->SetLineWidth(0.5);
        $this->Line(45, 10, 45, 25);
        $this->SetFont('Arial', 'B', 15);
        $this->SetX(50);
        $this->Cell(0, 10, 'Ciber Rotsen - Reporte de Corte de Caja', 0, 0, 'L');
        $this->Ln(15);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina '.$this->PageNo().'/{nb}', 0, 0, 'R');
    }

    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(40, 55, 133);
        $this->SetTextColor(255);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        $this->Ln(4);
        $this->SetTextColor(0);
    }

    function VentasTable($header, $data) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(40, 55, 133);
        $this->SetTextColor(255);
        $w = array(20, 70, 30, 30, 30); // Anchos ajustados
        
        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 8);

        $fill = false;
        foreach($data as $row) {
            for($i=0; $i<count($row); $i++) {
                $this->Cell($w[$i], 6, $row[$i], 'LR', 0, $i==0 ? 'C' : 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln(5);
    }

    function ImpresionesTable($header, $data) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(40, 55, 133);
        $this->SetTextColor(255);
        $w = array(20, 40, 25, 15, 20, 25, 25); // Anchos ajustados
        
        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFillColor(255, 240, 240);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 8);

        $fill = false;
        foreach($data as $row) {
            for($i=0; $i<count($row); $i++) {
                $this->Cell($w[$i], 6, $row[$i], 'LR', 0, $i==0 || $i==3 || $i==4 ? 'C' : 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln(5);
    }

    function EscaneosTable($header, $data) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(40, 55, 133);
        $this->SetTextColor(255);
        $w = array(20, 50, 30, 30, 30, 30); // Anchos ajustados
        
        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFillColor(240, 255, 240);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 8);

        $fill = false;
        foreach($data as $row) {
            for($i=0; $i<count($row); $i++) {
                $this->Cell($w[$i], 6, $row[$i], 'LR', 0, $i==0 ? 'C' : 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln(5);
    }
}

// Instanciación del PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Información del corte
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, 'Fecha de Corte: ' . date('d/m/Y', strtotime($fecha_corte)), 0, 1, 'R');
$pdf->Cell(0, 8, 'Generado por: ' . htmlspecialchars($_SESSION["username"]), 0, 1, 'R');
$pdf->Cell(0, 8, 'Fecha y hora de generacion: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
$pdf->Ln(5);

// Sección de ventas de accesorios
$pdf->SectionTitle('VENTAS DE ACCESORIOS');
if (!empty($ventas_dia)) {
    $header = array('ID Venta', 'Productos', 'Total Calc.', 'Efectivo', 'Descripcion');
    $data = array();
    foreach ($ventas_dia as $venta) {
        $data[] = array(
            $venta['Id_Venta'],
            substr($venta['productos'], 0, 60) . (strlen($venta['productos']) > 60 ? '...' : ''),
            '$' . formatMoney($venta['totalCalculado']),
            '$' . formatMoney($venta['MontoPago']),
            substr($venta['Descripcion'], 0, 25) . (strlen($venta['Descripcion']) > 25 ? '...' : '')
        );
    }
    $pdf->VentasTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'No hay ventas de accesorios registradas para esta fecha.', 0, 1, 'C');
    $pdf->Ln(5);
}

// Sección de servicios de impresión
$pdf->SectionTitle('SERVICIOS DE IMPRESION (ENTREGADOS)');
if (!empty($impresiones_dia)) {
    $header = array('ID', 'Documento', 'Tipo', 'Copias', 'Color', 'Precio', 'Efectivo');
    $data = array();
    foreach ($impresiones_dia as $impresion) {
        $data[] = array(
            $impresion['Impresion_Id'],
            substr($impresion['NomDoc'], 0, 30) . (strlen($impresion['NomDoc']) > 30 ? '...' : ''),
            substr($impresion['tipoDocumentp'], 0, 20),
            $impresion['numCopias'],
            $impresion['Color'] ? 'Color' : 'B/N',
            '$' . formatMoney($impresion['PrecioTotal']),
            '$' . formatMoney($impresion['MontoPago'])
        );
    }
    $pdf->ImpresionesTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'No hay servicios de impresion entregados para esta fecha.', 0, 1, 'C');
    $pdf->Ln(5);
}

// Sección de servicios de escaneo
$pdf->SectionTitle('SERVICIOS DE ESCANEO (ENTREGADOS)');
if (!empty($escaneos_dia)) {
    $header = array('ID', 'Tipo Documento', 'Precio Total', 'Efectivo', 'Fecha Entrega', 'Descripcion');
    $data = array();
    foreach ($escaneos_dia as $escaneo) {
        $data[] = array(
            $escaneo['Escanner_Id'],
            substr($escaneo['TipoDocumento'], 0, 35),
            '$' . formatMoney($escaneo['PrecioTotal']),
            '$' . formatMoney($escaneo['MontoPago']),
            date('d/m/Y', strtotime($escaneo['FechaEntrega'])),
            substr($escaneo['Descripcion'], 0, 25) . (strlen($escaneo['Descripcion']) > 25 ? '...' : '')
        );
    }
    $pdf->EscaneosTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'No hay servicios de escaneo entregados para esta fecha.', 0, 1, 'C');
    $pdf->Ln(5);
}

// Resumen de totales
$pdf->SectionTitle('RESUMEN DE TOTALES');
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(120, 8, 'Total ventas de accesorios:', 0, 0);
$pdf->Cell(40, 8, '$' . formatMoney($total_ventas), 0, 1, 'R');

$pdf->Cell(120, 8, 'Total servicios de impresion:', 0, 0);
$pdf->Cell(40, 8, '$' . formatMoney($total_impresiones), 0, 1, 'R');

$pdf->Cell(120, 8, 'Total servicios de escaneo:', 0, 0);
$pdf->Cell(40, 8, '$' . formatMoney($total_escaneos), 0, 1, 'R');

$pdf->Cell(120, 8, 'Total servicios (impresion + escaneo):', 0, 0);
$pdf->Cell(40, 8, '$' . formatMoney($total_servicios), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 0.5, '', 0, 1, '', true);

$pdf->Cell(120, 10, 'TOTAL GENERAL CALCULADO:', 0, 0);
$pdf->Cell(40, 10, '$' . formatMoney($total_general), 0, 1, 'R');

$pdf->Cell(120, 10, 'TOTAL EFECTIVO RECIBIDO:', 0, 0);
$pdf->Cell(40, 10, '$' . formatMoney($efectivo_total), 0, 1, 'R');

// Mostrar diferencia
$diferencia_color = $diferencia >= 0 ? 0 : 255; // Negro si positivo, Rojo si negativo
$pdf->SetTextColor($diferencia_color, 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(120, 12, 'DIFERENCIA:', 0, 0);
$pdf->Cell(40, 12, '$' . formatMoney($diferencia), 0, 1, 'R');

// Resetear color
$pdf->SetTextColor(0, 0, 0);

// Firma y observaciones
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 8, 'Observaciones:', 0, 1);
$pdf->MultiCell(0, 6, 'Este reporte incluye unicamente servicios que han sido marcados como ENTREGADOS en el sistema. Los servicios pendientes de entrega no se consideran en este corte.');

$pdf->Ln(8);
$pdf->Cell(80, 8, '_________________________', 0, 0, 'C');
$pdf->Cell(80, 8, '_________________________', 0, 1, 'C');
$pdf->Cell(80, 4, 'Firma Responsable', 0, 0, 'C');
$pdf->Cell(80, 4, 'Firma Revision', 0, 1, 'C');

// Salida del PDF
$pdf->Output('CorteDeCaja_'.$fecha_corte.'.pdf', 'D');
?>