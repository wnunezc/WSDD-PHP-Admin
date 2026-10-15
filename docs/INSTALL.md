# Instalacion e integracion

Version del paquete: 1.0.0. El contrato siguiente describe lo requerido por el codigo; no afirma haber validado un despliegue Release limpio. La evidencia actual procede de los entornos Dev WSDD.

## Requisitos

- Contenedor Linux con Apache mod_php y PHP CLI del mismo runtime/configuracion.
- POSIX para control de procesos y usuario/grupo www-data; nohup y setsid disponibles.
- Puerto 8989 libre en loopback (configurable mediante WSDD_PHP_ADMIN_PORT).
- Para compilar extensiones: fuentes correspondientes al PHP instalado, helpers docker-php-ext-install/docker-php-ext-enable, phpize, make y compilador. Algunas recetas requieren apt-get y paquetes de repositorios autenticados.
- Montajes persistentes independientes para estado y logs, con permisos adecuados. El proyecto prepara sus directorios internos; no crea montajes Docker ni administra el host.

Solo se han documentado los entornos enumerados en README. El rango de Composer no certifica otros minors ni imagenes.

## Raiz unica y datos separados

En Dev, montar el checkout como proyecto. En Release, el integrador coloca el contenido del paquete en /opt/wsdd-php-admin. Esta ruta contiene codigo, no una copia del perfil. No sobrescribir un despliegue activo ni mezclar versiones/raices entre agente, web y sonda Apache.

Los montajes por defecto son /var/lib/wsdd-php-admin y /var/log/wsdd-php-admin. En una instalacion nueva no copiar datos de desarrollo: runtime:start inicializa el perfil si no existe. Si existe, lo conserva y reconcilia; los metadatos desconocidos no se inventan. No borrar esos montajes al actualizar codigo.

El instalador historico deploy/container/install.sh se conserva solo en el repositorio fuente, fuera del ZIP de distribucion. No forma parte del contrato de arranque ni debe agregarse como nueva llamada en WSDD. El paquete no proporciona un actualizador transaccional.

## Dos rutas HTTP diferentes

1. Panel: Apache actua como proxy a http://127.0.0.1:8989/. El ejemplo deploy/apache/wsdd-php-admin.vhost.example.conf debe adaptarse al dominio y a la seguridad/TLS de la infraestructura.
2. Sonda: debe existir un vhost Apache directo cuyo DocumentRoot sea el public de la misma raiz. Su Host se configura mediante WSDD_PHP_ADMIN_APACHE_PROBE_HOST; el proyecto hace la peticion por http://127.0.0.1:80/runtime-probe.php. Esa ruta NO debe volver a pasar por el proxy de 8989. El endpoint exige loopback y clave propia.

Se incluye deploy/apache/wsdd-php-admin.probe.example.conf como ejemplo de la segunda ruta. Habilitar proxy/proxy_http para el panel y mod_php para la sonda es responsabilidad de la infraestructura. Apache debe estar disponible para la verificacion de arranque. No usar un vhost FPM o PHP CGI como sustituto: la sonda exige apache2handler.

## Entry point automatizado

El integrador invoca como root, desde una sola raiz instalada:

```sh
export WSDD_PHP_ADMIN_APACHE_PROBE_HOST=phpadmin-runtime.internal
php /opt/wsdd-php-admin/bin/wsdd-php-admin runtime:start
```

Es la llamada que debe automatizar el arranque del contenedor, no una tarea manual del usuario final. En WSDD se mantiene su deteccion Dev/Release y su llamada runtime:start; no ejecutar agent y web por separado. El integrador decide como coordinar esta llamada con Apache y el proceso principal del contenedor.

runtime:start valida/reconcilia antes de declarar agente/web disponibles. No es un supervisor permanente ni descarga/instala su propio codigo. El paquete debe estar presente antes de invocarlo. No instalar Docker dentro del contenedor ni montar su socket para este proyecto.

## Configuracion por entorno

| Variable | Valor por defecto |
| --- | --- |
| WSDD_PHP_ADMIN_PROFILE_DIR | /var/lib/wsdd-php-admin |
| WSDD_PHP_ADMIN_LOG_DIR | /var/log/wsdd-php-admin |
| WSDD_PHP_ADMIN_AGENT_LOG | /var/log/wsdd-php-admin/agent.log |
| WSDD_PHP_ADMIN_AUDIT_LOG | /var/log/wsdd-php-admin/audit.log |
| WSDD_PHP_ADMIN_INI_DIR | /usr/local/etc/php/conf.d |
| WSDD_PHP_ADMIN_INI_PREFIX | zz-wsdd-php-admin- |
| WSDD_PHP_ADMIN_PORT | 8989 |
| WSDD_PHP_ADMIN_APACHE_PROBE_HOST | Nombre del directorio raiz; establecer explicitamente fuera de Dev |
| WSDD_PHP_ADMIN_DEFAULT_USERNAME | admin, solo sin credenciales persistentes |
| WSDD_PHP_ADMIN_DEFAULT_PASSWORD | admin, con cambio obligatorio inicial |

Las variables deben llegar al agente y a la web. Las rutas separadas de log deben configurarse explicitamente si se cambia el directorio base. La aplicacion no carga automaticamente archivos .env. No publicar valores reales de contrasenas en archivos de distribucion.
