# SIGEL

Sistema de Gestión de Préstamo de Equipos de Laboratorio.

Autor: Donald Miranda

## Tecnologías

- PHP 8.x.
- Apache.
- MySQL o MariaDB con InnoDB.
- PDO.
- Git.

## Estructura

- config/: configuración privada y ejemplo de configuración.
- includes/: funciones reutilizables.
- storage/images/: imágenes sin acceso web directo.
- storage/logs/: registros de errores.
- sql/: estructura y datos iniciales.
- public/: único directorio expuesto mediante Apache.

## Estado

Preparación inicial del entorno.

## Regla individual

Quien registra un préstamo no puede aprobarlo.
La aprobación corresponde a otro usuario con permiso.
