# Subentorno de Analítica de Empleo — Youth Employment Bridge (YEB)

Este directorio (`analytics/`) aísla los flujos de ciencia de datos, perfilamiento de calidad e indicadores de empleabilidad juvenil respecto al código transaccional PHP del portal, garantizando reproducibilidad técnica (`TEC-007`) y protección de datos personales de candidatos menores de edad (`LEG-001`).

---

## 1. Estructura del Subentorno (`CA-ISS-01-01`)

```text
analytics/
├── data/
│   ├── raw/               # Extracciones crudas e inmutables (anonimizadas en origen)
│   └── processed/         # Datasets depurados y validados listos para análisis
├── docs/
│   └── matriz_preguntas_etica_yeb.md  # Matriz analítica y declaración ética (Ley 1581 / Ley 1098)
├── notebooks/
│   └── 01_verificar_entorno.ipynb     # Notebook de validación de rutas relativas y versiones
├── outputs/               # Reportes CSV de métricas, cohortes e incidencias de calidad
├── scripts/
│   └── 01_verificar_entorno.py        # Script CLI de verificación automatizada (CP-041 / CP-041B)
├── requirements.txt       # Dependencias base: pandas, numpy, jupyter, Faker, openpyxl
└── README.md              # Guía operativa del módulo analítico
```

---

## 2. Configuración Reproducible del Entorno

Desde la raíz del repositorio (`Youth-Employment-Bridge/`):

```powershell
# 1. Crear entorno virtual aislado dentro de analytics/.venv
py -m venv analytics/.venv

# 2. Activar el entorno virtual (PowerShell en Windows)
.\analytics\.venv\Scripts\Activate.ps1

# 3. Instalar dependencias reproducibles
pip install -r analytics/requirements.txt
```

---

## 3. Verificación del Entorno (`CA-ISS-01-02`)

Puedes validar las rutas relativas (`pathlib.Path`), versiones instaladas, matriz de preguntas y protocolo ético de dos maneras:

1. **Vía Script CLI (Automatizado para CI / Pruebas `CP-041` y `CP-041B`):**
   ```powershell
   python analytics/scripts/01_verificar_entorno.py
   ```
2. **Vía Jupyter Notebook:**
   ```powershell
   jupyter notebook analytics/notebooks/01_verificar_entorno.ipynb
   ```
