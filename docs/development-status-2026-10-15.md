# Estado real y revision de cierre

Fecha: 2026-10-15. Alcance: codigo actual y contenedores WSDD Apache + CLI en modo Dev. No es una certificacion Release ni una ampliacion del plan maestro.

Actualizacion de fiabilidad del mismo dia: P1/P2 descritos abajo fueron corregidos en codigo y tienen pruebas especificas. Se completo la repeticion final tambien en PHP 5.6 reconstruido (03a0b3463552): Smoke/passed, 22 comprobaciones de arranque, 11 comprobaciones como www-data y dos specs de navegador aprobadas. Se cierra el pendiente de fiabilidad en los siete interpretes presentes. Detalle y limites: [Correccion de reconciliacion y arranque](runtime-reliability-fixes-2026-10-15.md). La revision original se conserva como evidencia del defecto, no como descripcion del codigo ya corregido.

## Dictamen

El hito inicial de PHP 8.3 esta implementado y validado. El downgrade del codigo comun permite ejecutar la misma aplicacion en PHP 5.6, 7.2, 7.4 y 8.1-8.4. Las fases 1-5 estan cerradas en el alcance inicial Dev/hardening base y no se reabren por ampliaciones. La fase 6 conserva pendientes de versiones/evidencia de validacion, no por ampliaciones diferidas a issues. No se declara el producto listo para distribucion o produccion.

## Reconstruccion general validada

El usuario recreo los siete contenedores. La revision posterior fue de lectura: no se encolaron operaciones ni se instalaron, retiraron o reactivaron extensiones manualmente. Los logs de arranque acreditan la reinstalacion automatica; no se la deduce de un HTTP 200.

| PHP real | Contenedor nuevo | Agent/web PID | Perfil tras recreacion | Reinstalacion desde enabled |
| --- | --- | --- | --- | --- |
| 5.6.40 | ad56405d294f | 6775 / 6779 | bcmath enabled; calendar removed; pdo_pgsql disabled | bcmath, compilacion exit_code=0 |
| 7.2.34 | 1b4fc634f9ae | 6998 / 7002 | bcmath enabled; calendar/pdo_pgsql removed | bcmath, compilacion exit_code=0 |
| 7.4.33 | 84fa12057d38 | 6916 / 6920 | bcmath enabled; calendar/pdo_pgsql removed | bcmath, compilacion exit_code=0 |
| 8.1.34 | 52463383aa71 | 6776 / 6781 | bcmath enabled; calendar/pdo_pgsql removed | bcmath, compilacion exit_code=0 |
| 8.2.34 | fcb6def40aa8 | 1761 / 1766 | bcmath/calendar/pdo_pgsql removed | No ejercitada: no habia enabled |
| 8.3.35 | d86fd94a7e81 | 6605 / 6610 | bcmath enabled; calendar removed | bcmath, compilacion exit_code=0 |
| 8.4.26 | 2b021fc93cc8 | 1762 / 1767 | bcmath/calendar/pdo_pgsql removed | No ejercitada en esta recreacion |

Las creaciones ocurrieron entre 11:07:59 y 11:10:20 UTC del 2026-10-15. En los cinco casos enabled, el reporte sincrono de arranque registra docker-php-ext-install bcmath, INI propio, graceful de Apache y verificacion CLI/Apache antes de levantar la web. El segundo pase del agente no repite cambios. En 8.2/8.4 se respeta removed sin compilar extensiones.

En los siete entornos se comprobaron perfil coincidente por SHA256 con Windows, credenciales existentes, un agente root y una web www-data desde la misma raiz Dev, cola sin pendientes, procesos estables y avance de ciclos terminados del agente. La observacion demuestra continuidad durante esa ventana, no supervision o autorrecuperacion ilimitadas. El perfil se conserva; se restaura el estado declarado, no se reemplaza el perfil por uno nuevo.

Playwright termino **7 passed (2.0m)** con post-rebuild.spec.cjs: login real, vistas autenticadas, extensiones y contadores visibles, CLI/Apache/UI concordantes, cero incidencias, cero errores de observacion y perfil/cola sin cambios durante la prueba. Las sesiones/accesos producen auditoria normal. No se repitio la bateria interactiva completa en esta inspeccion.

En la revision de codigo posterior, validate-php-compatibility aprobo de nuevo los 63 archivos bajo los siete interpretes, con pruebas de contratos, JSON, procesos, inmutabilidad y errores HTTP. La evidencia Full anterior y sus limites se conserva en los informes por version.

## Revision original: defectos corregidos

### P1 corregido: agregacion de fallos de reconciliacion

En src/Reconciliation/ManagedReconciler.php:116, un fallo del instalador se guarda en skipped con install_status=failed. El resultado global en :95 solo considera fallos de reload/verificacion; la verificacion en :86 se ejecuta solo si hubo cambios. Por tanto, cuando nada cambio, puede devolver status=success con una extension enabled aun ausente. bin/wsdd-php-admin:87 convierte ese success en exit 0 y runtime:start puede continuar.

Se reprodujo como www-data en PHP 8.4, usando un perfil e INI temporales en /tmp, con calendar enabled y ausente. Resultado: status=success, applied=[], verification=skipped y skipped[0].install_status=failed por requerir root. No se intento apt, no hubo reload y se eliminaron los temporales. El caso confirma el defecto de agregacion; no simula un fallo real de repositorio como si ya hubiera ocurrido en ese contenedor.

Debe distinguirse idempotencia/skipped esperado de un requisito enabled incumplido; propagar el fallo/degradacion y comprobar el estado deseado aunque no se escriba un INI. Tambien revisar el retorno temprano por existencia de INI en :106: su existencia por si sola no acredita que el modulo cargue. La UI de lectura puede detectar drift, pero no corrige este resultado global. No se modifico codigo productivo en esta revision documental.

### P2 corregido: exclusion y disponibilidad de arranque

bin/wsdd-php-admin:168-173 despacha agente y web en background y sale sin confirmar readiness. No hay lock de instancia unica en runtime:start/AgentRunner ni claim atomico de operaciones en OperationRepository::pending. Una doble invocacion puede crear agentes competidores; AgentRunner::runOnce lee pendientes antes de escribir processing y processing sigue siendo retomable. Las escrituras atomicas de JSON no sustituyen el lock del consumidor.

Es un riesgo confirmado por lectura de codigo, no una carrera ejecutada en los contenedores. La topologia normal con una invocacion por CMD fue validada y no mostro duplicados. Deben incorporarse/verificarse exclusion y readiness antes de ofrecer garantias de arranque repetible o recuperacion. Son responsabilidades internas de WSDD-PHP-Admin, no nuevos scripts que WSDD deba perseguir.

## Cobertura frente al plan maestro

| Requisito existente | Estado comprobado |
| --- | --- |
| UI, navegacion, runtime, permisos, cola y actualizacion | Implementados y probados; faltan filtros explicitos por estado en la vista Extensions |
| Catalogo seguro y descubrimiento real | 21 recetas compartidas, variantes GD/ZIP antiguas; no 21 compilaciones certificadas |
| Instalar/activar/desactivar/revertir | Ciclos managed probados segun informes; remove retira INI propio, no paquetes/binarios |
| Perfil deseado y persistencia | Implementados; ampliacion aditiva de metadatos sin cambiar schema_version ni intencion |
| Estado observado, metodo/version/dependencias, ultima reconciliacion persistidos | Implementados en operaciones/reconciliacion; desconocidos explicitos, historial separado de verdad actual. Pruebas aisladas 5.6/8.4 aprobadas; bateria funcional final pendiente |
| Incompatibilidades entre extensiones y bloqueo preventivo | Decision posterior del usuario: tratar problemas detectados como issues; reglas preventivas solo con casos confirmados. No hay motor implementado ni certificacion de combinaciones |
| Configuracion obligatoria de extensiones | Revision documental de las 21 recetas: no se identificaron parametros de usuario obligatorios para carga. No se agregan formularios ficticios; el flujo condicional para futuras necesidades verificadas sigue sin implementar |
| Fallback de instalacion | Diferido por decision del usuario: se implementara/utilizara cuando un issue demuestre la necesidad y exista una alternativa segura verificable. fallback_methods sigue vacio; no hay motor ejecutable |
| Autenticacion, CSRF, auditoria, snapshots/rollback INI | Base implementada y probada; no equivale a rollback binario ni hardening Release completo |
| Modo seguro ampliado y ultimo estado funcional | Ampliaciones diferidas a issues por decision del usuario; no implementadas, no bloquean cierres del alcance actual |
| Distribucion/instalacion Release sin bind Dev | No validada; /opt es contrato futuro, no paquete publicado probado |

Actualizacion posterior: ProfileMetadataRecorder completa entradas existentes con observaciones CLI/Apache, version comprobada, evidencia de instalacion y dependencias de receta; actualiza last_reconciliation y last_operation sin sustituir la intencion. Las fechas/metodos historicos desconocidos y reglas de conflictos ausentes siguen null. Detalle y limites: [Metadatos del perfil](profile-metadata-2026-10-15.md). Esta ampliacion no cierra la bateria funcional pendiente ni los otros requisitos.

## Cierres y trabajo siguiente

Orden acordado: primero terminar desarrollo pendiente y al final ejecutar las validaciones pendientes. Primer bloque implementado: metadatos del perfil. Decision posterior: incompatibilidades pasan a seguimiento por issues ante problemas detectados, sin motor especulativo. Se reviso configuracion de las 21 recetas y no se identificaron parametros de usuario obligatorios de carga; no se declara implementado el flujo condicional futuro. El fallback tambien queda diferido a una necesidad demostrada mediante issue, no es el siguiente desarrollo. Siguen recuperacion ampliada y filtros por estado; los limites de remocion binaria y Release siguen documentados. Evidencia y alcance: [Revision de configuracion e incompatibilidades](extension-configuration-review-2026-10-15.md). No se cierran fases adicionales por esta revision.

Correccion de alcance por instruccion expresa del usuario: las ampliaciones no son necesidades obligatorias y se desarrollaran cuando un issue las justifique. Se retira la recomendacion de implementar recuperacion ampliada/modo seguro como siguiente bloque. Rollback, snapshots y verificaciones existentes conservan su cierre; no se duplican ni se reabren fases por funciones futuras. La referencia anterior a recuperacion ampliada como desarrollo pendiente queda sustituida por esta decision. Los filtros siguen siendo un requisito UI identificado, no un motivo para reabrir las fases 1-5. El trabajo de cierre se concentra en las validaciones pendientes del alcance acordado.

- Se cierran persistencia, arranque automatico, observacion UI y continuidad observada del agente en los siete contenedores actuales.
- Se cierran reinstalacion desde enabled y arranque automatico posterior al downgrade para PHP 5.6/7.2; se ratifican 7.4/8.1/8.3.
- PHP 8.2 cierra persistencia/arranque tras recreacion, pero no reinstalacion desde enabled; esa evidencia sigue pendiente para 8.2 y para el estado actual de 8.4.
- Fase 6 global abierta: PHP 8.0/8.5 aun sin entorno/evidencia; PHP 5.6 sin bateria de dependencias completa tras el fallo historico Stretch; no se ha elegido ni validado imagen alternativa. La comprobacion posterior a recreacion no reintento pdo_pgsql ni APT: no acredita que ese fallo este resuelto ni que se haya reproducido en la nueva imagen.
- Resultado fiable de reconciliacion y exclusion/readiness implementados y validados tambien en el PHP 5.6 reconstruido; ver `runtime-reliability-fixes-2026-10-15.md`. Siguen pendientes las obligaciones funcionales de la tabla, no esta comprobacion final. No se crean fases nuevas ni se trasladan estas tareas a WSDD.

Las fases 1-5 conservan su cierre en el alcance acordado. Incompatibilidades, fallback y recuperacion ampliada se gestionan por issues y no bloquean ese cierre. Los formularios condicionales sin un caso aplicable tampoco son una obligacion artificial. Esto no certifica Release ni sustituye evidencia de pruebas aun pendiente.
