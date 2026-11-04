# Catálogo de Patrones de Acceso Analítico y Justificación NoSQL — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Persistencia NoSQL) | **Issue:** `#02 [NoSQL/Setup]` | **Criterio cubierto:** `CA-ISS-02-01`  
**Base de datos objetivo:** `yeb_analytics_lab` (MongoDB) | **Riesgos mitigados:** `SEC-012`, `TEC-008` | **Caso de prueba:** `CP-042C`

---

## 1. Justificación Arquitectónica: ¿Por qué MongoDB frente a MySQL en YEB?

En el núcleo transaccional de **Youth Employment Bridge (`yeb_portal` en MySQL)**, las tablas relacionales (`users`, `companies`, `jobs`, `applications`) garantizan integridad ACID en el registro de cuentas, control de sesiones y autorizaciones legales de menores (`user_minor_documents`). Sin embargo, la evolución analítica y funcional del portal presenta **tres cuellos de botella estructurales** en el modelo relacional:

### 1.1 Rigidez en Perfiles Laborales Juveniles Heterogéneos (`users`)
En `database/schema.sql`, los atributos profesionales del joven (`skills`, `experience`, `education`) están almacenados como columnas `TEXT` planas:
- Un candidato de 16–17 años suele registrar proyectos escolares, voluntariados, insignias digitales o diagnósticos vocacionales con atributos variables.
- Un candidato de 18–24 años registra prácticas técnicas, certificaciones, portafolios y niveles de dominio por competencia (`basico`, `intermedio`, `avanzado`).
- En MySQL, consultar por habilidades con nivel de dominio o experiencia previa exige múltiples tablas puente (`user_skills`, `user_experiences`, `user_educations`) con costosos `JOIN` o búsquedas `LIKE '%texto%'` no indexables. En **MongoDB**, el perfil profesional se modela como un **subdocumento 1:1 rico y polimórfico** con arreglos tipados e indexación multikey nativa.

### 1.2 Mutabilidad Histórica de las Vacantes vs. Inmutabilidad de la Postulación (`applications` y `jobs`)
En el modelo relacional, `applications` solo almacena `(id, job_id, user_id, status, created_at)`. Si un reclutador edita el salario (`salary_min`, `salary_max`) o la modalidad (`work_mode`) de la vacante en `jobs` después de que el joven se postuló, se pierde la trazabilidad de **bajo qué condiciones económicas se postuló o fue entrevistado el candidato**. En **MongoDB**, el documento de postulación permite incrustar un **Snapshot inmutable** del estado exacto de la vacante al pasar a entrevista (`interview`) o estado definitivo (`accepted` / `rejected`).

### 1.3 Ingesta de Eventos de Navegación y Telemetría de Búsqueda
Cada búsqueda en el portal (filtros por ciudad, modalidad `Remote`/`Hybrid`/`Presencial`, rango salarial, clics en detalle de oferta, vacantes guardadas u ocultas) genera eventos semi-estructurados de alta frecuencia con metadatos variables según el dispositivo y los filtros aplicados. Almacenar este flujo en MySQL degrada el rendimiento transaccional; en **MongoDB (`yeb_analytics_lab`)**, los eventos de telemetría se insertan sin bloqueos de esquema y se agregan directamente mediante el *Aggregation Framework*.

### 1.4 Comparativa Sintética: Relacional (MySQL) vs. Documental (MongoDB)

| Dimensión en YEB | MySQL (`yeb_portal`) | MongoDB (`yeb_analytics_lab`) |
| :-- | :-- | :-- |
| **Habilidades y experiencia del candidato** | Columnas `TEXT` sin estructura interna o múltiples `JOIN` 1:N. | Subdocumento `perfil_profesional` embebido con arreglos indexables (`$elemMatch`). |
| **Auditoría de oferta al postularse** | Referencia simple `job_id`; vulnerable a ediciones posteriores de la vacante. | Patrón *Snapshot* embebido en la postulación con salario y modalidad congelados. |
| **Log de eventos de búsqueda y filtros** | Requiere columnas `JSON` sin validación granular o tablas dispersas. | Colección de eventos polimórficos con validación `$jsonSchema` e índices compuestos. |
| **Protección de menores (16–17 años)** | Soportes binarios `LONGBLOB` en `user_minor_documents`. | Metadatos de verificación legal desacoplados de binarios pesados (cumpliendo minimización `LEG-001`). |

---

## 2. Catálogo de 6 Consultas Analíticas y Operativas Esenciales (`CA-ISS-02-01`)

A continuación se especifican las **6 consultas esenciales en lenguaje natural** que guían el diseño de colecciones, subdocumentos e índices en `yeb_analytics_lab`:

---

### Consulta Esencial #01 — Vacantes activas filtradas por modalidad y banda salarial viable
* **Formulación en lenguaje natural:**
  > *«Obtener todas las vacantes actualmente publicadas (`status = 'published'`) en una ciudad específica (p. ej. Medellín o Bogotá) cuya modalidad de trabajo sea `Remoto` o `Híbrido`, y cuyo rango salarial ofertado garantice al menos un salario mínimo juvenil esperado (ej. `salario_max >= 1.400.000 COP`), ordenadas desde la publicación más reciente.»*
* **Actor consumidor:** Candidato joven (16–24 años) y motor de recomendación del buscador.
* **Colección objetivo:** `vacantes`
* **Patrón de acceso documental:** Filtro compuesto por igualdad (`estado`), pertenencia (`modalidad: { $in: [...] }`), comparación numérica (`rango_salarial.maximo: { $gte: ... }`) y ordenamiento descendente por `fecha_publicacion` (`Date`).

---

### Consulta Esencial #02 — Postulaciones pendientes y en revisión por empresa con antigüedad crítica
* **Formulación en lenguaje natural:**
  > *«Listar todas las postulaciones activas que se encuentran en estado `pending` o `review` asociadas a una empresa determinada, identificando aquellas que llevan más de 7 días calendario sin respuesta del reclutador, incluyendo el resumen del candidato y el título de la vacante.»*
* **Actor consumidor:** Reclutador de empresa y panel de alertas operativas de YEB.
* **Colección objetivo:** `postulaciones_historial`
* **Patrón de acceso documental:** Filtro por `empresa_id` (`ObjectId` / código), estado en conjunto `['pending', 'review']` y comparación temporal sobre `fecha_postulacion` (`Date` menor o igual al umbral de 7 días atrás) para priorizar atención oportuna.

---

### Consulta Esencial #03 — Habilidades técnicas y blandas más demandadas por sector industrial
* **Formulación en lenguaje natural:**
  > *«Calcular el top 10 de habilidades (`habilidades_requeridas`) más solicitadas en las vacantes publicadas durante el último trimestre, agrupadas por el sector económico de la empresa ofertante (Tecnología, Comercio, Servicios, Logística) y calculando el salario promedio ofertado para cada habilidad.»*
* **Actor consumidor:** Equipo de analítica de empleabilidad de YEB e instituciones educativas aliadas.
* **Colección objetivo:** `vacantes`
* **Patrón de acceso documental:** Pipeline de agregación con desenvoltura de arreglos (`$unwind` sobre `habilidades_requeridas`), agrupación (`$group`) por `empresa.sector` y `habilidad`, y cálculo de promedio salarial (`$avg`) y conteo (`$sum: 1`).

---

### Consulta Esencial #04 — Preselección de candidatos jóvenes por combinación de habilidad, nivel y expectativa salarial
* **Formulación en lenguaje natural:**
  > *«Buscar candidatos activos cuya expectativa salarial máxima esté dentro del presupuesto de la vacante (ej. entre `1.300.000` y `1.800.000 COP`), que posean al menos una habilidad específica (p. ej. `Python`, `Atención al Cliente` o `Excel`) con nivel `intermedio` o `avanzado`, y que si son menores de edad (16–17 años) cuenten con autorización legal verificada (`menor_autorizado = true`).»*
* **Actor consumidor:** Reclutador en proceso de preselección (*shortlisting*).
* **Colección objetivo:** `candidatos`
* **Patrón de acceso documental:** Consulta sobre subdocumento embebido `perfil_profesional` usando `$elemMatch` en el arreglo `habilidades` (`nombre` + `nivel`), operador de rango `$gte`/`$lte` sobre `perfil_profesional.expectativa_salarial`, y validación booleana de cumplimiento legal `proteccion_menor.autorizado`.

---

### Consulta Esencial #05 — Auditoría histórica de condiciones salariales mediante Snapshot en entrevistas y contrataciones
* **Formulación en lenguaje natural:**
  > *«Recuperar el historial de postulaciones de un candidato o de una empresa que hayan alcanzado el estado `interview`, `accepted` o `rejected`, contrastando el salario y modalidad capturados en el `snapshot_vacante` en el instante de la decisión frente a las condiciones actuales de la vacante.»*
* **Actor consumidor:** Administrador de auditoría de YEB y analista de equidad salarial.
* **Colección objetivo:** `postulaciones_historial`
* **Patrón de acceso documental:** Lectura directa de un único documento sin necesidad de `JOIN` gracias al subdocumento inmutable `snapshot_vacante` (`titulo`, `empresa_nombre`, `modalidad`, `salario_min_ofertado`, `salario_max_ofertado`, `fecha_captura`), garantizando trazabilidad histórica exacta.

---

### Consulta Esencial #06 — Análisis de brecha de búsqueda e intención laboral juvenil por ciudad (Log de Eventos)
* **Formulación en lenguaje natural:**
  > *«Analizar los eventos de búsqueda y filtrado ejecutados por los jóvenes en los últimos 30 días para identificar qué combinaciones de ciudad, modalidad (`Remoto`, `Híbrido`, `Presencial`) y palabra clave devuelven cero o muy pocos resultados (`resultados_encontrados <= 2`), revelando demanda laboral juvenil insatisfecha.»*
* **Actor consumidor:** Dirección del programa Youth Employment Bridge para captación de nuevas empresas.
* **Colección objetivo:** `eventos_busqueda` (o telemetría analítica en `yeb_analytics_lab`)
* **Patrón de acceso documental:** Filtro sobre subdocumento `filtros_aplicados` y métrica `resultados_encontrados`, agrupando por `filtros_aplicados.ciudad` y `filtros_aplicados.modalidad` para cuantificar la brecha entre búsquedas de jóvenes y vacantes disponibles.

---

## 3. Resumen de Trazabilidad del Catálogo

| Código | Consulta Esencial | Colección Principal | Operadores / Patrón NoSQL Clave |
| :-- | :-- | :-- | :-- |
| **CE-01** | Vacantes activas por modalidad y salario | `vacantes` | `$in`, `$gte`, índice compuesto `(ciudad, modalidad, estado)` |
| **CE-02** | Postulaciones pendientes por empresa y antigüedad | `postulaciones_historial` | Comparación BSON `Date`, filtro por `empresa_id` y `estado` |
| **CE-03** | Habilidades más demandadas por sector industrial | `vacantes` | Aggregation Pipeline: `$match` → `$unwind` → `$group` → `$sort` |
| **CE-04** | Candidatos por habilidad/nivel, salario y estatus legal | `candidatos` | Subdocumento 1:1 + `$elemMatch` en arreglo + invariante `LEG-001` |
| **CE-05** | Auditoría de condiciones pactadas en postulación | `postulaciones_historial` | **Patrón Snapshot** embebido (`snapshot_vacante`) |
| **CE-06** | Brecha de búsquedas sin resultados por ciudad/modalidad | `eventos_busqueda` | Documentos polimórficos de eventos + agrupación por filtros |
