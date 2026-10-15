# Seguridad

WSDD-PHP-Admin modifica configuracion PHP y ejecuta instalaciones mediante un agente root. Su alcance documentado es una red local/controlada de administracion, no un panel publico de hosting.

- Mantener el servidor interno en loopback, detras del proxy Apache y de los controles de acceso de la infraestructura.
- Cambiar las credenciales iniciales antes de usar operaciones. No reutilizar contrasenas de desarrollo.
- Proteger y respaldar estado/logs separados del codigo. auth.json, probe.key, sesiones, perfil y operaciones no pertenecen al repositorio ni al paquete.
- No desactivar firmas, hashes o autenticacion de repositorios para instalar una extension.
- Aplicar mantenimiento de PHP y del sistema operativo. Que el codigo ejecute en una version antigua no garantiza la seguridad de su imagen base.

No publicar credenciales, claves, cookies, volcados completos de estado ni logs sin revisar en issues. Para una vulnerabilidad sensible, usar el canal privado de reporte de GitHub si esta habilitado. Si no esta disponible, solicitar un canal privado al mantenedor sin revelar detalles de explotacion. Esta preparacion no habilita ni afirma haber habilitado esa opcion de GitHub.
