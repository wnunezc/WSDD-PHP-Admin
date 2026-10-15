# Perfil persistente v1

Fecha: 2026-10-11
Actualizado: 2026-10-15

Este documento define el primer formato operativo del perfil persistente de WSDD-PHP-Admin. El perfil es la fuente de verdad del estado deseado. En el estado actual se inicializa, se lee, se expone por HTTP, se compara contra el runtime, se actualiza cuando el agent aplica una operacion managed real sobre un `.ini` propio y se usa al arrancar el agent para reconciliar acciones soportadas.

La reconciliacion visible de perfil contra runtime sigue siendo de solo lectura. La reconciliacion managed de arranque instala faltantes solo si la politica comun autoriza el PHP y la receta Debian tiene fuentes coincidentes y herramientas requeridas; las versiones autorizadas actualmente son 5.6, 7.2, 7.4, 8.1, 8.2, 8.3 y 8.4, dentro del limite <8.5. Fuera de ese caso devuelve skipped o failed. El perfil declara intencion, no recetas ejecutables ni inventario copiado de otra version. Un fallo de repositorio/dependencias tampoco convierte desired_state en instalacion aplicada; ver `php56-php72-validation-2026-10-15.md` y `container-validation-standard-2026-10-14.md`.

## Ubicacion

Ruta por defecto dentro del contenedor:

```text
/var/lib/wsdd-php-admin/profile.json
```

La ruta puede cambiarse con:

```text
WSDD_PHP_ADMIN_PROFILE_DIR
```

## Estructura ampliada prevista

El siguiente ejemplo describe metadata objetivo del plan, NO todos los campos que el codigo registra actualmente. ProfileDocument::withExtensionState genera desired_state, managed_by, source y updated_at; metodo/version/dependencias/estado observado no se persisten sistematicamente y last_reconciliation permanece sin actualizacion. Los campos de conflictos de este ejemplo no tienen un motor de bloqueo implementado. La observacion efectiva se obtiene de CLI/Apache y los detalles de operaciones/catalogo; no deben confundirse con metadata completa del perfil. Revision vigente: `development-status-2026-10-15.md`.

```json
{
  "schema_version": 1,
  "created_at": "2026-10-11T00:00:00+00:00",
  "updated_at": "2026-10-11T00:00:00+00:00",
  "php": {
    "version": "8.3.35",
    "runtime": "apache",
    "sapi": "apache2handler",
    "ini_file": "/usr/local/etc/php/php.ini",
    "ini_scan_dir": "/usr/local/etc/php/conf.d"
  },
  "extensions": {
    "bcmath": {
      "desired_state": "enabled",
      "observed_state": "missing",
      "install_method": "docker-php-ext-install",
      "installed_version": null,
      "system_dependencies": [],
      "incompatible_with": [],
      "conflicts_with": [],
      "origin": "core",
      "installed_at": null,
      "updated_at": "2026-10-11T00:00:00+00:00",
      "last_reconciliation": null
    }
  },
  "last_reconciliation": null
}
```

## Estados deseados

- `enabled`: la extension debe estar instalada y cargada.
- `disabled`: la extension puede estar instalada, pero no debe estar cargada.
- `removed`: la extension no debe estar cargada y no debe reinstalarse durante reconciliacion automatica.

Cuando una operacion managed real cambia un `.ini` administrado, el agent actualiza la entrada correspondiente:

```json
{
  "desired_state": "enabled",
  "managed_by": "wsdd-php-admin",
  "source": "enable_extension",
  "updated_at": "2026-10-12T00:00:00+00:00"
}
```

Las operaciones `skipped` no cambian el perfil.

## Estado actual

La implementacion actual permite:

- crear un perfil vacio con `php bin/wsdd-php-admin profile:init`;
- leer el perfil con `php bin/wsdd-php-admin profile:status`;
- comparar perfil contra runtime con `php bin/wsdd-php-admin reconcile`;
- consultar estado desde HTTP con `?api=profile`, `?api=reconcile` o `?api=state`;
- registrar estado deseado cuando `enable_extension` crea un `.ini` administrado por WSDD-PHP-Admin;
- registrar estado deseado cuando `disable_extension` elimina un `.ini` administrado por WSDD-PHP-Admin;
- ejecutar reconciliacion managed de arranque desde el agent para acciones soportadas sobre `.ini` propios;
- conservar el perfil despues de reconstrucciones mediante el bind mount de WSDD en `/var/lib/wsdd-php-admin`.

La implementacion actual instala faltantes solo con receta autorizada por la politica comun PHP/OS y pasos allowlisted disponibles. La remocion binaria de extensiones compiladas no se ejecuta todavia; remove_extension elimina INI propios y devuelve skipped cuando solo quedaria remover binarios de la imagen base.

Antes de cambiar un `.ini` administrado, el agent crea snapshots persistentes en `/var/lib/wsdd-php-admin/backups/ini`. La operacion `rollback_extension` restaura el ultimo snapshot disponible para la extension y actualiza el perfil con el estado restaurado.

La activacion/desactivacion implementada en esta fase esta limitada a archivos `.ini` propios con prefijo `zz-wsdd-php-admin-`. Si una extension ya esta cargada por configuracion base de la imagen PHP, WSDD-PHP-Admin no crea un duplicado y devuelve `skipped`.

Recreacion general del 2026-10-15: perfil conservado y coincidente con Windows en los siete contenedores. Reinstalacion bcmath enabled probada en 5.6/7.2/7.4/8.1/8.3; removed respetado en 8.2/8.4. El defecto global de ManagedReconciler que ocultaba instalaciones requeridas fallidas ya se corrigio: se agregan errores y se comprueba el estado efectivo tambien sin cambios en INI. Un enabled incumplido devuelve failed y la CLI termina con 2. El perfil conserva la intencion original. Ver `runtime-reliability-fixes-2026-10-15.md`.
