# Contrato comun y bateria por contenedor

Fecha local: 2026-10-14.

Actualizacion de cierre 2026-10-15: las siete pruebas post-rebuild aprobaron tras la recreacion general del usuario. Se cierran persistencia/arranque y continuidad observada del agente; reinstalacion bcmath enabled comprobada en 5.6/7.2/7.4/8.1/8.3. El estado removed de 8.2/8.4 no acredita reinstalacion. El success global incorrecto de reconciliacion ya se corrigio y se cubre con pruebas especificas, junto con exclusion/readiness: `runtime-reliability-fixes-2026-10-15.md`. PHP 5.6 volvio reconstruido como 03a0b3463552 y aprobo Smoke, fixtures finales y navegador; ya no queda pendiente su comprobacion de estas correcciones. No se certifican dependencias APT con una prueba que no las instala.

## Regla de trabajo

Cada version PHP integrada debe satisfacer el mismo contrato de runtime, persistencia, seguridad, catalogo, operaciones y UI. No se implementan paneles, agentes o bucles de pruebas distintos por version. El agente y la web permanecen en la misma raiz seleccionada por WSDD; WSDD-PHP-Admin conserva sus responsabilidades internas.

`config/php-compatibility.json` centraliza versiones objetivo, minimo runtime, minors revisados de recetas, evidencia por version y plantillas de nombre del contenedor, dominio y raiz Dev. Incluye la excepcion historica de la raiz PHP 8.3. La matriz PHP, los ejecutores PowerShell y los proyectos Playwright consumen esos datos. Ya no contienen listas independientes 8.3/8.4 ni ternarios que dirijan cualquier otra version a 8.4.

Las recetas comunes estan en `config/extension-catalog.php`, agrupadas por distribucion. `CompatibilityPolicy` autoriza explicitamente 5.6, 7.2, 7.4, 8.1, 8.2, 8.3 y 8.4, bajo el limite <8.5 para esas recetas. El campo recipe_versions impide habilitar PHP 8.0 automaticamente por ampliar el intervalo. Se contrastan fuentes exactas del PHP instalado, sistema operativo, modulos/binarios y herramientas. GD/ZIP anteriores a 7.4 seleccionan variantes de configuracion/dependencias revisadas desde datos comunes. No se copian recetas ni motores para agregar un minor. Las dependencias externas siguen sujetas a repositorios autenticados disponibles.

El minimo declarado de Composer se verifica contra la politica central: ^5.6 || ^7.0 || ^8.0. El rango ejecutable y la evidencia no son sinonimos: una version pendiente no pasa a validada por cambiar una etiqueta. El codigo comun usa sintaxis PHP 5.6, contratos explicitos, resultados externamente inmutables y adaptadores de APIs. En PHP 5.6 el chequeo usa php -n -l por archivo porque TOKEN_PARSE no existe; en versiones posteriores se conserva el analisis por tokens. Ver `php56-php72-validation-2026-10-15.md` para evidencia y limites, incluido APT de Stretch.

## Ejecutar el procedimiento

Un solo ejecutor para cualquier version objetivo del contrato:

```powershell
./bin/validate-container.ps1 -PhpVersion 8.2 -DevelopmentPassword $env:WSDD_TEST_PASSWORD -Mode Full
```

Cambiar PhpVersion selecciona el entorno desde la politica; no requiere editar las specs. Los modos son:

- Smoke: deteccion, permisos, perfil, reconciliacion de lectura, analisis sintactico de todos los PHP/CLI bajo el interprete de destino, validacion de APIs portables e inmutabilidad, validaciones aisladas y navegador sin operaciones persistentes de extensiones. Los fixtures de seguridad/reconciliacion pueden usar graceful reload para verificar el controlador; no equivalen a ausencia absoluta de efectos operativos.
- Full: agrega ciclos reales sobre calendar, bcmath y pdo_pgsql, reconciliacion managed de prueba y UI interactiva. El ciclo instala solo cuando falta el binario; si ya existe, prueba activacion sin recompilar. Omite y reporta las extensiones previamente cargadas o con intencion enabled/disabled, preservando el estado del usuario. Conserva binarios, paquetes de sistema, auditoria y snapshots. Los ciclos individuales verifican removed; la preparacion final deja una candidata administrada enabled para la prueba de reconstruccion, segun el criterio de cierre. No elimina paquetes ni binarios base.

El ejecutor exige contenedor activo, mounts independientes escribibles de estado/logs, un agente root y una web www-data desde la misma raiz. Comprueba que la web interna conserva su PID durante las pruebas. Una fase fallida detiene la bateria con error; el resultado final identifica ciclos completados y estados preexistentes preservados.

Correccion 2026-10-15: el ciclo detiene inmediatamente las acciones siguientes si una instalacion/activacion o su verificacion falla; no continua con disable/rollback sobre una instalacion fallida. La limpieza admite skipped si no existe INI propio, sin declararlo una instalacion exitosa. PrepareRebuild normaliza tambien el Source de bind mounts /run/desktop/mnt/host/c hacia Windows antes de comparar hashes. No se reconstruye ningun contenedor automaticamente.

La bateria cubre login, primer cambio de password si procede, rechazo anonimo, CSRF, vistas y API, filtrado de Install, fuentes/herramientas ausentes, no apropiacion de configuracion base, snapshots, rollback, perfil, auditoria, recarga Apache y confirmacion separada CLI/Apache. Playwright compara los contadores y listas visibles, comprueba actualizacion automatica y recuperacion de un fallo temporal. Las comprobaciones de lectura verifican hashes de perfil y cantidad de operaciones intactos.

Desde la correccion de fiabilidad, Smoke/Full incluyen validate-reconciliation-failures y validate-runtime-startup. Estos fixtures verifican fallos requeridos e INI sin modulo, conservacion de diagnosticos, salidas CLI no cero, locks, arranques concurrentes, readiness autentica, puerto ocupado, timeout y limpieza de hijos propios. El segundo fixture crea un agente/web y una operacion inocua en /tmp con puerto alternativo, no en la cola real. No se ejecutan ambas baterias sobre el mismo fixture simultaneamente. Las pruebas adicionales como www-data se ejecutan con WSDD_REVIEW_NONROOT=1 para provocar un fallo de privilegios del instalador sin compilar.

La prueba UI interactiva dispone de 180 segundos para sus dos operaciones, ventanas de observacion/reconexion y limpieza. El anterior presupuesto global de 60 segundos podia agotarse aunque la cola confirmara exito; el primer intento PHP 8.2 lo evidencio. La limpieza no encola una retirada redundante si la accion UI ya la completo. Este ajuste pertenece a la prueba comun, no a una excepcion PHP 8.2.

La espera de recuperacion del aviso de red es de 30 segundos para todos los minors: en 5.6 la traza mostro peticiones de hasta 6.7 segundos en cola, mas el intervalo de 5 segundos, agotando el anterior limite de 12 segundos. La asercion sigue exigiendo desaparicion real del aviso. Las tres specs pasaron al repetir en 5.6; no se convirtio una recuperacion fallida en aprobada por omitir la prueba.

El descubrimiento de fuentes reutiliza un inventario de archivo solo mientras coincidan ruta, PHP, inode, tamano, mtime y ctime. No cachea snapshots CLI/Apache, decisiones de permisos o herramientas. El cambio de archivo invalida el inventario y tiene una continuidad funcional con archivos temporales; el arbol de fuentes extraido se inspecciona directamente. Esto evita releer el mismo archivo de fuentes dentro de una solicitud.

Playwright recibe la version del ejecutor mediante WSDD_TEST_PHP_VERSIONS; sin esa variable selecciona las versiones con evidencia en la politica. Dependencias/artefactos siguen en el runtime externo del workspace y solo se ejecutan specs de este proyecto. WSDD_TEST_PASSWORD se pasa por entorno y no se escribe en el repositorio.

Los artefactos Playwright se separan por conjunto de versiones solicitado. Ejecutar la continuidad funcional de otro minor ya no limpia capturas/trazas de la bateria anterior. La adaptacion PHP 7.4/8.1 y su evidencia se describen en `php74-php81-validation-2026-10-14.md`.

## Criterio de cierre

Correccion del cierre de Full: los ciclos individuales siguen verificando remove, pero al finalizar toda la bateria se deja una extension administrada enabled para la reconstruccion. Se conserva una candidata ya enabled; si no existe, se prefiere bcmath entre los ciclos completados. No se sobrescriben estados disabled preexistentes. La preparacion reutiliza `validate-runtime-integration.ps1 -Mode PrepareRebuild`, instala o activa mediante cola/agente y exige perfil enabled, INI propio, CLI/Apache alineados, 0 incidencias y hash del perfil coincidente con el bind mount Windows real. Si no hay candidata segura o falla la verificacion, Full no declara exito. Smoke no cambia el estado deseado. El modo PrepareRebuild se ejecuto correctamente para dejar bcmath enabled en PHP 7.4 y 8.1; la bateria Full completa ya habia pasado antes de este ajuste final.

Debe distinguirse el soporte funcional probado de la validacion de reconstruccion: el ejecutor no destruye ni reconstruye contenedores. Una prueba posterior a una reconstruccion del usuario comprueba persistencia y reinstalacion automatica desde el perfil. Esa evidencia no se inventa ni se sustituye por cambiar el rango de versiones.

La primera aplicacion completa del procedimiento comun corresponde a PHP 8.2.34: Full termino passed con tres ciclos y tres specs de navegador, conservando el PID de la web. Las pruebas previas y la continuidad funcional de lectura/catalogo PHP 8.3/8.4 se mantienen como referencia. El informe `php82-validation-2026-10-14.md` registra el resultado real y sus limites.

El mismo ejecutor termino Smoke/passed en PHP 8.3.35 y 8.4.26, incluyendo los fixtures comunes y dos specs de navegador por version. La comprobacion final de las tres versiones encontro 21 recetas, 0 incidencias, 0 errores de observacion y 0 operaciones pendientes. Los hashes de profile.json coinciden entre Windows y cada contenedor. Se conservo bcmath enabled de PHP 8.3; las extensiones de prueba permanecen removed en sus respectivos perfiles.

Ampliacion 7.4/8.1: Full/passed en PHP 7.4.33 (Debian Buster) y 8.1.34 (Debian Trixie), con compilacion real de calendar/bcmath/pdo_pgsql y tres specs de navegador por entorno. Tras el port comun se repitio Smoke/passed en 8.2.34, 8.3.35 y 8.4.26. La matriz solo marco catalog-validated-dev al terminar las baterias; no se cerraron reconstrucciones todavia no realizadas. Ver `php74-php81-validation-2026-10-14.md`.

Reconstruccion posterior 7.4/8.1 realizada por el usuario y validada: persistencia, credenciales, historico, snapshots, arranque automatico y concordancia CLI/Apache/UI correctos; 4 specs de lectura aprobadas, sin cambios de perfil/auth/cola/procesos. Esta evidencia cubre el perfil removed que existia realmente. Para cerrar reinstalacion automatica debe haber una extension enabled antes de reconstruir; una bateria Full que termina removed no prepara esa prueba. No se declara reinstalacion probada cuando no habia nada habilitado que reinstalar.

Segunda reconstruccion 7.4/8.1, ya con bcmath enabled: se comprobo instalacion automatica real en el log de arranque, exit_code=0 de docker-php-ext-install, INI propio, reload y verificacion CLI/Apache antes de levantar la web. Cuatro specs de navegador aprobaron en 50.3 segundos y los perfiles/auth/colas/procesos no cambiaron durante la inspeccion. Esta segunda evidencia cierra reinstalacion desde enabled para ambas versiones; bcmath permanece habilitada.
