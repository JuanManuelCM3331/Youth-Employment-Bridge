# Matriz de Riesgos - Youth Employment Bridge (YEB)

Registro de riesgos identificados en el desarrollo e implementación del proyecto, extraídos desde las Historias de Usuario:

## Riesgos de Seguridad y Privacidad (SEC & RSK)
* **SEC-001:** Robo o vulneración de credenciales en el inicio de sesión.
* **SEC-002:** Descarga o acceso no autorizado a hojas de vida o conversaciones por usuarios ajenos a la postulación.
* **SEC-003:** Inyección SQL a través de los campos de filtro del buscador.
* **SEC-004:** Secuestro de sesión por cookie insegura.
* **SEC-005:** Ataques de fuerza bruta por falta de límite de intentos.
* **SEC-007:** Inyección de código XSS almacenado en campos de texto del perfil.
* **SEC-008:** Vulnerabilidad a ataques de falsificación de peticiones (CSRF).
* **SEC-009:** Ausencia de captura de fecha de nacimiento y flujo de consentimiento parental.
* **RSK-02:** Brecha de datos personales o almacenamiento inseguro de contraseñas.
* **RSK-04:** Escalación de privilegios, edición no autorizada de perfiles ajenos, o eliminación no intencionada de cuentas de administración.
* **RSK-08:** Registro de menores de edad sin autorización del tratamiento de datos.

## Riesgos de Negocio y Reglas Operativas (RN & RT)
* **RN-003:** Intento de publicar vacantes sin perfil empresarial completo o sin validación de NIT.
* **RN-005:** Generación de postulaciones duplicadas en escenarios de envío múltiple.
* **RN-009:** Omisión del registro histórico de fechas y horas en las transiciones de estado de la postulación.
* **RN-010:** Filtración o exportación no autorizada de datos de candidatos de otras empresas.
* **RT-005:** Inconsistencia de datos por falta de transacciones explícitas.
* **RT-006:** Registro de empresas falsas; publicación, mantenimiento y falta de moderación de vacantes fraudulentas o engañosas.
* **RT-008:** Pérdida o corrupción de archivos de hojas de vida almacenados en disco.

## Riesgos de Defectos del Sistema (DEF & OBS)
* **DEF-001:** Error HTTP 500 al filtrar por ciudad/ubicación por uso de marcador repetido en PDO.
* **DEF-002:** El sistema permite el envío directo de postulaciones a vacantes en estado cerrada.
* **DEF-003:** El candidato no recibe la alerta interna cuando la empresa actualiza el estado de su postulación.
* **DEF-004:** El sistema muestra un mensaje de confirmación falso al intentar modificar una postulación o editar/cerrar vacantes ajenas.
* **OBS-004:** Discrepancia entre formatos de archivo aceptados en servidor vs. regla de negocio RN-006.
* *(Nota)* Inconsistencia por borrado en cascada en registros de auditoría y postulaciones.
* *(Nota)* Ausencia del estado «pausada» en el esquema ENUM de la base de datos.
* *(Nota)* Uso de enlaces de recuperación sin tiempo de expiración o de múltiples usos.
* *(Nota)* Inconsistencia en los registros de vacantes guardadas/ocultas al cerrar e iniciar sesión.

## Riesgos de Experiencia de Usuario e Interfaz (UXH)
* **UXH-001:** Desbordamiento visual del formulario en pantallas móviles (390px).
* **UXH-003:** Inconsistencias en el vocabulario de estados entre secciones de la interfaz.
* **UXH-005:** Redirección al panel principal tras «Ingresar para aplicar» sin retornar al detalle de la vacante.

## Riesgos de Rendimiento y Bloqueo (PERF, RNF, BLQ & CHG)
* **PERF-001:** Bajo rendimiento en consultas de agregación sobre la tabla `audit_logs` con alto volumen.
* **RNF-001:** Degradación del rendimiento en búsquedas con múltiples solicitudes concurrentes.
* **BLQ-02:** Indisponibilidad o falla de configuración del servicio de correo SMTP externo.
* **BLQ-03:** Incumplimiento de la Ley 1581 de 2012 (Habeas Data) en el tratamiento de datos de menores de edad.
* **CHG-04:** Incremento en la complejidad técnica e impacto en el cronograma del MVP por la implementación de mensajería interna.