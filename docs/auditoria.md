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

## Historial de movimientos por área (nov. 2026)

Vista de esta misma tabla para que cada encargado vea la actividad de **sus
departamentos**, hecha por **cualquier usuario**. No es un segundo registro:
se reutiliza `auditoria`, ampliada con el área afectada.

Decisiones confirmadas con el responsable del proyecto (2026-11-12):

- Lo consultan los **Encargados de Departamento y de Seguimiento** (sus
  departamentos) y el **Administrador** (todos). Auditor y Dirección no: el
  Auditor conserva la pantalla de Auditoría.
- Los registros anteriores a la ampliación se completaron con su área
  (migración `backfill_area_in_auditoria_table`): solo se rellenaron las
  columnas nuevas; evento, usuario, fecha y valores no cambiaron.

### Área de cada registro

| Columna | Contenido |
|---------|-----------|
| `departamento_id`, `proceso_id`, `matriz_id` | Departamento, proceso y edición afectados. Null en catálogos, usuarios y sesiones (no pertenecen a un área). |

Se guardan **al escribir el registro** (como una foto), con el método
`contextoAuditoria()` de cada modelo (trait `Auditable`):

| Registro | Área |
|----------|------|
| `departamento` | el departamento |
| `proceso` | departamento + proceso |
| `matriz` (edición; también exportaciones e importaciones) | departamento + proceso + edición |
| `riesgo` (incluye factores y estado del tratamiento) | el de su edición, aunque esté descartada |
| `seguimiento`, `evidencia` | el de su riesgo |

### API

| Método y ruta | Quién |
|---------------|-------|
| `GET /api/movimientos` (`desde`, `hasta`, `usuario_id`, `departamento_id`, `proceso_id`, `matriz_id`, `evento`, `entidad`, `per_page`) | Administrador y Encargados; 50 por página |
| `GET /api/movimientos/opciones` | Ídem: valores para los filtros, solo con movimientos al alcance |
| `GET /api/movimientos/{id}` | Ídem: detalle con valores anteriores y nuevos |

Seguridad:

- El alcance lo impone siempre `Auditoria::movimientosVisiblesPara()` (misma
  regla que `Departamento::visiblesPara()`). Los filtros solo acotan: pedir un
  departamento, proceso o edición ajenos devuelve una lista vacía, y el
  detalle de un movimiento ajeno responde 403.
- Un encargado al que se le retira un departamento deja de ver su historial.
- No se exponen IP ni navegador (sí en la Auditoría del Administrador/Auditor).
- Solo `GET`: no hay rutas de escritura y el modelo impide modificar o borrar.

cuenta Admin:
	administrador@infotep.local
  
-JosueSalas123456
