# Matriz de Preguntas Analíticas y Declaración Ética de Datos — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` | **Issue:** #01 [Data/Setup] | **Criterios cubiertos:** `CA-ISS-01-03`, `CA-ISS-01-04`  
**Riesgos mitigados:** `LEG-001`, `TEC-007` | **Casos de prueba:** `CP-041C`

---

## 1. Matriz de Preguntas Analíticas Iniciales del Dominio YEB (`CA-ISS-01-03`)

Esta matriz define las preguntas estratégicas de negocio para evaluar la eficiencia de inserción laboral juvenil (16 a 24 años) en la plataforma **Youth Employment Bridge (YEB)**, vinculando cada pregunta con las entidades del modelo de datos (`users`, `companies`, `jobs`, `applications`) y su definición matemática auditable.

| ID | Eje Analítico | Pregunta de Negocio | Entidades y Atributos Fuente | Indicador / Fórmula Operativa | Decisión de Negocio que Habilita |
| :-- | :-- | :-- | :-- | :-- | :-- |
| **PA-01** | **Embudo de Selección** (*Conversion Funnel*) | ¿Cuál es la tasa de conversión y deserción de los candidatos jóvenes en cada etapa del proceso de selección (`pending` → `review` → `interview` → `accepted` vs. `rejected`)? | `applications.status`, `applications.job_id`, `applications.user_id`, `users.is_minor` | $\text{Tasa Etapa } k = \frac{\text{Postulaciones que alcanzan etapa } k}{\text{Total postulaciones válidas}} \times 100$ | Identificar cuellos de botella donde los reclutadores descartan o estancan postulaciones juveniles. |
| **PA-02** | **Tiempo Promedio de Contratación / Respuesta** (*Time-to-Hire / Response*) | ¿Cuántos días transcurren en promedio desde que un joven se postula hasta que pasa a entrevista (`interview`) o recibe resolución definitiva (`accepted` / `rejected`)? | `applications.created_at`, timestamp de cambio de estado en `audit_logs` / historial documental, `companies.industry`, `jobs.work_mode` | $\overline{T}_{\text{resp}} = \frac{1}{N} \sum_{i=1}^{N} (\text{ fecha\_transición}_i - \text{fecha\_postulación}_i )$ | Detectar ofertas o empresas con postulaciones desactualizadas para activar alertas de gestión oportuna. |
| **PA-03** | **Tasa de Postulaciones por Vacante** (*Application Rate per Job*) | ¿Cuál es la presión de demanda (promedio y mediana de postulaciones recibidas) por cada vacante publicada según nivel de experiencia y modalidad? | `jobs.id`, `jobs.title`, `jobs.work_mode` (`Remote`, `Hybrid`, `Presencial`), `jobs.experience`, `applications.id` | $\text{Tasa Postulación}_j = \frac{\text{Conteo}(applications \text{ en } job_j)}{\text{Vacante activa } j}$ | Equilibrar la visibilidad de vacantes con baja tracción frente a ofertas saturadas de nivel inicial. |
| **PA-04** | **Brecha Territorial y Salarial por Ciudad** (*Geographic & Salary Gap*) | ¿Qué brecha existe entre la oferta de vacantes/salarios publicados en cada ciudad y la ubicación/expectativa salarial de los candidatos jóvenes (16–17 vs. 18–24 años)? | `jobs.city`, `jobs.salary_min`, `jobs.salary_max`, `users.location`, `users.skills`, expectativa salarial en perfil | $\text{Brecha Ciudad}_c = \frac{\text{Candidatos activos}_c}{\text{Vacantes publicadas}_c}$ y $\Delta W = W_{\text{aspirado}} - W_{\text{ofertado}}$ | Orientar alianzas con empresas locales en ciudades con déficit de ofertas o desajuste salarial juvenil. |

---

## 2. Declaración Ética: Minimización y Anonimización de Datos Sensibles (`CA-ISS-01-04`)

### 2.1 Marco Legal y Principios Rectores
El procesamiento analítico en **Youth Employment Bridge (YEB)** se rige estrictamente por:
1. **Ley Estatutaria 1581 de 2012** y **Decreto 1377 de 2013** (Régimen General de Protección de Datos Personales en Colombia): principios de finalidad, libertad, veracidad, transparencia, acceso y circulación restringida, seguridad y confidencialidad.
2. **Ley 1098 de 2006 (Código de la Infancia y la Adolescencia, Arts. 35 y 113)**: protección especial para adolescentes entre **15 y 17 años**, quienes requieren autorización del Inspector de Trabajo y consentimiento expreso de sus representantes legales para ejercer actividades laborales.
3. **Mitigación del Riesgo `LEG-001`**: ningún flujo de ciencia de datos, notebook exploratorio o exportación CSV/JSON puede exponer identidad civil ni soportes documentales de menores de edad.

### 2.2 Política de Minimización de Datos (Exclusión en Origen)
Al extraer datos desde el motor transaccional (`yeb_portal`) o documental (`yeb_analytics_lab`) hacia `analytics/data/raw/` y `analytics/data/processed/`, **queda estrictamente prohibido** exportar los siguientes atributos:

| Tabla / Colección Origen | Atributos Excluidos por Minimización | Justificación Ética y Técnica |
| :-- | :-- | :-- |
| `users` | `password`, `cv_data`, `cv_path`, `profile_photo_data`, `id_scan_data`, `id_scan_path` | Credenciales de autenticación y binarios biométricos/documentales innecesarios para analítica estadística. |
| `user_minor_documents` | `civil_registry_data`, `parent_id_data`, `authorization_letter_data` y sus rutas asociadas | Documentos legales altamente sensibles de menores de edad y sus padres/tutores; solo se extrae el booleano derivado de validación (`minor_authorized`). |
| `push_subscriptions` | `endpoint`, `p256dh`, `auth`, `device_token` | Secretos criptográficos de notificaciones push de dispositivos personales. |
| `messages` | `message` (cuerpo textual libre de conversaciones privadas) | Privacidad de las comunicaciones entre candidato y reclutador; solo se admiten métricas agregadas de tiempos de respuesta si se requieren. |

### 2.3 Protocolo de Anonimización y Seudonimización Reproducible
Para los atributos identificadores necesarios en las pruebas de unicidad y trazabilidad (p. ej. `Issue #05`), se aplica el siguiente tratamiento antes de persistir en `data/raw/` o `data/processed/`:

1. **Identificador de Usuario (`user_id` / `candidate_hash`)**:
   - Se conserva el ID numérico interno o se transforma mediante función hash criptográfica con sal de entorno (`SHA-256` truncado a 12 caracteres hexadecimales, ej. `CAND-8f3a19c04b2e`).
2. **Documento de Identidad (`cedula` / `tarjeta_identidad`)**:
   - Nunca se almacena el número real de documento de identidad en los datasets analíticos. Para ejercicios de diagnóstico de calidad y unicidad (`Issue #05`), se emplean identificadores sintéticos generados con `Faker` bajo semilla fija (`Faker.seed(42)`) con prefijo `DOC-SYN-*`.
3. **Datos de Contacto (`name`, `email`, `phone`)**:
   - Sustituidos íntegramente por valores sintéticos (`Faker('es_CO')`) o indicadores booleanos de completitud (`has_valid_email`, `has_phone`), impidiendo la re-identificación directa o indirecta de cualquier joven.
4. **Fecha de Nacimiento (`birth_date`) → Generalización en Cohortes (`age` y `age_group`)**:
   - En los reportes finales (`outputs/`), la fecha exacta de nacimiento se generaliza a edad entera cumplida (`16` a `24` años) y cohorte etaria:
     - `Menor Protegido (16-17)`
     - `Joven Adulto (18-24)`

### 2.4 Salvaguardas Especiales para Menores de Edad (16–17 años)
- **Verificación de Estado Legal (`is_minor = 1` y `minor_blocked = 0`)**: todo análisis de priorización o preselección de candidatos (`Issue #08` e `Issue #09`) debe verificar mediante aserciones (`assert`) que ningún candidato menor de 18 años con `minor_blocked = 1` (sin autorización parental/administrativa aprobada) sea incluido en listas de candidatos aptos para entrevista.
- **Umbral Mínimo Legal**: cualquier registro con edad inferior a 16 años detectado durante el perfilamiento (`Issue #05`) se clasifica automáticamente como **Incidencia Crítica de Validez Legal** y es enviado a cuarentena (`outputs/incidencias_calidad.csv`).
- **No divulgación individual**: los indicadores de empleabilidad de menores de 16–17 años solo se publican de forma agregada por ciudad o modalidad cuando el grupo cuenta con al menos $k \ge 5$ registros ($k$-anonimato básico).
