# Plan de Pruebas - Youth Employment Bridge (YEB)

**Versión:** 2.0 | **Fecha:** 19/10/2026 | **Documentos relacionados:** [HU.md](HU.md), [RIESGOS.md](RIESGOS.md)

## 1. Objetivo y alcance

Verificar que el prototipo cumple los criterios de aceptación de las historias de usuario y que los controles de seguridad declarados en el README funcionan.

- **Dentro del alcance:** HU-001 a HU-025, controles de seguridad (CSRF, roles, descarga de CV, SQL injection, XSS), diseño responsivo básico.
- **Funciones pendientes:** HU-019, HU-021, HU-022 y HU-024 no existen en el prototipo. Sus casos están **Bloqueados** y no cuentan como fallos. CP-040 (cuenta suspendida) también está bloqueado.
- **Fuera del alcance:** pruebas de carga reales, pruebas de penetración, accesibilidad formal.

## 2. Estrategia

| Nivel | Tipo | Herramienta |
| :-- | :-- | :-- |
| Chat (servicio) | Automatizada | `tests/ChatTest.php` (`php tests\ChatTest.php`) |
| Funcional y de aceptación | Manual (M) | Navegador, cuentas demo |
| Seguridad básica | Manual (M) | Navegador y herramientas de desarrollo (DevTools) |

Recomendación: automatizar a continuación los casos de prioridad Alta de Auth, Postulaciones y CV con el mismo estilo de `ChatTest.php`.

## 3. Entorno y datos de prueba

- XAMPP con PHP 8.2 y MySQL, base `yeb_portal` recién creada con `schema.sql`, `migrate_chat.sql` y `seed.sql`.
- Cuentas demo: candidato `ana@yeb.test`, reclutador `laura@talentolab.test`, administrador `admin@yeb.test` (contraseña `password`).
- Se necesita un segundo reclutador de **otra empresa** (registrarlo con CP-003) para las pruebas de acceso cruzado, y un segundo candidato.
- Archivos de prueba: `cv.pdf` (1 MB), `cv.docx`, `grande.pdf` (mayor a 5 MB), `script.exe` renombrado y `foto.png`.

## 4. Criterios de entrada y salida

- **Entrada:** base de datos cargada, login funcionando, cuentas demo disponibles.
- **Salida:** todos los casos de prioridad Alta ejecutados; ningún defecto de severidad Alta abierto (hoy: DEF-001, DEF-002); los fallos restantes registrados en RIESGOS.md con su defecto asociado.

## 5. Leyenda

- **Tipo:** M manual, A automatizada.
- **Prioridad:** A alta, M media, B baja.
- **Estado inicial:** *Pendiente* (por ejecutar), *Falla esperada* (hay un defecto confirmado en el código: el caso debe fallar hasta corregirlo), *Bloqueado* (la función no existe).
- Al ejecutar cada caso se anota resultado (Pasa/Falla), fecha, responsable y evidencia.

## 6. Casos de prueba

### 6.1 Autenticación y registro

| ID | Cubre | Tipo | Prior. | Pasos | Resultado esperado | Estado inicial |
| :-- | :-- | :-: | :-: | :-- | :-- | :-- |
| CP-001 | HU-001 CA-01 | M | A | 1. Abrir registro de candidato.<br>2. Ingresar nombre, correo nuevo y contraseña de 8+ caracteres.<br>3. Enviar. | Cuenta creada con rol `candidate`; se puede iniciar sesión. | Pendiente |
| CP-002 | HU-001 CA-02, HU-002 CA-02 | M | A | 1. Registrar con `ana@yeb.test`. | Rechazo con aviso de correo ya registrado; no se crea un segundo usuario. | Pendiente |
| CP-002B | HU-001 CA-03 | M | A | 1. Registrar con contraseña de 6 caracteres (`abc123`). | Rechazo por contraseña corta (mínimo 8). | Falla esperada (DEF-005) |
| CP-003 | HU-002, HU-013 | M | A | 1. Abrir registro de empresa.<br>2. Completar datos del reclutador y la empresa.<br>3. Enviar e iniciar sesión. | Se crean empresa y reclutador vinculados; el reclutador ve el panel de empresa. | Pendiente |
| CP-004 | HU-003 CA-01 | M | A | 1. Iniciar sesión con cada cuenta demo. | Cada rol llega a su panel (candidato, empresa, administrador). | Pendiente |
| CP-004B | HU-003 CA-02 | M | A | 1. Login con correo existente y contraseña errónea.<br>2. Login con correo inexistente. | Mismo mensaje genérico en ambos casos; nunca se indica cuál dato falló. Se audita `login_failed`. | Pendiente |
| CP-026 | HU-001 CA-01 | M | M | 1. Registrar un usuario.<br>2. Consultar `users.password` en MySQL. | El valor es un hash Bcrypt (`$2y$...`), nunca la contraseña en claro. | Pendiente |
| CP-037 | HU-003, SEC-004 | M | A | 1. Iniciar sesión.<br>2. DevTools, Application, Cookies: revisar `PHPSESSID`. | Cookie con `HttpOnly`, `SameSite` y `Secure` (bajo HTTPS). | Falla esperada (SEC-004: sin configurar) |
| CP-038 | HU-003 CA-03 | M | M | 1. Intentar login con contraseña errónea 6 veces seguidas.<br>2. Intentar con la correcta. | A partir del 5.º intento la cuenta se bloquea temporalmente. | Falla esperada (SEC-005: sin límite) |
| CP-031 | HU-019 | M | B | Solicitar recuperación y usar el enlace dos veces. | Enlace de un solo uso; mismo mensaje exista o no el correo. | Bloqueado (HU-019) |
| CP-039 | HU-021 | M | A | Registrar a una persona menor de 18 años sin autorización. | Registro rechazado; con política aceptada y autorización queda registrada con fecha y versión. | Bloqueado (HU-021) |
| CP-040 | HU-020 CA-03 | M | M | Suspender una cuenta y tratar de iniciar sesión. | Acceso denegado con mensaje claro. | Bloqueado (no existe suspensión) |

### 6.2 Perfil, hoja de vida y empresa

| ID | Cubre | Tipo | Prior. | Pasos | Resultado esperado | Estado inicial |
| :-- | :-- | :-: | :-: | :-- | :-- | :-- |
| CP-007 | HU-006, HU-011 | M | M | 1. Como Ana, editar titular, habilidades, bio y foto.<br>2. Guardar y recargar. | Los datos persisten y se muestran actualizados. | Pendiente |
| CP-008 | HU-007 CA-01 | M | A | 1. Subir `cv.pdf` y luego `cv.docx` (menores de 5 MB). | Ambos se aceptan; el perfil muestra la hoja de vida cargada. | Pendiente |
| CP-008B | HU-007 CA-02 | M | A | 1. Subir `grande.pdf` (más de 5 MB).<br>2. Subir `script.exe` renombrado. | Ambos se rechazan con mensaje claro; no queda ningún archivo guardado. | Pendiente |
| CP-008C | HU-007 | M | M | 1. Subir un segundo CV sobre uno existente. | El nuevo reemplaza al anterior sin dejar archivos huérfanos accesibles. | Pendiente |
| CP-009 | HU-007 CA-03, HU-015 CA-02 | M | A | 1. Ana se postula a una vacante de Laura.<br>2. Laura descarga el CV de Ana. | La descarga funciona y queda registrada en auditoría. | Pendiente |
| CP-009B | HU-007 CA-03, SEC-002 | M | A | 1. Con un reclutador de otra empresa (sin postulación de Ana) abrir la URL de descarga del CV de Ana. | Acceso denegado; no se entrega el archivo. | Pendiente |
| CP-010 | HU-013 | M | M | 1. Como Laura, editar razón social, descripción y logo.<br>2. Guardar. | Los datos persisten y el logo se muestra. El criterio del NIT no se puede probar (DEF-008). | Falla parcial esperada (DEF-008) |

### 6.3 Vacantes y búsqueda

| ID | Cubre | Tipo | Prior. | Pasos | Resultado esperado | Estado inicial |
| :-- | :-- | :-: | :-: | :-- | :-- | :-- |
| CP-011 | HU-004 CA-01 | M | A | 1. Como Laura, crear una vacante publicada con todos los campos.<br>2. Buscarla como Ana. | La vacante aparece en el buscador con sus datos. | Pendiente |
| CP-011B | HU-004 CA-02 | M | A | 1. Crear vacante dejando vacío el título (repetir con cada campo obligatorio). | No se guarda; aviso «Completa todos los campos de la vacante». | Pendiente |
| CP-011C | HU-004 CA-03 | M | M | 1. Guardar una vacante como borrador.<br>2. Buscarla como Ana. | No aparece en el buscador. | Pendiente |
| CP-012 | HU-017 CA-01 | M | A | 1. Editar una vacante propia.<br>2. Cerrarla. | Los cambios se guardan; la vacante cerrada deja de aparecer en el buscador. | Pendiente |
| CP-012B | HU-017 | M | M | 1. Editar con un campo obligatorio vacío. | Se rechaza con mensaje. | Pendiente |
| CP-012C | HU-017 CA-02 | M | A | 1. Como reclutador de otra empresa, enviar la edición o el cierre de una vacante ajena (modificar el `id` en el formulario). | No se modifica la vacante y **no** se muestra mensaje de éxito. | Falla esperada (DEF-004, por verificar) |
| CP-013 | HU-005 CA-01 | M | A | 1. Buscar por una palabra del título, de la descripción y del nombre de empresa. | Solo vacantes publicadas que coinciden. | Pendiente |
| CP-013B | HU-005 CA-02 | M | A | 1. Filtrar por una ciudad existente.<br>2. Filtrar por «Remote». | Solo vacantes de esa ciudad o modalidad; sin error. | Falla esperada (DEF-001: error 500) |
| CP-014 | HU-008 CA-01 | M | M | 1. Abrir el detalle de una vacante. | Muestra cargo, empresa, ciudad, modalidad y salario. | Pendiente |
| CP-014B | HU-008 CA-02 | M | M | 1. Abrir el detalle con un `id` inexistente. | Mensaje claro; no hay error del servidor ni datos técnicos. | Pendiente |
| CP-015 | HU-023 CA-01, CA-03 | M | B | 1. Guardar una vacante en favoritos.<br>2. Cerrar sesión, volver a entrar. | Aparece en «Mis favoritos» y se conserva. | Pendiente |
| CP-015B | HU-023 CA-02 | M | B | 1. Ocultar una vacante.<br>2. Repetir la búsqueda. | La vacante oculta no vuelve a aparecer para Ana. | Pendiente |
| CP-024 | HU-005 CA-03, SEC-003 | M | A | 1. Buscar `' OR '1'='1` y `'; DROP TABLE jobs;--`. | Se tratan como texto; sin error de SQL ni resultados indebidos. | Pendiente |
| CP-028 | HU-005, OPE-002 | M | B | 1. Lanzar 50 búsquedas concurrentes con una herramienta como Apache Bench. | Criterio propuesto: sin errores 5xx y tiempo p95 menor a 2 s. Ajustar el umbral con el equipo. | Pendiente |

### 6.4 Postulaciones

| ID | Cubre | Tipo | Prior. | Pasos | Resultado esperado | Estado inicial |
| :-- | :-- | :-: | :-: | :-- | :-- | :-- |
| CP-016 | HU-009 CA-01 | M | A | 1. Como Ana, postularse a una vacante publicada. | Se crea la postulación con estado `pending` («Pendiente»). | Pendiente |
| CP-017 | HU-009 CA-02 | M | A | 1. Postularse otra vez a la misma vacante. | Rechazo «Ya te postulaste a esta vacante»; una sola fila en `applications`. | Pendiente |
| CP-018 | HU-009 CA-03 | M | A | 1. Cerrar una vacante.<br>2. Enviar la postulación a esa vacante (formulario guardado o petición manual). | Rechazo con mensaje; no se crea la postulación. | Falla esperada (DEF-002) |
| CP-019 | HU-014 CA-01 | M | M | 1. Como Ana, abrir su historial. | Lista cada postulación con vacante, fecha y estado actual. | Pendiente |
| CP-019B | HU-014 CA-02 | M | B | 1. Con un candidato sin postulaciones, abrir el historial. | Estado vacío con enlace al buscador. | Pendiente |
| CP-020 | HU-015 CA-01 | M | A | 1. Como Laura, abrir postulantes.<br>2. Repetir con un reclutador de otra empresa. | Cada empresa ve solo los candidatos de sus propias vacantes. | Pendiente |
| CP-020B | HU-016 CA-01, CA-02 | M | A | 1. Cambiar una postulación por cada estado permitido.<br>2. Revisar fecha del cambio.<br>3. Enviar un estado inválido (`hired`) alterando el formulario. | Los estados válidos se guardan con fecha y hora (hoy no existe); el inválido se rechaza. | Falla parcial esperada (DEF-007) |
| CP-020C | HU-016 | M | M | 1. Cambiar el estado y revisar la auditoría. | Hay registro `application_status_updated` con usuario y datos. | Pendiente |
| CP-020D | HU-016 CA-03 | M | A | 1. Como reclutador de otra empresa, cambiar el estado de una postulación ajena (modificar `application_id`). | No cambia el estado y no aparece mensaje de éxito. | Falla esperada (DEF-004) |

### 6.5 Notificaciones y mensajería

| ID | Cubre | Tipo | Prior. | Pasos | Resultado esperado | Estado inicial |
| :-- | :-- | :-: | :-: | :-- | :-- | :-- |
| CP-021 | HU-018 CA-01 | M | A | 1. Ana se postula a una vacante de Laura.<br>2. Revisar las notificaciones de Laura. | Laura recibe alerta de nueva postulación. | Falla esperada (DEF-006: la alerta llega a Ana) |
| CP-021B | HU-018 CA-03 | M | M | 1. Marcar una notificación como leída.<br>2. Marcar todas. | El contador baja y se guarda `read_at`. | Pendiente |
| CP-021C | HU-018 CA-02 | M | A | 1. Laura cambia el estado de la postulación de Ana.<br>2. Revisar las notificaciones de Ana. | Ana recibe una alerta con el nuevo estado. | Falla esperada (DEF-003) |
| CP-032 | HU-010 CA-01 | A | A | 1. Ejecutar `ChatTest.php` (control de acceso).<br>2. Manual: intentar abrir la conversación de otra postulación por URL. | Solo candidato y empresa de la postulación acceden; el resto recibe denegación. | Pendiente |
| CP-032B | HU-010 CA-02 | A | A | 1. Enviar un mensaje en una postulación activa.<br>2. Abrirlo desde la otra cuenta. | Se guarda; al abrirlo queda con `read_at`. | Pendiente |
| CP-032C | HU-010 CA-03 | A | A | 1. Cambiar la postulación a `rejected`.<br>2. Intentar enviar mensaje (interfaz y servicio). | Interfaz bloqueada y el servicio lanza `DomainException`. | Pendiente |
| CP-032D | HU-010 CA-04 | A | M | 1. Enviar mensaje vacío y otro de solo espacios. | Ambos rechazados. | Pendiente |
| CP-032E | HU-010 CA-05 | A | M | 1. Enviar mensajes.<br>2. Revisar notificación y contador de no leídos de la contraparte. | La contraparte recibe notificación y el contador coincide con los mensajes sin leer. | Pendiente |

### 6.6 Administración, auditoría y seguridad transversal

| ID | Cubre | Tipo | Prior. | Pasos | Resultado esperado | Estado inicial |
| :-- | :-- | :-: | :-: | :-- | :-- | :-- |
| CP-005 | HU-012 CA-01, HU-011 CA-02 | M | A | 1. Como Ana, abrir URL de acciones de empresa y de administrador.<br>2. Como Laura, abrir URL de administrador.<br>3. Intentar editar el perfil de otro usuario. | Todas se rechazan o redirigen; sin cambios en datos. | Pendiente |
| CP-006 | HU-012 CA-02, SEC-008 | M | A | 1. Enviar un POST (por ejemplo, postularse) sin el campo `csrf` o con uno alterado. | Se rechaza la petición. | Pendiente |
| CP-022 | HU-025 CA-02 | M | M | 1. Como administrador, filtrar la auditoría por módulo, acción, fecha y estado. | Los resultados corresponden a cada filtro; el filtro combinado también. | Pendiente |
| CP-022B | HU-025 CA-01 | M | M | 1. Contar usuarios, empresas, vacantes y postulaciones en MySQL.<br>2. Comparar con el panel. | Las cifras del panel coinciden. | Pendiente |
| CP-023 | HU-020 CA-01 | M | A | 1. Eliminar un usuario de prueba y una empresa de prueba.<br>2. Intentar eliminar la cuenta propia. | Se eliminan con sus datos dependientes; la cuenta propia no se puede eliminar. Revisar que la auditoría conserve el registro. | Pendiente |
| CP-023B | HU-020 CA-02 | M | M | 1. Crear un administrador con correo nuevo.<br>2. Repetir con correo existente. | El primero se crea con rol `admin`; el segundo se rechaza. | Pendiente |
| CP-025 | HU-006 CA-02, SEC-007 | M | A | 1. Guardar `<script>alert(1)</script>` en bio, titular y mensaje de chat.<br>2. Ver cada pantalla donde se muestra. | Se ve como texto; no se ejecuta ningún script. | Pendiente |
| CP-029 | HU-006, UX-001 | M | B | 1. Abrir registro, login, buscador y perfil a 390 px de ancho. | Sin desplazamiento horizontal ni campos cortados. | Pendiente |
| CP-033 | HU-022 | M | M | Retirar una vacante como administrador y revisar la auditoría. | Deja de verse en el buscador y se audita. | Bloqueado (HU-022) |
| CP-033B | HU-024 | M | B | Filtrar candidatos por habilidades y exportar. | La exportación contiene solo los candidatos filtrados. | Bloqueado (HU-024) |

## 7. Matriz de trazabilidad HU → CP

| HU | Casos de prueba |
| :-- | :-- |
| HU-001 | CP-001, CP-002, CP-002B, CP-026 |
| HU-002 | CP-003, CP-002 |
| HU-003 | CP-004, CP-004B, CP-037, CP-038 |
| HU-004 | CP-011, CP-011B, CP-011C, CP-013 |
| HU-005 | CP-013, CP-013B, CP-024, CP-028 |
| HU-006 | CP-007, CP-025, CP-029 |
| HU-007 | CP-008, CP-008B, CP-008C, CP-009, CP-009B |
| HU-008 | CP-014, CP-014B |
| HU-009 | CP-016, CP-017, CP-018 |
| HU-010 | CP-032, CP-032B, CP-032C, CP-032D, CP-032E |
| HU-011 | CP-007, CP-005 |
| HU-012 | CP-005, CP-006 |
| HU-013 | CP-010, CP-003 |
| HU-014 | CP-019, CP-019B |
| HU-015 | CP-020, CP-009 |
| HU-016 | CP-020B, CP-020C, CP-020D |
| HU-017 | CP-012, CP-012B, CP-012C |
| HU-018 | CP-021, CP-021B, CP-021C |
| HU-019 | CP-031 (bloqueado) |
| HU-020 | CP-023, CP-023B, CP-040 (bloqueado) |
| HU-021 | CP-039 (bloqueado) |
| HU-022 | CP-033 (bloqueado) |
| HU-023 | CP-015, CP-015B |
| HU-024 | CP-033B (bloqueado) |
| HU-025 | CP-022, CP-022B |

Cobertura: los 25 HU tienen al menos un caso de prueba y cada criterio de aceptación tiene un caso que lo verifica.

## 8. Resumen de casos

- **Total:** 60 casos.
- **Pendientes de ejecutar (sin defecto conocido):** 44.
- **Falla esperada por defecto o riesgo confirmado en el código:** 11 (CP-002B, CP-010 parcial, CP-012C, CP-013B, CP-018, CP-020B parcial, CP-020D, CP-021, CP-021C, CP-037, CP-038).
- **Bloqueados por función no implementada:** 5 (CP-031, CP-033, CP-033B, CP-039, CP-040).
- **Automatizados:** 5 (CP-032 a CP-032E, en `tests/ChatTest.php`).