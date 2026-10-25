<?php
require_once __DIR__ . '/datos.php';
require_once __DIR__ . '/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(405, ['error' => 'Método no permitido. Use POST.']);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    responderJson(400, ['error' => 'Petición JSON no válida']);
}

$nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
$correo = isset($input['correo']) ? trim($input['correo']) : '';
$habitacionId = isset($input['habitacionId']) ? (int)$input['habitacionId'] : 0;
$noches = isset($input['noches']) ? (int)$input['noches'] : 0;

$errores = [];

if (mb_strlen($nombre) < 3) {
    $errores[] = "El nombre debe tener al menos 3 caracteres.";
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores[] = "El correo no es válido.";
}

$habitacionSeleccionada = null;
foreach ($habitaciones as $h) {
    if ($h['id'] === $habitacionId) {
        $habitacionSeleccionada = $h;
        break;
    }
}

if (!$habitacionSeleccionada) {
    $errores[] = "El paquete o tour seleccionado no existe.";
}

if ($noches < 1 || $noches > 14) {
    $errores[] = "Las noches o días de tour deben estar entre 1 y 14.";
}

if (!empty($errores)) {
    responderJson(422, ['errores' => $errores]);
}

$totalCalculado = $habitacionSeleccionada['precio'] * $noches;
$codigoConfirmacion = 'ATG-' . date('YmdHis');

responderJson(201, [
    'codigo' => $codigoConfirmacion,
    'habitacion' => $habitacionSeleccionada['nombre'],
    'noches' => $noches,
    'total' => $totalCalculado
]);