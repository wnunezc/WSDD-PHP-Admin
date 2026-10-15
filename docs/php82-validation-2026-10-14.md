# PHP 8.2: runtime y descenso gradual

Fecha local: 2026-10-14.

Actualizacion 2026-10-15: la recreacion general SI valido persistencia, credenciales, arranque automatico y continuidad del agente en PHP 8.2.34 (contenedor fcb6def40aa8). Perfil Windows coincidente, 0 pendientes, CLI/Apache/UI concordantes y prueba post-rebuild aprobada. Las tres extensiones del perfil estaban removed: NO se probo reinstalacion desde enabled. Las referencias posteriores a reconstruccion pendiente describen la bateria historica del 14, no el estado vigente. Cierre y pendientes actuales: `development-status-2026-10-15.md`.

La version minima declarada en Composer se ajusto de ^8.3 a ^8.2 despues de comprobar el contenedor real WSDD-Web-Server-PHP8.2 con PHP 8.2.34. La revision inicial fue smoke. Posteriormente se habilito el catalogo mediante la politica comun y se ejecuto la bateria funcional; ver ampliacion al final y `container-validation-standard-2026-10-14.md`. Install_catalog ya es implemented.

## Evidencia

- Raiz Dev `/var/www/html/phpadmin82.dock`, agente root y web interna www-data activos desde esa misma raiz.
- Estado/logs montados por separado en php82. Perfil creado automaticamente, valido, sin extensiones declaradas y con reconciliacion de arranque success sin cambios.
- CLI de reconciliacion con 0 incidencias; consulta local autenticada del probe confirma PHP 8.2.34, SAPI apache2handler y raiz correcta.
- HTTPS responde y el login inicial admin/admin conduce al cambio obligatorio de password. No se cambio la credencial ni se afirmo haber validado toda la UI autenticada.
- Composer validate --no-check-publish paso bajo PHP 8.2.34. Los archivos PHP de src/config/public revisados pasaron lint en ese interprete.
- Pasaron validate-profile-bootstrap, validate-agent-safety, validate-agent-response, validate-security-hardening y la prueba corregida validate-runtime-observation.
- La prueba de observacion elige un modulo compartido independiente existente entre bz2, calendar y sodium. En 8.2 usa sodium; no instala ninguno. Los cambios de INI ocurren solo en un directorio temporal y se valida que un proceso nuevo refleje enable/disable mientras el proceso original conserva su estado. La eleccion se hace antes de crear temporales para no dejar residuos si falta un modulo apto.
- La misma prueba paso en PHP 8.3 y 8.4 usando bz2. No se reiniciaron contenedores ni se modificaron INI reales para esta correccion.

El descubrimiento dinamico encuentra 64 definiciones config.m4 en las fuentes PHP 8.2.34. Inicialmente no habia recetas autorizadas; la politica comun ahora habilita las 21 recetas revisadas para el rango >=8.2 y <8.5 en Debian. Todas tienen fuente presente y los pasos GD/PostgreSQL usados se contrastaron con los config.m4 de 8.2.34. Sigue sin haberse realizado una reconstruccion posterior de PHP 8.2 para validar esa supervivencia.

## Direccion acordada

Continuar el descenso por versiones WSDD hacia PHP 5.6 si es viable, con el mismo contrato, ejecutor y specs, y estado independiente. Despues de PHP 8.2 se evaluaran 8.1, 8.0, 7.4, 7.2 y 5.6. No se decidio introducir un administrador externo, otra raiz runtime ni nuevas responsabilidades WSDD.

## Ampliacion funcional

Resultado final del ejecutor comun: PHP=8.2.34, Mode=Full, CompletedCycles=[calendar,bcmath,pdo_pgsql], Preserved=[], Status=passed. Pasaron las tres specs de navegador (catalogo, lectura y actualizacion interactiva). La matriz ya declara catalog-validated-dev para 8.2.

La verificacion final encontro 0 incidencias, 0 errores de observacion y 0 pendientes. Profile.json coincide por SHA256 con su archivo Windows. El PID web 1772 se mantuvo durante toda la bateria; libpq-dev/libpq5 17.11-0+deb13u1 permanecen como dependencias del ensayo. La reconstruccion posterior para probar restauracion desde perfil aun no se realizo.

- Se compartieron las recetas mediante politica de rango y distribucion, no mediante una copia especifica para PHP 8.2. La matriz y los ejecutores leen la misma definicion de entornos.
- Calendar se compilo realmente mediante la cola web y el agente. Pasaron install, disable, rollback, disable, enable, reconcile y remove, con verificacion CLI/Apache y 0 incidencias visibles.
- Bcmath y pdo_pgsql tambien se instalaron realmente en PHP 8.2, con los mismos ciclos. PDO_PGSQL ejercito la instalacion de libpq-dev mediante apt y los helpers de esa imagen.
- El primer login y el cambio obligatorio se probaron usando la password de desarrollo indicada por el usuario; ya no se limita la evidencia al formulario inicial.
- Las extensiones de prueba quedan removed, sin INI activo; permanecen los binarios/dependencias y los registros de operaciones/snapshots. No se destruyeron contenedores ni se reinicio la web interna.
- El conjunto de 21 recetas se valido estructuralmente y contra las fuentes, no se compilaron las 21 individualmente. Los ensayos de versiones/distribuciones no autorizadas siguen bloqueados.
- El primer intento completo detecto un timeout global demasiado corto en la prueba UI; se contrasto con operaciones exitosas, se corrigio el presupuesto/limpieza de la prueba comun y se repitio el ejecutor completo hasta obtener passed. No se interpreto ese primer intento como compatibilidad aprobada.

Cada version minima se declara solo despues de revisar sintaxis, APIs y runtime. El codigo aun contiene propiedades readonly, tipos union, match y otras construcciones modernas: no se declara compatible con versiones antiguas cambiando solamente Composer o la matriz. Si una version exige cambios incompatibles con el diseno actual, se documentaran los obstaculos antes de decidir la adaptacion.
