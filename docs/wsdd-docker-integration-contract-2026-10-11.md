# Contrato WSDD/Docker para WSDD-PHP-Admin

Fecha: 2026-10-11

Actualizado: 2026-10-14

Este documento separa las responsabilidades entre WSDD/Docker y WSDD-PHP-Admin. No reemplaza el plan maestro; sirve como lista operativa para preparar el contenedor PHP donde WSDD-PHP-Admin corre.

## Correccion de primer arranque: 2026-10-14

`runtime:start` carga o inicializa el perfil persistente antes de ejecutar la reconciliacion managed. Reutiliza `ProfileRepository::loadOrInitialize`: crea un perfil vacio del runtime local solo cuando no existe, conserva el existente y detiene el arranque si es invalido. Esta responsabilidad pertenece a WSDD-PHP-Admin; WSDD mantiene la llamada unica a `runtime:start`.

La recreacion general del 2026-10-15 valida el contrato Dev en PHP 5.6/7.2/7.4/8.1/8.2/8.3/8.4: perfil conservado en Windows, agente root y web www-data desde la misma raiz, arranque automatico y siete pruebas post-rebuild aprobadas. Los logs prueban compilacion/reinstalacion bcmath enabled antes de la web en 5.6/7.2/7.4/8.1/8.3. PHP 8.2/8.4 conservaron removed: se cierra su persistencia/arranque, no reinstalacion desde enabled en esta recreacion. Evidencia consolidada y limites: `development-status-2026-10-15.md`. Se mantienen el entrypoint unico y la separacion de responsabilidades; WSDD no instala ni copia codigo desde ZIP. La distribucion Release no queda validada por este cierre Dev.

## Responsabilidad de WSDD/Docker

La observacion actualizada CLI/Apache y su verificacion posterior pertenecen a WSDD-PHP-Admin. Se validaron para PHP 8.3 y 8.4 sin cambios en WSDD Desktop: ver `runtime-observation-2026-10-14.md`. La consulta local usa los vhosts directos ya existentes de los proyectos Dev; el vhost proxy hacia 8989 sigue siendo la entrada de administracion.

### 1. Raiz runtime Dev/Release

WSDD debe preparar la infraestructura para que WSDD-PHP-Admin exista dentro del contenedor, pero no debe embeber el proyecto en `docker-structure.zip` ni copiarlo automaticamente en modo Dev.

Ruta Release futura dentro del contenedor:

```text
/opt/wsdd-php-admin
```

Entrada web:

```text
/opt/wsdd-php-admin/public
```

CLI interna:

```text
/opt/wsdd-php-admin/bin/wsdd-php-admin
```

En desarrollo debe existir un bind mount del proyecto en:

```text
/var/www/html/phpadmin.dock
```

Ese bind mount es la raiz runtime valida para modo Dev. WSDD no debe mezclar raices: agent y web deben arrancar siempre desde la misma raiz.

### 2. Directorio persistente del perfil

WSDD debe crear o montar un directorio persistente del host hacia:

```text
/var/lib/wsdd-php-admin
```

Archivo esperado:

```text
/var/lib/wsdd-php-admin/profile.json
/var/lib/wsdd-php-admin/operations/
```

Este directorio debe sobrevivir reconstrucciones y recreaciones del contenedor. La cola `operations/` pertenece a WSDD-PHP-Admin y contiene solicitudes persistentes para el agent interno.

### 3. Directorio persistente de logs propios

WSDD debe crear o montar un directorio persistente del host hacia:

```text
/var/log/wsdd-php-admin
```

Este directorio es para logs operativos, auditoria y salida de reconciliacion/instalacion. No debe mezclarse con logs de Apache, cron o Webmin.

Archivo esperado para el agent:

```text
/var/log/wsdd-php-admin/agent.log
```

### 4. Integracion HTTP interna

WSDD-PHP-Admin debe quedar accesible por el patron actual de WSDD similar a Webmin.

Contrato actual:

```text
127.0.0.1:8989
```

WSDD debe:

- asignar un dominio interno por version PHP;
- agregar ese dominio a `VIRTUAL_HOST`;
- crear un vhost Apache propio;
- hacer proxy hacia `http://127.0.0.1:8989/`;
- escribir access/error logs del vhost en el arbol persistente correspondiente;
- no interferir con Webmin.

Dominio validado para PHP 8.3:

```text
phpadmin83.wsdd.dock
```

Las demas versiones deben seguir el mismo modelo por version usado por WSDD, por ejemplo `phpadmin82.wsdd.dock`, `phpadmin84.wsdd.dock` o el equivalente que WSDD defina por version.

### 5. Arranque runtime, web y agent interno

WSDD/Docker debe seleccionar una raiz runtime y llamar la CLI interna durante el arranque del contenedor. WSDD-PHP-Admin es dueño del arranque de su web interna y de su agent/root worker; WSDD Desktop solo lo instala o monta, monta rutas persistentes y llama un unico comando estable dentro del `CMD`/entrypoint del contenedor.

Regla de seleccion de raiz:

```text
1. Si existe /var/www/html/phpadmin.dock/bin/wsdd-php-admin, usar /var/www/html/phpadmin.dock como modo Dev.
2. Si no existe Dev, pero existe /opt/wsdd-php-admin/bin/wsdd-php-admin, usar /opt/wsdd-php-admin como modo Release.
3. Si no existe ninguna, registrar que WSDD-PHP-Admin no esta disponible y continuar sin arrancarlo.
```

Comando esperado despues de seleccionar raiz:

```text
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" runtime:start
```

Comando util para pruebas o arranques controlados:

```text
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" agent:once
```

`runtime:start` valida que la raiz seleccionada este completa, crea/verifica rutas persistentes, ejecuta primero una reconciliacion managed sincrona contra `profile.json` y, solo si esa reconciliacion no falla, arranca internamente:

```text
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" reconcile:managed
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" agent
php -S 127.0.0.1:8989 -t "$WSDD_PHP_ADMIN_ROOT/public"
```

El `CMD` de WSDD no debe arrancar `agent` y `php -S` por separado. Tampoco debe ejecutar scripts internos como `deploy/container/install.sh` en runtime.

El modo read-only funciona sin agent root: deteccion, perfil, permisos y reconciliacion de lectura siguen disponibles. El modo managed requiere que el agent pueda correr como root o tenga permisos equivalentes estrictamente limitados para sus propias rutas administradas.

La UI muestra el cambio de estado mediante polling contra la API interna de operaciones. Para que el usuario vea `pending -> success/skipped/failed` en tiempo real inicial, WSDD-integrated mode debe mantener el comando `agent` corriendo en paralelo al servidor web del contenedor. Si Apache se reinicia, la UI debe tolerar fallos temporales de la API: polling normal cada 2 segundos, backoff hasta 10 segundos y tolerancia de 90 segundos antes de tratar la caida como indisponibilidad persistente.

Durante runtime:start, WSDD-PHP-Admin carga o inicializa profile.json y reconcilia internamente antes de levantar la web. El agente toma un lease exclusivo antes de reconciliar o procesar la cola; agent:once y reconcile:managed comparten esa exclusion. El proyecto verifica intencion frente a CLI nuevo y Apache incluso sin modificaciones, propaga fallos y exige disponibilidad comprobada de agente/web antes de informar success. runtime:web es un worker interno con privilegios reducidos, no otro entrypoint que WSDD deba invocar. POSIX, nohup y setsid permiten aislamiento/limpieza de grupos propios; no se instalaron dependencias nuevas ni se modifico WSDD. Evidencia y limites de la correccion: `runtime-reliability-fixes-2026-10-15.md`.

La instalacion pertenece a WSDD-PHP-Admin, con politica PHP/OS y recetas comunes contrastadas con fuentes y herramientas reales. WSDD/Docker no genera catalogos ni construye comandos de instalacion: conserva fuentes/helpers y herramientas de compilacion. Las dependencias apt las ejecuta el proyecto. Esta estandarizacion no agrego responsabilidades ni cambios a WSDD; ver `container-validation-standard-2026-10-14.md`.

La llamada debe ser automatica. El usuario final no debe ejecutarla manualmente.

### 6. Separacion de configuracion administrada

WSDD-PHP-Admin no debe modificar directamente `php.ini` administrado por WSDD.

Los `.ini` administrados por WSDD-PHP-Admin deben vivir en el scan dir de PHP:

```text
/usr/local/etc/php/conf.d
```

Con prefijo propio:

```text
zz-wsdd-php-admin-
```

Ejemplo:

```text
/usr/local/etc/php/conf.d/zz-wsdd-php-admin-bcmath.ini
```

### 7. Permisos controlados

WSDD/Docker debe otorgar las rutas persistentes y arrancar el agent. La logica sensible queda dentro de WSDD-PHP-Admin.

Rutas implicadas:

```text
/var/lib/wsdd-php-admin
/var/lib/wsdd-php-admin/operations
/var/log/wsdd-php-admin
/usr/local/etc/php/conf.d/zz-wsdd-php-admin-*.ini
```

No conviene resolver esto dando escritura libre a `www-data` sobre todo `/usr/local/etc/php/conf.d`.

Contrato esperado:

- la web puede escribir operaciones en `/var/lib/wsdd-php-admin/operations`;
- la web no debe escribir `/usr/local/etc/php/conf.d`;
- el agent corre como root dentro del contenedor o detecta privilegios insuficientes;
- el agent solo toca `.ini` con prefijo `zz-wsdd-php-admin-`;
- WSDD Desktop no implementa instalacion de extensiones ni escribe `.ini` de PHP-Admin.

La forma exacta de arrancar el agent pertenece a WSDD/Docker porque depende del modelo de build y del usuario de runtime. La ejecucion de operaciones pertenece a WSDD-PHP-Admin.

## Responsabilidad de WSDD-PHP-Admin

WSDD-PHP-Admin debe encargarse de:

- UI/UX web;
- deteccion de runtime;
- lectura/escritura del perfil persistente;
- validacion de permisos antes de acciones sensibles;
- catalogo de extensiones;
- motor de instalacion/activacion/desactivacion;
- agent/root worker interno;
- cola persistente de operaciones;
- reconciliacion real;
- auditoria;
- autenticacion, sesion y CSRF;
- validacion de nombres de extensiones;
- generacion de `.ini` con prefijo propio.

## Estado validado de integracion WSDD PHP 8.3

Validado en `WSDD-Web-Server-PHP8.3` tras reconstruccion del contenedor:

- En modo Dev, WSDD-PHP-Admin existe por bind mount en `/var/www/html/phpadmin.dock`.
- La CLI interna Dev existe en `/var/www/html/phpadmin.dock/bin/wsdd-php-admin`.
- `/opt/wsdd-php-admin` no es requerido en modo Dev; queda reservado para Release futuro.
- El estado persistente se monta desde el host hacia `/var/lib/wsdd-php-admin`.
- Los logs persistentes se montan desde el host hacia `/var/log/wsdd-php-admin`.
- `profile.json` persiste despues de reconstruir el contenedor.
- `operations/` persiste despues de reconstruir el contenedor.
- `agent.log` persiste despues de reconstruir el contenedor.
- El `CMD` selecciona una sola raiz runtime Dev o Release y llama `runtime:start`.
- En Dev, agent y web corren desde `/var/www/html/phpadmin.dock`.
- En Release futuro, agent y web deben correr desde `/opt/wsdd-php-admin`.
- El agent ejecuta reconciliacion managed de arranque y registra el resultado en `agent.log`.
- La web ve `can_use_persistent_profile=true` y `can_use_operations_queue=true`.
- La web no puede escribir `/usr/local/etc/php/conf.d`, que queda reservado para el agent root.
- `phpadmin83.wsdd.dock` resuelve desde Windows hacia `127.0.0.1`.
- El vhost Apache de `phpadmin83.wsdd.dock` proxya hacia `http://127.0.0.1:8989/`.
- El perfil con `bcmath` en `desired_state=enabled` fue reconciliado tras reconstruccion y la extension quedo instalada/cargada.

Rutas host observadas:

```text
C:\WSDD-Environment\Docker-Structure\state\php-admin\php83
C:\WSDD-Environment\Docker-Structure\log\php-admin\php83
```

## Bloqueadores actuales para modo managed

Estado: resueltos para PHP 8.3 Apache en el entorno de desarrollo integrado.

1. Crear o montar `/var/lib/wsdd-php-admin` de forma persistente.
2. Dar permisos de escritura controlados al actor web para `profile.json` y `operations/`.
3. Crear o montar `/var/log/wsdd-php-admin` con permisos de escritura adecuados.
4. Arrancar `php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" runtime:start` como root o actor equivalente controlado.
5. Mantener dominio interno y vhost/proxy hacia `127.0.0.1:8989`.
6. Mantener `/usr/local/etc/php/conf.d` sin escritura amplia para `www-data`.

Los puntos anteriores deben mantenerse como criterios de integracion para nuevas versiones PHP.

## Minimo necesario para continuar en este proyecto

El minimo ya fue entregado y validado para PHP 8.3 Apache. Se conserva esta seccion como checklist para otras versiones.

### A. Persistencia del perfil

Debe existir dentro del contenedor:

```text
/var/lib/wsdd-php-admin
```

Y debe ser escribible por el actor definido para administrar el perfil.

Validacion esperada desde WSDD-PHP-Admin:

```text
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" permissions
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" profile:init
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" profile:status
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" operations:list
```

En modo Dev, `WSDD_PHP_ADMIN_ROOT` debe ser `/var/www/html/phpadmin.dock`. En Release futuro, debe ser `/opt/wsdd-php-admin`.

### B. Logs propios

Debe existir dentro del contenedor:

```text
/var/log/wsdd-php-admin
```

Y debe ser escribible por el actor definido para logs operativos/auditoria.

### C. Agent root para `.ini` administrados

Debe arrancar el runtime de WSDD-PHP-Admin:

```text
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" runtime:start
```

El agent es quien escribira archivos:

```text
/usr/local/etc/php/conf.d/zz-wsdd-php-admin-*.ini
```

Sin permitir escritura destructiva sobre configuracion base o archivos de otros sistemas.

### D. Arranque WSDD-integrated

```text
php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" runtime:start
```

El comando `reconcile` queda como verificacion read-only. La reconciliacion managed de arranque la ejecuta `runtime:start` antes de iniciar la web interna y el agent; el agent mantiene una segunda reconciliacion idempotente para ejecuciones directas.

## Lo que puede esperar

No es necesario resolver todavia:

- soporte PHP 8.0;
- soporte PHP 8.5;
- PHP-FPM;
- autenticacion final;
- rollback real;
- catalogo completo multi-version.

El reload controlado de Apache con `apache2ctl graceful` ya existe para cambios reales de `.ini` administrados. Los demas puntos pueden avanzar despues de estabilizar reconciliacion managed y catalogo inicial.
