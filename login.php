<?php
// login.php
session_start();
require_once 'conexion.php';

// Si ya tiene sesión activa, redirigir al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: panel.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = "Complete todos los campos.";
    } else {
        // Consulta preparada (protección contra Inyección SQL)
        $stmt = $pdo->prepare("SELECT id, usuario, password_hash, nombre, rol, activo FROM usuarios WHERE usuario = ? LIMIT 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && (int)$user['activo'] === 1 && password_verify($password, $user['password_hash'])) {
            // Regenerar ID de sesión (protección contra Session Fixation)
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario']    = $user['usuario'];
            $_SESSION['nombre']     = $user['nombre'];
            $_SESSION['rol']        = $user['rol'];

            header("Location: panel.php");
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos, o cuenta inactiva.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · Antigua Viva</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 360px; }
        h2 { margin-top: 0; color: #2c3e50; text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #34495e; }
        input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #1e8449; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background: #145a32; }
        .error { background: #fadbd8; color: #922b21; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-size: 14px; }
        .info { margin-top: 15px; font-size: 12px; color: #7f8c8d; background: #f8f9f9; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Tour Operadora Antigua Viva</h2>
        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>
        <div class="info">
            <strong>Usuarios de prueba:</strong><br>
            Admin: <code>admin</code> / Clave: <code>password</code><br>
            Agente: <code>agente</code> / Clave: <code>password</code>
        </div>
    </div>
</body>
</html>