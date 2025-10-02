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

// Obtener datos del servicio de IMPRESION con la estructura CORRECTA de tu base de datos
$sql = "SELECT s.Id_Servicio, s.nombreServicio, s.Descripcion, s.User_Id,
               i.NomDoc, i.tipoDocumento, i.numCopias, i.Color, i.PrecioTotal, 
               i.DiaEntrega, i.MesEntrega, i.AnioEntrega, i.MontoPago, u.Usuario
        FROM servicio s
        JOIN impresion i ON s.Id_Servicio = i.Id_Servicio
        JOIN users u ON s.User_Id = u.User_Id
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

// Calcular cambio si es necesario
$total = $servicio['PrecioTotal'];
$efectivoRecibido = $servicio['MontoPago'];
$cambio = $efectivoRecibido - $total;

// Formatear fecha de entrega a partir de los campos separados
$fechaEntrega = sprintf("%02d/%02d/%04d", 
    $servicio['DiaEntrega'], 
    $servicio['MesEntrega'], 
    $servicio['AnioEntrega']
);

// Crear PDF con soporte para UTF-8
class PDF extends FPDF {
    function Header() {
        // Logo
        if (file_exists('img/logo.png')) {
            $this->Image('img/logo.png', 10, 8, 40);
        } else if (file_exists('logoCut.png')) {
            $this->Image('logoCut.png', 10, 8, 40);
        }
        
        // Título
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode('CIBER ROTSEN'), 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, utf8_decode('Ticket de Servicio de Impresión'), 0, 1, 'C');
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

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Atendido por:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode($servicio['Usuario']), 0, 1);

$pdf->Ln(5);

// Detalles del servicio de IMPRESIÓN
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Servicio:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode($servicio['nombreServicio']), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Documento:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode($servicio['NomDoc']), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Tipo:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_decode($servicio['tipoDocumento']), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Copias:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['numCopias'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Color:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['Color'] ? utf8_decode('Color') : utf8_decode('Blanco/Negro'), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_decode('Fecha Entrega:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $fechaEntrega, 0, 1);

$pdf->Ln(5);

// Descripción
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Descripción:'), 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 10, utf8_decode($servicio['Descripcion']));

$pdf->Ln(5);

// Resumen de pago
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Resumen de Pago'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);

$pdf->Cell(100, 8, utf8_decode('Total del servicio:'), 0, 0);
$pdf->Cell(0, 8, '$' . number_format($total, 2), 0, 1, 'R');

$pdf->Cell(100, 8, utf8_decode('Efectivo recibido:'), 0, 0);
$pdf->Cell(0, 8, '$' . number_format($efectivoRecibido, 2), 0, 1, 'R');

if ($cambio > 0) {
    $pdf->Cell(100, 8, utf8_decode('Cambio:'), 0, 0);
    $pdf->Cell(0, 8, '$' . number_format($cambio, 2), 0, 1, 'R');
}

$pdf->Ln(5);

// Total
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('Total:'), 0, 0);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, '$' . number_format($total, 2), 0, 1, 'R');

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