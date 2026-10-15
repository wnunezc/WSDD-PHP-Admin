# Changelog

## 1.0.0 - Preparada para publicacion

Primera distribucion del codigo de WSDD-PHP-Admin. Esta entrada no afirma que exista un tag o release publicado.

- UI autenticada, sesiones, cambio inicial de contrasena, CSRF y auditoria.
- Catalogo de 21 recetas revisadas con seleccion PHP/distribucion y descubrimiento local de fuentes/binarios.
- Cola persistente, agente root, operaciones managed y snapshots/rollback de INI propios.
- Reconciliacion de arranque con propagacion de fallos y verificacion CLI/Apache.
- Arranque unico con locks, comprobacion de disponibilidad y limpieza de procesos propios ante fallos de arranque.
- Codigo comun adaptado para PHP 5.6 a 8.4, con evidencia Dev en las siete versiones enumeradas en README.
- Metadatos aditivos del perfil: observaciones fechadas, evidencia de instalacion y resultados de operaciones/reconciliacion. Comprobacion aislada; integracion completa posterior a este cambio no declarada aprobada.
- Separacion entre repositorio fuente y paquete sin datos de instalacion; licencia MIT con atribucion de autor/repositorio.

Limites: dependencias externas de PHP 5.6 sujetas a fuentes autenticadas Stretch; remove/rollback no eliminan/restauran paquetes; no se certifica FPM, PHP 8.0/8.5 ni despliegue Release limpio. Fallback e incompatibilidades se atienden por issues, no se anuncian como implementados.
