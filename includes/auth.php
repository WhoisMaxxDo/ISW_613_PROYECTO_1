<?php

function exigirAutenticacion(PDO $pdo): array
{
    header('Cache-Control: no-store');

    if (!isset($_SESSION['usuario_id'])) {
        if(!recuperarSesionRecordada($pdo)) {
            header('Location: /pages/login.php');
            exit;
        }
    }

    $ultimaActividad = $_SESSION['ultima_actividad'] ?? 0;

    if (time() - $ultimaActividad >= 1800) {
        $_SESSION = [];

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('No se pudo renovar la sesion.');
        }

        if (!recuperarSesionRecordada($pdo)) {
            header('Location: /pages/login.php');
            exit;
        }
    }

    $consulta = $pdo->prepare(
        'SELECT usuarios.id, usuarios.nombre, roles.nombre AS rol
        FROM usuarios
        INNER JOIN roles ON usuarios.rol_id = roles.id
        WHERE usuarios.id = :id
        AND usuarios.activo = 1
        AND usuarios.eliminado_en IS NULL'
    );

    $consulta->execute([
        ':id' => $_SESSION['usuario_id'],
    ]);

    $usuario = $consulta->fetch();

    if (!$usuario) {
        revocarRecordatorio($pdo);
        cerrarSesion();

        header('Location: /pages/login.php');
        exit;
    }
    $_SESSION['ultima_actividad'] = time();
    return $usuario;
 }

 function exigirRol(array $usuario, array $rolesPermitidos): void
 {
    if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
        http_response_code(403);

        exit('no tiene permiso para acceder a esta pagina');
    }
 }