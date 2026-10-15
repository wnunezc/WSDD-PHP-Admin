# Arquitectura inicial: WSDD-PHP-Admin

Fecha: 2026-10-11

Actualizado: 2026-10-15

Este documento resume la arquitectura inicial propuesta a partir del plan maestro `docs/php-container-admin-requirements-2026-10-11.md`. No agrega requisitos nuevos; solo organiza el proyecto para que el desarrollo pueda avanzar por fases sin mezclar responsabilidades.

Revision vigente: `development-status-2026-10-15.md`. Los siete entornos Dev validaron persistencia, arranque y continuidad observada del agente tras recreacion. La propagacion de fallos de reconciliacion, exclusion y readiness ya se corrigieron con validadores especificos: `runtime-reliability-fixes-2026-10-15.md`. Siguen pendientes requisitos funcionales del plan; no se declara supervision automatica ni instalacion Release terminadas.

## Principios

- La aplicacion corre dentro del mismo contenedor PHP que administra.
- El perfil persistente vive fuera del filesystem efimero del contenedor y se monta desde WSDD.
- La UI usa una API interna; el usuario final no ejecuta comandos manuales.
- La CLI existe para instalacion, deteccion, reconciliacion operativa y agent interno, llamada por build, entrypoint o WSDD Desktop.
- La configuracion administrada usa zonas propias y no sobrescribe vhosts, cron, logs, proyectos ni `php.ini` administrados por WSDD.
- La web no ejecuta operaciones privilegiadas; crea operaciones persistentes para el agent/root worker.
- El primer objetivo tecnico es PHP 8.3 Apache + CLI; PHP-FPM y otras imagenes quedan como compatibilidad futura por niveles.

## Componentes

### Web UI

Entrada publica en `public/`. Muestra estado PHP, extensiones, perfil, permisos, reconciliacion read-only y cola de operaciones. En modo managed solo crea solicitudes persistentes; no escribe configuracion PHP directamente.

### API interna

Capa HTTP para listar runtime, extensiones, perfil, permisos, reconciliacion y operaciones. Debe validar entradas, proteger acciones sensibles y no exponer ejecucion arbitraria de comandos. La creacion de operaciones escribe JSON en `/var/lib/wsdd-php-admin/operations/`.

### CLI interna

Entrada en `bin/wsdd-php-admin`. Debe reservar comandos operativos como:

- `detect`
- `reconcile`
- `reconcile:managed`
- `runtime:start`
- `agent`
- `agent:once`
- `operations:list`
- `operations:create`

La CLI no es una interfaz para usuario final; sera llamada por instalacion, entrypoint o WSDD Desktop. En modo WSDD-integrated, WSDD Desktop selecciona una raiz runtime y llama `runtime:start`; WSDD-PHP-Admin arranca internamente su web y su agent desde esa misma raiz. WSDD no implementa la logica de extensiones.

### Runtime start

El comando `runtime:start` es el entrypoint estable de WSDD-PHP-Admin dentro del contenedor. Valida que la raiz desde donde fue invocado contenga `bin/`, `config/`, `public/` y `src/`, crea/verifica `/var/lib/wsdd-php-admin/operations` y `/var/log/wsdd-php-admin`, y despacha:

- `agent`, escribiendo en `/var/log/wsdd-php-admin/agent.log`;
- servidor web interno en `127.0.0.1:8989`, sirviendo `public/` desde la misma raiz.

RuntimeStarter ejecuta la misma reconciliacion managed de forma sincrona bajo reserva exclusiva del consumidor, antes de despachar agent y web. No invoca otra CLI que pueda competir por ese lease. El proceso aborta si no se cumple el perfil y solo informa success tras heartbeat reciente del agente y respuesta de salud autenticada de la web. Usa startup.lock y agent.lock; los hijos no heredan esos locks. runtime:web baja privilegios a www-data mediante POSIX y mantiene el grupo de procesos propio para limpieza de fallos. El contrato externo sigue siendo runtime:start.

En desarrollo, WSDD debe invocarlo desde `/var/www/html/phpadmin.dock/bin/wsdd-php-admin`. En Release, debe invocarlo desde `/opt/wsdd-php-admin/bin/wsdd-php-admin`. Mezclar agent desde una raiz y web desde otra es invalido.

### Agent/root worker

Modulo `src/Agent/`. Procesa operaciones persistentes desde `/var/lib/wsdd-php-admin/operations/`, escribe resultados `<id>.result.json` y registra `/var/log/wsdd-php-admin/agent.log`. Debe correr como root o detectar privilegios insuficientes con claridad.

El agent aplica solo operaciones soportadas y seguras:

- `enable_extension`: crea/actualiza un `.ini` propio si la extension ya esta disponible.
- `disable_extension`: elimina solo el `.ini` propio correspondiente.
- `reconcile`: lee `profile.json` y aplica acciones managed soportadas.
- `install_extension`: instala solo extensiones con receta ejecutable en el catalogo Debian autorizado por la politica comun para la version detectada, usando herramientas permitidas.
- `remove_extension`: remueve `.ini` propios administrados; la remocion binaria no soportada se reporta como `skipped`.
- `rollback_extension`: restaura el ultimo snapshot persistente de `.ini` administrado para una extension.

### Operaciones persistentes

Modulo `src/Operations/`. Valida id UUID, tipo permitido, extension segura y evita path traversal. La web y CLI pueden crear operaciones, pero solo el agent las procesa.

La UI de operaciones usa polling corto contra `?api=operations` para reflejar cambios de estado sin recargar la pagina. Si el agent no esta corriendo, la operacion queda visible como pendiente; cuando el agent toma la operacion, escribe estado `processing`; cuando termina, la UI muestra `success`, `skipped` o `failed`. Durante reinicios de Apache, la UI entra en reconexion con backoff hasta 10 segundos y tolera hasta 90 segundos de API no disponible antes de mostrar indisponibilidad persistente. La API lista primero las operaciones mas recientes.

Al arrancar el loop `agent`, antes de procesar la cola, se ejecuta una reconciliacion managed unica contra `profile.json`. Instala extensiones faltantes solo con receta ejecutable del catalogo Debian autorizado por la politica comun; crea el INI propio para enabled disponible, lo elimina para disabled/removed y omite extensiones cargadas por configuracion base o estados no soportados. Apache solo se recarga si hubo cambios reales. RuntimeVerifier confirma el estado en CLI nuevo y Apache despues de cambios.

El agent no debe usar su propio `extension_loaded()` como verdad para decidir estados actuales, porque es un proceso PHP largo. Las decisiones operativas consultan la configuracion efectiva con un proceso PHP fresco. Si `docker-php-ext-install` crea un `docker-php-ext-<extension>.ini`, WSDD-PHP-Admin lo adopta hacia el prefijo administrado `zz-wsdd-php-admin-` para mantener desactivacion, remocion y rollback bajo control propio.

La instalacion real usa `ExtensionCatalog` y `ExtensionInstaller`. El catalogo comun mantiene una lista allowlisted de extensiones, paquetes apt requeridos y argumentos de configuracion permitidos. El instalador no acepta comandos libres: solo ejecuta `apt-get`, `docker-php-ext-configure`, `docker-php-ext-install` y `docker-php-ext-enable` cuando la extension existe en el catalogo. Si la extension no esta catalogada, falta una herramienta requerida o falla un paso, la operacion termina como `skipped` o `failed` y no se registra como aplicada en el perfil.

El resultado `processing` no se considera terminal. Si el agent se interrumpe despues de escribir ese estado y antes del resultado final, el siguiente ciclo del agent puede retomar la operacion.

El selector de extension depende de la accion seleccionada. `install_extension` muestra candidatas del catalogo inicial que no estan disponibles/cargadas; `disable_extension` y `remove_extension` muestran extensiones cargadas; `reconcile` no requiere extension.

Formato base:

```json
{
  "id": "uuid",
  "type": "enable_extension|disable_extension|install_extension|remove_extension|rollback_extension|reconcile",
  "extension": "bcmath",
  "requested_at": "ISO-8601",
  "requested_by": "web",
  "payload": {}
}
```

### INI administrado

Modulo `src/Ini/`. Centraliza escritura atomica de archivos `.ini` propios en `WSDD_PHP_ADMIN_INI_DIR` con prefijo `WSDD_PHP_ADMIN_INI_PREFIX`. Nunca toca `php.ini` ni archivos sin el prefijo propio. Cuando aplica, deja owner `root:root` y modo `0644`.

### Reload de servicio

Modulo `src/Service/`. Ejecuta reload controlado solamente con comandos allowlisted. En Apache usa `apache2ctl graceful` o `apachectl graceful` cuando alguno existe. El reload se intenta solo cuando una operacion cambia realmente un `.ini` administrado.

### Detector de runtime

Modulo `src/Runtime/`. Debe detectar binario PHP, version, `php.ini`, scan dir, runtime Apache/FPM/CLI, herramientas disponibles, sistema operativo, gestor de paquetes y permisos efectivos.

### Perfil persistente

Modulo `src/Profile/`. Debe leer y escribir el estado deseado, estado observado, metadata de extensiones, resultados de reconciliacion y compatibilidad. El directorio por defecto esperado dentro del contenedor es `/var/lib/wsdd-php-admin`, configurable por `WSDD_PHP_ADMIN_PROFILE_DIR`.

Cuando el agent aplica una operacion managed que cambia un `.ini` propio, registra `desired_state`, `managed_by`, `source` y `updated_at` en `profile.json`. Las operaciones `skipped` no cambian la intencion declarativa.

### Catalogo y motor de extensiones

Modulo `src/Extensions/`. Selecciona recetas allowlisted por distribucion desde `config/extension-catalog.php` y versiones PHP revisadas desde `config/php-compatibility.json`, actualmente 5.6, 7.2, 7.4, 8.1, 8.2, 8.3 y 8.4 en Debian. El minimo sintactico es PHP 5.6; PHP 8.0 no recibe recetas por estar dentro de un intervalo. CatalogEnvironment detecta el entorno y BundledExtensionDiscovery inspecciona fuentes coincidentes sin extraerlas. UI e instalador usan el mismo resolver, y las entradas sin receta/fuente/herramientas no se ofrecen para instalar. Las variantes de GD/ZIP antiguas son datos del mismo catalogo, no otro instalador. Una receta ejecutable no garantiza la disponibilidad ni autenticidad de repositorios externos: el instalador falla cerrado ante errores APT y no registra exito. Evidencia y limites: `php56-php72-validation-2026-10-15.md`.

### Compatibilidad del codigo comun

`src/Support/TypeGuard.php` conserva contratos escalares/de retorno y los DTO mantienen propiedades privadas de solo lectura. `JsonCodec` centraliza errores JSON sin depender de JSON_THROW_ON_ERROR. `Process` adapta argv/env a proc_open anterior a 7.4, escapando cada argumento y restaurando el entorno padre; CommandRunner mantiene timeout y drenaje de ambos streams. ExecutableFinder detecta herramientas incluso cuando su modifica PATH. No se introduce un vendor o una copia runtime diferente para PHP antiguo.

La sesion conserva HttpOnly y SameSite=Strict en PHP anteriores a 7.3 mediante cabecera de cookie explicita. La aleatoriedad antigua exige OpenSSL criptograficamente fuerte. HttpErrorHandler devuelve 503 sin detalles privados ante excepciones no controladas; no presenta una pagina fatal con 200. Los INI propios de PHP 5.6 incluyen .so, y Zend usa la ruta absoluta requerida. Todas estas adaptaciones se comprueban bajo los interpretes reales, no solo mediante la version local de PHP.

### Reconciliacion

Modulo `src/Reconciliation/`. Compara perfil persistente contra runtime observado en modo read-only. La reconciliacion managed se expresa como operacion persistente y la aplica el agent.

### Auditoria

Modulo `src/Audit/`. Debe registrar actor, accion, fecha, extension/configuracion afectada, resultado, salida relevante y estado antes/despues.

La implementacion base escribe eventos JSONL en `audit.log` para creacion y procesamiento de operaciones, rechazos de autenticacion/CSRF y eventos de sesion.

### Seguridad HTTP

Modulo `src/Security/`. La superficie web usa sesiones PHP con cookie `HttpOnly` y `SameSite=Strict`. La autenticacion usa usuario/contraseña persistente en `/var/lib/wsdd-php-admin/auth.json`, sin base de datos. Si el archivo no existe, el acceso inicial es `admin` / `admin` y obliga a cambiar la contraseña antes de usar la app. Los POST que crean operaciones requieren CSRF.

### Recuperacion

Modulo `src/Recovery/`. Antes de cambiar un `.ini` administrado, el agent crea un snapshot persistente en `/var/lib/wsdd-php-admin/backups/ini`. La operacion `rollback_extension` restaura el ultimo snapshot de esa extension, ya sea reponiendo el contenido anterior o eliminando el `.ini` si antes no existia.

## Estructura base

```text
bin/
  wsdd-php-admin
  validate-agent-safety
  validate-agent-response
config/
  wsdd-php-admin.php
deploy/
  apache/
    wsdd-php-admin.vhost.example.conf
  container/
    install.sh
docs/
  architecture-initial-2026-10-11.md
  php-container-admin-requirements-2026-10-11.md
public/
  index.php
src/
  Agent/
  Audit/
  Extensions/
  Http/
  Ini/
  Operations/
  Profile/
  Reconciliation/
  Recovery/
  Runtime/
  Security/
  Service/
  Support/
var/
  cache/
  logs/
```

## Contratos iniciales

- Puerto interno reservado: `8989`.
- Host interno de escucha para la app integrada: `127.0.0.1`.
- Dominio WSDD validado para el entorno PHP 8.3: `phpadmin83.wsdd.dock`.
- Ruta de perfil persistente por defecto: `/var/lib/wsdd-php-admin`.
- Ruta de operaciones persistentes: `/var/lib/wsdd-php-admin/operations`.
- Ruta de `.ini` administrados por defecto: `/usr/local/etc/php/conf.d`.
- Prefijo de archivos `.ini` administrados por defecto: `zz-wsdd-php-admin-`.
- Logs propios por defecto: `/var/log/wsdd-php-admin`.
- Log del agent por defecto: `/var/log/wsdd-php-admin/agent.log`.
- Modo read-only: no requiere agent root.
- Modo managed: requiere agent root o permisos equivalentes controlados.
- Autenticacion web: usuario/contraseña persistente en `/var/lib/wsdd-php-admin/auth.json`; default inicial `admin` / `admin` con cambio obligatorio.
- CSRF: requerido para POST de operaciones.
- Auditoria: `/var/log/wsdd-php-admin/audit.log`.
- Backups de rollback: `/var/lib/wsdd-php-admin/backups/ini`.
- En el entorno WSDD PHP 8.3, modo Dev usa el codigo montado en `/var/www/html/phpadmin.dock`; modo Release futuro usa la instalacion integrada en `/opt/wsdd-php-admin`.
- En el entorno WSDD PHP 8.3 validado, estado y logs persisten en `C:\WSDD-Environment\Docker-Structure\state\php-admin\php83` y `C:\WSDD-Environment\Docker-Structure\log\php-admin\php83`.
- En el entorno WSDD PHP 8.3 validado, el `CMD` de WSDD selecciona la raiz Dev y llama `runtime:start`; el agent y la web interna arrancan desde esa misma raiz.
- En el entorno WSDD PHP 8.3 validado, `bcmath` se instalo y cargo despues de una reconstruccion porque estaba declarada como `desired_state=enabled` en el perfil persistente.
- En el entorno WSDD PHP 8.3 validado, la Fase 5 paso pruebas no destructivas dentro del contenedor: login usuario/contraseña, cambio obligatorio de contraseña inicial, API `state`, API `compatibility`, vista `operations`, CSRF `419` sin token CSRF valido, `audit.log`, directorio de backups y `bin/validate-security-hardening`.

## Primeras tareas de implementacion

1. Implementar detector de runtime para PHP 8.3 Apache + CLI. Estado: hecho.
2. Definir formato versionado inicial del perfil persistente. Estado: hecho.
3. Mostrar dashboard minimo con version PHP, runtime y extensiones cargadas. Estado: hecho.
4. Agregar validacion de permisos antes de cualquier accion sensible. Estado: hecho.
5. Implementar reconciliacion de solo lectura antes de instalar o modificar extensiones. Estado: hecho.
6. Implementar agent interno y cola persistente de operaciones managed. Estado: hecho.
7. Integrar persistencia, logs y arranque del agent con WSDD PHP 8.3. Estado: hecho.
8. Implementar reconciliacion managed de arranque desde `profile.json` para acciones soportadas sobre `.ini` propios. Estado: hecho.
9. Implementar instalacion real de extensiones faltantes desde catalogo validado. Estado: hecho y validado en contenedor PHP 8.3 con `bcmath`.
10. Integrar `runtime:start` con WSDD PHP 8.3 usando raiz Dev/Release consistente. Estado: hecho y validado en modo Dev.
11. Implementar hardening base: autenticacion configurable, CSRF, auditoria y rollback basico. Estado: hecho y validado localmente y dentro de `WSDD-Web-Server-PHP8.3`.
12. Preparar matriz y codigo comun multi-version. Estado: hecho para 5.6/7.2/7.4/8.1-8.4 con downgrade de sintaxis/API y pruebas por interprete; fase global abierta para 8.0/8.5, dependencias Stretch y evidencias/requisitos restantes. Ver `development-status-2026-10-15.md`.
