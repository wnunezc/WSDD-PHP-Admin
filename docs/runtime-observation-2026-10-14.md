# Observacion actualizada del runtime

Fecha local: 2026-10-14.

Actualizacion 2026-10-15: las siete pruebas post-rebuild de lectura aprobaron tras la recreacion general, con CLI/Apache/UI concordantes y cero incidencias. Este documento conserva la implementacion y evidencia original 8.3/8.4; el estado conjunto y los defectos de reconciliacion/arranque aun abiertos estan en `development-status-2026-10-15.md`. Observacion fresca correcta no equivale por si sola a reporte de reconciliacion infalible.

## Comportamiento

La web interna no usa sus propias extensiones cargadas como estado del PHP administrado. `RuntimeSnapshot::freshCli()` ejecuta un proceso nuevo y devuelve un snapshot JSON con version, SAPI, configuracion y extensiones. El proceso tiene limite de cinco segundos; errores de arranque, stderr o JSON invalido no se convierten en una observacion exitosa.

Desde `cli-server`, `RuntimeDetector` tambien consulta PHP ejecutado por Apache. El snapshot de Apache es la fuente para Overview, Extensions y Reconciliation; la API publica la fuente y las observaciones separadas de CLI y Apache. Si Apache no responde, la API identifica el snapshot restante como CLI y agrega un error de observacion a la reconciliacion. Nunca declara Apache observado usando solo CLI.

Las vistas de lectura refrescan su contenido cada cinco segundos cuando estan visibles. Si la consulta falla, muestran `Runtime update unavailable` hasta recibir otra vista valida. Operations consulta el estado cada dos segundos, mantiene el backoff existente hasta diez segundos y su ventana de reconexion de noventa segundos, y actualiza tambien las opciones por accion conservando la seleccion cuando sigue disponible.

## Consulta de Apache

`public/runtime-probe.php` devuelve exclusivamente metadata de PHP y extensiones. Exige SAPI Apache, conexion loopback y una clave aleatoria interna. No acepta credenciales de usuario, comandos ni rutas desde la solicitud. Devuelve 403 si la solicitud no cumple las condiciones y usa Cache-Control no-store.

La clave se crea en `/var/lib/wsdd-php-admin/runtime/probe.key`, con modo 0640. Cuando la crea root, su grupo es www-data y el directorio usa modo 0750. Es dato persistente, no una copia del codigo. La respuesta incluye un nonce y la raiz real del proyecto para rechazar respuestas de otro vhost, otro proyecto o un proxy hacia la misma web interna.

El cliente conecta a `127.0.0.1:80` y solicita el archivo al vhost directo de la aplicacion. En los entornos Dev validados, Host se obtiene de la raiz: `phpadmin.dock` para PHP 8.3 y `phpadmin84.dock` para PHP 8.4. Esos vhosts ya existian; no se modificaron Dockerfiles, Compose ni WSDD Desktop.

Para un despliegue cuya raiz no corresponda al nombre de su vhost directo, el proyecto admite `WSDD_PHP_ADMIN_APACHE_PROBE_HOST`. Release debe proporcionar un vhost que ejecute este archivo bajo Apache; el dominio que solo proxya a 8989 no sirve como prueba de Apache. No se declara validado Release ni PHP-FPM.

## Verificacion posterior

La CLI de produccion conecta `RuntimeVerifier` al procesador y al reconciliador managed. Tras cambios reales de arranque, el reconciliador verifica el estado deseado en CLI nuevo y Apache. Las operaciones exitosas verifican las extensiones del perfil antes de registrar el resultado terminal y la auditoria.

La verificacion espera hasta treinta segundos, reintentando tras errores temporales o respuestas aun desactualizadas durante un graceful reload. Una consulta Apache puede esperar hasta dos segundos y un proceso CLI hasta cinco; la duracion total puede exceder treinta segundos por la ultima consulta en curso. Los detalles del resultado incluyen `verification.observed.cli`, `verification.observed.apache` y errores. Un fallo de verificacion o reload produce failed, indicando que hubo cambios aplicados pero no confirmados; no revierte automaticamente el estado deseado ni finge exito.

Los tests con directorios temporales mantienen sus dependencias aisladas y no configuran verificaciones contra Apache para sus INI ficticios. La bateria de integracion prueba la composicion real usada por la CLI.

## Evidencia PHP 8.3 y 8.4

- Regresion `bin/validate-runtime-observation`: un proceso iniciado sin bz2 conserva su estado original; dos procesos nuevos detectan habilitacion y retirada en INI temporales. Tambien rechaza snapshots invalidos y verifica que CLI exitoso no encubra una observacion Apache fallida.
- Las cinco validaciones previas pasaron en ambos contenedores durante esta tarea.
- `bin/validate-runtime-integration.ps1` paso en PHP 8.3.35 y PHP 8.4.26: instalacion real de calendar, desactivacion, rollback, nueva desactivacion, activacion, reconciliacion y retirada. Cada resultado exitoso incluyo verificacion CLI y Apache y 0 incidencias en la API web.
- Playwright verifica las acciones del formulario y el cambio automatico del contador de Overview sin recargar la pagina manualmente. Incluye capturas desktop/mobile y comprueba ausencia de errores JavaScript.
- Playwright tambien simula el fallo de la actualizacion de la vista y comprueba que aparece el aviso de indisponibilidad y desaparece cuando vuelve la conexion. Las dos versiones pasaron.
- Los procesos php -S conservaron PID 1494 en PHP 8.3 y 1490 en PHP 8.4 durante las baterias. Solo se recargaron los agentes para adoptar su nuevo codigo.
- Calendar queda removed, sin INI administrado y sin cargar; su binario permanece conforme al alcance existente. Bcmath del perfil PHP 8.3 permanece enabled.

Este cierre resolvio el desfase de observacion en las dos versiones probadas. El catalogo PHP 8.4 se implemento posteriormente: ver `extension-catalog-2026-10-14.md`. La fase 6 completa permanece abierta.

Al cerrar inicialmente esta observacion, la matriz marcaba runtime supported / runtime-validated-dev e install_catalog=missing. Tras la implementacion posterior de catalogos, PHP 8.4 declara install_catalog=implemented / catalog-validated-dev; el informe correspondiente detalla que recetas se instalaron realmente.

## Repetir pruebas

### Reconstruccion validada en ambas versiones

Despues de la reconstruccion del usuario, se verificaron nuevamente PHP 8.3.35 y PHP 8.4.26 mediante inspeccion Docker, CLI de lectura, API autenticada y Playwright. No se ejecutaron operaciones de extensiones ni reconciliacion managed manual durante esta revision.

- Los mounts de estado y logs siguen separados por php83/php84. Los perfiles conservan sus fechas de creacion; credenciales, operaciones, snapshots y logs estan disponibles. Los archivos profile.json, auth.json y runtime/probe.key coinciden por SHA256 entre Windows y el contenedor correspondiente, sin exponer credenciales o claves.
- PHP 8.3: el log de este arranque registra instalacion de bcmath desde el perfil, creacion de su INI propio, reload exitoso y verificacion positiva en CLI y Apache. Calendar sigue removed y no cargada. Agente root y servidor interno www-data comparten la raiz Dev.
- PHP 8.4: el arranque registra reconciliacion success sin cambios. No requiere reload ni verificacion posterior a cambios (skipped), porque solo contiene calendar removed. La observacion actual comprueba que calendar no esta cargada.
- Ambos presentan source=apache-probe, cero errores de observacion y cero incidencias de reconciliacion. Comparando la lista completa, las unicas diferencias CLI/Apache son readline (CLI) y apache2handler (Apache); las extensiones restantes coinciden.
- Se conservaron 33 operaciones en PHP 8.3 y 27 en PHP 8.4, sin pendientes; se observaron 18 y 21 snapshots respectivamente. Las siete vistas autenticadas y su actualizacion automatica funcionaron sin errores JavaScript.
- `post-rebuild.spec.cjs` paso en ambos proyectos Playwright. Comprueba contador visible, lista de extensiones visible, reconciliacion, estado deseado, ausencia de pendientes y hashes de perfil intactos antes/despues. No envia operaciones ni cambia passwords. Los accesos de prueba generan solamente las sesiones y registros de auditoria normales.

Para repetir exclusivamente esta revision de lectura, agregar `--grep post-rebuild` al runner. La suite completa tambien contiene pruebas que modifican calendar; no debe ejecutarse completa cuando se solicita una comprobacion solo de lectura. Esta evidencia valida la reconstruccion y observacion actuales, sin cerrar el catalogo PHP 8.4 ni las versiones restantes de fase 6.

Desde PowerShell en la raiz del proyecto, proporcionar la password local sin guardarla en el repositorio:

```powershell
./bin/validate-runtime-integration.ps1 -PhpVersion 8.3 -DevelopmentPassword $env:WSDD_TEST_PASSWORD
./bin/validate-runtime-integration.ps1 -PhpVersion 8.4 -DevelopmentPassword $env:WSDD_TEST_PASSWORD
$env:NODE_EXTRA_CA_CERTS = 'C:/WSDD-Environment/Docker-Structure/ssl/wsdd-mkcert-rootCA.crt'
node D:/OpsZone/DevWorkspace/Engines/Playwright/scripts/run-project-tests.js D:/OpsZone/DevWorkspace/Projects/Web/WSDD-PHP-Admin --suite app
```

Las specs y configuracion viven en `test/app/Playwright`; dependencias, capturas y trazas quedan en `Engines/Playwright/artifacts/wsdd-php-admin/runtime-observation`. No se ejecutan suites heredadas de otros proyectos.
