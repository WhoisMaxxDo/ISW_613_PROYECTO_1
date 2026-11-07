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

La autenticación, los CRUD y la gestión de préstamos están pendientes.

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

Estas cuentas se utilizarán cuando esté implementada la autenticación:

| Rol           | Correo                   | Contraseña de prueba |
| ------------- | ------------------------ | -------------------- |
| Administrador | admin@sigel.utn.ac.cr    | PruebaSIGEL2026!     |
| Operador      | operador@sigel.utn.ac.cr | PruebaSIGEL2026!     |
| Operador      | operador@sigel.utn.ac.cr | PruebaSIGEL2026!     |

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

### 8. Comprobar la instalación inicial

Con Apache y MySQL o MariaDB iniciados abrir:

```text
http://sigel.local/
```

En esta etapa del proyecto debe aparecer el mensaje:

```text
Entorno inicial disponible.
```

Comprobar también que no puedan abrirse los archivos privados mediante
sigel.local ni mediante localhost.

La página inicial únicamente verifica la conexión.
La autenticación y los módulos de gestión todavía están pendientes.
