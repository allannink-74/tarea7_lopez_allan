<?php
// panel.php
session_start();
require_once 'conexion.php';

// 1. Verificar sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$rol_actual = $_SESSION['rol'];
$es_admin   = ($rol_actual === 'Administrador');
$mensaje    = '';
$tipo_msg   = '';

// 3. Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_enviado = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token_enviado)) {
        die("Error de seguridad: Token CSRF inválido.");
    }

    $accion = $_POST['accion'] ?? '';

    try {
        // --- ACCIÓN A: CREAR RESERVA (Administrador y Agente de ventas) ---
        if ($accion === 'crear_reserva') {
            $tour_id           = (int)($_POST['tour_id'] ?? 0);
            $cliente_id        = (int)($_POST['cliente_id'] ?? 0);
            $fecha_tour        = $_POST['fecha_tour'] ?? '';
            $cantidad_personas = (int)($_POST['cantidad_personas'] ?? 0);
            $hoy               = date('Y-m-d');

            if ($tour_id <= 0 || $cliente_id <= 0 || $fecha_tour === '' || $cantidad_personas < 1) {
                throw new Exception("Todos los campos de la reserva son obligatorios y la cantidad debe ser al menos 1.");
            }

            // RN-03: No se reservan fechas pasadas
            if ($fecha_tour < $hoy) {
                throw new Exception("RN-03: No se pueden registrar reservas en fechas pasadas.");
            }

            // Consultar datos reales del tour en el servidor
            $stmtTour = $pdo->prepare("SELECT nombre, precio_persona, cupo_maximo, activo FROM tours WHERE id = ?");
            $stmtTour->execute([$tour_id]);
            $tour = $stmtTour->fetch();

            if (!$tour) {
                throw new Exception("El tour seleccionado no existe.");
            }

            // RN-04: Un tour inactivo no acepta reservas nuevas
            if ((int)$tour['activo'] !== 1) {
                throw new Exception("RN-04: El tour '{$tour['nombre']}' está inactivo y no acepta reservas nuevas.");
            }

            // RN-01: Suma de personas confirmadas + nueva reserva <= cupo_maximo
            $stmtOcup = $pdo->prepare("SELECT COALESCE(SUM(cantidad_personas), 0) AS ocupados FROM reservas WHERE tour_id = ? AND fecha_tour = ? AND estado = 'confirmada'");
            $stmtOcup->execute([$tour_id, $fecha_tour]);
            $ocupados = (int)$stmtOcup->fetch()['ocupados'];
            $disponibles = (int)$tour['cupo_maximo'] - $ocupados;

            if ($cantidad_personas > $disponibles) {
                throw new Exception("RN-01: Cupo insuficiente. Solo quedan {$disponibles} lugares disponibles para esa fecha.");
            }

            // RN-02: total = cantidad_personas * precio_persona, con 10% de descuento para 6 o más
            $subtotal = $cantidad_personas * (float)$tour['precio_persona'];
            $total    = ($cantidad_personas >= 6) ? ($subtotal * 0.90) : $subtotal;

            $stmtIns = $pdo->prepare("INSERT INTO reservas (tour_id, cliente_id, fecha_tour, cantidad_personas, total, estado) VALUES (?, ?, ?, ?, ?, 'confirmada')");
            $stmtIns->execute([$tour_id, $cliente_id, $fecha_tour, $cantidad_personas, $total]);

            $mensaje  = "Reserva confirmada exitosamente por Q " . number_format($total, 2) . ".";
            $tipo_msg = "exito";
        }

        // --- ACCIÓN B: CANCELAR RESERVA (Libera cupo según RN-03) ---
        elseif ($accion === 'cancelar_reserva') {
            $reserva_id = (int)($_POST['reserva_id'] ?? 0);
            $stmtCan = $pdo->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ?");
            $stmtCan->execute([$reserva_id]);

            $mensaje  = "Reserva #{$reserva_id} cancelada. Los cupos han sido liberados.";
            $tipo_msg = "exito";
        }

        // --- ACCIÓN C: REGISTRAR CLIENTE (Administrador y Agente de ventas) ---
        elseif ($accion === 'crear_cliente') {
            $nombre       = trim($_POST['nombre'] ?? '');
            $nacionalidad = trim($_POST['nacionalidad'] ?? '');
            $telefono     = trim($_POST['telefono'] ?? '');
            $correo       = trim($_POST['correo'] ?? '');

            if ($nombre === '' || $nacionalidad === '') {
                throw new Exception("El nombre y la nacionalidad del cliente son obligatorios.");
            }

            $stmtCli = $pdo->prepare("INSERT INTO clientes (nombre, nacionalidad, telefono, correo) VALUES (?, ?, ?, ?)");
            $stmtCli->execute([$nombre, $nacionalidad, $telefono, $correo]);

            $mensaje  = "Cliente '{$nombre}' registrado correctamente.";
            $tipo_msg = "exito";
        }

        // --- ACCIÓN D: CREAR O EDITAR TOUR (SOLO ADMINISTRADOR) ---
        elseif ($accion === 'guardar_tour') {
            if (!$es_admin) {
                throw new Exception("Acceso denegado: El rol Agente de ventas no puede modificar tours ni precios.");
            }

            $tour_id        = (int)($_POST['tour_id'] ?? 0);
            $nombre         = trim($_POST['nombre'] ?? '');
            $descripcion    = trim($_POST['descripcion'] ?? '');
            $precio_persona = (float)($_POST['precio_persona'] ?? 0);
            $cupo_maximo    = (int)($_POST['cupo_maximo'] ?? 0);
            $duracion_horas = (int)($_POST['duracion_horas'] ?? 0);
            $activo         = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '' || $precio_persona <= 0 || $cupo_maximo < 1 || $duracion_horas < 1) {
                throw new Exception("Complete correctamente los datos del tour (precio, cupo y duración mayores a 0).");
            }

            if ($tour_id > 0) {
                $stmtUpd = $pdo->prepare("UPDATE tours SET nombre = ?, descripcion = ?, precio_persona = ?, cupo_maximo = ?, duracion_horas = ?, activo = ? WHERE id = ?");
                $stmtUpd->execute([$nombre, $descripcion, $precio_persona, $cupo_maximo, $duracion_horas, $activo, $tour_id]);
                $mensaje = "Tour '{$nombre}' actualizado correctamente.";
            } else {
                $stmtNew = $pdo->prepare("INSERT INTO tours (nombre, descripcion, precio_persona, cupo_maximo, duracion_horas, activo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtNew->execute([$nombre, $descripcion, $precio_persona, $cupo_maximo, $duracion_horas, $activo]);
                $mensaje = "Nuevo tour '{$nombre}' agregado al catálogo.";
            }
            $tipo_msg = "exito";
        }
    } catch (Exception $e) {
        $mensaje  = $e->getMessage();
        $tipo_msg = "error";
    }
}

// 4. Consultas para poblar las vistas
$tours    = $pdo->query("SELECT * FROM tours ORDER BY id ASC")->fetchAll();
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nombre ASC")->fetchAll();
$reservas = $pdo->query("
    SELECT r.*, t.nombre AS tour_nombre, c.nombre AS cliente_nombre 
    FROM reservas r
    INNER JOIN tours t ON r.tour_id = t.id
    INNER JOIN clientes c ON r.cliente_id = c.id
    ORDER BY r.fecha_tour DESC, r.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control · Antigua Viva</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; color: #2c3e50; }
        header { background: #1e8449; color: #fff; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { margin: 0; font-size: 20px; }
        .user-info { font-size: 14px; }
        .user-info a { color: #f9e79f; text-decoration: none; font-weight: bold; margin-left: 15px; }
        .container { max-width: 1150px; margin: 20px auto; padding: 0 15px; }
        .alert { padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .exito { background: #d4efdf; color: #145a32; border: 1px solid #a9dfbf; }
        .error { background: #fadbd8; color: #922b21; border: 1px solid #f5b7b1; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
        .box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
        h2 { margin-top: 0; font-size: 18px; border-bottom: 2px solid #eee; padding-bottom: 8px; color: #1e8449; }
        .form-group { margin-bottom: 12px; }
        label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 4px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 9px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; color: #fff; background: #1e8449; }
        .btn:hover { background: #145a32; }
        .btn-danger { background: #c0392b; padding: 5px 10px; font-size: 12px; }
        .btn-danger:hover { background: #922b21; }
        .ajax-panel { background: #eafaf1; border: 1px solid #a9dfbf; padding: 12px; border-radius: 5px; margin: 12px 0; font-size: 14px; }
        .ajax-warn { background: #fcf3cf; border-color: #f7dc6f; color: #7d6608; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f8f9f9; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-ok { background: #d4efdf; color: #145a32; }
        .badge-cancel { background: #fadbd8; color: #922b21; }
    </style>
</head>
<body>

<header>
    <h1>Tour Operadora Antigua Viva</h1>
    <div class="user-info">
        Bienvenido, <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong> 
        (Rol: <strong><?= htmlspecialchars($rol_actual) ?></strong>)
        <a href="logout.php">Cerrar Sesión</a>
    </div>
</header>

<div class="container">
    <?php if ($mensaje !== ''): ?>
        <div class="alert <?= $tipo_msg ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="grid">
        <!-- MÓDULO 1: NUEVA RESERVA CON AJAX -->
        <div class="box">
            <h2>Nueva Reserva de Tour</h2>
            <form method="POST" action="panel.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="crear_reserva">

                <div class="form-group">
                    <label for="tour_id">Tour</label>
                    <select id="tour_id" name="tour_id" required>
                        <option value="">-- Seleccione un tour --</option>
                        <?php foreach ($tours as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['nombre']) ?> (Q <?= number_format($t['precio_persona'], 2) ?>/pers.)
                                <?= (int)$t['activo'] === 0 ? ' [INACTIVO]' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cliente_id">Cliente</label>
                    <select id="cliente_id" name="cliente_id" required>
                        <option value="">-- Seleccione un cliente --</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['nacionalidad']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha_tour">Fecha del Tour</label>
                    <input type="date" id="fecha_tour" name="fecha_tour" min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="cantidad_personas">Cantidad de Personas (6+ aplica 10% dto.)</label>
                    <input type="number" id="cantidad_personas" name="cantidad_personas" min="1" value="1" required>
                </div>

                <!-- RESULTADO EN VIVO POR AJAX -->
                <div id="ajax_resultado" class="ajax-panel">
                    Seleccione un tour y una fecha para verificar cupos disponibles y calcular el total en vivo.
                </div>

                <button type="submit" class="btn">Confirmar Reserva</button>
            </form>
        </div>

        <!-- MÓDULO 2: GESTIÓN DE CLIENTES -->
        <div class="box">
            <h2>Registrar Nuevo Cliente</h2>
            <form method="POST" action="panel.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="accion" value="crear_cliente">

                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Nacionalidad</label>
                    <input type="text" name="nacionalidad" required>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono">
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo">
                </div>
                <button type="submit" class="btn">Guardar Cliente</button>
            </form>
        </div>
    </div>

    <!-- MÓDULO 3: LISTADO DE RESERVAS -->
    <div class="box" style="margin-bottom: 25px;">
        <h2>Reservas Registradas</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tour</th>
                    <th>Cliente</th>
                    <th>Fecha Tour</th>
                    <th>Personas</th>
                    <th>Total (Q)</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservas)): ?>
                    <tr><td colspan="8">No hay reservas registradas aún.</td></tr>
                <?php else: ?>
                    <?php foreach ($reservas as $r): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['tour_nombre']) ?></td>
                            <td><?= htmlspecialchars($r['cliente_nombre']) ?></td>
                            <td><?= htmlspecialchars($r['fecha_tour']) ?></td>
                            <td><?= (int)$r['cantidad_personas'] ?></td>
                            <td><strong>Q <?= number_format($r['total'], 2) ?></strong></td>
                            <td>
                                <?php if ($r['estado'] === 'confirmada'): ?>
                                    <span class="badge badge-ok">Confirmada</span>
                                <?php else: ?>
                                    <span class="badge badge-cancel">Cancelada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['estado'] === 'confirmada'): ?>
                                    <form method="POST" action="panel.php" style="display:inline;" onsubmit="return confirm('¿Cancelar esta reserva y liberar los cupos?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="accion" value="cancelar_reserva">
                                        <input type="hidden" name="reserva_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Cancelar</button>
                                    </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MÓDULO 4: CATÁLOGO DE TOURS Y PRECIOS (EDICIÓN EXCLUSIVA DE ADMINISTRADOR) -->
    <div class="box">
        <h2>Catálogo de Tours y Precios <?= $es_admin ? '(Gestión de Administrador)' : '(Solo lectura para Agente de ventas)' ?></h2>
        <table>
            <thead>
                <tr>
                    <th>Tour</th>
                    <th>Descripción</th>
                    <th>Precio/Persona</th>
                    <th>Cupo Máx.</th>
                    <th>Duración</th>
                    <th>Estado</th>
                    <?php if ($es_admin): ?><th>Editar (Admin)</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tours as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($t['descripcion']) ?></td>
                        <td>Q <?= number_format($t['precio_persona'], 2) ?></td>
                        <td><?= (int)$t['cupo_maximo'] ?> pers.</td>
                        <td><?= (int)$t['duracion_horas'] ?> hrs</td>
                        <td><?= (int)$t['activo'] === 1 ? '<span class="badge badge-ok">Activo</span>' : '<span class="badge badge-cancel">Inactivo</span>' ?></td>
                        <?php if ($es_admin): ?>
                            <td>
                                <form method="POST" action="panel.php" style="display:flex; gap:5px; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="accion" value="guardar_tour">
                                    <input type="hidden" name="tour_id" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="nombre" value="<?= htmlspecialchars($t['nombre']) ?>">
                                    <input type="hidden" name="descripcion" value="<?= htmlspecialchars($t['descripcion']) ?>">
                                    <input type="hidden" name="duracion_horas" value="<?= (int)$t['duracion_horas'] ?>">
                                    <input type="number" step="0.01" name="precio_persona" value="<?= $t['precio_persona'] ?>" style="width:85px;" title="Precio">
                                    <input type="number" name="cupo_maximo" value="<?= $t['cupo_maximo'] ?>" style="width:65px;" title="Cupo">
                                    <label style="font-size:11px; margin:0;"><input type="checkbox" name="activo" <?= (int)$t['activo'] === 1 ? 'checked' : '' ?>> Activo</label>
                                    <button type="submit" class="btn" style="padding:5px 8px; font-size:12px;">Guardar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SCRIPT AJAX PARA CONSULTAR CUPOS Y CALCULAR TOTAL EN VIVO -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const selTour    = document.getElementById('tour_id');
    const inpFecha   = document.getElementById('fecha_tour');
    const inpPers    = document.getElementById('cantidad_personas');
    const boxResult  = document.getElementById('ajax_resultado');

    async function consultarDisponibilidad() {
        const tourId   = selTour.value;
        const fecha    = inpFecha.value;
        const personas = inpPers.value || 1;

        if (!tourId || !fecha) {
            boxResult.className = 'ajax-panel';
            boxResult.innerHTML = 'Seleccione un tour y una fecha para verificar cupos disponibles y calcular el total en vivo.';
            return;
        }

        try {
            const url = `ajax_cupos.php?tour_id=${encodeURIComponent(tourId)}&fecha=${encodeURIComponent(fecha)}&personas=${encodeURIComponent(personas)}`;
            const resp = await fetch(url);
            const data = await resp.json();

            if (!data.ok) {
                boxResult.className = 'ajax-panel ajax-warn';
                boxResult.innerHTML = data.mensaje;
                return;
            }

            if (data.activo === 0) {
                boxResult.className = 'ajax-panel ajax-warn';
                boxResult.innerHTML = '<strong>Atención (RN-04):</strong> Este tour se encuentra <strong>INACTIVO</strong> y no admite nuevas reservas.';
                return;
            }

            const alertaCupo = (parseInt(personas) > data.disponibles)
                ? `<br><span style="color:#c0392b; font-weight:bold;">⚠️ La cantidad solicitada supera los cupos disponibles.</span>`
                : '';

            const textoDescuento = data.descuento_aplicado
                ? `<span style="color:#1e8449; font-weight:bold;">(¡10% de descuento grupal aplicado!)</span>`
                : `(Tarifa regular: Q ${data.precio_persona} c/u)`;

            boxResult.className = (parseInt(personas) > data.disponibles) ? 'ajax-panel ajax-warn' : 'ajax-panel';
            boxResult.innerHTML = `
                <strong>Cupos disponibles para el ${fecha}:</strong> ${data.disponibles} de ${data.cupo_maximo} lugares.<br>
                <strong>Total estimado:</strong> Q ${data.total} ${textoDescuento}
                ${alertaCupo}
            `;
        } catch (err) {
            boxResult.className = 'ajax-panel ajax-warn';
            boxResult.innerHTML = 'Error al consultar disponibilidad por AJAX.';
        }
    }

    selTour.addEventListener('change', consultarDisponibilidad);
    inpFecha.addEventListener('change', consultarDisponibilidad);
    inpPers.addEventListener('input', consultarDisponibilidad);
});
</script>

</body>
</html>