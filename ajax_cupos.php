<?php
// ajax_cupos.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once 'conexion.php';

$tour_id  = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
$fecha    = $_GET['fecha'] ?? '';
$personas = isset($_GET['personas']) ? (int)$_GET['personas'] : 1;

if ($personas < 1) {
    $personas = 1;
}

if ($tour_id <= 0 || $fecha === '') {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Seleccione un tour y una fecha.'
    ]);
    exit;
}

// Obtener datos del tour
$stmtTour = $pdo->prepare("SELECT nombre, precio_persona, cupo_maximo, activo FROM tours WHERE id = ?");
$stmtTour->execute([$tour_id]);
$tour = $stmtTour->fetch();

if (!$tour) {
    echo json_encode(['ok' => false, 'mensaje' => 'Tour no encontrado.']);
    exit;
}

// Sumar personas con reserva 'confirmada' en esa fecha (RN-01 y RN-03)
$stmtRes = $pdo->prepare("SELECT COALESCE(SUM(cantidad_personas), 0) AS ocupados FROM reservas WHERE tour_id = ? AND fecha_tour = ? AND estado = 'confirmada'");
$stmtRes->execute([$tour_id, $fecha]);
$ocupados = (int)$stmtRes->fetch()['ocupados'];

$disponibles = (int)$tour['cupo_maximo'] - $ocupados;
if ($disponibles < 0) {
    $disponibles = 0;
}

// Cálculo del total con regla RN-02 (10% de descuento para 6 o más personas)
$precio_unitario = (float)$tour['precio_persona'];
$subtotal = $personas * $precio_unitario;
$descuento_aplicado = ($personas >= 6);
$total = $descuento_aplicado ? ($subtotal * 0.90) : $subtotal;

echo json_encode([
    'ok'                 => true,
    'activo'             => (int)$tour['activo'],
    'cupo_maximo'        => (int)$tour['cupo_maximo'],
    'ocupados'           => $ocupados,
    'disponibles'        => $disponibles,
    'precio_persona'     => number_format($precio_unitario, 2, '.', ''),
    'descuento_aplicado' => $descuento_aplicado,
    'total'              => number_format($total, 2, '.', '')
]);
?>