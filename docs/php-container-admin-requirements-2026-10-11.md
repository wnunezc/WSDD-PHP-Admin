# Plan maestro: WSDD-PHP-Admin

Fecha: 2026-10-11

Estado revisado: 2026-10-15. Resumen vigente, cierres por entorno y pendientes comprobados en codigo: [Estado real y revision de cierre](development-status-2026-10-15.md). Los avances fechados anteriores se conservan como historial; no sustituyen este estado vigente.

Avance posterior al respaldo cd9eb9d: implementado registro ampliado del perfil (observaciones CLI/Apache, evidencia de instalacion, dependencias y resultados de reconciliacion), sin alterar intenciones ni esquema v1. Pruebas aisladas aprobadas en 5.6/8.4; no equivale a bateria funcional final ni cierre adicional de fases. Se acuerda completar desarrollo pendiente antes de las validaciones pendientes. Ver [Metadatos del perfil](profile-metadata-2026-10-15.md).

Correccion posterior: fallos globales de reconciliacion y arranque sin exclusion/readiness resueltos en codigo, con validadores especificos integrados en la bateria. La comprobacion final ya aprobo tambien en PHP 5.6 reconstruido, contenedor 03a0b3463552. Evidencia y limites: [Fiabilidad de runtime](runtime-reliability-fixes-2026-10-15.md).

## Origen del proyecto

WSDD-PHP-Admin nace como una necesidad detectada durante el desarrollo y mantenimiento de WSDD:

`D:/OpsZone/DevWorkspace/Projects/Desktop/WSDD`

La necesidad aparece porque WSDD administra contenedores PHP para proyectos locales, pero no existe actualmente una herramienta que permita administrar extensiones PHP desde una interfaz web dentro del propio contenedor, con perfil persistente, reconciliacion automatica tras reconstrucciones y sin comandos manuales del usuario final.

Aunque WSDD-PHP-Admin surge desde WSDD, debe diseñarse como una herramienta independiente y reutilizable. La compatibilidad inicial se define por niveles: primero las imagenes PHP usadas por WSDD, luego otras imagenes PHP segun reportes, pruebas y necesidades reales.

## Vision

Crear WSDD-PHP-Admin: una aplicacion web instalable programaticamente dentro de un contenedor PHP para administrar extensiones y configuracion PHP del mismo contenedor, sin comandos manuales del usuario final y sin depender de un panel externo que controle contenedores propios.

WSDD-PHP-Admin debe resolver el problema de drift entre desarrollo, instalacion local y contenedores reconstruidos: toda extension o ajuste aplicado desde la interfaz debe quedar representado en un perfil persistente y debe poder restaurarse automaticamente despues de una reconstruccion o recreacion del contenedor.

Aunque el primer consumidor sera WSDD, el diseno debe permitir ampliar compatibilidad por niveles hacia otras imagenes PHP razonablemente estandar, incluyendo imagenes basadas en Apache, FPM o CLI, siempre que el contenedor incluya o permita instalar las herramientas necesarias.

## Objetivo del proyecto

Desarrollar WSDD-PHP-Admin como administrador web de extensiones/configuracion PHP con:

- interfaz web clara y usable;
- instalacion programatica dentro del contenedor PHP;
- deteccion del runtime PHP disponible;
- instalacion, activacion y desactivacion de extensiones;
- perfil persistente montable con Docker bind mount o volumen;
- reconciliacion automatica del estado al iniciar el contenedor;
- auditoria de cambios;
- separacion entre configuracion base del contenedor y configuracion administrada por WSDD-PHP-Admin.

## Alcance inicial

El alcance inicial de WSDD-PHP-Admin es administrar extensiones PHP y configuracion PHP del contenedor donde la aplicacion esta instalada.

El proyecto no debe intentar administrar contenedores externos, crear proyectos web, reemplazar Docker Compose, reemplazar WSDD, reemplazar Webmin ni convertirse en un panel de hosting completo.

## Decisiones tecnicas confirmadas

- En modo Dev, WSDD-PHP-Admin corre desde el bind mount del proyecto en `/var/www/html/phpadmin.dock`.
- En modo Release futuro, WSDD-PHP-Admin debe existir como paquete/instalacion en `/opt/wsdd-php-admin` o una raiz equivalente definida por release.
- La reconciliacion del perfil persistente corre en runtime al arrancar el contenedor.
- Se permite una CLI interna/operativa, siempre que el usuario final no tenga que ejecutarla manualmente.
- El backend web puede ejecutar comandos internos para instalar, activar, desactivar o reconciliar extensiones.
- WSDD-PHP-Admin debe detectar permisos disponibles antes de ejecutar acciones sensibles, incluyendo usuario efectivo, permisos de escritura, disponibilidad de herramientas, posibilidad de usar privilegios elevados y errores por falta de permisos.
- El scope inicial de release es PHP Apache + PHP CLI en contenedores WSDD.
- PHP-FPM queda fuera del primer hito y se evaluara como compatibilidad futura.
- PHP 8.3 sera la version usada para el desarrollo inicial y las pruebas manuales principales, para no afectar el entorno local PHP 8.4 y porque PHP 8.5 aun no esta implementado en WSDD.
- WSDD-PHP-Admin debe reiniciar Apache/PHP internamente cuando sea suficiente. Si hace falta recrear o reiniciar el contenedor completo, esa accion debe quedar en manos de WSDD Desktop.
- Si una extension requiere parametros obligatorios de configuracion, la activacion debe quedar bloqueada hasta que el usuario complete el formulario correspondiente.
- Los archivos `.ini` generados por WSDD-PHP-Admin deben usar zona o prefijo propio, y deben ser reconocidos por PHP/Apache inicialmente y por PHP-FPM cuando exista soporte futuro.

## Comportamiento WSDD que debe copiarse

WSDD ya conserva estado local de contenedores PHP mediante archivos y directorios persistentes montados desde Docker Compose. WSDD-PHP-Admin debe copiar este modelo, no inventar un modelo paralelo.

En las versiones PHP actuales, `options.phpXX.yml` monta directorios locales del arbol Docker de WSDD dentro del contenedor PHP:

- `./dev:/var/www/html/dev`
- `./vhost:/etc/apache2/sites-enabled`
- `./php.ini:/usr/local/etc/php/php.ini`
- `./cron:/etc/cron-task`
- `./log/apache2:/var/log/apache2`
- `./log/cron:/var/log/cron`

Este patron permite que logs, cron, vhosts y configuracion PHP sobrevivan recreaciones del contenedor porque viven fuera del filesystem efimero del contenedor.

WSDD tambien agrega proyectos al mismo `options.phpXX.yml` de forma incremental:

- agrega dominios a `VIRTUAL_HOST`;
- agrega `extra_hosts` para self-calls;
- agrega mounts de proyecto;
- agrega declaraciones globales de volumen externo;
- preserva estado existente porque `options.phpXX.yml` es estado vivo administrado por WSDD, no una plantilla inmutable.

WSDD sincroniza `Dockerfile` y `webserver.phpXX.yml` desde templates administrados, pero no debe sobrescribir de forma destructiva `options.phpXX.yml`, vhosts de proyectos, certificados, volumenes, datos ni configuraciones persistentes.

WSDD-PHP-Admin debe seguir ese mismo contrato:

- WSDD Desktop debe crear/montar un directorio persistente propio para WSDD-PHP-Admin;
- el perfil persistente debe vivir fuera del filesystem efimero del contenedor;
- la reconciliacion debe leer ese perfil al iniciar;
- WSDD Desktop debe actualizar los compose/templates PHP para montar ese directorio;
- los cambios de WSDD-PHP-Admin no deben pisar vhosts, cron, logs, proyectos ni `php.ini` administrados por WSDD.

## Integracion HTTP interna

Actualmente Webmin se instala en el contenedor PHP durante build, escucha internamente en el puerto `10000` y se publica mediante un vhost Apache como `wmXX.wsdd.dock`, por ejemplo `wm83.wsdd.dock`, usando `ProxyPass` hacia `http://127.0.0.1:10000/`.

WSDD-PHP-Admin debe copiar ese patron:

- escuchar en un puerto interno propio, inicialmente `8989`;
- tener un vhost Apache propio por version PHP;
- ser accesible mediante un dominio interno administrado por WSDD, analogo a `wmXX.wsdd.dock`;
- agregarse a `VIRTUAL_HOST` para que el proxy de WSDD lo resuelva;
- escribir logs de acceso/error en el arbol persistente de logs;
- no reemplazar ni interferir con Webmin.

El dominio interno validado para PHP 8.3 es `phpadmin83.wsdd.dock`. Las demas versiones deben seguir el mismo modelo por version PHP que ya usan `phpXX.wsdd.dock`, `cronXX.wsdd.dock` y `wmXX.wsdd.dock`.

## Trabajo paralelo en WSDD Desktop

El desarrollo de WSDD-PHP-Admin requiere trabajo paralelo en WSDD Desktop:

- agregar soporte WSDD para PHP 8.0;
- agregar soporte WSDD para PHP 8.5;
- soportar WSDD-PHP-Admin en modo Dev mediante bind mount del proyecto;
- soportar WSDD-PHP-Admin en modo Release futuro mediante paquete/instalacion en una raiz estable;
- montar el directorio persistente de WSDD-PHP-Admin en `options.phpXX.yml`;
- exponer el puerto interno `8989` en `webserver.phpXX.yml` si aplica;
- agregar el dominio interno de WSDD-PHP-Admin a `VIRTUAL_HOST`;
- agregar el vhost Apache proxy hacia `127.0.0.1:8989`;
- ejecutar el entrypoint interno `runtime:start` durante el arranque del contenedor;
- conservar el contrato de no sobrescribir estado vivo administrado por WSDD.

## Versiones PHP objetivo para WSDD

WSDD debe poder instalar WSDD-PHP-Admin sobre contenedores PHP de estas versiones:

- PHP 5.6
- PHP 7.2
- PHP 7.4
- PHP 8.0 (nueva; WSDD aun no tiene presente esta version)
- PHP 8.1
- PHP 8.2
- PHP 8.3
- PHP 8.4
- PHP 8.5 (nueva; WSDD aun no tiene presente esta version)

Nota: WSDD actualmente soporta PHP 5.6, 7.2, 7.4, 8.1, 8.2, 8.3 y 8.4. PHP 8.0 y PHP 8.5 quedan definidos como versiones objetivo adicionales para esta linea de trabajo.

## Caracteristicas funcionales

### Aclaracion de alcance acordada el 2026-10-15

Correccion posterior expresa del usuario: las ampliaciones se atenderan cuando un issue demuestre su necesidad y NO deben mantener ni reabrir fases del alcance actual. Esto incluye recuperacion/modo seguro ampliados, ademas de incompatibilidades y fallback ya diferidos. Queda retirada la recomendacion anterior de desarrollarlos como siguiente bloque obligatorio. Las fases 1-5 conservan su cierre; la fase 6 conserva exclusivamente sus pendientes de soporte/evidencia, no ampliaciones. Los requisitos originales y recomendaciones anteriores que aparecen debajo son historial y quedan subordinados a esta decision.

Decision posterior del usuario: el fallback de instalacion se implementara y utilizara cuando un issue demuestre su necesidad y documente una alternativa segura verificable. Hasta entonces se conserva el metodo autorizado con skipped/failed y diagnostico segun corresponda; un estado deseado enabled incumplido no se convierte en exito de reconciliacion. No se declara implementado el fallback ni se introduce como obligacion nueva para WSDD. Se recomienda continuar con recuperacion ampliada/modo seguro y despues filtros por estado, revisando primero el codigo; las validaciones integrales quedan al final.

El usuario decide tratar incompatibilidades como issues cuando se detecten problemas, con evidencia y reproduccion, en lugar de implementar ahora un motor preventivo sin casos confirmados. Los requisitos originales se conservan debajo como trazabilidad, no como funcionalidad implementada. La revision documental de las 21 recetas actuales no identifico parametros de usuario obligatorios para cargar esas extensiones; no se agregan formularios sin necesidad comprobada. El flujo condicional para extensiones que si los requieran sigue sin implementar. Ver [Revision, fuentes y limites](extension-configuration-review-2026-10-15.md). No se cierran fases ni se adelanta la bateria final por esta decision.

### 1. Aplicacion web

WSDD-PHP-Admin debe ser una aplicacion web, no solo una CLI o scripts sueltos. La UI/UX es critica: el usuario debe poder entender el estado del contenedor, las extensiones disponibles y las acciones posibles sin conocer comandos internos.

Caracteristicas esperadas:

- dashboard de estado PHP;
- listado de extensiones instaladas;
- listado de extensiones disponibles;
- filtros por estado;
- acciones claras para instalar, activar, desactivar y revertir;
- vista de logs de instalacion;
- indicacion visual de operaciones en progreso, exito o error.

### 2. Instalacion programatica dentro del contenedor PHP

WSDD debe poder ejecutar WSDD-PHP-Admin dentro del contenedor PHP usando dos modos claramente separados:

- Dev: el proyecto existe por bind mount en `/var/www/html/phpadmin.dock`; WSDD no debe copiarlo ni embeberlo.
- Release futuro: WSDD-PHP-Admin debe llegar como paquete, artifact, Composer package, copia de release o mecanismo equivalente hacia `/opt/wsdd-php-admin` o una raiz estable.

La instalacion Release futura debe ocurrir despues de que PHP ya esta disponible dentro del contenedor.

La instalacion no debe requerir intervencion manual del usuario final.

### 3. Administracion del mismo contenedor

WSDD-PHP-Admin debe administrar el contenedor donde esta instalado. No debe depender de contenedores anidados ni de controlar Docker desde dentro del contenedor como requisito base.

El modelo correcto es:

- WSDD-PHP-Admin corre dentro del contenedor PHP;
- detecta el PHP local;
- instala/Desintala o activa/desactiva extensiones para ese PHP local;
- registra el estado en un perfil persistente.
- Guarda el perfil persistente en un directorio creado y montado por WSDD mediante Docker bind mount o volumen, similar al modelo actual de directorios persistentes para proyectos, vhosts, cron y logs.
- en la instalacion debe evaluar si este perfil ya existe, y autoconfigurarce basado en el perfil.
- debe tener su propio puerto interno, inicialmente `8989`, publicado mediante vhost Apache y dominio interno administrado por WSDD.

Adicional: Implementacion desde WSDD (aplicacion de escritorio)
- WSDD debe al crear un contendor para una version de php debe asignar una URL interna como lo hace con webmin (validar).

### 4. Administracion de extensiones PHP

WSDD-PHP-Admin debe permitir:

- listar extensiones cargadas por `php -m`;
- listar extensiones instaladas pero desactivadas;
- listar extensiones disponibles para instalar;
- instalar extensiones faltantes;
- activar extensiones instaladas;
- desactivar extensiones instaladas;
- mostrar errores de instalacion;
- mostrar dependencias requeridas cuando se conozcan;
- detectar incompatibilidades conocidas entre extensiones antes de aplicar cambios;
- advertir o bloquear combinaciones de extensiones que puedan romper el runtime PHP;
- reiniciar Apache/PHP internamente para cargar las extensiones cuando sea suficiente.
- verificar que una extension quedo cargada realmente.

### 5. Cero comandos manuales para el usuario final

El usuario final no debe ejecutar manualmente:

- `docker-php-ext-install`;
- `docker-php-ext-enable`;
- `pecl install`;
- `apt install`;
- `apk add`;
- scripts internos del contenedor.

WSDD-PHP-Admin debe usar estas herramientas internamente si existen y si el perfil persistente registra lo que se hizo. la instalacion debe ser no interactiva y al finalziar la instalacion si la extension requiere parametros de configuracion, se debe generar un modal/formulario de configuracion. 

### 5.1 Deteccion de permisos y privilegios

WSDD-PHP-Admin debe detectar si el backend web tiene permisos suficientes para ejecutar operaciones sensibles dentro del contenedor.

Debe evaluar como minimo:

- usuario efectivo del proceso web, por ejemplo `www-data` u otro;
- permisos de escritura sobre el perfil persistente;
- permisos de escritura sobre directorios `.ini` administrados por WSDD-PHP-Admin;
- disponibilidad de herramientas internas requeridas;
- posibilidad de ejecutar comandos con privilegios suficientes;
- errores producidos por falta de permisos;
- acciones que requieren privilegios elevados y no pueden completarse con el usuario actual.

Si una accion no puede ejecutarse por permisos insuficientes, la UI debe mostrar el motivo y evitar dejar el contenedor en estado parcial.

### 6. Perfil persistente

WSDD-PHP-Admin debe mantener un perfil persistente en un directorio montado por volumen Docker o bind mount (tal cual es el por defecto actual para los proyectos).

El perfil debe ser la fuente de verdad del estado deseado (si existe), no solo un log historico.

Debe registrar como minimo:

- version PHP;
- extension;
- estado deseado: activa, desactivada o removida;
- estado observado;
- metodo de instalacion;
- version instalada si aplica;
- dependencias del sistema requeridas;
- incompatibilidades conocidas;
- extensiones que no deben coexistir;
- origen de la extension: core PHP, PECL, paquete externo, binario, fuente;
- fecha de instalacion;
- fecha de ultima modificacion;
- resultado de ultima reconciliacion.

### 7. Supervivencia a reconstrucciones

Si el contenedor se reconstruye al agregar proyectos, actualizar WSDD o recrear servicios, el perfil debe permanecer en el host y volver a montarse en el nuevo contenedor, esta caracteristica mescla la postabilidad del perfil de WSDD-PHP-Admin la persistencia de datos por parte de WSDD/Docker.

WSDD-PHP-Admin no debe depender del filesystem efimero del contenedor para recordar cambios.

Una vez reconstruido el contenedor, debe existir una CLI interna que pueda levantar el perfil/backup/extensiones implementadas y administradas desde WSDD-PHP-Admin. Esta CLI debe ser llamada por el arranque del contenedor o por WSDD Desktop, no por el usuario final.

### 8. Reconciliacion automatica

Al iniciar el contenedor, WSDD-PHP-Admin debe leer el perfil persistente y comparar:

- estado deseado;
- estado real del PHP local;
- extensiones instaladas;
- archivos `.ini`;
- dependencias disponibles.

Si falta algo que el perfil declara como requerido, debe reinstalarlo o reactivarlo automaticamente, dejando log de la operacion.

La reconciliacion debe ejecutarse mediante el agent iniciado por `runtime:start`, llamado por el entrypoint/CMD del contenedor. El usuario final no debe ejecutar este comando manualmente.

### 9. Compatibilidad con multiples imagenes PHP

El diseno debe funcionar primero con las imagenes PHP soportadas por WSDD y luego ampliar compatibilidad por niveles hacia otras imagenes PHP compatibles, no solo `php:<version>-apache`.

WSDD-PHP-Admin debe detectar:

- ruta del binario PHP;
- version PHP;
- ruta de `php.ini`;
- `scan dir` de archivos `.ini`;
- si existen herramientas oficiales como `docker-php-ext-install` y `docker-php-ext-enable`;
- si existe PECL;
- sistema operativo base;
- gestor de paquetes disponible: `apt`, `apk`, `dnf`, `yum` u otro;
- tipo de runtime: Apache module, FPM o CLI.

nota: utilizaremos cada version de PHP de WSDD como escenarios de pruebas manuales.

### 10. Integracion con WSDD sin romper arquitectura

WSDD-PHP-Admin debe integrarse con WSDD sin exigir contenedores anidados ni migracion inmediata a PHP-FPM.

Debe funcionar con el modelo actual de WSDD basado en contenedores PHP Apache, por ejemplo `php:8.3-apache` para el desarrollo inicial, y dejar abierta la posibilidad de soportar FPM si WSDD lo adopta en el futuro.

### 11. Separacion de configuracion base y configuracion administrada

WSDD-PHP-Admin no debe sobrescribir archivos que WSDD modifica normalmente al agregar proyectos, vhosts o dominios.

Debe operar en zonas propias, por ejemplo:

- directorio persistente propio;
- archivos `.ini` administrados por WSDD-PHP-Admin y reconocidos por apache o FPM;
- logs propios;
- metadata propia;
- cache propia.

### 12. Prevencion de drift manual

Todo cambio realizado desde la UI debe quedar registrado en el perfil persistente.

El estado del contenedor no debe depender de cambios manuales invisibles hechos dentro de una sesion interactiva.

WSDD-PHP-Admin puede detectar cambios en la configuracion de extensiones que no estan en el perfil de configuracion.

### 13. Mantenibilidad

No se debe resolver el problema principalmente agregando extensiones una por una hardcodeadas en el Dockerfile.

El Dockerfile puede instalar prerequisitos que ya estan documentados actualmente, pero el set final de extensiones debe provenir del perfil persistente de WSDD-PHP-Admin.

### 14. Seguridad

WSDD-PHP-Admin tendra permisos sensibles porque podra instalar extensiones y modificar configuracion PHP.

Debe incluir:

- autenticacion;
- control de sesion;
- proteccion CSRF;
- restriccion de acceso por red cuando aplique;
- logs de auditoria;
- validacion estricta de nombres de extensiones;
- bloqueo de comandos arbitrarios desde la UI;
- separacion entre acciones permitidas y comandos internos.

### 15. Rollback y recuperacion

WSDD-PHP-Admin debe permitir recuperar el contenedor ante una extension o configuracion problematica.

Capacidades deseadas:

- desactivar una extension;
- restaurar archivo `.ini` anterior;
- marcar extension como no deseada;
- revertir una instalacion que genere incompatibilidad;
- ejecutar validacion posterior a cambios para confirmar que PHP sigue cargando correctamente;
- impedir que una reconciliacion repita automaticamente una combinacion marcada como incompatible;
- registrar ultimo estado funcional conocido;
- permitir reconciliacion en modo seguro.

### 16. Auditoria

Debe registrar:

- usuario o actor;
- accion realizada;
- extension/configuracion afectada;
- fecha;
- resultado;
- salida relevante del instalador;
- estado antes y despues.

### 17. Extensibilidad

El diseno debe permitir agregar:

- nuevas versiones PHP;
- nuevos metodos de instalacion;
- nuevas familias de sistema operativo;
- nuevos tipos de runtime;
- nuevas fuentes de extensiones;
- integracion con WSDD;
- integracion futura con otros orquestadores.

## Arquitectura propuesta

### Componentes principales

1. **Web UI**

   Interfaz de usuario para consultar estado, ejecutar acciones y revisar logs.

2. **API interna**

   Endpoints usados por la UI para listar, instalar, activar, desactivar, reconciliar y consultar logs.

3. **Detector de entorno**

   Modulo que detecta version PHP, rutas de configuracion, runtime, sistema operativo, gestor de paquetes y herramientas disponibles.

4. **Catalogo de extensiones**

   Fuente de datos local/remota con extensiones soportadas, metodos de instalacion, dependencias conocidas e incompatibilidades conocidas por version PHP y sistema operativo.

5. **Motor de instalacion**

   Ejecuta operaciones internas de instalacion usando el metodo apropiado para la imagen detectada.

6. **Motor de reconciliacion**

   Compara perfil persistente contra estado real y aplica cambios pendientes al iniciar o bajo demanda.

7. **Perfil persistente**

   Archivo o conjunto de archivos versionados que describen el estado deseado y el historial de cambios.

8. **Auditoria y logs**

   Registro de acciones y salida de procesos de instalacion/reconciliacion.

## Perfil persistente esperado

El formato exacto debe definirse durante el diseno tecnico, pero debe ser legible, versionado y facil de respaldar.

Ejemplo conceptual (puede cambiar segun necesidades del desarrollo y actualziaciones, y debe tener retrocompatibilidad):

```json
{
  "schema_version": 1,
  "php": {
    "version": "8.4",
    "runtime": "apache",
    "ini_scan_dir": "/usr/local/etc/php/conf.d"
  },
  "extensions": {
    "bcmath": {
      "desired_state": "enabled",
      "install_method": "docker-php-ext-install",
      "installed_version": null,
      "incompatible_with": [],
      "last_reconciled_at": "2026-10-11T00:00:00Z"
    }
  }
}
```

## Fases de desarrollo

### Fase 1: investigacion tecnica

Estado: cerrada para el alcance inicial PHP 8.3 Apache + CLI.

- Definir imagenes PHP objetivo.
- Comparar Apache, FPM y CLI.
- Identificar herramientas disponibles por imagen.
- Definir formato inicial del perfil persistente.
- Definir modelo de seguridad minimo.

### Fase 2: prototipo local

Estado: cerrada para el prototipo base integrado en contenedor PHP 8.3.

- Crear aplicacion minima.
- Detectar PHP local.
- Leer `php -m`.
- Detectar `php.ini` y `conf.d`.
- Mostrar extensiones instaladas desde UI.
- Guardar perfil persistente.
- Usar PHP 8.3 como version principal para desarrollo y pruebas manuales.

### Fase 3: instalacion y reconciliacion

Estado: cerrada para PHP 8.3 Apache en modo Dev. La activacion/desactivacion managed de `.ini` propios, el reload controlado de Apache, el perfil persistente, la cola operativa, la reconciliacion managed de arranque, el catalogo seguro PHP 8.3 y la instalacion real allowlisted existen y fueron validados dentro del contenedor reconstruido. Tras la reconstruccion final del 2026-10-14, el perfil persistente reconciliado instalo/cargo `bcmath` mediante `.ini` administrado y mantuvo `calendar` removida sin drift.

- Instalar extensiones desde UI. Estado: implementado para entradas allowlisted del catalogo PHP 8.3; devuelve `skipped` si no hay metodo seguro o herramienta requerida.
- Activar/desactivar extensiones. Estado: implementado para `.ini` propios administrados por WSDD-PHP-Admin.
- Registrar cambios en perfil. Estado: implementado cuando una operacion managed cambia realmente un `.ini` propio.
- Ejecutar reconciliacion al inicio. Estado: implementado para acciones managed soportadas; instala extensiones faltantes solo si estan en el catalogo seguro PHP 8.3.
- Validar reconstruccion de contenedor sin perdida de estado. Estado: hecho para PHP 8.3 Apache con bind mounts WSDD.
- Validar instalacion real desde perfil persistente al arrancar. Estado: hecho con `bcmath` declarada como `desired_state=enabled` en `profile.json`; tras reconstruccion, el agent instalo la extension mediante el catalogo seguro y quedo cargada en `php -m`.

### Fase 4: integracion WSDD

Estado: cerrada para la integracion inicial PHP 8.3 Apache en modo Dev. WSDD monta persistencia/logs, expone `phpadmin83.wsdd.dock`, sincroniza hosts/vhost/options, selecciona una sola raiz runtime Dev (`/var/www/html/phpadmin.dock`) o Release (`/opt/wsdd-php-admin`) y llama `runtime:start` desde esa raiz. La recreacion general del 2026-10-15 ratifica el contrato Dev en las siete versiones presentes, con agente root y web www-data desde su misma raiz. No cierra distribucion/instalacion Release.

- Agregar soporte runtime en templates PHP de WSDD. Estado: hecho para PHP 8.3.
- Montar directorio persistente. Estado: hecho para PHP 8.3.
- Exponer ruta/dominio interno de administracion. Estado: hecho para `phpadmin83.wsdd.dock`.
- Evitar conflicto con vhosts, Webmin, cron y configuracion existente. Estado: validado para PHP 8.3.

### Fase 5: endurecimiento

Estado: cerrada como hardening base para entorno local/controlado PHP 8.3 Apache en modo Dev. Ya hay validacion de entradas, separacion web/agent, comandos allowlisted para reload, bloqueo de escritura directa desde la web, autenticacion por usuario/contraseña persistente sin base de datos, cambio obligatorio de contraseña inicial, CSRF para POST de operaciones, auditoria JSONL y rollback basico de `.ini` administrados mediante snapshots persistentes.

- Autenticacion. Estado: implementada por usuario/contraseña persistente en `/var/lib/wsdd-php-admin/auth.json`; si el archivo no existe, se usa el acceso inicial `admin` / `admin` y se fuerza cambio de contraseña antes de usar el sistema.
- CSRF. Estado: implementado para creacion de operaciones.
- Logs de auditoria. Estado: implementado en JSONL sobre `audit.log`.
- Validacion de entradas. Estado: implementado para API, operaciones, extension names, rutas y comandos allowlisted.
- Deteccion de permisos y privilegios efectivos. Estado: implementado y visible en UI/API.
- Modo seguro. Estado: implementado como restricciones de catalogo, operaciones allowlisted, no escritura web en `.ini` y rollback de `.ini` administrados; queda pendiente un modo seguro ampliado para desactivar reconciliacion problematica de forma global.
- Rollback basico. Estado: implementado para restaurar el ultimo snapshot de `.ini` administrado por extension.

### Fase 6: soporte multi-version

Validacion real PHP 8.4.26 realizada el 2026-10-14: ver `php84-validation-2026-10-14.md`. El desfase de observacion se corrigio en PHP 8.3/8.4: ver `runtime-observation-2026-10-14.md`. Ya se implemento el catalogo inicial PHP 8.4 con seleccion por version/distribucion, descubrimiento dinamico y recetas controladas, probado con calendar, bcmath y pdo_pgsql: ver `extension-catalog-2026-10-14.md`. La fase permanece abierta para versiones restantes; no se declara compatibilidad universal ni compilacion probada de todas las recetas.

Estado: cierre parcial de hitos, no cierre global. PHP 5.6/7.2/7.4/8.1/8.2/8.3/8.4 Debian comparten codigo, motor, recetas, observacion y bateria. Se realizo un downgrade de sintaxis y adaptacion de APIs para soportar PHP antiguo, comprobando continuidad funcional de las versiones ya integradas. PHP 7.2 completo calendar/bcmath/pdo_pgsql; PHP 5.6 completo calendar/bcmath, con dependencias pdo_pgsql bloqueadas por Stretch. Tras la recreacion general del 2026-10-15, los siete entornos conservan perfil y arrancan agente/web automaticamente; las siete pruebas de navegador aprobaron. Se cierra reinstalacion real de bcmath enabled en 5.6/7.2/7.4/8.1/8.3. En 8.2/8.4 se respeto removed, sin acreditar reinstalacion desde enabled. PHP 8.0/8.5 siguen pendientes. Evidencia y defectos abiertos: `development-status-2026-10-15.md`.

- Validar PHP 5.6, 7.2, 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 y 8.5.
- Documentar diferencias por version.
- Ajustar catalogo de extensiones por version.

Avance 7.4/8.1 del 2026-10-14: se adapto el mismo codigo a PHP 7.4 sin duplicar agentes ni interfaces. Full/passed en 7.4.33 y 8.1.34 con instalaciones reales calendar/bcmath/pdo_pgsql, todas sus acciones managed y tres specs de navegador por entorno. Se repitio Smoke/passed en 8.2/8.3/8.4 despues del port. Los catalogos de los cinco entornos contienen 21 recetas revisadas; no se declara compilacion de todas ellas. Tras las reconstrucciones del usuario, PHP 7.4/8.1 conservan perfil, credenciales, cola y backups y arrancan automaticamente. La segunda reconstruccion, preparada con bcmath enabled, instalo automaticamente esa extension y verifico CLI/Apache antes de levantar la web. Cuatro specs de lectura aprobaron con 0 incidencias y sin cambios de perfil/auth/colas/procesos. Se cierra la evidencia de persistencia, arranque y reinstalacion desde enabled para ambas versiones. En ese momento seguian pendientes otros entornos; el cierre posterior vigente se detalla en `development-status-2026-10-15.md`. Evidencia y limites: `php74-php81-validation-2026-10-14.md`.

## Estado actual del desarrollo

Actualizado: 2026-10-15

La base inicial se probo en PHP 5.6/7.2/7.4/8.1-8.4 Apache + CLI con el mismo codigo. Existen UI, API, CLI, perfil persistente, cola/agente, INI propios, reload, observacion fresca y 21 recetas revisadas. La agregacion incorrecta de fallos y la ausencia de exclusion/readiness ya fueron corregidas y verificadas en las siete versiones, incluido PHP 5.6 reconstruido. Este ultimo aprobo Smoke, 22 comprobaciones de arranque, 11 pruebas como www-data y dos specs de navegador, conservando perfil, credenciales, cola, INI y extensiones. Quedan los requisitos funcionales y limites de compatibilidad documentados, no la comprobacion final de P1/P2. Ver `runtime-reliability-fixes-2026-10-15.md` y `development-status-2026-10-15.md`.

Archivos y directorios creados:

- `composer.json`
- `.gitignore`
- `public/index.php`
- `bin/wsdd-php-admin`
- `bin/validate-agent-safety`
- `bin/validate-agent-response`
- `bin/validate-security-hardening`
- `src/Support/bootstrap.php`
- `src/Support/JsonFile.php`
- `src/Support/ProcessIdentity.php`
- `src/Agent/AgentLogger.php`
- `src/Agent/AgentRunner.php`
- `src/Agent/OperationProcessor.php`
- `src/Audit/AuditLogger.php`
- `src/Security/HttpSecurity.php`
- `src/Recovery/IniBackupRepository.php`
- `src/Http/Application.php`
- `src/Ini/ManagedIniRepository.php`
- `src/Operations/Operation.php`
- `src/Operations/OperationId.php`
- `src/Operations/OperationRepository.php`
- `src/Operations/OperationResult.php`
- `src/Operations/OperationService.php`
- `src/Operations/OperationType.php`
- `src/Support/AppConfig.php`
- `src/Permissions/PathPermission.php`
- `src/Permissions/PermissionReport.php`
- `src/Permissions/PermissionChecker.php`
- `src/Profile/ProfileDocument.php`
- `src/Profile/ProfileLoadResult.php`
- `src/Profile/ProfileRepository.php`
- `src/Profile/ProfileStateRecorder.php`
- `src/Service/ServiceReloader.php`
- `src/Reconciliation/ReconciliationIssue.php`
- `src/Reconciliation/ReconciliationReport.php`
- `src/Reconciliation/ReadOnlyReconciler.php`
- `src/Extensions/ExtensionName.php`
- `src/Extensions/ExtensionCatalog.php`
- `src/Runtime/RuntimeDetection.php`
- `src/Runtime/RuntimeDetector.php`
- `src/Runtime/`
- `src/Agent/`
- `src/Ini/`
- `src/Operations/`
- `src/Profile/`
- `src/Extensions/`
- `src/Reconciliation/`
- `src/Service/`
- `src/Audit/`
- `src/Security/`
- `src/Recovery/`
- `config/wsdd-php-admin.php`
- `deploy/apache/wsdd-php-admin.vhost.example.conf`
- `deploy/container/install.sh`
- `var/cache/`
- `var/logs/`
- `docs/architecture-initial-2026-10-11.md`
- `docs/profile-schema-v1.md`
- `docs/wsdd-docker-integration-contract-2026-10-11.md`

Decisiones aplicadas en la estructura base:

- Se separo la entrada web publica en `public/`.
- Se reservo una CLI interna en `bin/wsdd-php-admin` para comandos operativos como `detect` y `reconcile`.
- Se agregaron comandos operativos `runtime:start`, `agent`, `agent:once`, `reconcile:managed`, `operations:list` y `operations:create`.
- Se agrego un agent/root worker interno que procesa operaciones persistentes desde `/var/lib/wsdd-php-admin/operations/`.
- Se agrego abstraccion de `.ini` administrado con escritura atomica, prefijo `zz-wsdd-php-admin-`, validacion de path y restriccion para no tocar `php.ini` ni archivos ajenos.
- Se separaron modulos iniciales para runtime, perfil persistente, extensiones, reconciliacion y auditoria.
- Se definio configuracion inicial para puerto interno `8989`, host `127.0.0.1`, ruta de perfil persistente, ruta de `.ini` administrados, prefijo propio de archivos `.ini` y logs propios.
- Se agrego un vhost Apache de ejemplo con proxy hacia `127.0.0.1:8989`.
- Se documento la arquitectura inicial en `docs/architecture-initial-2026-10-11.md`.
- Se conecto un primer detector de runtime a `php bin/wsdd-php-admin detect`.
- Se conecto el detector de runtime al dashboard web de desarrollo.
- Se definio el perfil persistente versionado v1 en `docs/profile-schema-v1.md`.
- Se agrego carga, inicializacion y estado del perfil persistente.
- Se agrego validacion de permisos para perfil persistente, archivo de perfil, directorio de operaciones, directorio `.ini` administrado, logs operativos, log del agent y auditoria.
- Se agrego reconciliacion de solo lectura para comparar perfil deseado contra runtime observado sin modificar el contenedor.
- Se agrego reconciliacion managed de arranque para reaplicar acciones soportadas desde `profile.json`.
- Se agrego API HTTP interna de desarrollo mediante `?api=runtime`, `?api=permissions`, `?api=profile`, `?api=reconcile`, `?api=operations`, `?api=operations_create` y `?api=state`.
- Se agrego navegacion principal visible para evitar depender de URLs manuales.
- Se separo la interfaz en vistas navegables: `overview`, `runtime`, `extensions`, `profile`, `operations`, `permissions` y `reconciliation`.
- Se documento el contrato de integracion WSDD/Docker en `docs/wsdd-docker-integration-contract-2026-10-11.md`.
- Se definio la integracion WSDD correcta: el `CMD` selecciona una sola raiz runtime Dev (`/var/www/html/phpadmin.dock`) o Release (`/opt/wsdd-php-admin`) y llama `runtime:start`; WSDD-PHP-Admin arranca agent y web desde esa misma raiz.

Estado de implementacion real:

- La aplicacion web muestra un dashboard minimo con shell de navegacion y vistas separadas.
- La CLI interna implementa `detect` y devuelve JSON con el runtime observado.
- La CLI interna implementa `permissions`, `profile:init`, `profile:status` y `reconcile`.
- La CLI interna implementa `runtime:start`, `agent`, `agent:once`, `reconcile:managed`, `operations:list` y `operations:create`.
- El agent procesa `enable_extension`, `disable_extension`, `install_extension`, `remove_extension` y `reconcile` con acciones managed seguras y sin comandos arbitrarios.
- El agent procesa `enable_extension`, `disable_extension`, `install_extension`, `remove_extension`, `rollback_extension` y `reconcile` con acciones managed seguras y sin comandos arbitrarios.
- Los catalogos PHP 5.6/7.2/7.4/8.1/8.2/8.3/8.4 Debian seleccionan recetas allowlisted por minor autorizado/distribucion, con metodo `docker-php-ext-install`, dependencias apt y configuracion previa explicitas. El inventario efectivo se descubre de fuentes, binarios y herramientas reales; no se generan recetas desconocidas a partir de nombres de extensiones. GD/ZIP antiguos seleccionan variantes de datos revisadas.
- `install_extension` instala solo extensiones presentes en el catalogo seguro. Si la extension no esta catalogada, falta `docker-php-ext-install`, falta `apt-get` para dependencias requeridas o falla un comando allowlisted, la operacion termina como `skipped` o `failed` con detalle y no se marca el perfil como aplicado.
- Cuando `docker-php-ext-install` genera un `.ini` propio de la imagen, WSDD-PHP-Admin lo adopta hacia el prefijo administrado `zz-wsdd-php-admin-` para que `disable_extension`, `remove_extension` y `rollback_extension` puedan operar sobre configuracion propia y reversible.
- `remove_extension` remueve solo `.ini` propios administrados; la eliminacion binaria de extensiones compiladas no se ejecuta todavia y se reporta como `skipped` para evitar romper la imagen base.
- `rollback_extension` restaura el ultimo snapshot persistente de `.ini` administrado para una extension.
- Antes de cambiar o remover un `.ini` administrado, el agent crea un snapshot en `/var/lib/wsdd-php-admin/backups/ini`.
- La web puede crear operaciones persistentes, pero no ejecuta operaciones privilegiadas ni escribe `.ini` directamente.
- La web requiere sesion autenticada con usuario/contraseña. En primera instalacion, `admin` / `admin` solo permite entrar al flujo obligatorio de cambio de contraseña.
- La API rechaza POST de operaciones sin CSRF valido.
- Las operaciones creadas y procesadas quedan registradas en `audit.log` como eventos JSONL.
- El formulario de operaciones usa un `select` de extensiones dependiente de la accion seleccionada; no usa texto libre para el usuario final.
- La vista de operaciones actualiza estado sin recargar la pagina y tolera reinicios de Apache con reconexion/backoff antes de declarar indisponibilidad persistente.
- La UI bloquea la creacion de operaciones si `/var/lib/wsdd-php-admin/operations` no existe o no es escribible, para evitar estados falsos como `Queued. Waiting for agent...`.
- El agent escribe estado intermedio `processing` antes del resultado final y la API lista las operaciones mas recientes primero.
- El estado `processing` no se considera terminal; si el agent se interrumpe, la operacion puede retomarse.
- `disable_extension` devuelve `skipped` cuando no existe `.ini` administrado propio para remover.
- Se agrego reload controlado de Apache con comando allowlisted `apache2ctl graceful`/`apachectl graceful`, ejecutado solo cuando cambia un `.ini` administrado.
- El agent actualiza `profile.json` con `desired_state` cuando una operacion managed cambia realmente un `.ini`; las operaciones `skipped` no modifican el perfil.
- Al arrancar, `runtime:start` inicializa/carga el perfil y ejecuta reconciliacion managed interna bajo reserva exclusiva antes de levantar agent y web: aplica acciones permitidas y verifica intencion frente a CLI nuevo y Apache incluso sin cambios. Un requisito incumplido produce failed; no se despachan procesos. El arranque solo informa success tras comprobar heartbeat y respuesta web de la misma raiz. La web no usa sus propios modulos como fuente de verdad. La CLI separada `reconcile:managed` sigue disponible y comparte el lock del agente.
- El agent conserva una reconciliacion managed idempotente al iniciar su loop, para cubrir ejecuciones directas de `agent` y mantener el mismo comportamiento seguro antes de procesar la cola.
- Las decisiones del agent sobre si una extension esta cargada usan un proceso PHP fresco (`php -m`) y no el estado en memoria del propio agent, porque el agent es un proceso largo y puede conservar extensiones cargadas despues de quitar un `.ini`.
- El perfil persistente y la cola de operaciones sobreviven reconstrucciones del contenedor usando bind mounts de WSDD en `C:\WSDD-Environment\Docker-Structure\state\php-admin\php83`.
- El runtime integrado arranca automaticamente desde el `CMD` llamando `runtime:start` en la raiz Dev o Release seleccionada.
- El detector de runtime obtiene version PHP, SAPI, runtime, binario PHP, `php.ini`, scan dir, extensiones cargadas, familia de sistema operativo, usuario efectivo, herramientas internas disponibles y gestores de paquetes conocidos.
- Existe un formato inicial versionado del perfil persistente.
- La reconciliacion visible de perfil contra runtime es de solo lectura; la reconciliacion managed de arranque queda limitada a acciones seguras sobre `.ini` propios.
- La reconciliacion valida nombres de extensiones y estados deseados antes de reportar drift.
- Existen catalogos iniciales para PHP 5.6/7.2/7.4/8.1/8.2/8.3/8.4 Debian, con 21 recetas compartidas y seleccion explicita por entorno. Otras versiones no heredan automaticamente las recetas. No se afirma compilacion real de las 21 recetas ni disponibilidad garantizada de paquetes externos.
- La matriz UI/API marca esos catalogos implementados y conserva el resto pendiente o no soportado. El informe de catalogos distingue recetas revisadas de instalaciones realmente compiladas.
- El motor de instalacion resuelve el mismo catalogo que la UI, rechaza fuentes/herramientas ausentes, no instala fuera de receta y omite recompilar extensiones ya presentes para conservar la configuracion base.
- Existe hardening base con autenticacion configurable, CSRF, auditoria funcional y rollback basico de `.ini` administrado.

Validacion realizada:

- Sintaxis PHP validada con `php -l` sobre todos los archivos PHP actuales.
- La CLI responde correctamente a `php bin/wsdd-php-admin help`.
- La CLI responde correctamente a `php bin/wsdd-php-admin detect`.
- La CLI responde correctamente a `php bin/wsdd-php-admin permissions`.
- La CLI responde correctamente a `php bin/wsdd-php-admin profile:status`.
- La CLI responde correctamente a `php bin/wsdd-php-admin profile:init` usando una ruta temporal de pruebas.
- La CLI responde correctamente a `php bin/wsdd-php-admin reconcile` en modo de solo lectura.
- La validacion `php bin/validate-agent-safety` cubre creacion de operacion segura, rechazo de extension insegura, rechazo de path traversal, resultado de `agent:once` y proteccion de archivos `.ini` no prefijados.
- La validacion `php bin/validate-agent-response` cubre respuesta no destructiva del agent: operacion pendiente, procesamiento `agent:once`, creacion de `result.json`, salida de la cola pendiente y escritura de `agent.log`.
- La validacion `php bin/validate-managed-reconciliation` cubre reconciliacion managed de arranque contra directorios temporales: crea un `.ini` propio para una extension disponible/no cargada declarada como `enabled`, no repite la escritura en una segunda pasada y escribe log del agent.
- La validacion `php bin/validate-managed-reconciliation` cubre tambien observacion de extensiones con un proceso PHP fresco y adopcion de `.ini` generado por Docker hacia el prefijo administrado.
- La validacion `php bin/validate-agent-response` cubre que una instalacion fuera de catalogo termina en `skipped` sin ejecutar instalacion real.
- La validacion `php bin/validate-security-hardening` cubre backup, rollback de `.ini` administrado y escritura de auditoria.
- La prueba HTTP local contra `php -S 127.0.0.1:8991 -t public` valido `?api=state` con `200 OK` y rechazo CSRF de `?api=operations_create` sin token CSRF con `419`.
- Tras reconstruir `WSDD-Web-Server-PHP8.3` el 2026-10-14, `https://phpadmin83.wsdd.dock/?api=state` respondio `200 OK`.
- Tras reconstruir `WSDD-Web-Server-PHP8.3` el 2026-10-14, `https://phpadmin83.wsdd.dock/?api=compatibility` respondio `200 OK` y expuso la matriz multi-version.
- Tras reconstruir `WSDD-Web-Server-PHP8.3` el 2026-10-14, `https://phpadmin83.wsdd.dock/?view=operations` respondio `200 OK`, incluyo CSRF y mostro la cola de operaciones.
- Tras reconstruir `WSDD-Web-Server-PHP8.3` el 2026-10-14, `POST https://phpadmin83.wsdd.dock/?api=operations_create` sin CSRF respondio `419` y no aumento la cola.
- Tras corregir autenticacion el 2026-10-14, `https://phpadmin83.wsdd.dock/?view=overview` muestra login por usuario/contraseña, no token; `admin` / `admin` inicia sesion y obliga a cambiar contraseña antes de mostrar la app.
- Tras corregir integridad de UI el 2026-10-14, el resultado `ok` de reconciliacion ya no cuenta como issue: `?api=reconcile`, Overview y Reconciliation muestran `0 issue(s)` cuando no hay drift accionable.
- En Overview, el directorio `.ini` administrado se muestra como `agent-only` cuando la web no puede escribirlo pero el agent esta disponible, porque ese estado es esperado y no un bloqueo operativo.
- Dentro de `WSDD-Web-Server-PHP8.3`, `php bin/validate-security-hardening` paso usando rutas temporales no destructivas.
- Dentro de `WSDD-Web-Server-PHP8.3`, existen `src/Security/HttpSecurity.php`, `src/Audit/AuditLogger.php` y `src/Recovery/IniBackupRepository.php` en la raiz Dev `/var/www/html/phpadmin.dock`.
- Dentro de `WSDD-Web-Server-PHP8.3`, existen `/var/lib/wsdd-php-admin/backups` y `/var/log/wsdd-php-admin/audit.log`.
- La validacion local de sintaxis y validaciones automaticas paso despues de implementar catalogo e instalador seguro.
- `https://phpadmin83.wsdd.dock/` responde `200 OK` en el entorno de desarrollo y muestra PHP `8.3.35`, runtime `apache / apache2handler`, usuario `www-data`, `php.ini` en `/usr/local/etc/php/php.ini`, scan dir en `/usr/local/etc/php/conf.d`, y herramientas `docker-php-ext-install`, `docker-php-ext-enable`, `pecl`, `apache2ctl`, `apachectl` y `apt-get` disponibles.
- `https://phpadmin83.wsdd.dock/?api=state` responde JSON con runtime, permisos, perfil y reconciliacion.
- `https://phpadmin83.wsdd.dock/?api=profile` responde `200 OK`.
- `https://phpadmin83.wsdd.dock/?api=permissions` responde `200 OK` con `can_use_persistent_profile=true`, `can_use_operations_queue=true`, `can_write_managed_ini=false` para el proceso web y `agent_available=true`.
- Las vistas `?view=overview`, `?view=runtime`, `?view=extensions`, `?view=profile`, `?view=operations`, `?view=permissions` y `?view=reconciliation` responden correctamente.
- Se valido con Playwright que la navegacion principal aparece en desktop y mobile.
- Se valido con Playwright que la vista `operations` crea operaciones por AJAX y actualiza la tabla sin recargar la pagina.
- Se valido previamente el modo degradado sin persistencia montada: la UI muestra la cola como no disponible y deshabilita el boton de crear operaciones.
- `https://php83.wsdd.dock/info.php` responde `200 OK` en el entorno de desarrollo.
- Tras reconstruir `WSDD-Web-Server-PHP8.3`, se valido que `profile.json` persiste y carga correctamente desde `/var/lib/wsdd-php-admin/profile.json`.
- Tras reconstruir `WSDD-Web-Server-PHP8.3`, se valido que `operations/` y `agent.log` persisten en los bind mounts del host.
- Tras reconstruir `WSDD-Web-Server-PHP8.3`, se valido que el `CMD` selecciona la raiz Dev `/var/www/html/phpadmin.dock` y ejecuta `php "$WSDD_PHP_ADMIN_ROOT/bin/wsdd-php-admin" runtime:start`.
- En el contenedor reconstruido, el agent corre desde `/var/www/html/phpadmin.dock/bin/wsdd-php-admin agent` y la web interna corre desde `/var/www/html/phpadmin.dock/public`; no se mezcla `/opt/wsdd-php-admin` con la raiz Dev.
- Tras actualizar el agent integrado, se valido que el loop ejecuta reconciliacion managed de arranque y registra `startup reconciliation finished`.
- En el entorno integrado, `www-data` puede escribir perfil y operaciones, pero no `/usr/local/etc/php/conf.d`; el agent root si puede aplicar `.ini` administrados.
- El dominio `phpadmin83.wsdd.dock` resuelve desde Windows hacia `127.0.0.1`.
- Se valido instalacion real de `bcmath` tras reconstruccion: `php -m` muestra `bcmath`, existe `bcmath.so` y existe `/usr/local/etc/php/conf.d/zz-wsdd-php-admin-bcmath.ini`.
- Se valido en el contenedor que `php bin/wsdd-php-admin reconcile:managed` existe y termina correctamente; con `bcmath` ya cargada no aplica cambios nuevos y no fuerza reload.
- Se ejecuto una bateria no destructiva con `calendar` en `WSDD-Web-Server-PHP8.3`: `install_extension` compilo la extension allowlisted, adopto el `.ini` generado hacia `zz-wsdd-php-admin-calendar.ini`, `disable_extension` removio el `.ini` administrado, `rollback_extension` lo restauro desde backup, `remove_extension` lo removio sin borrar el binario compilado, `enable_extension` volvio a crear el `.ini` administrado desde el binario disponible y `reconcile` termino sin cambios pendientes. Al finalizar, `calendar` quedo sin `.ini` y no cargada; el binario permanece presente porque la remocion binaria segura todavia no esta soportada.
- Tras una nueva reconstruccion de `WSDD-Web-Server-PHP8.3`, se valido que el contenedor arranca `runtime:start`, agent y web interna desde `/var/www/html/phpadmin.dock`; el perfil persistente conserva `bcmath=enabled` y `calendar=removed`; `bcmath` queda cargada con `/usr/local/etc/php/conf.d/zz-wsdd-php-admin-bcmath.ini`; `calendar` queda sin `.ini` y no cargada; `reconcile` reporta `issue_count=0`; `reconcile:managed` no aplica cambios adicionales.

Fases cerradas, alcance ratificado al 2026-10-15:

- Fase 1 cerrada para investigacion tecnica inicial PHP 8.3 Apache + CLI.
- Fase 2 cerrada para prototipo base local/integrado.
- Fase 3 cerrada para instalacion y reconciliacion PHP 8.3 Apache en modo Dev.
- Fase 4 cerrada para integracion inicial WSDD PHP 8.3 Apache en modo Dev.
- Fase 5 cerrada como hardening base para PHP 8.3 Apache en modo Dev, validada localmente y dentro de `WSDD-Web-Server-PHP8.3`.

Fases abiertas:

- Fase 6 sigue abierta globalmente. Se cierran persistencia, arranque automatico y continuidad observada del agente en los siete entornos; tambien reinstalacion enabled en 5.6/7.2/7.4/8.1/8.3. PHP 8.2/8.4 quedaron removed en esta recreacion y no acreditan ese ultimo caso. Siguen pendientes PHP 8.0/8.5 y dependencias autenticadas Stretch para completar la bateria 5.6.
- Las fases 1-5 estan cerradas para Dev/hardening base, no para Release. Las ampliaciones diferidas a issues no bloquean ni reabren estos cierres. Los metadatos del perfil se implementaron posteriormente y conservan su validacion funcional final pendiente. Los dos defectos de fiabilidad reproducidos se corrigieron y tienen pruebas de fallos/concurrencia, no solo pruebas felices.

Siguiente paso recomendado:

1. Comprobacion final PHP 5.6 completada en 03a0b3463552: se cierran reconciliacion fiable, exclusion y readiness para los siete interpretes presentes. Conservar estos validadores como condicion de continuidad; no confundir este cierre con instalacion APT completa o Release terminado.
2. Completar los requisitos funcionales pendientes ya definidos por este plan. Reconciliacion fiable, exclusion del consumidor y readiness ya estan implementadas; los validadores deben mantenerse en la bateria comun.
3. Preparar y validar enabled en PHP 8.2/8.4 antes de otra reconstruccion del usuario. Resolver fuentes autenticadas Stretch o evaluar una imagen alternativa sin darla por elegida; continuar PHP 8.0/8.5 cuando existan entornos.

Ver tambien: `docs/wsdd-docker-integration-contract-2026-10-11.md`.

## Criterios de aceptacion iniciales

1. WSDD-PHP-Admin se instala programaticamente dentro de un contenedor PHP.
2. La UI muestra version PHP, runtime y extensiones cargadas.
3. La UI permite instalar al menos una extension faltante.
4. La UI permite activar/desactivar una extension instalada.
5. WSDD-PHP-Admin detecta, advierte o bloquea incompatibilidades conocidas entre extensiones antes de aplicar cambios.
6. WSDD-PHP-Admin detecta permisos insuficientes antes de ejecutar acciones sensibles y no deja el contenedor en estado parcial.
7. Cada cambio queda guardado en perfil persistente.
8. Al reconstruir el contenedor, el perfil se conserva.
9. Al iniciar el contenedor reconstruido, WSDD-PHP-Admin restaura automaticamente las extensiones declaradas.
10. WSDD puede agregar un proyecto nuevo sin borrar el perfil ni romper la configuracion administrada.
11. No se requiere ejecutar comandos manuales dentro del contenedor.
12. WSDD-PHP-Admin funciona inicialmente sobre PHP 8.3 Apache + CLI y queda preparado para extenderse a otras versiones.

## Riesgos tecnicos

- Algunas extensiones requieren dependencias del sistema diferentes segun distribucion base.
- PHP 5.6 y 7.x pueden requerir rutas, repositorios o versiones de dependencias antiguas.
- No todas las imagenes PHP incluyen las mismas herramientas auxiliares.
- Instalar extensiones en runtime puede aumentar tiempo de arranque si hay reconciliacion pendiente.
- Algunas extensiones pueden requerir recompilar o reiniciar servicios.
- Algunas extensiones pueden ser incompatibles entre si o con una version especifica de PHP, por lo que WSDD-PHP-Admin debe validar antes y despues de aplicar cambios.
- WSDD-PHP-Admin tendra permisos sensibles dentro del contenedor.
- El soporte universal para "cualquier imagen PHP" debe manejarse por niveles de compatibilidad, no como promesa absoluta.

## Decision de producto

WSDD-PHP-Admin debe tratarse como un proyecto propio, no como una integracion directa de OpenPanel, Webmin, PIE o `docker-php-extension-installer`.

Esos proyectos pueden servir como referencia o backend tecnico puntual, pero ninguno cumple por si solo el modelo requerido:

- aplicacion web dentro del contenedor PHP;
- administracion del mismo contenedor;
- perfil persistente declarativo;
- reconciliacion automatica tras rebuild;
- compatibilidad con la arquitectura actual de WSDD.

