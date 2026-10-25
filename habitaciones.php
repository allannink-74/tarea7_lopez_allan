<?php
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/funciones.php';

$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'todas';
$personas = isset($_GET['personas']) ? (int)$_GET['personas'] : 1;

$tiposPermitidos = ['todas', 'sencilla', 'doble', 'suite'];
if (!in_array($tipo, $tiposPermitidos)) {
    responderJson(400, ['error' => 'Tipo de paquete o categoría no válido']);
}

if ($personas < 1 || $personas > 6) {
    responderJson(400, ['error' => 'El número de personas debe estar entre 1 y 6']);
}

$filtradas = array_filter($habitaciones, function ($h) use ($tipo, $personas) {
    $coincideTipo = ($tipo === 'todas') || ($h['tipo'] === $tipo);
    $coincideCapacidad = $h['capacidad'] >= $personas;
    return $coincideTipo && $coincideCapacidad;
});

$resultado = array_values($filtradas);

responderJson(200, [
    'total' => count($resultado),
    'habitaciones' => $resultado
]);