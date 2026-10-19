# HU-001: Registro de candidato
**Como** joven que busca su primer empleo,
**quiero** crear una cuenta con mi correo y una contraseña,
**para** acceder a las vacantes y postularme.

### Criterios de Aceptación
- [ ] **CA-HU-001-01:** Con datos válidos se crea la cuenta con rol candidato y contraseña cifrada.
- [ ] **CA-HU-001-02:** Un correo ya registrado se rechaza con un aviso claro.
- [ ] **CA-HU-001-03:** Una contraseña de menos de 8 caracteres se rechaza.

### Riesgos relacionados
* RSK-02. Brecha de datos personales o almacenamiento inseguro de contraseñas.
* RSK-08. Registro de menores de edad sin autorización del tratamiento de datos.

### Casos de prueba relacionados
* CP-001, CP-002, CP-002B, CP-026.

---

# HU-002: Registro de empresa y reclutador
**Como** reclutador de una empresa local,
**quiero** registrar la empresa y mi cuenta de reclutador,
**para** publicar vacantes para jóvenes.

### Criterios de Aceptación
- [ ] **CA-HU-002-01:** Se crean la empresa y la cuenta del reclutador vinculada.
- [ ] **CA-HU-002-02:** Un correo ya registrado se rechaza.

### Riesgos relacionados
* RT-006. Registro de empresas falsas que publiquen vacantes engañosas.
* RT-005. Inconsistencia de datos por falta de transacciones explícitas.

### Casos de prueba relacionados
* CP-003, CP-002.

---

# HU-003: Inicio de sesión
**Como** usuario registrado,
**quiero** entrar con mi correo y mi contraseña,
**para** llegar al panel de mi rol.

### Criterios de Aceptación
- [ ] **CA-HU-003-01:** Con credenciales válidas el usuario llega al panel de su rol.
- [ ] **CA-HU-003-02:** Con credenciales inválidas se muestra un mensaje genérico.

### Riesgos relacionados
* SEC-001. Robo o vulneración de credenciales en el inicio de sesión.
* SEC-004. Secuestro de sesión por cookie insegura.
* SEC-005. Ataques de fuerza bruta por falta de límite de intentos.

### Casos de prueba relacionados
* CP-004, CP-004B, CP-038.

---

# HU-004: Publicar vacante
**Como** reclutador,
**quiero** publicar una oferta con cargo, ciudad, modalidad y salario,
**para** que los jóvenes la encuentren.

### Criterios de Aceptación
- [ ] **CA-HU-004-01:** La vacante publicada aparece de inmediato en el buscador.
- [ ] **CA-HU-004-02:** Sin título, descripción o ciudad no se guarda la vacante.

### Riesgos relacionados
* RT-006. Publicación de vacantes fraudulentas o engañosas.
* RN-003. Intento de publicar vacantes sin perfil empresarial completo o NIT.

### Casos de prueba relacionados
* CP-011, CP-011B, CP-013.

---

# HU-005: Buscador y filtros de vacantes
**Como** joven,
**quiero** buscar vacantes por palabra clave, ciudad, área, experiencia y tipo de empleo,
**para** encontrar ofertas acordes a mi perfil.

### Criterios de Aceptación
- [ ] **CA-HU-005-01:** La búsqueda por palabra clave devuelve solo vacantes publicadas que coinciden.
- [ ] **CA-HU-005-02:** El filtro por ciudad devuelve solo vacantes de esa ciudad.
- [ ] **CA-HU-005-03:** Un texto con código SQL no altera la consulta.

### Riesgos relacionados
* DEF-001. Error HTTP 500 al filtrar por ciudad/ubicación por uso de marcador repetido en PDO.
* RNF-001. Degradación del rendimiento en búsquedas con múltiples solicitudes concurrentes.
* SEC-003. Inyección SQL a través de los campos de filtro del buscador.

### Casos de prueba relacionados
* CP-013, CP-013B, CP-024, CP-028.

---

# HU-006: Registro de perfil del candidato
**Como** joven,
**quiero** registrar mi formación, habilidades, experiencia y foto,
**para** presentarme a las empresas.

### Criterios de Aceptación
- [ ] **CA-HU-006-01:** El perfil guarda formación, habilidades, experiencia y foto.
- [ ] **CA-HU-006-02:** Un texto con código HTML se muestra como texto y no se ejecuta.

### Riesgos relacionados
* SEC-007. Inyección de código XSS almacenado en campos de texto del perfil.
* UXH-001. Desbordamiento visual del formulario en pantallas móviles (390px).

### Casos de prueba relacionados
* CP-007, CP-025, CP-029.

---

# HU-007: Carga de hoja de vida (PDF)
**Como** joven,
**quiero** adjuntar mi hoja de vida en PDF,
**para** que la empresa la descargue al revisar mi postulación.

### Criterios de Aceptación
- [ ] **CA-HU-007-01:** Un PDF de hasta 5 MB se carga y queda asociado al perfil.
- [ ] **CA-HU-007-02:** Un archivo de otro formato o mayor a 5 MB se rechaza.
- [ ] **CA-HU-007-03:** Solo las empresas donde el joven se postuló pueden descargarla.

### Riesgos relacionados
* SEC-002. Descarga o acceso no autorizado a hojas de vida por empresas sin relación de postulación.
* OBS-004. Discrepancia entre formatos de archivo aceptados en servidor vs regla RN-006.
* RT-008. Pérdida o corrupción de archivos de hojas de vida almacenados en disco.

### Casos de prueba relacionados
* CP-008, CP-008B, CP-008C, CP-009, CP-009B.

---

# HU-008: Detalle de la vacante
**Como** joven,
**quiero** leer la descripción completa de una vacante,
**para** decidir si me postulo.

### Criterios de Aceptación
- [ ] **CA-HU-008-01:** El detalle muestra cargo, empresa, ciudad, modalidad y salario o «a convenir».
- [ ] **CA-HU-008-02:** Una vacante inexistente muestra un mensaje y no un error del servidor.

### Riesgos relacionados
* UXH-005. Redirección al panel principal tras «Ingresar para aplicar» sin retornar al detalle de la vacante.

### Casos de prueba relacionados
* CP-014, CP-014B.

---

# HU-009: Postulación a vacantes
**Como** joven,
**quiero** postularme con un clic usando mi perfil,
**para** participar en el proceso de selección.

### Criterios de Aceptación
- [ ] **CA-HU-009-01:** La postulación a una vacante publicada se registra en estado enviada.
- [ ] **CA-HU-009-02:** Una segunda postulación a la misma vacante se rechaza.
- [ ] **CA-HU-009-03:** No se acepta la postulación a una vacante cerrada.

### Riesgos relacionados
* DEF-002. El sistema permite el envío directo de postulaciones a vacantes en estado cerrada.
* RN-005. Generación de postulaciones duplicadas en escenarios de envío múltiple.

### Casos de prueba relacionados
* CP-016, CP-017, CP-018.

---

# HU-010: Mensajería interna
**Como** joven postulado,
**quiero** intercambiar mensajes con la empresa sin compartir mi teléfono,
**para** resolver dudas del proceso.

### Criterios de Aceptación
- [ ] **CA-HU-010-01:** Solo existe conversación si hay postulación previa.
- [ ] **CA-HU-010-02:** Los mensajes muestran fecha y estado de lectura.

### Riesgos relacionados
* SEC-002. Acceso no autorizado a conversaciones por usuarios ajenos a la postulación.
* CHG-04. Incremento en la complejidad técnica e impacto en el cronograma del MVP.

### Casos de prueba relacionados
* CP-032.

---

# HU-011: Actualización de perfil
**Como** joven,
**quiero** actualizar mis datos cuando cambien,
**para** mantener mi perfil vigente.

### Criterios de Aceptación
- [ ] **CA-HU-011-01:** Los cambios del perfil se guardan y se ven al recargar.
- [ ] **CA-HU-011-02:** Un joven no puede editar el perfil de otro.

### Riesgos relacionados
* SEC-007. Vulnerabilidad a XSS almacenado en campos de texto libre.
* RSK-04. Edición no autorizada de perfiles ajenos por fallas en la validación de sesión.

### Casos de prueba relacionados
* CP-007, CP-005.

---

# HU-012: Control de acceso por roles
**Como** administrador de YEB,
**quiero** que cada usuario acceda solo a las acciones de su rol,
**para** proteger los datos de candidatos y empresas.

### Criterios de Aceptación
- [ ] **CA-HU-012-01:** Un candidato no puede ejecutar acciones de empresa ni de administrador.
- [ ] **CA-HU-012-02:** Un formulario sin token CSRF válido se rechaza.

### Riesgos relacionados
* RSK-04. Escalación de privilegios o ejecución no autorizada de acciones protegidas.
* SEC-008. Vulnerabilidad a ataques de falsificación de peticiones (CSRF).

### Casos de prueba relacionados
* CP-005, CP-006.

---

# HU-013: Perfil corporativo de la empresa
**Como** reclutador,
**quiero** registrar y editar la razón social, el NIT, la descripción y el logo,
**para** dar confianza a los candidatos.

### Criterios de Aceptación
- [ ] **CA-HU-013-01:** La empresa guarda razón social, descripción y logo.
- [ ] **CA-HU-013-02:** Sin NIT y razón social la empresa no puede publicar.

### Riesgos relacionados
* RN-003. Publicación de ofertas por empresas con perfil incompleto o sin validación de NIT.
* RT-006. Presencia de organizaciones no verificadas en la plataforma.

### Casos de prueba relacionados
* CP-010, CP-003.

---

# HU-014: Historial de postulaciones del candidato
**Como** joven,
**quiero** ver el estado de cada postulación,
**para** saber en qué va cada proceso.

### Criterios de Aceptación
- [ ] **CA-HU-014-01:** El joven ve cada postulación con vacante, fecha y estado actual.
- [ ] **CA-HU-014-02:** Sin postulaciones se muestra un estado vacío con acceso al buscador.

### Riesgos relacionados
* UXH-003. Inconsistencias en el vocabulario de estados entre secciones de la interfaz.

### Casos de prueba relacionados
* CP-019.

---

# HU-015: Visualización de postulantes por empresa
**Como** reclutador,
**quiero** ver la lista de postulados y su hoja de vida,
**para** evaluar a los candidatos.

### Criterios de Aceptación
- [ ] **CA-HU-015-01:** La empresa ve solo los candidatos de sus propias vacantes.
- [ ] **CA-HU-015-02:** Puede descargar la hoja de vida de sus candidatos.

### Riesgos relacionados
* RN-010. Filtración o visualización de candidatos postulados a ofertas de otras empresas.

### Casos de prueba relacionados
* CP-020, CP-009.

---

# HU-016: Gestión de estados de postulación
**Como** reclutador,
**quiero** mover cada postulación entre revisión, entrevista, aceptada o rechazada,
**para** llevar el proceso y mantener informado al candidato.

### Criterios de Aceptación
- [ ] **CA-HU-016-01:** El cambio a entrevista queda guardado con fecha y hora.
- [ ] **CA-HU-016-02:** Un estado fuera de la lista permitida se rechaza.
- [ ] **CA-HU-016-03:** No se puede cambiar una postulación de otra empresa, y el sistema no muestra éxito.

### Riesgos relacionados
* DEF-004. El sistema muestra un mensaje de éxito falso al intentar modificar una postulación ajena.
* RN-009. Omisión del registro histórico de fechas y horas en las transiciones de estado.

### Casos de prueba relacionados
* CP-020B, CP-020C, CP-012C.

---

# HU-017: Gestión de vacantes propias
**Como** reclutador,
**quiero** editar, pausar y cerrar mis vacantes,
**para** mantener actualizada mi oferta.

### Criterios de Aceptación
- [ ] **CA-HU-017-01:** La empresa edita y cierra sus vacantes.
- [ ] **CA-HU-017-02:** No puede editar vacantes de otra empresa.

### Riesgos relacionados
* DEF-004. Mensaje de confirmación falso al intentar editar o cerrar vacantes ajenas.
* Ausencia del estado «pausada» en el esquema ENUM de la base de datos. *(Nota de riesgo)*

### Casos de prueba relacionados
* CP-012, CP-012B, CP-012C.

---

# HU-018: Sistema de alertas y notificaciones
**Como** usuario registrado,
**quiero** recibir alertas cuando hay una postulación nueva o cambia el estado de la mía,
**para** reaccionar a tiempo.

### Criterios de Aceptación
- [ ] **CA-HU-018-01:** La empresa recibe una alerta por cada nueva postulación.
- [ ] **CA-HU-018-02:** El joven recibe una alerta cuando cambia el estado de su postulación.
- [ ] **CA-HU-018-03:** Las alertas pueden marcarse como leídas.

### Riesgos relacionados
* DEF-003. El candidato no recibe la alerta interna cuando la empresa actualiza el estado de su postulación.

### Casos de prueba relacionados
* CP-021, CP-021B, CP-021C.

---

# HU-019: Recuperación de contraseña
**Como** usuario registrado,
**quiero** restablecer mi contraseña con un enlace enviado a mi correo,
**para** no perder el acceso a mi cuenta.

### Criterios de Aceptación
- [ ] **CA-HU-019-01:** Se envía un enlace de un solo uso al correo registrado.
- [ ] **CA-HU-019-02:** El mensaje no revela si el correo existe.

### Riesgos relacionados
* BLQ-02. Indisponibilidad o falla de configuración del servicio de correo SMTP externo.
* Uso de enlaces de recuperación sin tiempo de expiración o de múltiples usos. *(Nota de riesgo)*

### Casos de prueba relacionados
* CP-031.

---

# HU-020: Gestión administrativa de cuentas
**Como** administrador,
**quiero** consultar, suspender y eliminar cuentas y crear otros administradores,
**para** mantener la plataforma segura.

### Criterios de Aceptación
- [ ] **CA-HU-020-01:** El administrador busca y elimina cuentas, pero no la propia.
- [ ] **CA-HU-020-02:** Puede crear otro administrador con correo único.
- [ ] **CA-HU-020-03:** Una cuenta suspendida no puede iniciar sesión.

### Riesgos relacionados
* RSK-04. Eliminación no intencionada de cuentas de administración activas.
* Inconsistencia por borrado en cascada en registros de auditoría y postulaciones. *(Nota de riesgo)*

### Casos de prueba relacionados
* CP-023, CP-023B.

---

# HU-021: Autorización de tratamiento de datos
**Como** joven que se registra,
**quiero** aceptar la política de datos y, si soy menor de 18 años, registrar la autorización de mi representante legal,
**para** que mis datos se traten conforme a la ley.

### Criterios de Aceptación
- [ ] **CA-HU-021-01:** El registro no se completa sin aceptar la política de datos.
- [ ] **CA-HU-021-02:** Si la persona tiene 16 o 17 años, se exige la autorización de su representante legal.
- [ ] **CA-HU-021-03:** La aceptación queda registrada con fecha y versión de la política.

### Riesgos relacionados
* BLQ-03. Incumplimiento de la Ley 1581 de 2012 (Habeas Data) en el tratamiento de datos de menores de edad.
* SEC-009. Ausencia de captura de fecha de nacimiento y flujo de consentimiento parental.

### Casos de prueba relacionados
* CP-037.

---

# HU-022: Moderación de vacantes
**Como** administrador,
**quiero** retirar vacantes que infrinjan los términos de uso,
**para** evitar ofertas fraudulentas.

### Criterios de Aceptación
- [ ] **CA-HU-022-01:** El administrador retira una vacante y deja de verse en el buscador.
- [ ] **CA-HU-022-02:** La acción queda registrada en la auditoría.

### Riesgos relacionados
* RT-006. Publicación y mantenimiento de vacantes engañosas sin moderación oportuna.

### Casos de prueba relacionados
* CP-033.

---

# HU-023: Favoritos y ocultar vacantes
**Como** joven,
**quiero** guardar las vacantes que me interesan y ocultar las que no,
**para** organizar mi búsqueda.

### Criterios de Aceptación
- [ ] **CA-HU-023-01:** La vacante guardada aparece en «Mis favoritos».
- [ ] **CA-HU-023-02:** La vacante oculta no vuelve a aparecer en la búsqueda del joven.

### Riesgos relacionados
* Inconsistencia en los registros de vacantes guardadas/ocultas al cerrar e iniciar sesión. *(Nota de riesgo)*

### Casos de prueba relacionados
* CP-015.

---

# HU-024: Filtrado y exportación de postulantes
**Como** reclutador,
**quiero** filtrar candidatos por habilidades y exportar la lista de postulantes,
**para** agilizar la preselección.

### Criterios de Aceptación
- [ ] **CA-HU-024-01:** La empresa filtra sus candidatos por habilidades.
- [ ] **CA-HU-024-02:** La exportación contiene solo los candidatos visibles en la consulta.

### Riesgos relacionados
* RN-010. Fuga o exportación no autorizada de datos de candidatos de otras empresas.

### Casos de prueba relacionados
* CP-033.

---

# HU-025: Panel general de auditoría e indicadores
**Como** administrador,
**quiero** ver totales de usuarios, vacantes y postulaciones y la auditoría filtrada,
**para** conocer el uso y detectar acciones sospechosas.

### Criterios de Aceptación
- [ ] **CA-HU-025-01:** El panel muestra totales de usuarios, empresas, vacantes y postulaciones.
- [ ] **CA-HU-025-02:** La auditoría se filtra por módulo y acción.

### Riesgos relacionados
* PERF-001. Bajo rendimiento en consultas de agregación sobre la tabla audit_logs con alto volumen.

### Casos de prueba relacionados
* CP-022.