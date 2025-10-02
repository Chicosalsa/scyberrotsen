<?php
session_start();
require_once __DIR__ . '/conexion.php';
require('fpdf/fpdf.php');

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}

// Obtener ID del equipo desde la URL
$idEquipo = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($idEquipo)) {
    die("ID de equipo no especificado");
}

// Consulta para obtener datos del equipo y cliente (incluyendo FechaSalida)
$query = "SELECT e.*, r.nombreCliente, r.Diagnostico, r.costoEstimado,
                 r.Id_Reparacion, r.FechaSalida
          FROM equipo e
          JOIN reparacionequipos r ON e.Id_Equipo = r.Id_Equipo
          WHERE e.Id_Equipo = ?";

$stmt = $conexion->prepare($query);
if ($stmt === false) {
    die("Error en la preparación de la consulta: " . $conexion->error);
}
$stmt->bind_param("s", $idEquipo);
$stmt->execute();
$result = $stmt->get_result();
$equipo = $result->fetch_assoc();
$stmt->close();

if (!$equipo) {
    die("No se encontró el equipo especificado");
}

// Crear PDF
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('logoCut.png', 9, 7, 26); // Ajusta la ruta y tamaño del logo
        
        // Título
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'CIBER ROTSEN', 0, 0, 'C');
        $this->Ln(20);
        
      
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Fecha de impresion: ' . date('d/m/Y H:i'), 0, 0, 'L');
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

// Función para convertir caracteres UTF-8
function utf8_to_win1252($text) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
}

// Crear instancia de PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Configurar fuente
$pdf->SetFont('Arial', '', 12);

// Título del ticket
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, utf8_to_win1252('TICKET DE ENTREGA DE EQUIPO'), 0, 1, 'C');
$pdf->Ln(5);

// Línea divisoria
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.5);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(10);

// Información del ticket y fechas
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'No. Ticket:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, $equipo['Id_Reparacion'], 0, 0);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Fecha Entrega:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$fechaSalida = !empty($equipo['FechaSalida']) ? date('d/m/Y', strtotime($equipo['FechaSalida'])) : date('d/m/Y');
$pdf->Cell(0, 10, $fechaSalida, 0, 1);
$pdf->Ln(5);

// Información del cliente
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'DATOS DEL CLIENTE', 0, 1);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Nombre:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_to_win1252($equipo['nombreCliente']), 0, 1);
$pdf->Ln(8);

// Información del equipo
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'DATOS DEL EQUIPO', 0, 1);

// Tipo y Marca en una sola fila
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Tipo:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, utf8_to_win1252($equipo['Tipo']), 0, 0);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 10, 'Marca:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_to_win1252($equipo['Marca']), 0, 1);

// Modelo y No. Serie en una sola fila
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Modelo:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 10, utf8_to_win1252($equipo['Modelo']), 0, 0);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 10, 'No. Serie:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, utf8_to_win1252($equipo['NumSerie']), 0, 1);

// Fecha de ingreso
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Fecha Ingreso:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, date('d/m/Y', strtotime($equipo['FechaIngreso'])), 0, 1);
$pdf->Ln(8);

// Detalles de reparación
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_to_win1252('DETALLES DE REPARACIÓN'), 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Problema reportado:', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, utf8_to_win1252($equipo['ProblemaReportado']), 0, 'L');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_to_win1252('Diagnóstico:'), 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, utf8_to_win1252($equipo['Diagnostico']), 0, 'L');
$pdf->Ln(8);

// Costo y firma
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, utf8_to_win1252('Costo de reparación:'), 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, '$' . number_format($equipo['costoEstimado'], 2), 0, 1);
$pdf->Ln(10);

// Términos y condiciones
$pdf->SetFont('Arial', 'I', 10);
$pdf->MultiCell(0, 8, utf8_to_win1252('TÉRMINOS Y CONDICIONES:'), 0, 'L');
$pdf->MultiCell(0, 6, utf8_to_win1252('1. El cliente acepta que el equipo fue entregado en buenas condiciones.'), 0, 'L');
$pdf->MultiCell(0, 6, utf8_to_win1252('2. La garantía cubre defectos de reparación por 30 días.'), 0, 'L');
$pdf->MultiCell(0, 6, utf8_to_win1252('3. No cubre daños por mal uso o accidentes.'), 0, 'L');
$pdf->Ln(10);

// Firma - Versión modificada para poner ambas firmas en la misma fila
$pdf->SetY(-40); // Posicionar 40mm desde el fondo

// Celda para firma del cliente (mitad izquierda)
$pdf->Cell(95, 10, '__________________________', 0, 0, 'C');
// Celda para firma del responsable (mitad derecha)
$pdf->Cell(95, 10, '__________________________', 0, 1, 'C');

// Texto debajo de las firmas
$pdf->Cell(95, 5, 'Firma del cliente', 0, 0, 'C');
$pdf->Cell(95, 5, 'Firma del responsable', 0, 1, 'C');

// Salida del PDF
$pdf->Output('I', 'Ticket de Entrega' . $equipo['Id_Reparacion'] . '.pdf');
?>