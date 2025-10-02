<?php
session_start();
require_once __DIR__ . '/conexion.php';
require('fpdf/fpdf.php');

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener ID del servicio
$idServicio = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idServicio <= 0) {
    die("ID de servicio no válido");
}

// Obtener datos del servicio
$sql = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, s.Precio,
               i.tipoDocumento, i.numCopias, i.Color, i.costoPorCopia, i.completado
        FROM servicio s
        JOIN impresionescaner i ON s.Id_Servicio = i.Id_Servicio
        WHERE s.Id_Servicio = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $idServicio);
$stmt->execute();
$result = $stmt->get_result();
$servicio = $result->fetch_assoc();
$stmt->close();

if (!$servicio) {
    die("Servicio no encontrado");
}

// Crear PDF con soporte para UTF-8
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('img/logo.png', 10, 8, 40);
        
        // Título
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode('CIBER ROTSEN'), 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, utf8_decode('Ticket de Servicio de Impresión/Escaneo'), 0, 1, 'C');
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Fecha de impresión: ' . date('d/m/Y H:i')), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();

// Información del ticket
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('No. Ticket:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['Id_Servicio'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Fecha:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, date('d/m/Y H:i'), 0, 1);

$pdf->Ln(5);

// Detalles del servicio
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Tipo de servicio:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode($servicio['nombreServicio']), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Documento:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode($servicio['tipoDocumento']), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Copias:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['numCopias'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Color:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['Color'] ? utf8_decode('Sí') : 'No', 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Costo por copia:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, '$' . number_format($servicio['costoPorCopia'], 2), 0, 1);

$pdf->Ln(5);

// Descripción
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Descripción:'), 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 10, utf8_decode($servicio['Descripcion']));

$pdf->Ln(5);

// Total
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('Total a pagar:'), 0, 1);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, '$' . number_format($servicio['Precio'], 2), 0, 1, 'R');

$pdf->Ln(15);

// Firma
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 5, utf8_decode('Firma del cliente:'), 0, 1);
$pdf->Cell(0, 15, '', 'B', 1); // Línea para firma

$pdf->Ln(10);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 5, utf8_decode('Gracias por su preferencia. Para cualquier aclaración, presentar este ticket.'));

// Salida del PDF
$pdf->Output('I', 'Ticket_Impresion_' . $servicio['Id_Servicio'] . '.pdf');
?>