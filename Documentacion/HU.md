# Historias de Usuario - Youth Employment Bridge (YEB)

## Cómo leer este documento

- **Prioridad (MoSCoW):** Must = imprescindible, Should = importante, Could = deseable.
- **Estado:** *Implementada* (existe en el código), *Parcial* (existe pero incumple algún criterio), *Pendiente* (no existe en el prototipo).
- El estado proviene de una revisión estática del código (19/10/2026) y debe confirmarse al ejecutar el [Plan de Pruebas](PLAN_PRUEBAS.md).
- Los riesgos (`SEC-`, `NEG-`, `TEC-`, `OPE-`, `UX-`, `LEG-`, `RSK-`) están en [RIESGOS.md](RIESGOS.md). Los defectos (`DEF-`) están en la sección de defectos conocidos de ese mismo documento.
- Un criterio marcado **(Pendiente)** describe un comportamiento esperado que hoy no existe.

## Resumen

| HU | Título | Prioridad | Estado |
| :-- | :-- | :-- | :-- |
| HU-001 | Registro de candidato | Must | Parcial (DEF-005) |
| HU-002 | Registro de empresa y reclutador | Must | Implementada |
| HU-003 | Inicio de sesión | Must | Parcial (sin límite de intentos) |
| HU-004 | Publicar vacante | Must | Implementada |
| HU-005 | Buscador y filtros de vacantes | Must | Parcial (DEF-001) |
| HU-006 | Registro de perfil del candidato | Must | Implementada |
| HU-007 | Carga de hoja de vida | Must | Implementada |
| HU-008 | Detalle de la vacante | Must | Implementada |
| HU-009 | Postulación a vacantes | Must | Parcial (DEF-002) |
| HU-010 | Mensajería interna | Should | Implementada |
| HU-011 | Actualización de perfil | Should | Implementada |
| HU-012 | Control de acceso por roles | Must | Implementada |
| HU-013 | Perfil corporativo de la empresa | Must | Parcial (DEF-008) |
| HU-014 | Historial de postulaciones del candidato | Must | Implementada |
| HU-015 | Visualización de postulantes por empresa | Must | Implementada |
| HU-016 | Gestión de estados de postulación | Must | Parcial (DEF-004, DEF-007) |
| HU-017 | Gestión de vacantes propias | Must | Parcial (DEF-004) |
| HU-018 | Alertas y notificaciones | Should | Parcial (DEF-003, DEF-006) |
| HU-019 | Recuperación de contraseña | Could | Pendiente |
| HU-020 | Gestión administrativa de cuentas | Should | Parcial (sin suspensión) |
| HU-021 | Autorización de tratamiento de datos | Must (legal) | Pendiente |
| HU-022 | Moderación de vacantes | Should | Pendiente |
| HU-023 | Favoritos y ocultar vacantes | Could | Implementada |
| HU-024 | Filtrado y exportación de postulantes | Could | Pendiente |
| HU-025 | Panel general de auditoría e indicadores | Should | Implementada |

---

# HU-001: Registro de candidato
**Prioridad:** Must | **Estado:** Parcial (DEF-005: el código acepta contraseñas de 6 caracteres)

**Como** joven que busca su primer empleo,
**quiero** crear una cuenta con mi correo y una contraseña,
**para** acceder a las vacantes y postularme.

### Criterios de Aceptación
- [ ] **CA-HU-001-01:** Con datos válidos se crea la cuenta con rol `candidate` y la contraseña almacenada como hash.
- [ ] **CA-HU-001-02:** Un correo ya registrado se rechaza con un aviso claro.
- [ ] **CA-HU-001-03:** Una contraseña de menos de 8 caracteres se rechaza.

### Riesgos relacionados
SEC-001, LEG-001

### Casos de prueba relacionados
CP-001, CP-002, CP-002B, CP-026

---

# HU-002: Registro de empresa y reclutador
**Prioridad:** Must | **Estado:** Implementada

**Como** reclutador de una empresa local,
**quiero** registrar la empresa y mi cuenta de reclutador,
**para** publicar vacantes para jóvenes.

### Criterios de Aceptación
- [ ] **CA-HU-002-01:** Se crean la empresa y la cuenta del reclutador vinculada a ella.
- [ ] **CA-HU-002-02:** Un correo ya registrado se rechaza.

### Riesgos relacionados
NEG-001, TEC-001

### Casos de prueba relacionados
CP-003, CP-002

---

# HU-003: Inicio de sesión
**Prioridad:** Must | **Estado:** Parcial (CA-HU-003-03 pendiente)

**Como** usuario registrado,
**quiero** entrar con mi correo y mi contraseña,
**para** llegar al panel de mi rol.

### Criterios de Aceptación
- [ ] **CA-HU-003-01:** Con credenciales válidas el usuario llega al panel de su rol.
- [ ] **CA-HU-003-02:** Con credenciales inválidas se muestra un mensaje genérico que no revela si el correo existe.
- [ ] **CA-HU-003-03 (Pendiente):** Tras 5 intentos fallidos consecutivos se bloquea temporalmente el inicio de sesión de esa cuenta.

### Riesgos relacionados
SEC-001, SEC-004, SEC-005

### Casos de prueba relacionados
CP-004, CP-004B, CP-037, CP-038

---

# HU-004: Publicar vacante
**Prioridad:** Must | **Estado:** Implementada

**Como** reclutador,
**quiero** publicar una oferta con cargo, ciudad, modalidad y salario,
**para** que los jóvenes la encuentren.

### Criterios de Aceptación
- [ ] **CA-HU-004-01:** Una vacante publicada (estado `published`) aparece en el buscador.
- [ ] **CA-HU-004-02:** Sin título, descripción, ciudad, experiencia o rango salarial no se guarda la vacante.
- [ ] **CA-HU-004-03:** Una vacante guardada como borrador (`draft`) no aparece en el buscador.

### Riesgos relacionados
NEG-001, NEG-002

### Casos de prueba relacionados
CP-011, CP-011B, CP-011C, CP-013

---

# HU-005: Buscador y filtros de vacantes
**Prioridad:** Must | **Estado:** Parcial (DEF-001)

**Como** joven,
**quiero** buscar vacantes por palabra clave y por ubicación (ciudad o modalidad),
**para** encontrar ofertas acordes a mi perfil.

> Los filtros por área, experiencia, salario y tipo de empleo que mencionaba la versión anterior y el README **no existen** en el buscador actual. Quedan en el backlog (Could) hasta que se implementen.

### Criterios de Aceptación
- [ ] **CA-HU-005-01:** La búsqueda por palabra clave devuelve solo vacantes publicadas que coinciden en título, descripción o empresa.
- [ ] **CA-HU-005-02:** El filtro por ubicación devuelve solo vacantes de esa ciudad o modalidad.
- [ ] **CA-HU-005-03:** Un texto con código SQL no altera la consulta.

### Riesgos relacionados
SEC-003, OPE-002 (defecto asociado: DEF-001)

### Casos de prueba relacionados
CP-013, CP-013B, CP-024, CP-028

---

# HU-006: Registro de perfil del candidato
**Prioridad:** Must | **Estado:** Implementada

**Como** joven,
**quiero** registrar mi formación, habilidades, experiencia y foto,
**para** presentarme a las empresas.

### Criterios de Aceptación
- [ ] **CA-HU-006-01:** El perfil guarda formación, habilidades, experiencia y foto.
- [ ] **CA-HU-006-02:** Un texto con código HTML se muestra como texto y no se ejecuta.

### Riesgos relacionados
SEC-007, UX-001

### Casos de prueba relacionados
CP-007, CP-025, CP-029

---

# HU-007: Carga de hoja de vida
**Prioridad:** Must | **Estado:** Implementada

**Como** joven,
**quiero** adjuntar mi hoja de vida en PDF, DOC o DOCX,
**para** que la empresa la descargue al revisar mi postulación.

> Se alinea con el código (`UserFileService`) y con el README: formatos PDF, DOC y DOCX. Esto cierra la observación OBS-004.

### Criterios de Aceptación
- [ ] **CA-HU-007-01:** Un archivo PDF, DOC o DOCX de hasta 5 MB se carga y queda asociado al perfil.
- [ ] **CA-HU-007-02:** Un archivo de otro formato o mayor a 5 MB se rechaza con un mensaje claro.
- [ ] **CA-HU-007-03:** Solo las empresas a las que el joven se postuló pueden descargarla.

### Riesgos relacionados
SEC-002, SEC-011, TEC-002

### Casos de prueba relacionados
CP-008, CP-008B, CP-008C, CP-009, CP-009B

---

# HU-008: Detalle de la vacante
**Prioridad:** Must | **Estado:** Implementada

**Como** joven,
**quiero** leer la descripción completa de una vacante,
**para** decidir si me postulo.

### Criterios de Aceptación
- [ ] **CA-HU-008-01:** El detalle muestra cargo, empresa, ciudad, modalidad y salario.
- [ ] **CA-HU-008-02:** Una vacante inexistente muestra un mensaje y no un error del servidor.

### Riesgos relacionados
UX-005

### Casos de prueba relacionados
CP-014, CP-014B

---

# HU-009: Postulación a vacantes
**Prioridad:** Must | **Estado:** Parcial (DEF-002: no se valida que la vacante esté publicada)

**Como** joven,
**quiero** postularme con un clic usando mi perfil,
**para** participar en el proceso de selección.

### Criterios de Aceptación
- [ ] **CA-HU-009-01:** La postulación a una vacante publicada se registra con estado `pending` (mostrado como «Pendiente»).
- [ ] **CA-HU-009-02:** Una segunda postulación a la misma vacante se rechaza.
- [ ] **CA-HU-009-03:** No se acepta la postulación a una vacante cerrada.

### Riesgos relacionados
NEG-003 (defecto asociado: DEF-002)

### Casos de prueba relacionados
CP-016, CP-017, CP-018

---

# HU-010: Mensajería interna
**Prioridad:** Should | **Estado:** Implementada (cubierta por `tests/ChatTest.php`)

**Como** joven postulado o reclutador de la empresa,
**quiero** intercambiar mensajes dentro de una postulación,
**para** resolver dudas del proceso sin compartir mi teléfono.

### Criterios de Aceptación
- [ ] **CA-HU-010-01:** Solo existe conversación entre el candidato y la empresa de una postulación existente; ningún otro usuario puede leerla ni escribir en ella.
- [ ] **CA-HU-010-02:** Los mensajes muestran fecha y estado de lectura (`read_at`).
- [ ] **CA-HU-010-03:** Si la postulación está en estado `rejected`, no se pueden enviar mensajes nuevos (bloqueo en interfaz y en el servicio).
- [ ] **CA-HU-010-04:** Un mensaje vacío o de solo espacios se rechaza.
- [ ] **CA-HU-010-05:** La contraparte recibe una notificación al recibir un mensaje y ve el contador de no leídos.

### Riesgos relacionados
SEC-006, OPE-004

### Casos de prueba relacionados
CP-032, CP-032B, CP-032C, CP-032D, CP-032E

---

# HU-011: Actualización de perfil
**Prioridad:** Should | **Estado:** Implementada

**Como** joven,
**quiero** actualizar mis datos cuando cambien,
**para** mantener mi perfil vigente.

### Criterios de Aceptación
- [ ] **CA-HU-011-01:** Los cambios del perfil se guardan y se ven al recargar.
- [ ] **CA-HU-011-02:** Un joven no puede editar el perfil de otro.

### Riesgos relacionados
SEC-007, RSK-05

### Casos de prueba relacionados
CP-007, CP-005

---

# HU-012: Control de acceso por roles
**Prioridad:** Must | **Estado:** Implementada

**Como** administrador de YEB,
**quiero** que cada usuario acceda solo a las acciones de su rol,
**para** proteger los datos de candidatos y empresas.

### Criterios de Aceptación
- [ ] **CA-HU-012-01:** Un candidato no puede ejecutar acciones de empresa ni de administrador, y viceversa.
- [ ] **CA-HU-012-02:** Un formulario POST sin token CSRF válido se rechaza.

### Riesgos relacionados
RSK-04, SEC-008

### Casos de prueba relacionados
CP-005, CP-006

---

# HU-013: Perfil corporativo de la empresa
**Prioridad:** Must | **Estado:** Parcial (DEF-008: el esquema no tiene campo NIT)

**Como** reclutador,
**quiero** registrar y editar la razón social, el NIT, la descripción y el logo,
**para** dar confianza a los candidatos.

### Criterios de Aceptación
- [ ] **CA-HU-013-01:** La empresa guarda razón social, descripción y logo.
- [ ] **CA-HU-013-02 (Pendiente):** Sin NIT y razón social la empresa no puede publicar vacantes.

### Riesgos relacionados
NEG-001, NEG-002

### Casos de prueba relacionados
CP-010, CP-003

---

# HU-014: Historial de postulaciones del candidato
**Prioridad:** Must | **Estado:** Implementada

**Como** joven,
**quiero** ver el estado de cada postulación,
**para** saber en qué va cada proceso.

### Criterios de Aceptación
- [ ] **CA-HU-014-01:** El joven ve cada postulación con vacante, fecha y estado actual.
- [ ] **CA-HU-014-02:** Sin postulaciones se muestra un estado vacío con acceso al buscador.

### Riesgos relacionados
UX-003

### Casos de prueba relacionados
CP-019, CP-019B

---

# HU-015: Visualización de postulantes por empresa
**Prioridad:** Must | **Estado:** Implementada

**Como** reclutador,
**quiero** ver la lista de postulados y su hoja de vida,
**para** evaluar a los candidatos.

### Criterios de Aceptación
- [ ] **CA-HU-015-01:** La empresa ve solo los candidatos de sus propias vacantes.
- [ ] **CA-HU-015-02:** Puede descargar la hoja de vida de sus candidatos.

### Riesgos relacionados
NEG-005, SEC-002

### Casos de prueba relacionados
CP-020, CP-009

---

# HU-016: Gestión de estados de postulación
**Prioridad:** Must | **Estado:** Parcial (DEF-004, DEF-007)

**Como** reclutador,
**quiero** mover cada postulación entre revisión, entrevista, aceptada o rechazada,
**para** llevar el proceso y mantener informado al candidato.

### Criterios de Aceptación
- [ ] **CA-HU-016-01 (Pendiente):** Cada cambio de estado queda guardado con fecha y hora (hoy `applications` solo tiene `created_at`).
- [ ] **CA-HU-016-02:** Un estado fuera de la lista permitida (`pending`, `review`, `interview`, `accepted`, `rejected`) se rechaza.
- [ ] **CA-HU-016-03:** No se puede cambiar una postulación de otra empresa, y el sistema no muestra mensaje de éxito.

### Riesgos relacionados
NEG-004 (defectos asociados: DEF-004, DEF-007)

### Casos de prueba relacionados
CP-020B, CP-020C, CP-020D

---

# HU-017: Gestión de vacantes propias
**Prioridad:** Must | **Estado:** Parcial (DEF-004)

**Como** reclutador,
**quiero** editar y cerrar mis vacantes,
**para** mantener actualizada mi oferta.

> «Pausar» sale de esta historia: el ENUM `jobs.status` solo admite `draft`, `published` y `closed`. Si se quiere pausar, se abre una historia nueva que incluya el cambio de esquema (TEC-004).

### Criterios de Aceptación
- [ ] **CA-HU-017-01:** La empresa edita y cierra sus vacantes.
- [ ] **CA-HU-017-02:** No puede editar ni cerrar vacantes de otra empresa, y el sistema no muestra mensaje de éxito.

### Riesgos relacionados
TEC-004 (defecto asociado: DEF-004)

### Casos de prueba relacionados
CP-012, CP-012B, CP-012C

---

# HU-018: Sistema de alertas y notificaciones
**Prioridad:** Should | **Estado:** Parcial (DEF-003, DEF-006)

**Como** usuario registrado,
**quiero** recibir alertas cuando hay una postulación nueva o cambia el estado de la mía,
**para** reaccionar a tiempo.

### Criterios de Aceptación
- [ ] **CA-HU-018-01:** La empresa recibe una alerta por cada nueva postulación a sus vacantes.
- [ ] **CA-HU-018-02:** El joven recibe una alerta cuando cambia el estado de su postulación.
- [ ] **CA-HU-018-03:** Las alertas pueden marcarse como leídas (una o todas).

### Riesgos relacionados
Defectos asociados: DEF-003, DEF-006

### Casos de prueba relacionados
CP-021, CP-021B, CP-021C

---

# HU-019: Recuperación de contraseña
**Prioridad:** Could | **Estado:** Pendiente

**Como** usuario registrado,
**quiero** restablecer mi contraseña con un enlace enviado a mi correo,
**para** no perder el acceso a mi cuenta.

### Criterios de Aceptación
- [ ] **CA-HU-019-01:** Se envía un enlace de un solo uso y con vencimiento al correo registrado.
- [ ] **CA-HU-019-02:** El mensaje es el mismo exista o no el correo.

### Riesgos relacionados
OPE-003, TEC-005

### Casos de prueba relacionados
CP-031 (bloqueado hasta implementar)

---

# HU-020: Gestión administrativa de cuentas
**Prioridad:** Should | **Estado:** Parcial (la suspensión no existe)

**Como** administrador,
**quiero** consultar, suspender y eliminar cuentas y crear otros administradores,
**para** mantener la plataforma segura.

### Criterios de Aceptación
- [ ] **CA-HU-020-01:** El administrador busca y elimina cuentas, pero no la propia.
- [ ] **CA-HU-020-02:** Puede crear otro administrador con correo único.
- [ ] **CA-HU-020-03 (Pendiente):** Una cuenta suspendida no puede iniciar sesión.

### Riesgos relacionados
RSK-06, TEC-003

### Casos de prueba relacionados
CP-023, CP-023B, CP-040 (bloqueado)

---

# HU-021: Autorización de tratamiento de datos
**Prioridad:** Must (legal) | **Estado:** Pendiente

**Como** joven que se registra,
**quiero** aceptar la política de datos y, si soy menor de 18 años, registrar la autorización de mi representante legal,
**para** que mis datos se traten conforme a la ley.

> **Por definir con asesoría jurídica:** el umbral mínimo de edad para registrarse. La versión anterior decía «16 o 17 años» en el criterio y «menor de 18» en la historia, sin cubrir a menores de 16. En Colombia la edad mínima para trabajar es de 15 años y entre 15 y 17 se exige autorización específica; confirmar el requisito exacto antes de fijar el valor.

### Criterios de Aceptación
- [ ] **CA-HU-021-01:** El registro no se completa sin aceptar la política de datos.
- [ ] **CA-HU-021-02:** Si la persona es menor de 18 años, se exige la autorización de su representante legal.
- [ ] **CA-HU-021-03:** La aceptación queda registrada con fecha y versión de la política.
- [ ] **CA-HU-021-04:** Las personas por debajo del umbral mínimo definido no pueden registrarse.

### Riesgos relacionados
LEG-001

### Casos de prueba relacionados
CP-039 (bloqueado hasta implementar)

---

# HU-022: Moderación de vacantes
**Prioridad:** Should | **Estado:** Pendiente

**Como** administrador,
**quiero** retirar vacantes que infrinjan los términos de uso,
**para** evitar ofertas fraudulentas.

### Criterios de Aceptación
- [ ] **CA-HU-022-01:** El administrador retira una vacante y deja de verse en el buscador.
- [ ] **CA-HU-022-02:** La acción queda registrada en la auditoría.

### Riesgos relacionados
NEG-001

### Casos de prueba relacionados
CP-033 (bloqueado hasta implementar)

---

# HU-023: Favoritos y ocultar vacantes
**Prioridad:** Could | **Estado:** Implementada

**Como** joven,
**quiero** guardar las vacantes que me interesan y ocultar las que no,
**para** organizar mi búsqueda.

### Criterios de Aceptación
- [ ] **CA-HU-023-01:** La vacante guardada aparece en «Mis favoritos».
- [ ] **CA-HU-023-02:** La vacante oculta no vuelve a aparecer en la búsqueda del joven.
- [ ] **CA-HU-023-03:** Favoritos y ocultas se conservan al cerrar e iniciar sesión.

### Riesgos relacionados
TEC-006

### Casos de prueba relacionados
CP-015, CP-015B

---

# HU-024: Filtrado y exportación de postulantes
**Prioridad:** Could | **Estado:** Pendiente

**Como** reclutador,
**quiero** filtrar candidatos por habilidades y exportar la lista de postulantes,
**para** agilizar la preselección.

### Criterios de Aceptación
- [ ] **CA-HU-024-01:** La empresa filtra sus candidatos por habilidades.
- [ ] **CA-HU-024-02:** La exportación contiene solo los candidatos visibles en la consulta.

### Riesgos relacionados
NEG-005

### Casos de prueba relacionados
CP-033B (bloqueado hasta implementar)

---

# HU-025: Panel general de auditoría e indicadores
**Prioridad:** Should | **Estado:** Implementada

**Como** administrador,
**quiero** ver totales de usuarios, vacantes y postulaciones y la auditoría filtrada,
**para** conocer el uso y detectar acciones sospechosas.

### Criterios de Aceptación
- [ ] **CA-HU-025-01:** El panel muestra totales de usuarios, empresas, vacantes y postulaciones.
- [ ] **CA-HU-025-02:** La auditoría se filtra por módulo, acción, fecha y estado.

### Riesgos relacionados
OPE-001

### Casos de prueba relacionados
CP-022, CP-022B