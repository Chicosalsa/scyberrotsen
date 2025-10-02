<?php
require('fpdf/fpdf.php');
session_start();
require_once __DIR__ . '/conexion.php';

// Verificar sesión
if (!isset($_SESSION["loggedin"])) {
    header("Location: login.php");
    exit;
}
date_default_timezone_set('America/Mexico_City');

// Crear clase PDF personalizada
class PDF extends FPDF {
    // Cabecera de página
    function Header() {
        // Logo
        $this->Image('logo.png', 10, 6, 30);
        // Arial bold 15
        $this->SetFont('Arial', 'B', 15);
        // Movernos a la derecha
        $this->Cell(80);
        // Título
        $this->Cell(30, 10, 'Reporte de Inventario de Accesorios', 0, 0, 'C');
        // Salto de línea
        $this->Ln(20);
    }

    // Pie de página
    function Footer() {
        // Posición a 1.5 cm del final
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Pagina '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    // Tabla coloreada
    function ImprovedTable($header, $data) {
        // Anchuras de las columnas (ajustadas para incluir Presentación)
        $w = array(15, 30, 25, 25, 25, 20, 25, 25, 30, 20);
        // Cabeceras
        for($i=0; $i<count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
        $this->Ln();
        // Datos
        foreach($data as $row) {
            $this->Cell($w[0], 6, $row['Id_Accesorios'], 'LR');
            $this->Cell($w[1], 6, $this->ShortenText($row['Nombre'], 25), 'LR');
            $this->Cell($w[2], 6, $this->ShortenText($row['Marca'], 20), 'LR');
            $this->Cell($w[3], 6, $this->ShortenText($row['Modelo'], 20), 'LR');
            $this->Cell($w[4], 6, $this->ShortenText($row['Presentacion_Producto'], 20), 'LR');
            $this->Cell($w[5], 6, '$'.number_format($row['Precio'], 2), 'LR', 0, 'R');
            
            // Resaltar stock bajo
            if($row['stockDisponible'] == 0) {
                $this->SetTextColor(255, 0, 0);
                $this->Cell($w[6], 6, $row['stockDisponible'], 'LR', 0, 'R');
                $this->SetTextColor(0);
            } else {
                $this->Cell($w[6], 6, $row['stockDisponible'], 'LR', 0, 'R');
            }
            
            $this->Cell($w[7], 6, date('d/m/Y', strtotime($row['fechaIngreso'])), 'LR');
            $this->Cell($w[8], 6, $this->ShortenText($row['Proveedor'], 25), 'LR');
            $this->Cell($w[9], 6, $row['Codigo'], 'LR');
            $this->Ln();
        }
        // Línea de cierre
        $this->Cell(array_sum($w), 0, '', 'T');
    }

    // Función para acortar texto largo
    function ShortenText($text, $maxLength) {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength-3) . '...';
        }
        return $text;
    }
}

// Consulta SQL para obtener los accesorios
$sql = "SELECT * FROM accesorio ORDER BY Nombre";
$result = $conexion->query($sql);

if ($result->num_rows > 0) {
    // Crear PDF
    $pdf = new PDF('L'); // Orientación horizontal para mejor ajuste
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 9); // Reducir tamaño de fuente para más columnas
    
    // Cabecera de la tabla (incluye Presentación)
    $header = array('ID', 'Nombre', 'Marca', 'Modelo', 'Presentación', 'Precio', 'Stock', 'F. Ingreso', 'Proveedor', 'Código');
    
    // Obtener datos
    $data = array();
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    // Crear tabla
    $pdf->ImprovedTable($header, $data);
    
    // Agregar total
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Total de accesorios en inventario: ' . $result->num_rows, 0, 1);
    
    // Fecha del reporte
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 10, 'Reporte generado el: ' . date('d/m/Y H:i:s'), 0, 1);
    
    // Salida del PDF
    $pdf->Output('D', 'Reporte_Inventario_'.date('Ymd').'.pdf');
} else {
    // Mostrar mensaje si no hay datos
    echo "No se encontraron accesorios en el inventario.";
}

// Cerrar conexión
$conexion->close();
?>