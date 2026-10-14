# Auditoría (Fase 7)

Registro de quién hizo qué y cuándo (RF-9), en la tabla `auditoria`.

Decisiones confirmadas con el responsable del proyecto (oct. 2026):

- Se audita **todo lo que cambia datos**, más los inicios de sesión.
- Lo consultan **Administrador y Auditor**. El historial de un riesgo o de una
  edición lo ve, además, quien puede ver ese registro.
- Se conserva **indefinidamente**. Si un día fuera necesario archivarlo, debe
  hacerse fuera de la aplicación con un procedimiento autorizado.

## Qué contiene cada registro

| Campo | Contenido |
|-------|-----------|
| `user_id` | Usuario de la sesión (null si no hay sesión, p. ej. login fallido) |
| `evento` | `creado`, `actualizado`, `eliminado`, `publicado`, `archivado`, `clonado`, `anulado`, `login`, `login_fallido`, `logout` |
| `entidad`, `entidad_id` | Qué registro: `riesgo`, `matriz`, `seguimiento`, `evidencia`, `usuario`, `departamento`, `proceso`, `factor_riesgo`, `periodicidad`, `nivel_riesgo`, `escala_nivel`, `matriz_calor_celda` |
| `valores_anteriores`, `valores_nuevos` | Solo los campos que cambiaron |
| `ip`, `user_agent`, `created_at` | Desde dónde y cuándo |

## Cómo se registra

- **Automático en los modelos** (trait `App\Models\Concerns\Auditable`): altas,
  cambios y eliminaciones. Ningún controlador futuro puede olvidarlo.
- **Relaciones muchos a muchos** (factores de un riesgo, departamentos de un
  usuario): las registran `RiesgoService` y `UserController`, porque la tabla
  pivote no dispara eventos de modelo.
- **Sesión**: escuchando los eventos `Login`, `Failed` y `Logout` de Laravel
  (`AppServiceProvider`).

## Datos que nunca se guardan

- Contraseña: se registra **que cambió** (`"password": "[oculto]"`), nunca su
  valor ni su hash.
- `remember_token` y secretos de doble factor: excluidos.
- Login fallido: solo el email intentado, nunca la contraseña.

## Protección

- El modelo `Auditoria` lanza `LogicException` ante cualquier modificación o
  borrado; no existe ningún endpoint de escritura.
- Para la base de datos de producción se recomienda además que el usuario de la
  aplicación no tenga permisos `UPDATE`/`DELETE` sobre la tabla `auditoria`.

## API

| Método y ruta | Quién |
|---------------|-------|
| `GET /api/auditoria` (`entidad`, `entidad_id`, `evento`, `usuario_id`, `desde`, `hasta`) | Administrador y Auditor; 50 por página |
| `GET /api/riesgos/{id}/historial` | Quien puede ver el riesgo |
| `GET /api/matrices/{id}/historial` | Quien puede ver la edición |
