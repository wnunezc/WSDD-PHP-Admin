# Preparacion de publicacion 1.0.0

Fecha: 2026-10-15. Repositorio destino: https://github.com/wnunezc/WSDD-PHP-Admin.

## Estado verificado

- Commit de seguridad previo a preparacion: 94a237d. Conserva el desarrollo de metadatos y las decisiones de alcance.
- Consulta gh repo view: repositorio publico, vacio, sin rama por defecto ni licencia al revisar. git ls-remote no devolvio referencias. No se realizo push ni se creo tag/release durante la preparacion.
- No habia profile.json, auth.json, claves de sonda ni operaciones runtime versionados. var solo contenia marcadores de cache/logs. Las capturas y scripts temporales de tmp ya estaban ignorados.
- Se conserva src/Profile y su esquema: son implementacion/documentacion, no perfiles reales de instalaciones.
- Se incorpora MIT, con autor original wnunezc y URL del repositorio en el aviso de copyright. La decision del usuario fue una licencia open source con atribucion; no se inventa una obligacion de credito visible en la UI.

## Contenido fuente frente a paquete

El repositorio conserva codigo, pruebas y evidencia tecnica. .gitignore excluye estado, perfiles reales, credenciales, claves, logs, backups, dependencias descargadas y artefactos. No se borra ningun dato local/persistente para lograrlo. Ignorar no retira secretos de un historial anterior; la revision no encontro perfiles reales ni patrones de credenciales publicados en los archivos revisados.

.gitattributes establece LF para fuentes/scripts y export-ignore para recursos de desarrollo. bin/build-release.ps1 exporta exclusivamente una lista de rutas de un commit limpio, verifica contenido, y genera ZIP, SHA256 y manifiesto en dist, ignorado por Git. No copia archivos arbitrarios del filesystem ni incluye datos del host/otros proyectos.

El paquete contiene VERSION, licencia, README/changelog/seguridad, composer.json, CLI principal, config, src, public, instrucciones y ejemplos Apache. No contiene pruebas, informes historicos, scripts de validacion, tmp, vendor, datos runtime ni deploy/container/install.sh. Este ultimo conserva su caracter historico y no se promueve como instalador o actualizador de 1.0.0.

## Generacion reproducible

Despues de confirmar los archivos de preparacion en Git:

```powershell
./bin/build-release.ps1 -Ref HEAD
```

El script no crea commits/tags, no publica, no instala la aplicacion y no sobreescribe un ZIP existente. VERSION se lee del commit, no de un archivo modificado sin confirmar. El manifiesto identifica el SHA de origen. Las exclusiones de .gitattributes tambien se aplican a exportaciones Git que respeten export-ignore.

## Antes de publicar

La preparacion no modifica el contrato WSDD ni las imagenes/instalaciones existentes. No se declara certificacion de despliegue limpio por crear un ZIP. Se conservan los resultados de pruebas anteriores y sus limites; no se exige repetir indiscriminadamente las baterias ya aprobadas.

La publicacion posterior debe enviar el commit elegido al remoto, crear un tag v1.0.0 que coincida con VERSION y adjuntar ZIP, checksum y manifiesto a la release. Verificar que el SHA del tag corresponde al manifiesto antes de publicar. No usar force push ni incluir datos de desarrollo en archivos subidos manualmente. Esta tarea prepara esos elementos; no ejecuta la publicacion remota.
