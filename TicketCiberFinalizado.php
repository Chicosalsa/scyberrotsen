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
               si.nombreCliente, si.HoraInicio, si.HoraFinal, si.TipoServicio, si.costoPorHora
        FROM servicio s
        JOIN serviciointernet si ON s.Id_Servicio = si.Id_Servicio
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

// Calcular duración
$horaInicio = new DateTime($servicio['HoraInicio']);
$horaFinal = new DateTime($servicio['HoraFinal']);
$duracion = $horaInicio->diff($horaFinal);

// Crear PDF
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('img/logo.png', 10, 8, 40);
        
        // Título
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'CIBER ROTSEN', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Ticket de Servicio', 0, 1, 'C');
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Fecha de impresion: ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();

// Información del ticket
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'No. Ticket:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['Id_Servicio'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Cliente:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['nombreCliente'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Servicio:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $servicio['TipoServicio'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Horario:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $horaInicio->format('d/m/Y H:i') . ' - ' . $horaFinal->format('H:i'), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Duracion:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $duracion->format('%H horas %I minutos'), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Tarifa:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, '$' . number_format($servicio['costoPorHora'], 2) . ' por hora', 0, 1);

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Total a pagar:', 0, 1);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, '$' . number_format($servicio['Precio'], 2), 0, 1, 'R');

$pdf->Ln(15);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 5, 'Firma del cliente:', 0, 1);
$pdf->Cell(0, 15, '', 'B', 1); // Línea para firma

$pdf->Ln(10);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 5, 'Gracias por su preferencia. Para cualquier aclaracion, presentar este ticket.');

// Salida del PDF
$pdf->Output('I', 'Ticket_Ciber_' . $servicio['Id_Servicio'] . '.pdf');
?>