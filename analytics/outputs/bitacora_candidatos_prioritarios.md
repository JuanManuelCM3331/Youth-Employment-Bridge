# Bitácora técnica — Candidatos prioritarios validados (Issue #08)

Archivo generado por `analytics/scripts/08_seleccionar_candidatos_prioritarios.py`. Acompaña a
`outputs/candidatos_prioritarios_validados.csv` y se regenera en cada ejecución.

## Fuente y parámetros

| Parámetro | Valor |
| :-- | :-- |
| Dataset de entrada | `data/processed/yeb_postulaciones_preparado.csv` (93 postulaciones, salida del Issue #05) |
| Rango de edad de la plataforma | 16 a 24 años |
| Estados considerados activos | submitted, under_review |
| Habilidades demandadas | Atención al Cliente, Canva, Comunicación Asertiva, Control de Inventarios, Excel, Facturación Electrónica, Git, HTML/CSS, JavaScript, Python, SQL (tomadas de `habilidades_requeridas` en `data/seeds/vacantes.ejson`) |
| Salario viable | `expected_salary_cop` entre `salary_min_offered` y `salary_max_offered` (`.between()`, inclusivo) |
| Completitud mínima del perfil | 80 % |
| Regla para menores | Solo entran si `minor_permit_verified = True` (LEG-001) |

## Cohortes de edad

| Cohorte | Registros en el dataset | Priorizados |
| :-- | :-: | :-: |
| Menor protegido (16-17) | 21 | 4 |
| Joven adulto (18-24) | 72 | 6 |

## Embudo de filtros (aplicados en orden acumulado)

| Paso | Registros que quedan |
| :-- | :-: |
| Dataset preparado | 93 |
| Estado activo (.isin) | 38 |
| Habilidad demandada (.isin) | 29 |
| Aspiración dentro del rango ofertado (.between) | 17 |
| Perfil completo >= 80 % | 10 |
| Menor solo con permiso verificado (LEG-001) | 10 |

La selección con máscaras booleanas y la selección con `.query()` devolvieron exactamente los
mismos 10 registros (mismo índice), lo que descarta errores de precedencia en los
operadores `&`, `|` y `~` (TEC-013).

## Invariantes verificados con `assert` sobre el subconjunto final

| # | Invariante | Estado |
| :-: | :-- | :-- |
| 1 | Edad dentro de 16-24 años | Cumple |
| 2 | is_protected_minor coherente con la edad | Cumple |
| 3 | Todo menor priorizado tiene permiso verificado (LEG-001) | Cumple |
| 4 | Ningún mayor de edad tiene bandera de permiso de menor | Cumple |
| 5 | salary_min_offered <= salary_max_offered | Cumple |
| 6 | expected_salary_cop dentro del rango ofertado | Cumple |
| 7 | job_published_at <= applied_at <= updated_at | Cumple |
| 8 | application_id y candidate_hash únicos | Cumple |
| 9 | Solo postulaciones activas (submitted / under_review) | Cumple |
| 10 | Habilidad principal demandada por las vacantes | Cumple |
| 11 | Sin columnas de contacto directo (minimización) | Cumple |

## Prueba con registros inconsistentes forzados (CP-048B)

| Registro forzado | Pipeline | Mensaje de la aserción |
| :-- | :-- | :-- |
| Candidato de 17 años sin bandera is_protected_minor | ABORTÓ | Bandera is_protected_minor contradice la edad declarada |
| Candidato de 16 años sin permiso de trabajo verificado | ABORTÓ | LEG-001: menor de edad sin permiso de trabajo verificado en la lista de priorizados |
| Edad fuera de la plataforma (27 años) | ABORTÓ | Edad fuera del rango permitido en la plataforma (16-24) |
| Postulación anterior a la publicación de la vacante | ABORTÓ | Cronología imposible: postulación antes de la publicación o actualización antes de la postulación |

## Notas

- El script se niega a correr con `python -O`, porque esa opción elimina las sentencias `assert`.
- El reporte no incluye correo ni teléfono: se identifica a cada joven solo por `candidate_hash`.
- En el dataset preparado todos los menores tienen permiso verificado, por eso ninguno se excluye
  por la regla LEG-001; la prueba forzada demuestra que, si apareciera uno sin permiso, el pipeline
  se detiene en lugar de exportarlo.
