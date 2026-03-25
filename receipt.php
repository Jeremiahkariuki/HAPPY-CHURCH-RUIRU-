<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

$id = (int)($_GET["id"] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM donations WHERE id=?");
$stmt->execute([$id]);
$don = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$don) die("Invalid receipt.");

require_once __DIR__ . "/vendor/fpdf/fpdf.php";

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Happy Church Ruiru',0,1,'C');
$pdf->Ln(5);

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Receipt ID: '.$don["id"],0,1);
$pdf->Cell(0,8,'Name: '.$don["full_name"],0,1);
$pdf->Cell(0,8,'Amount: KSh '.number_format($don["amount"],2),0,1);
$pdf->Cell(0,8,'Method: '.$don["payment_method"],0,1);
$pdf->Cell(0,8,'Date: '.format_date($don["created_at"]),0,1);

$pdf->Output();
