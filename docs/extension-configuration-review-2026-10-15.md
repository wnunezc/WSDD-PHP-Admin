# Revision de configuracion e incompatibilidades

Fecha: 2026-10-15. Alcance: las 21 recetas actuales de config/extension-catalog.php y el flujo existente de operaciones/INI. No agrega extensiones, requisitos ni versiones al plan maestro.

## Decision acordada sobre incompatibilidades

Por decision expresa del usuario, las incompatibilidades se trataran como issues al detectar problemas. No se implementa ahora un motor especulativo ni una lista de bloqueos sin evidencia.

Cada issue debera registrar extension(es), versiones PHP, imagen/distribucion, accion afectada, resultado esperado/real, reproduccion, logs y fuentes disponibles. Solo un caso confirmado justificara evaluar una regla preventiva con alcance concreto y su prueba. Un fallo de repositorio o dependencia no se etiqueta automaticamente como incompatibilidad entre extensiones.

Los campos incompatible_with y conflicts_with siguen admitiendo null para desconocido. La ausencia de issues o reglas no certifica compatibilidad entre todas las combinaciones. No se declara implementado el criterio original de bloqueo preventivo; se difiere su desarrollo a los issues que lo justifiquen. Esta decision actualiza el orden de trabajo, no borra la trazabilidad del requisito original.

## Resultado de la revision de configuracion

No se ha identificado en las fuentes oficiales consultadas un parametro que el usuario deba completar obligatoriamente para cargar las extensiones de las 21 recetas actuales. Esta es una conclusion de la revision documental, no una certificacion mediante compilacion/carga de todas las recetas en cada version PHP 5.6-8.4.

Se distinguen tres conceptos:

- Dependencias y opciones de compilacion: ya pertenecen a packages/configure en las recetas; GD/ZIP tienen variantes por version. No son formularios de configuracion runtime.
- Directivas INI opcionales o con valores predeterminados: su existencia no exige pedir datos al instalar ni autoriza a sobrescribir la configuracion base.
- Datos de uso de una aplicacion (conexion a base de datos, locale, WSDL, archivos, sockets): no son automaticamente requisitos para cargar el modulo PHP ni pertenecen a credenciales globales de este administrador.

### Fuentes oficiales consultadas

| Receta(s) | Evidencia relevante para la revision | Fuente oficial |
| --- | --- | --- |
| bcmath | bcmath.scale tiene valor predeterminado; no se identifica entrada obligatoria previa a carga | [Configuracion BC Math](https://www.php.net/manual/en/bc.configuration.php) |
| bz2 | Describe instalacion y dependencia de compilacion; no se identifica formulario runtime obligatorio | [Instalacion Bzip2](https://www.php.net/manual/en/bzip2.installation.php), [Configuracion/instalacion](https://www.php.net/manual/en/bzip2.setup.php) |
| calendar | Instalacion de la extension; no se identifica parametro de usuario previo a carga | [Configuracion/instalacion Calendar](https://www.php.net/manual/en/calendar.setup.php) |
| exif | Codificaciones con valores predeterminados; el requisito de valor no vacio en algunas directivas no obliga a solicitar un valor nuevo | [Configuracion Exif](https://www.php.net/manual/en/exif.configuration.php) |
| gd | Ajuste jpeg con valor predeterminado y advertencia de memoria segun uso; distinto de las opciones de compilacion | [Configuracion GD](https://www.php.net/manual/en/image.configuration.php) |
| gettext | Describe habilitacion en compilacion; no se identifica formulario runtime obligatorio | [Instalacion Gettext](https://www.php.net/manual/en/gettext.installation.php), [Configuracion/instalacion](https://www.php.net/manual/en/gettext.setup.php) |
| intl | Tiene valores predeterminados, incluido locale de ICU si no se especifica; la recomendacion de elegir locale no equivale a requisito de carga | [Configuracion Intl](https://www.php.net/manual/en/intl.configuration.php), [Instalacion](https://www.php.net/manual/en/intl.installation.php) |
| mysqli | Directivas con valores predeterminados; datos de conexion no son requisito para cargar el modulo | [Configuracion MySQLi](https://www.php.net/manual/en/mysqli.configuration.php) |
| opcache | Requiere zend_extension para carga, ya atendido por INI managed; sus opciones tienen valores predeterminados, no un formulario obligatorio identificado | [Instalacion OPcache](https://www.php.net/manual/en/opcache.installation.php), [Configuracion](https://www.php.net/manual/en/opcache.configuration.php) |
| pcntl | Requisitos/instalacion de la extension; no se identifica formulario runtime obligatorio. Sus restricciones de uso no se consideran resueltas por esta revision | [Configuracion/instalacion PCNTL](https://www.php.net/manual/en/pcntl.setup.php) |
| pdo_mysql | Opciones de instalacion y socket predeterminado; DSN/credenciales corresponden al uso del controlador | [Controlador PDO MySQL](https://www.php.net/manual/en/ref.pdo-mysql.php) |
| pdo_pgsql | Dependencia libpq y opciones de compilacion; no se identifica formulario runtime obligatorio para carga | [Controlador PDO PostgreSQL](https://www.php.net/manual/en/ref.pdo-pgsql.php) |
| pgsql | Limites y conexiones persistentes con valores predeterminados | [Configuracion PostgreSQL](https://www.php.net/manual/en/pgsql.configuration.php) |
| shmop | Requisitos/instalacion; no se identifica formulario runtime obligatorio | [Configuracion/instalacion Shmop](https://www.php.net/manual/en/shmop.setup.php) |
| soap | Cache WSDL tiene valores predeterminados; el WSDL de una aplicacion no es requisito para cargar la extension | [Configuracion SOAP](https://www.php.net/manual/en/soap.configuration.php) |
| sockets | Requisitos/instalacion; no se identifica formulario runtime obligatorio | [Configuracion/instalacion Sockets](https://www.php.net/manual/en/sockets.setup.php) |
| sysvmsg, sysvsem, sysvshm | Habilitacion por compilacion; sysvshm.init_mem tiene valor predeterminado | [Instalacion System V](https://www.php.net/manual/en/sem.installation.php), [Configuracion](https://www.php.net/manual/en/sem.configuration.php) |
| xsl | Dependencias/compilacion; no se identifica formulario runtime obligatorio | [Instalacion XSL](https://www.php.net/manual/en/xsl.installation.php), [Configuracion/instalacion](https://www.php.net/manual/en/xsl.setup.php) |
| zip | Requisitos y opciones de compilacion por version; no se identifica formulario runtime obligatorio | [Instalacion ZIP](https://www.php.net/manual/en/zip.installation.php), [Configuracion/instalacion](https://www.php.net/manual/en/zip.setup.php) |

Se uso el contenido editorial del manual, no los comentarios de usuarios como evidencia de requisitos. Algunas URLs configuration.php devuelven 404; un 404 no se uso como prueba de ausencia de parametros. Se consultaron las secciones setup/installation correspondientes. El manual actual no sustituye la validacion historica por version ni las pruebas reales pendientes.

## Consecuencia para el desarrollo

No se agregan formularios vacios, campos inventados, editor INI libre ni cambios a instalaciones que hoy no requieren esos datos. Tampoco se anade una extension fuera del catalogo solo para justificar un formulario.

El requisito condicional del plan se mantiene: cuando una extension soportada tenga parametros obligatorios comprobados, su integracion debe incluir formulario, validacion backend, persistencia y bloqueo de activacion/reconciliacion hasta completarlos. Ese flujo aun no esta implementado; la revision actual no lo declara terminado ni cierra una fase.

No se modificaron archivos PHP, perfiles reales, colas, procesos ni contenedores en esta tarea documental. Correccion posterior del usuario: las ampliaciones se atenderan por issues cuando exista necesidad demostrada, incluida recuperacion/modo seguro ampliados. Se retira la recomendacion de implementarlos como siguiente bloque obligatorio; no mantienen ni reabren fases del alcance actual. Se conserva la evidencia del rollback ya implementado y probado. Las validaciones pendientes del alcance acordado se distinguen de estas ampliaciones.

## Decision posterior sobre fallback

El usuario decide implementar y utilizar un fallback solo cuando se detecte su necesidad mediante un issue. El issue debe incluir el metodo actual, entorno/version, causa y evidencia del impedimento, asi como una alternativa segura verificable antes de incorporarla. Un fallo no autoriza automaticamente cambiar de origen ni ejecutar otro instalador.

Mientras no exista ese caso, se mantiene el metodo autorizado actual: devuelve skipped cuando no puede ejecutarse de forma soportada y failed cuando se ejecuta pero falla, conservando el diagnostico. En reconciliacion, un enabled incumplido sigue provocando failed global aunque el intento individual haya sido skipped. No se eluden firmas, verificaciones o controles del catalogo.

fallback_methods permanece vacio y no se declara implementado un motor de fallback. Se difiere este desarrollo; no se marca como terminado ni se agrega una alternativa especulativa. La decision no crea un gestor de issues en la UI ni modifica WSDD Desktop.
