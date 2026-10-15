# Catalogo Comun PHP 5.6, 7.2, 7.4 y 8.1-8.4

Fecha local: 2026-10-14.

Decision de alcance del 2026-10-15: el fallback se desarrollara y utilizara solo ante una necesidad demostrada mediante issue y una alternativa segura verificable. No se considera implementado ni proximo desarrollo automatico. Se mantiene el metodo autorizado con sus diagnosticos y controles de seguridad. Ver [Decision documentada](extension-configuration-review-2026-10-15.md#decision-posterior-sobre-fallback).

Estado vigente tras recreacion y revision de codigo: `development-status-2026-10-15.md`. Reinstalacion automatica bcmath validada en 5.6/7.2/7.4/8.1/8.3. Las 21 recetas no equivalen a compilacion certificada de todas ellas; fallback_methods permanece vacio y no hay motor ejecutable de fallback ni reglas de incompatibilidad. Son limites de implementacion, no funcionalidades ya terminadas.

Actualizacion 2026-10-15: se incorporaron PHP 5.6/7.2 con el mismo resolver, inventario exacto de fuentes y 21 recetas. Variantes revisadas para GD anterior a 7.4 (--with-freetype-dir y --with-jpeg-dir) y ZIP antiguo (zlib1g-dev/--with-zlib-dir). Las pruebas de recetas comprueban estas diferencias para cada minor. PHP 7.2 supero instalaciones reales calendar/bcmath/pdo_pgsql; PHP 5.6 supero calendar/bcmath, pero pdo_pgsql fallo en apt-get update por firmas/claves y hash del snapshot Stretch. No se cambiaron repositorios ni se desactivaron verificaciones: el instalador fuerza AllowUnauthenticated=false, AllowInsecureRepositories=false y AllowDowngradeToInsecureRepositories=false. Los fallos no equivalen a exito ni a receta ausente. Ver `php56-php72-validation-2026-10-15.md`.

Actualizacion posterior: las recetas ya no se copian por minor. `config/php-compatibility.json` autoriza explicitamente los minors revisados 7.4, 8.1, 8.2, 8.3 y 8.4, con limite <8.5, y `config/extension-catalog.php` contiene una sola coleccion Debian. La matriz y los ejecutores usan esa politica central. Las evidencias originales 8.3/8.4 se conservan abajo; PHP 8.2 y el procedimiento comun se detallan en `php82-validation-2026-10-14.md` y `container-validation-standard-2026-10-14.md`. La ampliacion 7.4/8.1 se registra en `php74-php81-validation-2026-10-14.md`; PHP 8.0 no se habilita por proximidad numerica.

## Alcance y aclaracion

El plan maestro define una fuente local/remota de extensiones, metodos y dependencias por PHP y sistema operativo. No establece que las recetas deban inferirse de la version PHP. La implementacion anterior solo contenia una constante PHP_83_APACHE_CATALOG, y la consumia incluso en PHP 8.4: faltaban seleccion por entorno y descubrimiento del inventario de fuentes. Era una carencia de WSDD-PHP-Admin, no de WSDD Desktop.

Ahora el catalogo efectivo se resuelve dinamicamente con dos entradas distintas:

- Recetas locales revisadas en config/extension-catalog.php, seleccionadas por distribucion y rango explicito de CompatibilityPolicy. Los targets fuera del rango revisado no las heredan automaticamente.
- Inventario observado de extensiones cargadas, binarios disponibles, herramientas y fuentes de la imagen actual. Se consulta al resolver el catalogo; no se conserva una lista de otra version en el perfil persistente.

Descubrir una extension no demuestra como instalarla. Una entrada sin receta se muestra como descubierta o cargada, pero no obtiene permiso para compilar, instalar dependencias ni aparecer en Install. PHP tampoco proporciona por si solo el mapa de paquetes Debian, opciones configure ni metodos seguros PECL de cada extension.

## Descubrimiento y controles

`BundledExtensionDiscovery` inspecciona `ext/*/config.m4` en `/usr/src/php`, comprobando PHP_VERSION en su header, o lista `/usr/src/php.tar.xz` sin extraerlo. La raiz del archivo debe corresponder exactamente a la version PHP local. Fuentes ausentes, distintas o ilegibles bloquean las recetas en lugar de reutilizar las de otra imagen.

`CatalogEnvironment` detecta version PHP, ID de `/etc/os-release` y herramientas reales. Las recetas actuales corresponden a Debian con Apache y helpers oficiales Docker. Se verifican docker-php-ext-install, docker-php-ext-enable, phpize, make y cc. Las recetas con paquetes requieren apt-get y las que configuran opciones requieren docker-php-ext-configure.

El catalogo/API distingue loaded, available, source_available, recipe_available, installable, reason, discovery_source, php_version y distribution. El formulario Install ofrece solo recetas ejecutables que no estan cargadas ni tienen ya un binario disponible. Los datos de UI y el instalador usan el mismo resolver. Una solicitud directa fuera de estos controles termina skipped con motivo, sin ejecutar un metodo alternativo improvisado.

El instalador vuelve a resolver el entorno antes de ejecutar y rechaza la reinstalacion de un modulo ya cargado o con binario presente. Esa comprobacion evita recompilar/adoptar configuracion base por una solicitud directa. Los pasos siguen siendo arrays de argumentos allowlisted y los nombres de paquetes/opciones provienen del proyecto, no del formulario. CommandRunner drena stdout/stderr conjuntamente para no bloquear compilaciones con salida abundante y limita el tiempo de ejecucion.

La diferencia de inventario se comprobo realmente: las fuentes PHP 8.3.35 contienen IMAP, OCI8, PDO_OCI y PSpell; las fuentes PHP 8.4.26 ya no las contienen. Coincide con [Removed Extensions, PHP 8.4](https://www.php.net/manual/en/migration84.removed-extensions.php) y el [RFC oficial](https://wiki.php.net/rfc/unbundle_imap_pspell_oci8). No se agrego un metodo PECL para ellas ni se ofrece una instalacion core inexistente.

## Recetas actuales

Hay 21 recetas para cada minor soportado en Debian:

| Extension | Paquetes adicionales declarados | Configuracion |
| --- | --- | --- |
| bcmath | Ninguno | Predeterminada |
| bz2 | libbz2-dev | Predeterminada |
| calendar | Ninguno | Predeterminada |
| exif | Ninguno | Predeterminada |
| gd | libfreetype6-dev, libjpeg62-turbo-dev, libpng-dev | --with-freetype --with-jpeg |
| gettext | Ninguno | Predeterminada |
| intl | libicu-dev | Predeterminada |
| mysqli | Ninguno | Predeterminada |
| opcache | Ninguno | INI Zend |
| pcntl | Ninguno | Predeterminada |
| pdo_mysql | Ninguno | Predeterminada |
| pdo_pgsql | libpq-dev | Predeterminada |
| pgsql | libpq-dev | Predeterminada |
| shmop | Ninguno | Predeterminada |
| soap | libxml2-dev | Predeterminada |
| sockets | Ninguno | Predeterminada |
| sysvmsg | Ninguno | Predeterminada |
| sysvsem | Ninguno | Predeterminada |
| sysvshm | Ninguno | Predeterminada |
| xsl | libxslt1-dev | Predeterminada |
| zip | libzip-dev | Predeterminada |

Metodo actual: docker-php-ext-install. No se agregaron fallbacks; fallback_methods sigue vacio. Una receta revisada y contrastada con fuentes no equivale a haber compilado todas sus variantes en todas las imagenes.

OPcache se reconoce por el nombre observado Zend OPcache sin falso drift. Al generar un INI managed usa zend_extension=opcache; las demas recetas usan extension=. Se probo la generacion en un directorio temporal y la observacion/reconciliacion contra el modulo cargado, sin modificar el OPcache base.

## Pruebas reales y estado final

Entornos existentes: WSDD-Web-Server-PHP8.3 (8.3.35) y WSDD-Web-Server-PHP8.4 (8.4.26), ambos Debian 13. No se destruyeron ni reconstruyeron contenedores. Se recargaron los agentes para incorporar codigo; no se requirieron cambios WSDD, Dockerfile, Compose ni reiniciar la web interna.

- Calendar: instalacion real en ambos, seguida de disable, rollback, disable, enable, reconcile y remove del INI. Todos los resultados success incluyeron verificacion CLI y Apache, con 0 incidencias en la web.
- Bcmath: mismo ciclo completo con instalacion real en PHP 8.4. La bcmath ya deseada y habilitada de PHP 8.3 se conservo.
- PDO_PGSQL: mismo ciclo completo en PHP 8.4. Se verificaron exit_code=0 para apt-get update, apt-get install -y --no-install-recommends libpq-dev y docker-php-ext-install pdo_pgsql. Quedaron libpq-dev/libpq5 17.11-0+deb13u1 en el contenedor de pruebas. No se intento conectar a una base de datos PostgreSQL.
- Validate-extension-catalog paso en ambos: seleccion de version/distribucion, fuentes ausentes/incompatibles, herramientas ausentes, extension sin receta, nombres inseguros, OPcache, no reinstalacion de base, timeout y drenaje de salida del ejecutor.
- Las validaciones previas de bootstrap, seguridad, respuesta del agente, reconciliacion y observacion se mantuvieron satisfactorias en las comprobaciones realizadas.
- Las pruebas Playwright de catalogo verifican la version mostrada y que ninguna extension sin receta ejecutable entre al selector Install. Las de lectura verifican que las pantallas coincidan con CLI/Apache y que sus consultas no cambien el perfil.
- Resultado del runner de navegador: 4 pruebas satisfactorias, catalogo y lectura en cada version. Las 21 recetas tienen definiciones config.m4 presentes en las fuentes de ambos runtimes; esta comprobacion no equivale a compilar las 21.

Calendar queda removed en ambos perfiles; bcmath y pdo_pgsql de prueba quedan removed en PHP 8.4. No queda INI activo para ellas. Se conservan binarios compilados, dependencias apt, snapshots, operaciones y auditoria como evidencia; remove sigue limitado a INI, no elimina binarios/paquetes de sistema.

No se compilaron las 21 recetas en esta bateria ni se reinstalaron las extensiones base para hacerlo. Se validaron fuentes/controles de todas las entradas y ciclos de instalacion representativos, incluidos paquetes del sistema. No se validaron nuevos fallbacks, nuevos repositorios, PECL arbitrario, otras distribuciones, FPM o PHP anterior a 8.3. La fase 6 permanece abierta para las versiones restantes; el catalogo inicial PHP 8.4 ya esta implementado y probado con este alcance.

## Repetir

`php bin/validate-extension-catalog` se ejecuta dentro de cada contenedor; usa fixtures temporales y lecturas del entorno real, sin compilar extensiones.

`bin/validate-runtime-integration.ps1 -PhpVersion 8.4 -Extension calendar|bcmath|pdo_pgsql -DevelopmentPassword ...` ejecuta operaciones reales sobre la extension seleccionada. Requiere estado inicial no cargado y perfil ausente o removed. Si ya hay binario, prueba activacion en vez de recompilarlo. Al terminar intenta dejarla removed. No usar bcmath en PHP 8.3 mientras siga declarada enabled en su perfil.

Para navegador, las specs del catalogo y revision de lectura se ejecutan con el runner del workspace y `--grep 'catalog selects|post-rebuild'`. Sus artefactos permanecen en el runtime Playwright, no en el codigo del proyecto.
