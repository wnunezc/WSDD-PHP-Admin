# Compatibilidad PHP 7.4 y 8.1

Estado posterior 2026-10-15: se conserva este historial del primer downgrade a 7.4. El codigo comun ya fue adaptado hasta sintaxis/API 5.6 y probado en los siete interpretes actuales; los rangos minimos descritos historicamente abajo no son la politica vigente. La recreacion general ratifico reinstalacion bcmath enabled, persistencia y agente/web estables en 7.4/8.1. Evidencia y pendientes globales: `development-status-2026-10-15.md`.

Fecha local: 2026-10-14.

## Estado inicial comprobado

- PHP 8.1.34, Debian 13 Trixie: agente root y web www-data activos desde /var/www/html/phpadmin81.dock; perfil inicial vacio persistente.
- PHP 7.4.33, Debian 10 Buster: proyecto montado en /var/www/html/phpadmin74.dock, pero runtime detenido por parse error en src/Support/AppConfig.php:12. No existia profile.json.
- Ambos contenedores ya tenian persistencia independiente para estado y logs preparada por WSDD. No se modifico WSDD Desktop, Dockerfiles, Compose ni vhosts.

## Adecuacion del mismo codigo

El bloqueo 7.4 pertenecia a WSDD-PHP-Admin. Se sustituyeron promocion de propiedades, readonly, match, argumentos nombrados, nullsafe, tipos mixed/union y catch sin variable por construcciones compatibles. Las propiedades tipadas y la inyeccion de dependencias se mantienen, con asignacion explicita en constructores.

Los objetos de resultado no se convirtieron en propiedades publicas modificables. ReadOnlyProperties permite lectura externa, isset y serializacion explicita toArray; bloquea asignacion, unset y propiedades dinamicas. Los servicios conservan dependencias privadas. La compatibilidad de str_contains, str_starts_with y str_ends_with se carga una sola vez y no sustituye funciones nativas cuando existen.

Composer y la politica central declaran ahora minimo sintactico PHP 7.4, con restriccion ^7.4 || ^8.0. Esto no declara validado PHP 8.0 ni promete PHP 7.2/5.6. Las versiones autorizadas para recetas son explicitas en recipe_versions: 7.4, 8.1, 8.2, 8.3 y 8.4. El limite superior revisado sigue siendo <8.5. PHP 8.0 permanece sin recetas autorizadas hasta disponer de revision/evidencia; ampliar un intervalo no lo habilita accidentalmente.

El minimo 7.4 sigue siendo necesario: se usan propiedades tipadas, arrow functions y proc_open con argv. Una version inferior exige otro port real, no una nueva etiqueta de compatibilidad.

Se conserva un solo agente, servidor web, resolver de catalogo, instalador y procedimiento de validacion. No hay una copia legacy ni otra raiz runtime.

## Revision de fuentes y dependencias

Las 21 recetas compartidas tienen config.m4 en los archivos exactos php-7.4.33 y php-8.1.34. Se revisaron las definiciones de bz2, gd, intl, pdo_pgsql, pgsql, soap, xsl y zip. GD admite los argumentos --with-freetype y --with-jpeg en ambos; PostgreSQL usa libpq/pg_config; SOAP/XSL dependen de libxml/libxslt; ZIP usa libzip. Las recetas mantienen sus paquetes y argumentos permitidos.

PHP 7.4 ya apuntaba a archive.debian.org para Buster; no se cambiaron repositorios ni se deshabilitaron verificaciones de firmas. Existencia de fuentes/herramientas significa receta ejecutable, no compilacion garantizada de cada una de las 21 extensiones.

## Pruebas comunes

Se agrego bin/validate-php-compatibility al ejecutor comun. Analiza con TOKEN_PARSE todos los PHP/CLI del proyecto bajo el interprete de destino; prueba funciones portables, inmutabilidad externa, resultados nullable y coherencia Composer/politica.

PHP 7.4 supero el analisis de 58 archivos y arranco mediante runtime:start, inicializando el perfil antes de agente/web. Ambos entornos pasaron fixtures de bootstrap, seguridad del agente, respuesta del agente, auditoria/rollback y observacion fresca. El fixture del catalogo ahora comprueba la presencia real de OPcache: no exige que una imagen sin esa extension la tenga instalada para fingir exito.

La bateria Full usa el mismo ejecutor bin/validate-container.ps1 y los mismos ciclos calendar, bcmath y pdo_pgsql, junto con UI/API autenticadas y Playwright.

Resultado PHP 8.1.34: Full/passed con los tres ciclos completos. calendar, bcmath y pdo_pgsql se compilaron realmente; todas las acciones terminaron success con verificacion CLI y Apache. Pasaron las siete vistas, ocho recursos API, rechazo anonimo 401 y CSRF 419. Playwright termino 3 passed en 49.7 segundos: catalogo/select, concordancia de lectura y actualizacion automatica/recuperacion sin reiniciar la web. La reconciliacion final devuelve issue_count=0; la web conserva PID 1777. El agente se recargo para incorporar el codigo actualizado, con cola vacia; no se reinicio el contenedor.

Instalaciones reales registradas en PHP 8.1: calendar c3d0406e-a3e4-4239-b776-407bc0c59f02, bcmath de86875b-3973-4559-93dc-20de2e21af6e y pdo_pgsql 3118347f-b97e-4d70-999f-b418a5de647c. libpq-dev/libpq5 quedaron en 17.11-0+deb13u1.

Instalaciones reales registradas en PHP 7.4: calendar 439c0738-2403-4ff8-8ff1-ce03fd0186bc, bcmath 89041ea9-e834-426a-a8eb-2b7c8a01f98e y pdo_pgsql 4ca62198-22fb-4508-8548-0b06699d7f37. Los tres pasos de esta ultima operacion finalizaron con exit_code=0: apt-get update, apt-get install -y --no-install-recommends libpq-dev y docker-php-ext-install pdo_pgsql. libpq-dev/libpq5 quedaron en 11.22-0+deb10u2; APT registro 2 paquetes nuevos, 0 actualizados y 0 eliminados.

Regresion posterior al port: Smoke/passed en PHP 8.2.34, 8.3.35 y 8.4.26, con fixtures comunes y dos specs de navegador por version. TOKEN_PARSE y los tests portables pasaron en los cinco interpretes; no se relajo la seguridad para permitir 7.4.

Resultado PHP 7.4.33: Full/passed con los tres ciclos completos. Playwright termino 3 passed en 59.9 segundos: catalogo/select, lectura CLI/Apache/UI y operaciones/actualizacion automatica, incluida recuperacion tras fallo temporal simulado. Reconciliacion final issue_count=0; un solo agente root PID 2426 y web www-data PID 2430 desde la misma raiz; la web mantuvo ese PID durante toda la bateria. Las siete vistas, ocho recursos API, autenticacion/cambio inicial de password y CSRF se comprobaron realmente.

El estado de las extensiones de prueba al finalizar es removed, con binarios conservados y sin INI activo. No se copio el perfil 8.3 a los nuevos entornos: cada contenedor conserva su propio profile.json.

Comprobacion final autenticada en los cinco entornos: 21 recetas, issue_count=0, errors de observacion vacio, 0 operaciones pendientes y source=apache-probe. El SHA256 de cada profile.json coincide entre Windows y su contenedor. Los cinco contenedores permanecen ejecutandose sin reconstruccion/reinicio durante esta tarea. Se conserva bcmath enabled de PHP 8.3; no se modificaron estados previos de los entornos de continuidad funcional.

La inspeccion visual detecta una limitacion previa del etiquetado de permisos: Overview puede mostrar Ready y marcar Profile file/Agent log file/Audit log file como blocked cuando esos archivos son de escritura exclusiva del agente. Las operaciones y las verificaciones reales si funcionan. No se han ampliado permisos de www-data para ocultar esas etiquetas; este informe no declara corregido ese etiquetado.

## Reconstruccion realizada por el usuario

Fecha local: 2026-10-14. Se inspeccionaron los nuevos contenedores sin arrancar manualmente runtime:start ni el agente, sin instalar extensiones, sin encolar acciones y sin recargar Apache.

- PHP 8.1: contenedor 97167fca08f8, creado 2026-10-15T04:16:25Z; agente root PID 1768 y web www-data PID 1773 desde /var/www/html/phpadmin81.dock.
- PHP 7.4: contenedor c7557827e78b, creado 2026-10-15T04:16:38Z; agente root PID 1848 y web www-data PID 1855 desde /var/www/html/phpadmin74.dock.
- El CMD real de ambos selecciona la raiz Dev y llama runtime:start. Los logs de esta reconstruccion muestran reconciliacion sincrona antes de levantar la web y reconciliacion del agente status=success, applied=0, skipped=3.
- Persisten los perfiles con sus fechas anteriores, las credenciales y los resultados historicos de las tres instalaciones. En cada entorno hay 46 archivos de operaciones y 17 snapshots INI. Los hashes de profile.json coinciden entre Windows y el contenedor.
- Se inicia sesion con la credencial de desarrollo existente, sin repetir onboarding ni cambiar auth.json. La API autenticada usa apache-probe, muestra 21 recetas, 0 incidencias, 0 errores de observacion y 0 operaciones pendientes.
- TOKEN_PARSE y las pruebas portables pasaron en ambos interpretes. Playwright termino 4 passed en 40.8 segundos: catalogo y concordancia de lectura CLI/Apache/UI para cada version. Se comprobaron hashes de perfil/auth, cantidad de archivos de operaciones y procesos sin cambios durante la bateria.

El perfil conservado declara bcmath, calendar y pdo_pgsql como removed en ambos entornos. Ninguna esta cargada, ni tiene INI administrado ni binario .so en la imagen reconstruida. Es el resultado esperado: se conserva la intencion persistente, no los binarios del filesystem anterior. Los tres skipped del log corresponden a la remocion binaria no implementada; no son instalaciones fallidas ni operaciones pendientes.

Se cierra la evidencia de persistencia y arranque automatico tras esta reconstruccion para PHP 7.4/8.1. No se cierra la reinstalacion automatica de enabled: la bateria anterior habia dejado las extensiones removed, por lo que esta reconstruccion no ejercito ese caso. Para comprobarlo se debe instalar/activar una extension de prueba, verificar desired_state=enabled y solo entonces reconstruir; no se modifico el perfil para fingir esa evidencia.

## Limites

Preparacion corregida para la siguiente reconstruccion: bcmath quedo instalada y desired_state=enabled en PHP 7.4 (operacion accb68c2-bcde-4fc8-a1a7-aa27d9049d2c) y PHP 8.1 (5b4478a1-8327-4eaa-8ee5-8c95159b4ba4). Ambas operaciones terminaron success con verificacion CLI/Apache, INI administrado presente, perfil coincidente con Windows y 0 incidencias. No se retirara esa extension al terminar esta preparacion. El usuario puede reconstruir ambos contenedores para ejercitar reinstalacion automatica; todavia no se declara realizada esa segunda reconstruccion.

El agente de desarrollo no reconstruyo ni destruyo contenedores. La primera reconstruccion descrita arriba solo cubrio removed. La segunda, documentada a continuacion, ya valida reinstalacion desde enabled. Los binarios no persisten por si solos tras reconstruir una imagen limpia. No se eliminaron binarios ni paquetes base mediante estas pruebas.

## Segunda reconstruccion: reinstalacion enabled validada

Fecha local: 2026-10-14. El usuario reconstruyo ambos entornos despues de la preparacion bcmath=enabled. No se ejecutaron instalaciones, reconciliaciones managed ni arranques manuales durante la inspeccion.

- PHP 7.4.33: nuevo contenedor c854e8a84bb9, creado 2026-10-15T04:27:43Z; agente root PID 6974 y web www-data PID 6978 desde la raiz Dev phpadmin74.dock.
- PHP 8.1.34: nuevo contenedor 9ff69905d51b, creado 2026-10-15T04:28:02Z; agente root PID 6790 y web www-data PID 6795 desde la raiz Dev phpadmin81.dock.
- En ambos, el bloque mas reciente del log de arranque registra docker-php-ext-install bcmath con exit_code=0, creacion del INI administrado, graceful Apache exitoso y verificacion success en CLI/Apache antes de Starting WSDD-PHP-Admin agent and internal web server.
- Existe bcmath.so y /usr/local/etc/php/conf.d/zz-wsdd-php-admin-bcmath.ini. PHP 7.4 usa extension=bcmath.so y PHP 8.1 extension=bcmath; ambas formas fueron cargadas realmente. La comprobacion no debe exigir igualdad literal entre esas dos directivas validas.
- El perfil conserva desired_state=enabled y sus fechas anteriores a esta reconstruccion; calendar/pdo_pgsql permanecen removed y no cargadas. La reconciliacion posterior del loop no reaplica cambios: applied=0.
- CLI nuevo, Apache y API de la web interna detectan bcmath cargada. La API autenticada devuelve issue_count=0, errors vacio y 0 operaciones pendientes. Los hashes de perfil Windows/contenedor coinciden.
- Playwright termino 4 passed en 50.3 segundos: catalogo y concordancia CLI/Apache/UI por version, incluidas las vistas y sus contadores. Perfil, auth.json, cantidad de operaciones y procesos se mantuvieron sin cambios durante las pruebas de lectura.

Se cierra para PHP 7.4/8.1 la evidencia de persistencia, arranque automatico y reinstalacion real desde enabled tras reconstruccion. bcmath se deja habilitada para el uso y pruebas siguientes; no se retira al terminar la inspeccion. La fase multi-version global sigue abierta para los objetivos restantes y no implica compilacion probada de las 21 recetas.

Referencias tecnicas: https://www.php.net/manual/en/migration80.new-features.php y https://www.php.net/manual/en/migration81.new-features.php. El error inicial y las pruebas de sintaxis se comprobaron con los interpretes reales, no solamente con referencias documentales.
