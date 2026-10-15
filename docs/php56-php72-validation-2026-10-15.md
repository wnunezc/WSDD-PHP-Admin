# Compatibilidad PHP 5.6 y 7.2

Fecha local: 2026-10-15. Ampliacion de la fase 6; no cierre global.

Ultima reconstruccion PHP 5.6: contenedor 03a0b3463552, creado 13:08:33 UTC del mismo dia, aun Debian Stretch/PHP 5.6.40. Reinstalacion automatica bcmath desde enabled, readiness verificada, Smoke/passed y dos specs de navegador aprobadas. Tambien pasaron las 22 comprobaciones de arranque y 11 pruebas de reconciliacion como www-data. Perfil/credenciales/INI/extensiones/39 operaciones intactos, 0 pendientes y procesos estables. Se cierra el pendiente final de las correcciones P1/P2; detalles en `runtime-reliability-fixes-2026-10-15.md`. No se reintento APT/pdo_pgsql.

## Entornos comprobados

- PHP 5.6.40 / Debian 9 Stretch: WSDD-Web-Server-PHP5.6, raiz Dev /var/www/html/phpadmin56.dock, https://phpadmin56.wsdd.dock/.
- PHP 7.2.34 / Debian 10 Buster: WSDD-Web-Server-PHP7.2, raiz Dev /var/www/html/phpadmin72.dock, https://phpadmin72.wsdd.dock/.
- Ambos ven el mismo proyecto Windows; estado, credenciales, operaciones, backups y logs son independientes por contenedor. El codigo no se copia a /var/lib/wsdd-php-admin.
- No se modifico WSDD Desktop, Dockerfiles, Compose ni vhosts. No se reconstruyeron ni eliminaron contenedores para probar.

## Implementacion comun

El mismo arbol PHP usa sintaxis 5.6, no ramas ni copias por interprete. Se retiraron declaraciones escalares/de retorno, propiedades tipadas, strict_types, flechas, coalescencia y otras construcciones posteriores. Se conservan contratos mediante TypeGuard, PHPDoc y propiedades privadas de solo lectura. OperationId::generate y OperationRepository::listing evitan nombres reservados de interpretes antiguos; las rutas HTTP no cambian.

JsonCodec distingue JSON invalido de null valido sin depender de JSON_THROW_ON_ERROR. La compatibilidad random_bytes exige OpenSSL y strong=true; no incorpora aleatoriedad debil. Process/CommandRunner preservan argumentos literales, entorno heredado, restauracion del padre, timeout y drenaje stdout/stderr. Para proc_open antiguo se escapa cada argumento y se usa exec; argv nativo se conserva desde PHP 7.4.

ExecutableFinder comparte deteccion de herramientas entre runtime y catalogo. En Stretch se comprobo que el PATH del proceso www-data no incluia /usr/sbin, aunque Apache existia; se agrego busqueda en directorios Linux estandar sin elevar permisos de la web.

Las sesiones antiguas conservan HttpOnly y SameSite=Strict sin usar opciones inexistentes. Se capturan Exception y Throwable donde corresponde. HttpErrorHandler devuelve HTTP 503 y un mensaje generico ante errores no controlados, guardando detalles en logs; evita falsos HTTP 200 con paginas fatales.

PHP 5.6 requiere extension=nombre.so para estos INI propios; Zend usa extension_dir/opcache.so absoluto. Se corrigieron generacion y adopcion de INI, con fixtures por version. Las decisiones siguen usando CLI nuevo y sonda Apache autenticada, nunca las extensiones retenidas en memoria por el agente/web.

## Catalogo

Se autorizan explicitamente 5.6 y 7.2 en la politica comun. Los archivos exactos de fuentes exponen las definiciones de las 21 recetas compartidas. Se revisaron variantes GD antiguas (--with-freetype-dir=/usr y --with-jpeg-dir=/usr) y ZIP anterior a 7.4 (zlib1g-dev, --with-zlib-dir=/usr). Las diferencias estan en datos del mismo resolver y tienen pruebas por minor.

Descubrir fuentes no genera recetas ni acredita repositorios externos. No se afirma que las 21 extensiones se hayan compilado. PHP 8.0/8.5 siguen pendientes y no heredan recetas por ampliar el rango de Composer.

## Evidencia PHP 7.2

validate-container.ps1 -PhpVersion 7.2 -Mode Full termino passed: calendar, bcmath y pdo_pgsql compiladas realmente; activacion, desactivacion, rollback, reconciliacion y retirada managed verificadas con CLI/Apache/UI. Las tres pruebas Playwright aprobaron, incluidas actualizacion automatica y recuperacion de fallo temporal. El servidor web interno mantuvo su proceso durante los ciclos.

Operaciones de instalacion: calendar 3434bb18-884b-4c11-a464-85167d8ca479; bcmath 31261372-5267-4088-9039-fddbb74020c2; pdo_pgsql 79137a0b-a177-4653-b567-4cd588556856. Activacion final bcmath 095cfb90-6cf5-45cc-af75-68892c435724: success, desired_state=enabled, INI propio, CLI y Apache cargados, 0 incidencias y perfil Windows coincidente por SHA256. Calendar/pdo_pgsql quedan removed; sus binarios/dependencias se conservan.

## Evidencia PHP 5.6

Se compilaron calendar y bcmath y se verificaron sus ciclos managed reales tras corregir los INI .so. Calendar b15cfec2-480a-43e6-87e2-dabfeadf930c y bcmath 708e662b-8c7f-463e-9f88-222afe58f923 son instalaciones registradas. La activacion final bcmath be8d0f80-bb66-4bd1-b116-d29fe4228bb8 termino success: desired_state=enabled, INI propio, CLI/Apache cargados, 0 incidencias y hash Windows coincidente. Calendar queda removed; no se borraron binarios.

Smoke completo aprobo bootstrap de perfil, seguridad/respuesta del agente, auditoria/rollback, observacion fresca, catalogo y dos pruebas de navegador. La sintaxis y contratos portables aprobaron sobre 63 archivos con el interprete 5.6 real. Los tests del catalogo cubren variantes GD/ZIP, fuentes/herramientas ausentes, alias Zend, proteccion de mysqli base, stdout/stderr de 128KB y timeout.

La ejecucion posterior de las tres specs Playwright termino 3 passed en 1.7 minutos: catalogo/select y cookie HttpOnly/SameSite=Strict, concordancia CLI/Apache/UI, actualizacion interactiva y recuperacion de fallo de red. El primer intento de recuperacion agoto 12 segundos: la traza registro respuestas de hasta 6.7 segundos bajo sondeos concurrentes, ademas del intervalo de refresco de 5 segundos. Se amplio esa espera de prueba a 30 segundos sin eliminar la comprobacion; la siguiente ejecucion verifico que el aviso desaparece realmente.

Tras recargar el agente y corregir el ejecutor, se repitio el ciclo completo calendar con salida 0: enable 215e08cc-5f3a-41f7-a9de-849d0190e6dd, disable 8e262ee5-c466-47b9-9118-a5177ab355d9, rollback 7e026d03-84f4-4d66-a99b-cc19fb37a0ed, segundo disable 0885c17c-df38-4540-9291-fb38fd44f199, enable 92bfff9e-23d1-4a6b-a440-7e4aab62033d, reconcile e1aad70c-26e2-4b49-a67b-63abcd21a5ec y remove 522bf054-7861-44ea-843f-e0d184c3b6f7. Todos terminaron success con verificacion CLI/Apache y 0 incidencias. PrepareRebuild se repitio al final en 5.6 y 7.2: ambos conservaron bcmath enabled, cargada e INI propio, con hash Windows coincidente.

La bateria Full NO se declara aprobada en 5.6: pdo_pgsql fallo en apt-get update, operacion 758e168e-43a3-4d3c-901d-8a24ca0e5f3f. El snapshot Debian Stretch configurado en la imagen reporto EXPKEYSIG, NO_PUBKEY y, en el primer intento, Hash Sum mismatch. La imagen contenia AllowUnauthenticated=true; el instalador ahora fuerza false y rechaza tambien repositorios inseguros o degradados. Una ejecucion real posterior del instalador con estas opciones termino failed/exit_code=100 antes de instalar paquetes o compilar.

No se cambiaron repositorios, claves, reloj, TLS ni hashes para fingir exito. La limitacion actual es la fuente de dependencias configurada, no la sintaxis del proyecto. Resolver una fuente autenticada de Stretch sigue pendiente. Referencia de seguridad: https://manpages.debian.org/trixie/apt/apt-secure.8.en.html.

Un defecto del ejecutor anterior permitio continuar disable/rollback despues del fallo pdo_pgsql y dejo esa entrada disabled en el perfil 5.6. Se conserva ese historial, no se borra para ocultar el fallo. El ejecutor corregido detiene inmediatamente las acciones posteriores, y solo admite skipped durante limpieza cuando no existe INI propio. Esa entrada no acredita una instalacion.

## Downgrade de codigo y continuidad funcional

Smoke paso en PHP 7.4.33, 8.1.34, 8.2.34, 8.3.35 y 8.4.26 tras el port. La comprobacion final de sintaxis, contratos y catalogo paso en los siete interpretes. No se alteraron intenciones preexistentes de extensiones en esos cinco entornos.

Durante la primera validacion se recargaron agentes propios con colas vacias para incorporar el codigo nuevo, sin reiniciar webs ni contenedores. Esa prueba inicial no acreditaba CMD automatico. La recreacion general posterior del usuario SI lo acredita en 5.6/7.2: nuevos contenedores ad56405d294f y 1b4fc634f9ae, runtime:start automatico, compilacion bcmath exit_code=0, INI propio, reload y verificacion CLI/Apache antes de la web. Ambos conservan el perfil previo y bcmath enabled. Evidencia conjunta: `development-status-2026-10-15.md`.

En el bind mount Dev se observo que PHP antiguo analizaba contenido anterior aunque file_get_contents y SHA256 ya devolvian bytes nuevos. Reescribir esos mismos bytes a traves del mount del contenedor restablecio el analisis correcto. No se creo una segunda instalacion, una sincronizacion /opt ni una responsabilidad nueva WSDD. Este hecho operativo no se atribuye a una causa interna no comprobada.

## Pendientes de cierre

- Cerrado: persistencia, reinstalacion bcmath enabled, arranque automatico y continuidad observada del agente despues de la recreacion general del usuario. Las dos pruebas post-rebuild forman parte del resultado conjunto 7 passed; no se retiro bcmath.
- Dependencias autenticadas Stretch para completar pdo_pgsql en 5.6; no cerrar Full por omitir el caso fallido.
- PHP 8.2 ya tiene persistencia/arranque tras recreacion; reinstalacion desde enabled sigue pendiente para su estado actual y el de 8.4. PHP 8.0/8.5 y pruebas adicionales de recetas permanecen pendientes. La fase 6 global sigue abierta.
