# SIGEL

Sistema de Gestión de Préstamo de Equipos de Laboratorio.

Autor: Donald Miranda

## Tecnologías

- PHP 8.x.
- Apache.
- MySQL con InnoDB.
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

Proyecto en desarrollo. Se definieron la estructura inicial,
el esquema relacional y los datos de prueba.

- Sesión persistente mediante selector y validador.
- Validación y rotación del token al recuperar una sesión.
- Revocación del token al cerrar sesión.

La cookie de Recordarme vence 14 días después de su creación.
La rotación no extiende ese plazo.

Una sesión autenticada que alcanza 30 minutos de inactividad
se cierra y revoca su recordatorio cuando se detecta el vencimiento.

## Implementado

- CRUD de categorías exclusivo del administrador.
- Búsqueda por nombre y listado paginado.
- Eliminación lógica mediante eliminado_en.
- Preferencia de paginación de categorías mediante cookie validada.

La paginación admite 5, 10, 20 o 50 registros.
Ante una cookie ausente o inválida se utilizan 10 registros.

Las categorías admiten entre 1 y 3650 días máximos de préstamo.
Los nombres permanecen reservados aunque la categoría se elimine
lógicamente.

## Categorías

El administrador puede crear, buscar, listar, editar y eliminar
lógicamente categorías.

La cantidad de registros por página se guarda en la cookie
sigel_categorias_por_pagina.

Se permiten 5, 10, 20 o 50 registros. Cuando la cookie falta
o contiene un valor inválido, se utilizan 10.

## Usuarios

Administración exclusiva del rol administrador:

- Crear, buscar, listar y editar usuarios.
- Contraseña opcional al editar.
- Activar y desactivar cuentas.
- Eliminar lógicamente y revocar sus tokens persistentes.
- Paginación con preferencia validada mediante cookie.

Un administrador no puede eliminarse, desactivarse ni quitarse
su propio rol administrativo.

## Equipos

El administrador puede crear, buscar, listar, editar y eliminar
lógicamente equipos. El operador puede consultar el inventario.

La paginación admite 5, 10, 20 o 50 registros y utiliza una cookie
validada. El valor predeterminado es 10.

El campo version detecta modificaciones realizadas desde formularios
desactualizados. Los códigos son únicos y admiten letras ASCII,
números, guiones y guiones bajos.

No se permite eliminar equipos asociados a préstamos activos.

La carga de imágenes y los equipos consultados recientemente
todavía están pendientes.

## Imágenes y equipos recientes

Cada equipo admite varias imágenes, agregadas individualmente
por el administrador. Administradores y operadores pueden consultarlas.

Se permiten JPEG, PNG y WebP, con un máximo de 2 MiB por archivo.
Se valida la extensión, el MIME real con finfo y el tamaño real.
El servidor genera el nombre del archivo.

Las imágenes se guardan en storage/images, fuera del directorio
público, y se entregan mediante imagen.php después de validar el acceso.

storage/images debe permitir escritura al usuario de Apache.

La cookie sigel_equipos_recientes conserva hasta cinco identificadores
sin duplicados durante 30 días. Su contenido se valida y los equipos
se consultan nuevamente en la base de datos.

## Préstamos

Administradores y operadores pueden registrar y gestionar préstamos
con uno o varios equipos.

Estados:
solicitado, aprobado, entregado, devuelto y cancelado.

Los intervalos de préstamos activos no pueden traslaparse para
un mismo equipo. Las operaciones utilizan transacciones y bloqueos
de InnoDB.

Quien registra el préstamo no puede aprobarlo, aunque sea administrador.
La devolución registra todos los equipos del préstamo.

La creación y los cambios de estado se registran en bitácora.
Las fechas se interpretan en America/Costa_Rica.

## Reportes y CSV

El reporte de equipos más prestados es exclusivo del administrador.

Cuenta préstamos en estado entregado o devuelto, según su fecha
de inicio. La fecha final seleccionada se incluye completa.
Los préstamos solicitados, aprobados y cancelados no se contabilizan.

Los equipos eliminados lógicamente conservan su participación
histórica en el reporte.

La exportación utiliza fputcsv() y neutraliza prefijos de fórmulas
en las celdas exportadas.

## Regla individual

Quien registra un préstamo no puede aprobarlo.
La aprobación corresponde a otro usuario con permiso.

## Instalación local con XAMPP en macOS

### 1. Requisitos del entorno

- PHP 8.x con las extensiones PDO y pdo_mysql.
- Apache 2.4.
- MySQL con soporte para InnoDB.
- Git.
- XAMPP instalado en /Applications/XAMPP.

### 2. Obtener el proyecto

Clonar el repositorio y entrar en la carpeta:

```bash
git clone https://github.com/WhoisMaxxDo/ISW_613_PROYECTO_1.git "Proyecto 1"
cd "Proyecto 1"
```

Para utilizar las rutas de Apache descritas a continuación, la carpeta
debe quedar ubicada en:

```text
/Applications/XAMPP/xamppfiles/htdocs/Proyecto 1
```

Si se instala en otra ubicación, ajustar las rutas del virtual hosst

### 3. Configurar Apache

En /Applications/XAMPP/xamppfiles/etc/httpd.conf, comprobar que
la siguiente inclusión esté habilitada y no duplicada:

```apache
Include etc/extra/httpd-vhosts.conf
```

En /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf,
conservar las configuraciones existentes y agregar, sin duplicarlos,
los siguientes bloques.

Este bloqueo debe estar fuera de los bloques VirtualHost:

```apache
<Directory "/Applications/XAMPP/xamppfiles/htdocs/Proyecto 1">
    AllowOverride None
    Require all denied
</Directory>
```

Virtual host de la aplicación:

```apache
<VirtualHost *:80>
    ServerName sigel.local
    DocumentRoot "/Applications/XAMPP/xamppfiles/htdocs/Proyecto 1/public"

    <Directory "/Applications/XAMPP/xamppfiles/htdocs/Proyecto 1/public">
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

Agregar en /etc/hosts (esto en MacOS):

```text
127.0.0.1 sigel.local
```

Comprobar la configuración:

```bash
/Applications/XAMPP/xamppfiles/bin/httpd -t
```

Si el resultado es Syntax OK, reiniciar Apache desde XAMPP.

La dirección local de la aplicación es:

```text
http://sigel.local/
```

Solo public/ debe ser accesible desde la web. Las carpetas config/,
includes/, storage/ y sql/ no deben servirse mediante sigel.local
ni mediante rutas alternativas de localhost.

### 4. Crear la base de datos

Iniciar MySQL desde XAMPP.

Ejecutar los siguientes comandos desde la raíz del proyecto,
primero el esquema y después los datos iniciales:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root -p < sql/schema.sql
/Applications/XAMPP/xamppfiles/bin/mysql -u root -p sigel < sql/seed.sql
```

Los comandos solicitan la contraseña del usuario root de MySQL.
Si se utiliza otra cuenta con permisos para importar, sustituir root en dado casso.

El esquema crea la base sigel y sus tablas, los datos iniciales incluyen dos roles, tres usuarios,
dos categorías y tres equipos.

Estos scripts están destinados a una instalación nueva.

No volver a importarlos sobre una base ya inicializada seria lo ideal

Comprobar las tablas:

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root -p -e "SHOW TABLES FROM sigel;"
```

### 5. Usuarios de prueba

Cuentas iniciales disponibles después de importar seed.sql:

| Rol           | Correo                    | Contraseña de prueba |
| ------------- | ------------------------- | -------------------- |
| Administrador | admin@sigel.utn.ac.cr     | PruebaSIGEL2026!     |
| Operador 1    | operador@sigel.utn.ac.cr  | PruebaSIGEL2026!     |
| Operador 2    | operador2@sigel.utn.ac.cr | PruebaSIGEL2026!     |

Las contraseñas se almacenan como hashes generados con password_hash().
Estas credenciales son exclusivamente para pruebas locales.

Los dos operadores permiten comprobar la regla individual:
quien registra un préstamo no puede aprobarlo.

### 6. Configurar la conexión privada

Desde la raíz del proyecto, copiar el archivo de ejemplo:

```bash
cp config/database.example.php config/database.php
```

Editar config/database.php con los datos locales de MySQL o MariaDB:

- host: dirección del servidor.
- port: puerto del servidor.
- name: sigel.
- user: usuario de la base de datos.
- password: contraseña del usuario.

La cuenta debe tener acceso a la base sigel.
Para el uso de la aplicación se recomienda una cuenta con permisos
limitados a esta base de datos.

config/database.php está excluido mediante .gitignore.
No se deben publicar sus credenciales.

### 7. Registro de errores

Los errores de PHP se registran en:

```text
storage/logs/php-error.log
```

La carpeta storage/logs debe existir y permitir escritura al usuario
que ejecuta Apache. No debe quedar expuesta desde la web.

Los archivos de registro están excluidos del control de versiones.

En el php.ini utilizado por Apache deben estar configuradas
estas opciones:

```ini
display_errors = Off
display_startup_errors = Off
log_errors = On
```

Reiniciar Apache después de modificar php.ini.

### Permisos de almacenamiento

En esta instalación de XAMPP para macOS, Apache ejecuta las peticiones
con el usuario/grupo daemon.

Desde la raíz del proyecto:

```bash
sudo chgrp daemon storage/images storage/logs
sudo chmod 2770 storage/images storage/logs
```

Los permisos se limitan a las carpetas de imágenes y registros.
Si se utiliza otro servidor o usuario de ejecución, deben adaptarse.

Para cargar imágenes, file_uploads debe estar habilitado.
upload_max_filesize debe permitir al menos 2 MiB y post_max_size
debe permitir el archivo más los campos del formulario.

La aplicación limita cada imagen a 2 MiB, independientemente de que
PHP permita cargas mayores. Reiniciar Apache si se modifica php.ini.

### 8. Acceder y comprobar la instalación

Iniciar Apache y MySQL/MariaDB desde XAMPP.

Abrir el acceso de la aplicación:

```text
http://sigel.local/pages/login.php
```

Comprobar:

- Acceso con administrador y operador.
- Restricciones de rol mediante URL directa.
- CRUD, búsqueda, paginación y eliminación lógica.
- Persistencia y validación de las cookies de preferencias.
- Carga y consulta protegida de imágenes.
- Rechazo de préstamos traslapados.
- Rechazo de autoaprobacion.
- Flujo de aprobación, entrega, devolución y cancelación.
- Reporte y descarga CSV con el mismo rango.
- Cierre de sesión y recuperación mediante Recordarme.
- Bloqueo del acceso directo a las carpetas privadas.

Las pruebas de concurrencia deben comprobar que dos solicitudes
simultáneas incompatibles no puedan confirmarse ambaas.
