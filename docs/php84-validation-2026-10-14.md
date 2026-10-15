# Validacion PHP 8.4 en WSDD

Fecha local: 2026-10-14. Los timestamps del contenedor corresponden al 2026-10-15 UTC.

Actualizacion 2026-10-15: la recreacion general valido nuevamente PHP 8.4.26, contenedor 2b021fc93cc8, agente root PID 1762 y web www-data PID 1767 desde la misma raiz. Perfil/credenciales/cola conservados, cero pendientes y ciclos del agente avanzando con procesos estables. La prueba post-rebuild aprobo CLI/Apache/UI sin drift. Las extensiones del perfil estaban removed, asi que esta recreacion no acredita reinstalacion desde enabled. Estado conjunto: `development-status-2026-10-15.md`.

## Alcance y resultado

Contenedor real `WSDD-Web-Server-PHP8.4`, PHP 8.4.26, proyecto en `/var/www/html/phpadmin84.dock`, estado y logs independientes de PHP 8.3. La primera bateria encontro un desfase reproducible y el catalogo definido para PHP 8.3. El desfase se corrigio: ver `runtime-observation-2026-10-14.md`. Posteriormente se implemento y probo el catalogo inicial PHP 8.4: ver `extension-catalog-2026-10-14.md`. Las limitaciones listadas abajo describen la bateria inicial; no son una declaracion de que el catalogo siga ausente.

## Pruebas satisfactorias

- Tras el reinicio del usuario, `runtime:start` creo el perfil ausente antes de reconciliar. Agente root y web www-data arrancaron desde la misma raiz.
- Pasaron `validate-profile-bootstrap`, `validate-agent-safety`, `validate-agent-response`, `validate-security-hardening` y `validate-managed-reconciliation` en PHP 8.4.
- API anonima rechazada con 401. Login inicial, cambio obligatorio de password y acceso autenticado real comprobados. Se configuro la password de desarrollo indicada por el usuario; no se almacena en el script de pruebas.
- Las siete vistas y ocho recursos API respondieron con contenido autenticado. Crear operaciones sin CSRF devolvio 419.
- `calendar` estaba ausente antes de la primera prueba. Se instalo realmente mediante la cola web y el agente. Se verificaron desactivacion, rollback desde snapshot persistente, nueva desactivacion, activacion, reconciliacion idempotente y retirada final del INI administrado.
- Las operaciones registraron success, el perfil cambio junto al INI y los reloads Apache devolvieron success. La reconciliacion sin cambios omitio el reload.
- Se comprobaron snapshots y auditoria persistentes. Apache continuo con configuracion valida y los procesos del agente/web siguieron activos.

## Fallos y limites

1. La web interna `php -S` conserva las extensiones del proceso al arrancar. Despues de instalar, activar o restaurar `calendar`, un PHP CLI nuevo la ve cargada, pero la API interna no y reporta una incidencia. Se reprodujo en dos ciclos. La consulta autenticada directa mediante Apache confirmo `apache2handler`, `calendar` cargada y 0 incidencias mientras el perfil estaba enabled. `RuntimeDetector::detect()` usa `get_loaded_extensions()` del proceso que atiende la solicitud; `ServiceReloader` recarga Apache, no el servidor interno. Este defecto pertenece a WSDD-PHP-Admin.
2. `ExtensionCatalog` declara el catalogo PHP 8.4 como missing, pero entrega entradas de `PHP_83_APACHE_CATALOG` sin seleccionar por version. El exito con calendar no valida todas esas entradas ni sus dependencias para PHP 8.4.
3. Remove retira el INI propio y marca removed; no desinstala el binario. Esa limitacion se mantuvo explicita y se verifico.
4. No se reconstruyo el contenedor en esta bateria ni se probaron todas las extensiones, fallos de red/repositorios, fallbacks o compatibilidad con otras versiones PHP. No se hizo validacion visual en navegador ni de interaccion JavaScript; se verificaron HTML autenticado, API y cola por HTTP.

## Estado final

`calendar` queda con desired_state=removed, sin INI administrado y sin carga en PHP nuevo; el binario compilado se conserva. Se mantienen operaciones, snapshots y auditoria como evidencia. El contenedor no se destruyo ni se reinicio para la bateria. La fase 6 sigue abierta.

Script reproducible actualizado: `bin/validate-runtime-integration.ps1`, con version PHP y password de desarrollo como argumentos. Comprueba estados reales y verificacion posterior; devuelve error si la web interna difiere del PHP CLI nuevo. Siempre intenta retirar el INI de calendar al terminar el ciclo. Solo admite calendar inicialmente no cargada y sin perfil o con estado removed. Ejecutarlo modifica el estado de prueba y registra operaciones reales. Las limitaciones enumeradas arriba describen la bateria inicial; la evidencia posterior esta en el informe de observacion actualizado.
