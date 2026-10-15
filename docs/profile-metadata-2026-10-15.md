# Metadatos persistentes del perfil

Fecha: 2026-10-15. Desarrollo posterior al respaldo Git cd9eb9d.

## Contrato

- Se mantiene schema_version=1 y se preservan claves existentes/desconocidas, incluida la intencion desired_state. No se copian inventarios ni perfiles entre versiones.
- La reconciliacion managed registra last_reconciliation global y por extension tanto en success como en failed. Incluye fecha de registro, mensaje, errores, reload y evidencia de verificacion. Las excepciones durante la aplicacion tambien intentan registrar el fallo antes de propagarse. Un perfil ilegible no se reemplaza; una escritura fallida no se anuncia como exito.
- Las operaciones registran last_operation para su extension ya declarada. No cambian last_reconciliation, salvo la operacion reconcile, cuyo resultado final de verificacion se registra tambien. Skipped no crea intencion ni entrada nueva.
- observed_state: enabled si CLI y Apache confirman carga; disabled si ambos confirman ausencia de carga y existe binario; missing si ambos confirman ausencia de carga y no se encuentra binario; inconsistent si difieren; unknown si falta alguna observacion. removed sigue siendo intencion, no prueba de desinstalacion binaria.
- observations conserva loaded, version de extension, version PHP y observed_at separados por cli/apache. Las versiones provienen de phpversion(extension) en los procesos observados, nunca del proceso antiguo del agente. installed_version solo se publica si ambas fuentes cargan la extension y concuerdan; de otro modo es null, incluso para un binario desactivado cuya version no se ha comprobado.
- install_method e installed_at se escriben con evidencia de instalacion exitosa del instalador. installed_at es la fecha de registro de esa instalacion terminada; no reconstruye fechas historicas desconocidas. Se conservan durante activacion, desactivacion o reconciliacion sin reinstalacion. installation conserva detalles acotados de los pasos del instalador y su entorno.
- system_dependencies contiene paquetes requeridos por la receta revisada correspondiente a PHP/distribucion; no acredita que todos esos paquetes esten instalados. origin procede de esa receta. Sin receta, ambos son null.
- incompatible_with/conflicts_with conservan datos existentes y se inicializan a null si se desconocen. No hay nuevas reglas de conflictos ni bloqueo preventivo en este cambio.
- observed_at fecha el registro de metadatos; observations.*.observed_at fecha la medicion real. updated_at registra la modificacion de la entrada/perfil. No se reescribe el objetivo php del perfil con la identidad CLI.

## Flujo y responsabilidades

RuntimeSnapshot agrega versiones de extensiones. RuntimeVerifier incluye las mediciones CLI/Apache en su resultado. ProfileMetadataRecorder combina ese resultado y los hechos de instalacion con las recetas revisadas y guarda atomicamente mediante ProfileRepository. ProfileDocument::withMetadata protege los campos de intencion.

ManagedReconciler registra al terminar su aplicacion; OperationProcessor registra despues de la verificacion final, incluidos fallos de reload/verificacion. Una operacion sobre una extension no reescribe las observaciones de otras. Las vistas y API de lectura no escriben metadatos. La UI sigue consultando CLI nueva/Apache: el perfil no reemplaza esa fuente de verdad observada.

No hay nuevos scripts de arranque para WSDD, copias de codigo, cambios de mounts ni obligaciones de Dockerfile. Los procesos ya residentes deben recargar el codigo en su siguiente arranque para usar la nueva logica del agente; este desarrollo no los reinicio ni actualizo perfiles reales retroactivamente.

## Comprobaciones de desarrollo

- validate-profile-metadata: 12 comprobaciones aprobadas con PHP local 8.4.19 y en interpretes de contenedor PHP 5.6.40/8.4.26. Usa un perfil temporal y evidencia simulada, sin instalar extensiones, recargar Apache ni cambiar el perfil real.
- validate-php-compatibility: 70 archivos PHP/CLI y contratos portables aprobados en PHP local 8.4.19 y en los interpretes de contenedor 5.6.40/8.4.26. Son comprobaciones de sintaxis/APIs, no instalacion real ni certificacion de las versiones intermedias tras este cambio.
- Validador agregado a Smoke/Full para la bateria final. Pruebas anteriores ajustadas para distinguir intencion conservada de JSON actualizado con metadatos.

No se declara validacion funcional integral de esta ampliacion en los siete contenedores. Quedan para el final las pruebas de instalaciones reales, fallos, reconstruccion y concordancia UI/CLI/Apache con estos metadatos. Se mantienen todos los pendientes de compatibilidad existentes (8.0/8.5, enabled 8.2/8.4, dependencias autenticadas Stretch y Release).
