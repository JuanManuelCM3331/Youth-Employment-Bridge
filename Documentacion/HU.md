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

### Módulo de Analítica y Persistencia NoSQL

| Issue | Título | Prioridad | Estado |
| :-- | :-- | :-- | :-- |
| Issue #01 | [Data/Setup] Estructura reproducible para el módulo de analítica de empleo | Must | Implementada |
| Issue #02 | [NoSQL/Setup] Definición del catálogo de accesos analíticos y conexión a MongoDB | Must | Pendiente |
| Issue #03 | [NoSQL/Model] Modelado documental v1 de perfiles laborales y Snapshots históricos | Must | Pendiente |
| Issue #04 | [Data/Inspection] Extracción tabular e inspección exploratoria de postulaciones | Must | Pendiente |
| Issue #05 | [Data/Quality] Diagnóstico y tratamiento de las 6 dimensiones de calidad en el dataset de empleo | Must | Pendiente |
| Issue #06 | [NoSQL/CRUD] Operaciones CRUD aisladas y catálogo de 10 consultas para reclutamiento | Must | Pendiente |
| Issue #07 | [NoSQL/Schema] Contratos estrictos con $jsonSchema, índices únicos y migración idempotente | Must | Pendiente |
| Issue #08 | [Data/Filtering] Selección avanzada, máscaras booleanas e invariantes analíticos | Must | Pendiente |
| Issue #09 | [Data/Transform] Variables derivadas vectorizadas y cálculo de indicadores de empleabilidad | Must | Pendiente |
| Issue #10 | [NoSQL/Release] Consolidación y entrega de YEB Analytics v1.0 (P05/C05) | Must | Pendiente |

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


---

## Módulo de Analítica y Persistencia NoSQL

### Semana 1: Entorno de Analítica y Catálogo NoSQL

---

# Issue #01 [Data/Setup]: Estructura reproducible para el módulo de analítica de empleo
**Prioridad:** Must | **Estado:** Implementada

**Como** ingeniero de datos del equipo YEB,
**quiero** crear dentro del repositorio un subentorno estructurado en `analytics/` aislando datos crudos y procesados con protocolo ético,
**para** garantizar la reproducibilidad de los análisis de empleo juvenil y la protección de datos sensibles de candidatos menores de edad.

> **Contexto:** Las guías exigen aislar datos crudos y procesados con entornos reproducibles.
> **Descripción:** Crear dentro del repositorio un subentorno `analytics/` (o repositorio satélite) con carpetas `data/raw/`, `data/processed/`, `notebooks/`, `scripts/`, `outputs/` y `requirements.txt` (`pandas`, `numpy`, `jupyter`, `faker`).

### Criterios de Aceptación
- [x] **CA-ISS-01-01:** Subentorno `analytics/` creado con carpetas `data/raw/`, `data/processed/`, `notebooks/`, `scripts/`, `outputs/` y `requirements.txt` con librerías base (`pandas`, `numpy`, `jupyter`, `faker`).
- [x] **CA-ISS-01-02:** Script o notebook `notebooks/01_verificar_entorno.ipynb` validando rutas relativas (`pathlib.Path`) y versiones de dependencias instaladas.
- [x] **CA-ISS-01-03:** Matriz de preguntas analíticas iniciales formuladas sobre el dominio YEB: embudo de selección, tiempo promedio de contratación, tasa de postulaciones por vacante y brecha por ciudad.
- [x] **CA-ISS-01-04:** Declaración ética documentada sobre minimización y anonimización de datos sensibles de candidatos (protección especial para menores de 16–17 años).

### Riesgos relacionados
LEG-001, TEC-007

### Casos de prueba relacionados
CP-041, CP-041B, CP-041C

---

# Issue #02 [NoSQL/Setup]: Definición del catálogo de accesos analíticos y conexión a MongoDB
**Prioridad:** Must | **Estado:** Pendiente

**Como** arquitecto de datos de YEB,
**quiero** catalogar las consultas analíticas esenciales y establecer la conexión a la base `yeb_analytics_lab` en MongoDB,
**para** almacenar perfiles de candidatos dinámicos y eventos de navegación/búsqueda sin las restricciones rígidas de MySQL.

> **Contexto:** Evaluar y justificar el uso de bases documentales para almacenar eventos y datos no estructurados de YEB que en MySQL relacional resultan rígidos.
> **Descripción:** Justificar el uso de MongoDB para almacenar los perfiles analíticos de candidatos y el log de eventos de navegación/búsqueda de empleo.

### Criterios de Aceptación
- [ ] **CA-ISS-02-01:** Documento `docs/patrones_acceso_yeb.md` con 6 consultas esenciales en lenguaje natural (ej. vacantes activas por modalidad/salario, postulaciones pendientes por empresa, habilidades más demandadas por sector) justificando el modelo documental.
- [ ] **CA-ISS-02-02:** Configuración de la base `yeb_analytics_lab` en MongoDB local/Docker.
- [ ] **CA-ISS-02-03:** Script `01_explorar_yeb.js` insertando un documento de prueba tipado y verificando tipos BSON `Date`, `ObjectId` y subdocumentos.

### Riesgos relacionados
SEC-012, TEC-008

### Casos de prueba relacionados
CP-042, CP-042B, CP-042C

---

### Semana 2: Modelado Documental de Hojas de Vida y Extracción Tabular con Pandas

---

# Issue #03 [NoSQL/Model]: Modelado documental v1 de perfiles laborales y Snapshots históricos
**Prioridad:** Must | **Estado:** Pendiente

**Como** diseñador de base de datos de YEB,
**quiero** modelar las colecciones documentales con subdocumentos y el patrón Snapshot para postulaciones,
**para** evitar sobrepasar el límite BSON de 16 MiB y desacoplar cambios futuros de vacantes de las condiciones vigentes al momento de la postulación.

> **Contexto:** Evitar arreglos ilimitados de 16 MiB y desacoplar cambios presentes de registros históricos (técnica del *snapshot*).
> **Descripción:** Diseñar las colecciones documentales de YEB: `candidatos`, `vacantes` y `postulaciones_historial`.

### Criterios de Aceptación
- [ ] **CA-ISS-03-01:** Modelar subdocumentos 1:1 para el perfil profesional (habilidades, expectativas salariales) embebidos en el candidato.
- [ ] **CA-ISS-03-02:** Modelar colecciones independientes para el historial de postulaciones referenciando al candidato y la vacante para evitar sobrepasar límites BSON.
- [ ] **CA-ISS-03-03:** Implementar el **Patrón Snapshot**: al pasar una postulación al estado `interview` o definitivo (`accepted`/`rejected`), capturar un snapshot inmutable de los datos de la vacante (título, empresa, salario ofertado en ese instante) dentro de la postulación.
- [ ] **CA-ISS-03-04:** Semilla reproducible en Extended JSON (`candidatos.ejson`, `vacantes.ejson`, `postulaciones.ejson`) con verificación de 0 referencias rotas.

### Riesgos relacionados
NEG-006, TEC-009

### Casos de prueba relacionados
CP-043, CP-043B, CP-043C

---

# Issue #04 [Data/Inspection]: Extracción tabular e inspección exploratoria de postulaciones
**Prioridad:** Must | **Estado:** Pendiente

**Como** analista de datos de YEB,
**quiero** extraer datos anonimizados de vacantes y postulaciones hacia estructuras tabulares de Pandas y NumPy,
**para** realizar la inspección técnica de tipos, rangos salariales medios y tasas de avance a entrevista mediante operaciones vectorizadas.

> **Contexto:** Uso de NumPy y Pandas para transformar registros en estructuras tabulares e inspeccionar métricas iniciales.
> **Descripción:** Generar una extracción ficticia o dump anonimizado de la tabla `applications` y `jobs` de YEB a formatos CSV/Excel en `data/raw/`.

### Criterios de Aceptación
- [ ] **CA-ISS-04-01:** Extracción ficticia o dump anonimizado de la tabla `applications` y `jobs` de YEB a formatos CSV/Excel en `data/raw/`.
- [ ] **CA-ISS-04-02:** Notebook `notebooks/02_inspeccion_postulaciones.ipynb` leyendo datos con rutas relativas (`pathlib.Path`).
- [ ] **CA-ISS-04-03:** Inspección técnica documentada con `shape`, `columns`, `dtypes`, `info()` y `describe()`.
- [ ] **CA-ISS-04-04:** Operaciones vectorizadas en NumPy para calcular rangos salariales medios y tasas porcentuales de avance a entrevista.

### Riesgos relacionados
OPE-005, TEC-010

### Casos de prueba relacionados
CP-044, CP-044B

---

### Semana 3: Perfilamiento de Calidad de Datos y Operaciones CRUD Seguras

---

# Issue #05 [Data/Quality]: Diagnóstico y tratamiento de las 6 dimensiones de calidad en el dataset de empleo
**Prioridad:** Must | **Estado:** Pendiente

**Como** analista de calidad de datos de YEB,
**quiero** someter a perfilamiento un dataset con errores inducidos en candidatos y postulaciones,
**para** diagnosticar las 6 dimensiones de calidad y generar un dataset preparado con tratamiento auditado de duplicados y valores atípicos.

> **Contexto:** Detectar y tratar errores en el dataset (nulos, duplicados exactos vs. claves conflictivas) sin falsificar la evidencia.
> **Descripción:** Someter a perfilamiento un dataset sintético de candidatos y postulaciones de YEB con errores intencionales (salarios negativos, fechas inconsistentes, candidatos duplicados, ciudades mal tipadas).

### Criterios de Aceptación
- [ ] **CA-ISS-05-01:** Evaluación de las 6 dimensiones: Completitud (campos obligatorios de contacto), Unicidad (cédula única por rol), Validez (salarios no negativos, edades entre 16 y 24 años), Consistencia (modalidades 'Remoto'/'Híbrido'/'Presencial'), Oportunidad (postulaciones en ventana temporal válida) y Trazabilidad.
- [ ] **CA-ISS-05-02:** Tratamiento argumentado: descarte de duplicados exactos, aislamiento de identificadores en conflicto sin eliminarlos a ciegas, y conversión numérica segura (`pd.to_numeric(errors='coerce')`).
- [ ] **CA-ISS-05-03:** Generación de `data/processed/yeb_postulaciones_preparado.csv` y reporte de incidencias en `outputs/incidencias_calidad.csv`.

### Riesgos relacionados
NEG-007, TEC-011

### Casos de prueba relacionados
CP-045, CP-045B

---

# Issue #06 [NoSQL/CRUD]: Operaciones CRUD aisladas y catálogo de 10 consultas para reclutamiento
**Prioridad:** Must | **Estado:** Pendiente

**Como** desarrollador backend de YEB,
**quiero** construir scripts operativos en mongosh con actualizaciones atómicas y un catálogo de 10 consultas avanzadas sobre registros `TMP-*`,
**para** dotar al área de reclutamiento de búsquedas de candidatos y gestión de postulaciones atómica y paginada sin afectar datos de producción.

> **Contexto:** Implementar operaciones CRUD atómicas sin afectar registros de producción y realizar consultas con filtros complejos.
> **Descripción:** Construir scripts operativos en mongosh para simular la gestión de postulaciones y candidatos temporales (`TMP-*`).

### Criterios de Aceptación
- [ ] **CA-ISS-06-01:** Implementar operaciones atómicas con `$set` e `$inc` (por ejemplo, para incrementar el contador de postulaciones recibidas o intentos de contacto de un reclutador) sobre datos temporales `TMP-*`.
- [ ] **CA-ISS-06-02:** Catálogo de 10 consultas (C01–C10) con contraste entre valor esperado y obtenido documentado:
  - Filtros anidados en habilidades del candidato.
  - Evaluación de salarios con operadores de rango (`$gte`, `$lte`) y pertenencia (`$in`).
  - Uso de `$elemMatch` sobre arreglos de experiencia laboral previa o diagnósticos de habilidades.
  - Distinción entre `null` explícito y campos ausentes (ej. candidato menor sin teléfono fijo reportado).
  - Paginación ordenada comparando `skip/limit` frente a cursores por continuación (ID/fecha).

### Riesgos relacionados
OPE-006, SEC-013

### Casos de prueba relacionados
CP-046, CP-046B

---

### Semana 4: Validación con $jsonSchema, Limpieza en Cuarentena y Filtros Compuestos

---

# Issue #07 [NoSQL/Schema]: Contratos estrictos con $jsonSchema, índices únicos y migración idempotente
**Prioridad:** Must | **Estado:** Pendiente

**Como** administrador de bases de datos de YEB,
**quiero** establecer contratos estrictos mediante `$jsonSchema`, índices únicos e implementar una migración idempotente versionada,
**para** blindar la base documental frente a ofertas inválidas y permitir la evolución controlada del esquema sin interrupción del servicio.

> **Contexto:** Blindar la base de datos documental contra inserciones erróneas y gestionar evolución de esquemas sin caída de servicio.
> **Descripción:** Crear validadores a nivel de motor en colecciones de YEB y simular una migración de esquema versionada.

### Criterios de Aceptación
- [ ] **CA-ISS-07-01:** Validador `$jsonSchema` en nivel `strict` y acción `error` que exija estructura a las vacantes (salario mínimo <= salario máximo usando `$expr`, catálogo de modalidades `enum: ['Remoto', 'Híbrido', 'Presencial']` y longitud mínima en título).
- [ ] **CA-ISS-07-02:** Índice único compuesto sobre colecciones clave (unicidad de postulación `user_id` + `job_id`, o código único de vacante).
- [ ] **CA-ISS-07-03:** Flujo de limpieza: procesar registros de staging con errores, enviar a cuarentena los que no tengan soporte y cargar únicamente los limpios.
- [ ] **CA-ISS-07-04:** Matriz de 8 pruebas de validación (3 válidas aceptadas y 5 inválidas rechazadas por el motor).
- [ ] **CA-ISS-07-05:** Migración idempotente `M04_001` sobre copia de vacantes para pasar de `schema_version: 1` a `schema_version: 2` agregando `canal_publicacion: 'portal_web'`.

### Riesgos relacionados
NEG-002, NEG-003, TEC-012

### Casos de prueba relacionados
CP-047, CP-047B, CP-047C

---

# Issue #08 [Data/Filtering]: Selección avanzada, máscaras booleanas e invariantes analíticos
**Prioridad:** Must | **Estado:** Pendiente

**Como** analista de selección de YEB,
**quiero** segmentar cohortes de candidatos juveniles con filtros compuestos y validar invariantes con `assert`,
**para** priorizar candidatos aptos y certificar que ningún registro del subconjunto viole rangos legales de edad ni contenga inconsistencias lógicas.

> **Contexto:** Consultas reproducibles sobre el dataset preparado usando `.loc`, `.iloc`, `.query()` y validación lógica con `assert`.
> **Descripción:** Segmentar subconjuntos de candidatos juveniles para responder preguntas de negocio de YEB.

### Criterios de Aceptación
- [ ] **CA-ISS-08-01:** Extracción de cohortes mediante filtros compuestos: postulantes jóvenes (16–17 años marcados como menores frente a 18–24 años), con habilidades específicas (`.isin()`) y rangos salariales viables (`.between()`).
- [ ] **CA-ISS-08-02:** Implementación de consultas expresivas con `.query()` usando parámetros `@variable`.
- [ ] **CA-ISS-08-03:** Inclusión de aserciones lógicas (`assert`) que garanticen que ningún candidato del subconjunto final tenga datos contradictorios o fuera de los límites de edad permitidos en la plataforma.
- [ ] **CA-ISS-08-04:** Exportación del reporte a `outputs/candidatos_prioritarios_validados.csv` acompañado de su bitácora técnica.

### Riesgos relacionados
LEG-001, TEC-013

### Casos de prueba relacionados
CP-048, CP-048B, CP-048C

---

### Semana 5: Feature Engineering, Indicadores de Impacto y Entrega Integrada v1.0

---

# Issue #09 [Data/Transform]: Variables derivadas vectorizadas y cálculo de indicadores de empleabilidad
**Prioridad:** Must | **Estado:** Pendiente

**Como** analista de analítica de empleo de YEB,
**quiero** derivar variables vectorizadas y calcular indicadores consolidados con NumPy y Pandas,
**para** medir el embudo de conversión, tiempos de respuesta y clasificar la prioridad de atención de postulaciones con precedencia estricta.

> **Contexto:** Crear columnas derivadas auditables y calcular métricas con numerador y denominador rigurosamente definidos.
> **Descripción:** Enriquecer el dataset de postulaciones de YEB mediante transformaciones vectorizadas con NumPy y Pandas.

### Criterios de Aceptación
- [ ] **CA-ISS-09-01:** Creación de variables calculadas vectorizadas: `tiempo_respuesta_dias` (diferencia entre fecha de postulación y cambio a entrevista/cierre), y `brecha_salarial_aspiracion` frente a la oferta de la vacante.
- [ ] **CA-ISS-09-02:** Normalización controlada de títulos de cargos y ciudades mediante diccionarios y `.map()` sin inventar equivalencias.
- [ ] **CA-ISS-09-03:** Clasificación de prioridad de atención con `np.select()` aplicando precedencia explícita: 1° inconsistencias de identidad/edad > 2° postulaciones desactualizadas > 3° candidatos con perfil completo aptos para entrevista.
- [ ] **CA-ISS-09-04:** Reporte consolidado `outputs/metricas_empleabilidad_yeb.csv` con indicadores de conversión del embudo, tasa de rechazo y tiempo medio de respuesta.

### Riesgos relacionados
NEG-008, TEC-014

### Casos de prueba relacionados
CP-049, CP-049B, CP-049C

---

# Issue #10 [NoSQL/Release]: Consolidación y entrega de YEB Analytics v1.0 (P05/C05)
**Prioridad:** Must | **Estado:** Pendiente

**Como** líder de entrega del equipo de datos de YEB,
**quiero** consolidar y documentar la base de datos `yeb_analytics_p05` con verificación de tipos Extended JSON y versionado Git,
**para** empaquetar una solución analítica documental completamente reproducible por terceros y etiquetada como release v1.0.

> **Contexto:** Empaquetar la solución documental para que sea completamente reproducible por un tercero.
> **Descripción:** Consolidar los scripts de base de datos en la base `yeb_analytics_p05`.

### Criterios de Aceptación
- [ ] **CA-ISS-10-01:** Repositorio limpio con `README.md` que permita reconstruir el entorno MongoDB desde cero, cargar la semilla, aplicar contratos y ejecutar consultas sin pasos manuales ocultos.
- [ ] **CA-ISS-10-02:** Comprobación de integridad en exportación e importación Extended JSON canónico (`mongoexport` / `mongoimport`), demostrando la preservación de tipos `Int32`, `Decimal128` y `Date`.
- [ ] **CA-ISS-10-03:** Bitácora de incidencias cerradas y etiquetado final de la versión en Git mediante `git tag v1.0`.

### Riesgos relacionados
OPE-007, TEC-015

### Casos de prueba relacionados
CP-050, CP-050B, CP-050C
