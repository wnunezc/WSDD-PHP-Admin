# WSDD-PHP-Admin

Version preparada: **1.0.0**. Administrador web de extensiones PHP dentro del propio contenedor, con perfil persistente, agente privilegiado y reconciliacion de arranque.

Autor original: [wnunezc](https://github.com/wnunezc). Repositorio: [wnunezc/WSDD-PHP-Admin](https://github.com/wnunezc/WSDD-PHP-Admin).

## Alcance

- Interfaz autenticada para consultar runtime, catalogo, permisos, reconciliacion y operaciones.
- Instalacion mediante recetas revisadas; activacion/desactivacion y rollback de INI administrados.
- Perfil y cola persistentes, auditoria y snapshots.
- Un unico entrypoint runtime:start para agente root y web www-data desde la misma raiz.
- Observacion nueva de PHP CLI y sonda Apache para evitar informacion obsoleta de procesos residentes.

Entornos Dev documentados: PHP **5.6, 7.2, 7.4, 8.1, 8.2, 8.3 y 8.4**, Apache + CLI en imagenes WSDD/Debian. No equivale a compatibilidad certificada con cualquier imagen, distribucion o version intermedia. PHP 5.6 conserva una limitacion conocida de repositorios Stretch para dependencias externas. PHP 8.0/8.5 y FPM no estan validados.

## Instalacion e integracion

Consultar [instalacion y contrato runtime](docs/INSTALL.md). La aplicacion no depende de Composer/vendor para arrancar: incluye su autoloader y no tiene dependencias PHP externas.

El paquete no incluye perfiles, usuarios, claves, operaciones, logs ni extensiones preinstaladas. Cada contenedor conserva sus propios datos fuera del codigo:

| Directorio | Contenido |
| --- | --- |
| /var/lib/wsdd-php-admin | Perfil, credenciales, cola, snapshots y datos runtime |
| /var/log/wsdd-php-admin | Logs operativos y auditoria |

El acceso inicial, solo si no existen credenciales persistentes, es admin/admin y obliga a cambiar la contrasena. Restringir el panel a una red administrada; no exponer el puerto 8989 directamente a Internet. Ver [seguridad](SECURITY.md).

## Limites explicitos

remove_extension retira el INI propio; no elimina binarios ni paquetes de la imagen. Rollback restaura INI, no revierte paquetes. Las 21 recetas no significan 21 compilaciones certificadas por version.

Fallback, reglas de incompatibilidad y recuperacion ampliada se incorporaran mediante issues cuando exista necesidad demostrada. El ultimo cambio de metadatos del perfil tiene pruebas aisladas; no se presenta como una nueva bateria integral aprobada. La preparacion del archivo 1.0.0 tampoco certifica una instalacion Release limpia.

## Desarrollo y distribucion

El repositorio conserva pruebas y documentos de desarrollo. Los ZIP de distribucion excluyen esos recursos, scripts de validacion, temporales y datos runtime. Las instrucciones de empaquetado estan en docs/release-1.0.0.md del repositorio fuente; el paquete incluye [instalacion](docs/INSTALL.md) y [cambios](CHANGELOG.md).

## Licencia

[MIT](LICENSE). Conservar el aviso de copyright y la licencia en copias o porciones sustanciales; el aviso identifica al autor original y al repositorio. No se exige atribucion visible en cada pantalla.
