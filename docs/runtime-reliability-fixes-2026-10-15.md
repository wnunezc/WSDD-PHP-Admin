# Correccion de reconciliacion y arranque

Fecha local: 2026-10-15. Correccion de P1/P2 identificados en `development-status-2026-10-15.md`. No agrega responsabilidades a WSDD ni cierra los requisitos funcionales pendientes del plan maestro.

## Reconciliacion

ManagedReconciler conserva la intencion del perfil, agrega errores reales del instalador al resultado global y verifica el estado deseado tambien en pasadas sin cambios. Un requisito enabled ausente, un INI que no carga el modulo o un estado invalido producen failed; no quedan presentados como idempotencia exitosa. Los skipped esperados se conservan con su motivo, pero no sustituyen la verificacion.

La ruta productiva verifica CLI fresco y Apache autenticado con la ventana normal de 30 segundos; no se reduce esa ventana por los reinicios de Apache. Los callers aislados sin RuntimeVerifier verifican al menos CLI fresco. Las pruebas pueden solicitar un solo intento del verificador sin modificar el timeout productivo.

La CLI reconcile:managed devuelve 2 al fallar y comparte el bloqueo exclusivo del agente. Las operaciones de reconciliacion conservan errores y verificacion en result.json; una verificacion posterior reemplaza la anterior para no dejar detalles success con resultado failed. runtime:start no despacha procesos si la reconciliacion previa no satisface el perfil.

## Exclusion y disponibilidad

InstanceLock mantiene flock exclusivo y no bloqueante sobre archivos estables: runtime/startup.lock serializa runtime:start y runtime/agent.lock protege toda la vida del agente, agent:once y la reconciliacion CLI. Nunca se borran locks en produccion para liberarlos: los libera el kernel al cerrar el descriptor o terminar el proceso. Los descriptores usan close-on-exec para no transferir la reserva a los hijos.

AgentRunner toma el lease antes de reconciliar o consultar pendientes. El loop publica runtime/agent-status.json despues de completar ciclos, con PID, raiz, identidad del arranque, estado y timestamp. Un segundo consumidor falla antes de procesar la cola. No se convierte la cola en un sistema multiworker ni se afirma un claim distribuido por operacion.

RuntimeStarter rechaza un puerto ocupado y agentes previos, incluidos procesos anteriores a los locks cuando coinciden raiz y perfil. Reserva el consumidor durante la reconciliacion, lanza grupos de procesos propios con nohup/setsid y espera hasta 45 segundos por ambas comprobaciones:

- Heartbeat reciente ready del PID/raiz/identidad del agente recien creado.
- HTTP 200 de runtime-health.php con nonce de arranque, raiz seleccionada y usuario www-data. Un 200 de otro proceso/identidad no acredita disponibilidad.

runtime-health.php solo responde desde loopback y con la cabecera de comprobacion correcta; no sustituye el login de usuario/contraseña. La web queda en 127.0.0.1:8989 por defecto. WSDD_PHP_ADMIN_PORT permite un puerto distinto para fixtures/instalaciones configuradas, siempre limitado a loopback.

El worker interno runtime:web baja grupos, GID y UID a www-data mediante POSIX y ejecuta el servidor PHP dentro del mismo grupo de procesos, sin su/PAM. Esto evita que un hijo cambie de sesion y escape a la limpieza. Se requieren POSIX, nohup y setsid ya presentes en los entornos comprobados; no se instalaron paquetes para esta correccion.

Si un hijo termina o vence el timeout, se detienen exclusivamente grupos creados por ese intento, identificados por PID, tiempo de nacimiento en /proc y PGID propio. No se mata un puerto ocupado ni se usan PID files antiguos como autorizacion para detener procesos ajenos. El arranque solo devuelve success despues de verificar ambas disponibilidades.

## Pruebas automatizadas

bin/validate-reconciliation-failures cubre requisito enabled incumplido, perfil conservado, INI existente sin modulo cargado, verificacion CLI/Apache sin escrituras nuevas, idempotencia, estados invalidos y salida CLI 2. Como www-data tambien reproduce un fallo real del instalador por falta de root sobre una candidata ausente, sin ejecutar apt ni compilarla.

bin/validate-runtime-startup usa perfil, cola, logs e INI desechables en /tmp y puerto alternativo. Copia la credencial de la sonda Apache a su fixture root-owned sin cambiar la real. Cubre arranque positivo, heartbeat viejo/otra identidad, locks no heredados, rechazo de duplicados, una operacion inocua procesada exactamente una vez, liberacion del lease tras terminacion y dos CLI realmente simultaneas con exactamente un arranque verificado. Tambien cubre puerto ocupado, HTTP 200 con identidad incorrecta, timeout, salida prematura de un hijo y limpieza del hermano. Los fixtures nunca instalan extensiones reales.

Ambos validadores se integran en validate-container.ps1, incluidos Smoke y Full. Smoke no altera el perfil deseado real; puede usar los reloads controlados de los fixtures anteriores del proyecto y crea procesos/colas aislados para esta comprobacion.

La primera bateria aislada aprobo en los siete interpretes 5.6/7.2/7.4/8.1-8.4. Durante la bateria final el usuario retiro PHP 5.6 para otras pruebas. Posteriormente lo reconstruyo y se completo la comprobacion pendiente sobre el nuevo contenedor 03a0b3463552; ver el cierre especifico abajo. No se recreo el contenedor desde esta bateria.

Resultados de cierre:

- Smoke/passed en 5.6.40, 7.2.34, 7.4.33, 8.1.34, 8.2.34, 8.3.35 y 8.4.26; 5.6 completo esa pasada antes de retirarse. Cada pasada incluyo sintaxis de 68 archivos, fixtures comunes, pruebas nuevas y dos specs de navegador.
- La repeticion final sobre las seis versiones disponibles aprobo sintaxis, 22 comprobaciones de arranque aislado y fallos de reconciliacion tanto root como www-data. Las 11 comprobaciones www-data incluyen fallo real de privilegios del instalador y preservacion/reemplazo correcto de diagnosticos de operaciones.
- Ultimo Playwright de lectura sobre esas seis versiones: **6 passed (1.8m)**, con CLI/Apache/UI alineados, cero incidencias y perfiles/colas intactos durante las pruebas.
- En los seis agentes activos se rechazo con exit 2 cada intento duplicado de agent, agent:once, reconcile:managed y runtime:start. Se recargaron solo agentes para incorporar el ultimo detalle de diagnosticos; las webs conservaron sus PID y perfil/auth/cola/INI/extensiones no cambiaron.

La ultima ampliacion de pruebas incluye HTTP 200 comprobado con identidad incorrecta y terminacion prematura de un hijo. Esa ampliacion final ya se repitio y aprobo tambien en PHP 5.6 reconstruido. Se cierra el pendiente de validacion de P1/P2 para los siete interpretes presentes, no la fase 6 global.

## Cierre PHP 5.6 reconstruido

Contenedor 03a0b3463552, creado 2026-10-15T13:08:33Z; PHP 5.6.40, Debian 9 Stretch. No se constato una migracion de distribucion. El CMD arranco runtime:start automaticamente desde /var/www/html/phpadmin56.dock y obtuvo readiness=verified: agente root PID 6811, worker www-data PID 6813 y servidor web www-data PID 6814.

El reporte sincrono de este arranque acredita docker-php-ext-install bcmath con exit_code=0, INI administrado creado, graceful Apache success y verificacion CLI/Apache success antes de declarar disponibilidad. El perfil conserva bcmath enabled, calendar removed y pdo_pgsql disabled; su SHA256 coincide con Windows.

validate-container.ps1 -PhpVersion 5.6 -Mode Smoke termino passed: 68 archivos PHP analizados por el interprete real, fixtures comunes, 22 comprobaciones de arranque, 9 comprobaciones de reconciliacion root y dos specs Playwright aprobadas en 29.7 segundos. La ejecucion adicional como www-data aprobo las 11 comprobaciones, incluido fallo del instalador sin ejecutar paquetes ni compilar y conservacion de diagnosticos de operaciones.

El agente real rechazo agent, agent:once, reconcile:managed y runtime:start duplicados con exit 2 antes de consumir la cola. La comparacion antes/despues confirmo hashes de perfil/credenciales e INI iguales, mismas extensiones CLI, 39 operaciones y 0 pendientes. Los PID de agente/worker/web no cambiaron y el heartbeat avanzo en estado ready. La UI coincide con CLI/Apache y muestra 0 incidencias. No se reiniciaron procesos reales ni se cambiaron extensiones durante esta comprobacion; los accesos escribieron la auditoria normal y los fixtures usaron /tmp.

Este cierre no instala pdo_pgsql ni consulta APT: el fallo historico de dependencias Stretch no queda resuelto ni reproducido en la imagen nueva por estas pruebas.

## Aplicacion al runtime real

Se relanzaron de forma controlada agente y web del proyecto, no Apache ni los contenedores. runtime:start devolvio readiness=verified en los siete entornos antes de retirarse 5.6. La comparacion anterior/posterior confirmo mismos hashes de perfil y credenciales, misma cantidad de operaciones/pending=0, mismos INI administrados y mismas extensiones CLI cargadas. Bcmath enabled permanecio intacta donde existia.

Durante la continuacion se observo un reinicio de Docker y el transporte tcp://localhost:2375 dejo de responder; las comprobaciones siguieron por el contexto desktop-linux sin cambiar la configuracion de WSDD. Un intento intermedio con su no cumplio readiness; se corrigio el aislamiento de grupos antes de aprobar las pruebas. No se contabiliza ese intento como arranque exitoso.

Los procesos PHP antiguos del bind Dev volvieron a analizar bytes anteriores despues de ediciones Windows. Reescribir exactamente los bytes actuales a traves del mount restablecio el analisis, sin segunda copia del proyecto ni sincronizacion /opt. Es una observacion del entorno Dev, no una nueva mecanica de instalacion del producto.

## Limites conservados

Se corrigen los dos defectos de fiabilidad; no se implementan un supervisor permanente, reinicio automatico de hijos despues del arranque, rollback binario ni reglas de incompatibilidad. El loop puede publicar degraded si su reconciliacion directa de arranque fallo; eso no se considera readiness correcta.

La fase 6 global sigue abierta por versiones/evidencias y requisitos ya documentados. No se cierra el problema historico de dependencias Stretch ni se valida una imagen 5.6 nueva sin probarla. El contrato WSDD sigue siendo una sola llamada runtime:start desde una sola raiz Dev/Release.
