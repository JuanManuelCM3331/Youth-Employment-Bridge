# Plan de Pruebas - Youth Employment Bridge (YEB)

A continuación, se listan todos los Casos de Prueba (CP) extraídos de las Historias de Usuario para la verificación del sistema:

## 1. Módulo de Autenticación y Registro
* **CP-001:** Registro de candidato con datos válidos.
* **CP-002:** Registro con correo existente.
* **CP-002B:** Registro con contraseña corta.
* **CP-003:** Registro de empresa y reclutador.
* **CP-004:** Inicio de sesión correcto.
* **CP-004B:** Inicio de sesión con contraseña incorrecta.
* **CP-026:** Contraseñas almacenadas como hash.
* **CP-031:** Recuperación de contraseña.
* **CP-037:** Cookie de sesión, cabeceras y validación de datos de menores.
* **CP-038:** Intentos repetidos de inicio de sesión.

## 2. Módulo de Perfiles (Candidato y Empresa)
* **CP-007:** Actualización del perfil profesional del candidato.
* **CP-008:** Carga de hoja de vida en PDF válida.
* **CP-008B:** Carga de archivo con formato no permitido.
* **CP-008C:** Carga de archivo mayor a 5 MB.
* **CP-009:** Descarga de hoja de vida de un candidato postulado.
* **CP-009B:** Descarga de hoja de vida de un candidato no postulado.
* **CP-010:** Edición del perfil corporativo.

## 3. Módulo de Vacantes
* **CP-011:** Publicación de vacante con todos los campos.
* **CP-011B:** Publicación de vacante con campos vacíos.
* **CP-012:** Edición de una vacante propia.
* **CP-012B:** Cierre de una vacante propia.
* **CP-012C:** Empresa intenta editar una vacante de otra empresa o modificar datos.
* **CP-013:** Búsqueda por palabra clave.
* **CP-013B:** Búsqueda por ciudad.
* **CP-014:** Detalle de una vacante existente.
* **CP-014B:** Detalle de una vacante inexistente.
* **CP-015:** Guardar vacante en favoritos.
* **CP-024:** Intento de inyección SQL en el buscador.
* **CP-028:** Búsqueda con 50 solicitudes concurrentes.

## 4. Módulo de Postulaciones
* **CP-016:** Postulación a vacante publicada.
* **CP-017:** Postulación duplicada.
* **CP-018:** Postulación a vacante cerrada.
* **CP-019:** Historial y consulta de postulaciones.
* **CP-020:** Empresa visualiza los candidatos postulados.
* **CP-020B:** Cambio de estado de una postulación a entrevista.
* **CP-020C:** Cambio a un estado no permitido.

## 5. Notificaciones y Mensajería
* **CP-021:** Alerta tras postulación.
* **CP-021B:** Marcar alertas como leídas.
* **CP-021C:** Alerta al candidato por cambio de estado.
* **CP-032:** Mensajería interna y restricciones de acceso.

## 6. Módulo de Administración y Seguridad
* **CP-005:** Control de acceso por rol y propiedad de cuenta (Ej: Candidato intenta publicar una vacante).
* **CP-006:** Formulario enviado sin token CSRF.
* **CP-022:** Auditoría filtrada por módulo.
* **CP-023:** Administrador intenta eliminar su propia cuenta.
* **CP-023B:** Creación de un nuevo administrador.
* **CP-025:** Intento de XSS almacenado en el perfil.
* **CP-029:** Responsive sin desbordamiento a 390 px.
* **CP-033:** Moderación de vacantes y exportación de listas.