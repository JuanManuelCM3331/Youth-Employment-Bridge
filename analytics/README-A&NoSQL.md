# Subentorno de Analítica de Empleo y Persistencia NoSQL — Youth Employment Bridge (YEB)

Este directorio (`analytics/`) aísla los flujos de ciencia de datos, perfilamiento de calidad, persistencia documental en MongoDB (`yeb_analytics_lab`) e indicadores de empleabilidad juvenil respecto al código transaccional PHP del portal, garantizando reproducibilidad técnica (`TEC-007`), tipado BSON estricto (`TEC-008`) y protección de datos personales de candidatos menores de edad (`LEG-001`).

---

## 1. Estructura del Subentorno (`Issue #01` e `Issue #02`)

```text
analytics/
├── data/
│   ├── raw/               # Extracciones crudas e inmutables (anonimizadas en origen)
│   └── processed/         # Datasets depurados y validados listos para análisis
├── docs/
│   ├── matriz_preguntas_etica_yeb.md  # Matriz analítica y declaración ética (Issue #01)
│   └── patrones_acceso_yeb.md         # Catálogo de 6 consultas esenciales y justificación NoSQL (Issue #02)
├── notebooks/
│   └── 01_verificar_entorno.ipynb     # Notebook de validación de rutas relativas y versiones
├── outputs/               # Reportes CSV de métricas, cohortes e incidencias de calidad
├── scripts/
│   ├── 01_verificar_entorno.py        # Script CLI de verificación de entorno Python (CP-041 / CP-041B)
│   ├── 01_explorar_yeb.js             # Script mongosh de conexión y tipado BSON en yeb_analytics_lab (Issue #02)
│   └── 02_verificar_nosql_setup.py    # Runner automatizado para verificar Issue #02 (CP-042 / CP-042B / CP-042C)
├── requirements.txt       # Dependencias base: pandas, numpy, jupyter, Faker, openpyxl
└── README.md              # Guía operativa del módulo analítico y NoSQL
```

---

## 2. Configuración Reproducible del Entorno Python (`Issue #01`)

Desde la raíz del repositorio (`Youth-Employment-Bridge/`):

```powershell
# 1. Crear entorno virtual aislado dentro de analytics/.venv
py -m venv analytics/.venv

# 2. Activar el entorno virtual (PowerShell en Windows)
.\analytics\.venv\Scripts\Activate.ps1

# 3. Instalar dependencias reproducibles
pip install -r analytics/requirements.txt

# 4. Verificar entorno (Issue #01)
python analytics/scripts/01_verificar_entorno.py
```

---

## 3. Exploración NoSQL y Verificación BSON en MongoDB (`Issue #02`)

1. **Con servidor MongoDB local o contenedor Docker (`yeb_mongo`):**
   ```powershell
   docker compose up -d mongodb
   mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/01_explorar_yeb.js
   ```
2. **Verificación automatizada completa (`CP-042`, `CP-042B`, `CP-042C`):**
   ```powershell
   python analytics/scripts/02_verificar_nosql_setup.py
   ```
