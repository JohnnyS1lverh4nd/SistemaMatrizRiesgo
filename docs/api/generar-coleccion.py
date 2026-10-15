# Genera docs/api/SistemaMatrizRiesgo.postman_collection.json y la tabla de
# endpoints de docs/api.md a partir de una sola definición de las rutas (R).
# Uso, desde la raíz del repositorio:  python3 docs/api/generar-coleccion.py .
# Escribe la tabla en docs/api/.tabla.md: cópiela a la sección "Endpoints" de docs/api.md.
import json, sys, os

REPO = sys.argv[1]
ADMIN = 'Administrador'
TODOS = 'Todos los roles (según su alcance)'
GESTION = 'Administrador y Encargado de Departamento (sus departamentos)'
SEGUIM = 'Administrador y Encargados (sus departamentos)'

def guardar(var, expr):
    return f"pm.collectionVariables.set('{var}', {expr});"

# (carpeta, nombre, método, ruta, quién, descripción, opciones)
R = []
def r(carpeta, nombre, metodo, ruta, quien, desc, **o):
    R.append(dict(carpeta=carpeta, nombre=nombre, metodo=metodo, ruta=ruta, quien=quien, desc=desc, **o))

C = '1. Autenticación'
r(C, 'Cookie CSRF', 'GET', '/sanctum/csrf-cookie', 'Público', 'Entrega la cookie XSRF-TOKEN. La colección la pide sola antes de cada petición que modifica datos.', estado=204)
r(C, 'Iniciar sesión', 'POST', '/login', 'Público', 'Inicia sesión con correo y contraseña (cookie de sesión). 5 intentos por minuto. Credenciales erróneas: 422.', body={'email': '{{email}}', 'password': '{{password}}'}, estado=200)
r(C, 'Usuario actual', 'GET', '/api/user', 'Cualquier usuario autenticado', 'Datos del usuario con sesión, su rol, departamentos y `meta.sesion_minutos` (inactividad permitida).', estado=200,
  pruebas="pm.test('Sesión iniciada', () => pm.expect(pm.response.json().data.email).to.eql(pm.collectionVariables.get('email')));")

C = '2. Usuarios y roles'
r(C, 'Roles', 'GET', '/api/roles', ADMIN, 'Lista de roles para asignar a los usuarios.', estado=200, guardar_js=guardar('rol_id', "pm.response.json().data.find(r => r.slug === 'auditor').id"))
r(C, 'Listar usuarios', 'GET', '/api/users', ADMIN, 'Todos los usuarios (activos e inactivos).', estado=200)
r(C, 'Crear usuario', 'POST', '/api/users', ADMIN, 'Crea un usuario con rol y departamentos. En producción la contraseña exige 8 caracteres con mayúsculas, minúsculas, números y símbolos.',
  body={'name': 'Usuario de Postman', 'email': 'postman.{{$timestamp}}@ejemplo.test', 'password': 'Postman-Prueba-2026!', 'password_confirmation': 'Postman-Prueba-2026!', 'role_id': '{{rol_id}}', 'departamento_ids': []},
  numeros=['role_id'], estado=201, guardar_js=guardar('user_id', 'pm.response.json().data.id'))
r(C, 'Ver usuario', 'GET', '/api/users/{{user_id}}', ADMIN, 'Un usuario con su rol y departamentos.', estado=200)
r(C, 'Modificar usuario', 'PUT', '/api/users/{{user_id}}', ADMIN, 'Cambios parciales: solo se validan los campos enviados. `activo: false` desactiva la cuenta (no se borra). Un administrador no puede desactivarse ni quitarse el rol a sí mismo.',
  body={'name': 'Usuario de Postman (modificado)', 'activo': False}, estado=200)

C = '3. Departamentos y procesos'
r(C, 'Listar departamentos', 'GET', '/api/departamentos', TODOS, 'Departamentos visibles para el usuario. Filtro opcional `activo`.', query=[('activo', '1', True, '1 = activos, 0 = inactivos')], estado=200)
r(C, 'Crear departamento', 'POST', '/api/departamentos', ADMIN, 'El `slug` se genera del nombre si no se envía.', body={'nombre': 'Departamento Postman {{$timestamp}}', 'direccion': 'Dirección de ejemplo'}, estado=201, guardar_js=guardar('departamento_id', 'pm.response.json().data.id'))
r(C, 'Ver departamento', 'GET', '/api/departamentos/{{departamento_id}}', TODOS, 'Un departamento.', estado=200)
r(C, 'Modificar departamento', 'PUT', '/api/departamentos/{{departamento_id}}', ADMIN, 'Sin borrado: se desactiva con `activo: false`.', body={'direccion': 'Dirección de ejemplo (modificada)'}, estado=200)
r(C, 'Listar procesos del departamento', 'GET', '/api/departamentos/{{departamento_id}}/procesos', TODOS, 'Procesos de un departamento. Filtro opcional `activo`.', estado=200)
r(C, 'Crear proceso', 'POST', '/api/departamentos/{{departamento_id}}/procesos', ADMIN, 'Proceso nuevo en un departamento activo.', body={'nombre': 'Proceso Postman', 'descripcion': 'Proceso de ejemplo'}, estado=201, guardar_js=guardar('proceso_id', 'pm.response.json().data.id'))
r(C, 'Crear proceso para importar', 'POST', '/api/departamentos/{{departamento_id}}/procesos', ADMIN, 'Segundo proceso, sin ediciones, para el ejemplo de importación.', body={'nombre': 'Proceso Postman importado'}, estado=201, guardar_js=guardar('proceso_importacion_id', 'pm.response.json().data.id'))
r(C, 'Ver proceso', 'GET', '/api/procesos/{{proceso_id}}', TODOS, 'Un proceso; `permisos` indica qué puede hacer el usuario (crear ediciones, importar).', estado=200)
r(C, 'Modificar proceso', 'PUT', '/api/procesos/{{proceso_id}}', ADMIN, 'Sin borrado: se desactiva con `activo: false` (deja de contar en los reportes).', body={'descripcion': 'Proceso de ejemplo (modificado)'}, estado=200)

C = '4. Catálogos'
r(C, 'Listar factores', 'GET', '/api/factores-riesgo', TODOS, 'Factores de riesgo. Filtro opcional `activo`.', estado=200, guardar_js=guardar('factor_id', 'pm.response.json().data[0].id'))
r(C, 'Crear factor', 'POST', '/api/factores-riesgo', ADMIN, 'Factor nuevo. El `slug` se genera del nombre si no se envía.', body={'nombre': 'Factor Postman {{$timestamp}}'}, estado=201, guardar_js=guardar('factor_nuevo_id', 'pm.response.json().data.id'))
r(C, 'Ver factor', 'GET', '/api/factores-riesgo/{{factor_nuevo_id}}', TODOS, 'Un factor.', estado=200)
r(C, 'Desactivar factor', 'PUT', '/api/factores-riesgo/{{factor_nuevo_id}}', ADMIN, 'Los catálogos no se borran: se desactivan.', body={'activo': False}, estado=200)
r(C, 'Listar periodicidades', 'GET', '/api/periodicidades', TODOS, 'Periodicidades; `frecuencia_dias` marca cuándo un seguimiento está atrasado.', estado=200, guardar_js=guardar('periodicidad_id', 'pm.response.json().data[0].id'))
r(C, 'Crear periodicidad', 'POST', '/api/periodicidades', ADMIN, 'Periodicidad nueva (`frecuencia_dias` opcional: sin plazo).', body={'nombre': 'Periodicidad Postman {{$timestamp}}', 'frecuencia_dias': 15}, estado=201, guardar_js=guardar('periodicidad_nueva_id', 'pm.response.json().data.id'))
r(C, 'Ver periodicidad', 'GET', '/api/periodicidades/{{periodicidad_nueva_id}}', TODOS, 'Una periodicidad.', estado=200)
r(C, 'Desactivar periodicidad', 'PUT', '/api/periodicidades/{{periodicidad_nueva_id}}', ADMIN, 'Se desactiva en lugar de borrarse.', body={'activo': False}, estado=200)
r(C, 'Listar niveles de riesgo', 'GET', '/api/niveles-riesgo', TODOS, 'Bajo, Moderado, Alto y Crítico.', estado=200,
  guardar_js="const n = pm.response.json().data[0]; pm.collectionVariables.set('nivel_id', n.id); pm.collectionVariables.set('nivel_nombre', n.nombre); pm.collectionVariables.set('nivel_color', n.color);")
r(C, 'Ver nivel', 'GET', '/api/niveles-riesgo/{{nivel_id}}', TODOS, 'Un nivel.', estado=200)
r(C, 'Modificar nivel', 'PUT', '/api/niveles-riesgo/{{nivel_id}}', ADMIN, 'Cambia nombre o color (#RRGGBB). El ejemplo reenvía los valores actuales: no cambia nada.', body={'nombre': '{{nivel_nombre}}', 'color': '{{nivel_color}}'}, estado=200)
r(C, 'Listar escalas', 'GET', '/api/escalas-nivel', TODOS, 'Nombres de los valores 1–4 de Probabilidad e Impacto.', estado=200,
  guardar_js="const e = pm.response.json().data[0]; pm.collectionVariables.set('escala_id', e.id); pm.collectionVariables.set('escala_nombre', e.nombre);")
r(C, 'Ver escala', 'GET', '/api/escalas-nivel/{{escala_id}}', TODOS, 'Un valor de escala.', estado=200)
r(C, 'Modificar escala', 'PUT', '/api/escalas-nivel/{{escala_id}}', ADMIN, 'Cambia el nombre de un valor. El ejemplo reenvía el actual.', body={'nombre': '{{escala_nombre}}'}, estado=200)
r(C, 'Matriz de calor', 'GET', '/api/matriz-calor-celdas', TODOS, 'Las 16 celdas Probabilidad × Impacto con su nivel.', estado=200,
  guardar_js="const c = pm.response.json().data[0]; pm.collectionVariables.set('celda_id', c.id); pm.collectionVariables.set('celda_nivel_id', c.nivel_riesgo.id);")
r(C, 'Ver celda', 'GET', '/api/matriz-calor-celdas/{{celda_id}}', TODOS, 'Una celda.', estado=200)
r(C, 'Modificar celda', 'PUT', '/api/matriz-calor-celdas/{{celda_id}}', ADMIN, 'Asigna otro nivel a una combinación. Los borradores se recalculan; las ediciones publicadas no cambian. El ejemplo reenvía el nivel actual.', body={'nivel_riesgo_id': '{{celda_nivel_id}}'}, numeros=['nivel_riesgo_id'], estado=200)

C = '5. Matrices y riesgos'
r(C, 'Listar ediciones del proceso', 'GET', '/api/procesos/{{proceso_id}}/matrices', TODOS, 'Ediciones de la matriz de un proceso, con `permisos` por edición.', estado=200)
r(C, 'Crear edición (borrador)', 'POST', '/api/procesos/{{proceso_id}}/matrices', GESTION, 'Edición nueva en borrador. El nombre no se repite dentro del proceso.', body={'nombre': 'Abril-Junio 2026', 'fecha_revision': '2026-06-30'}, estado=201, guardar_js=guardar('matriz_id', 'pm.response.json().data.id'))
r(C, 'Ver edición', 'GET', '/api/matrices/{{matriz_id}}', TODOS, 'Una edición.', estado=200)
r(C, 'Modificar borrador', 'PUT', '/api/matrices/{{matriz_id}}', GESTION, 'Solo en borrador.', body={'fecha_revision': '2026-06-29'}, estado=200)
r(C, 'Crear riesgo', 'POST', '/api/matrices/{{matriz_id}}/riesgos', GESTION, 'Fila de la matriz con los 13 campos. El nivel lo calcula el servidor con la matriz de calor; `nivel_ajustado_id` + `justificacion_ajuste` lo ajustan a mano.',
  body={'actividad': 'Evaluación de ofertas', 'descripcion': 'Adjudicaciones erróneas', 'causas': 'Criterios de evaluación ambiguos', 'control_actual': 'Pautas a los peritos', 'probabilidad': 3, 'impacto': 4, 'factor_ids': ['{{factor_id}}'], 'sintoma': 'Reclamaciones de suplidores', 'tratamiento': 'Revisión de pliegos', 'responsable': 'Comité de Compras', 'periodicidad_id': '{{periodicidad_id}}'},
  numeros=['periodicidad_id', 'factor_ids'], estado=201, guardar_js=guardar('riesgo_id', 'pm.response.json().data.id'),
  pruebas="pm.test('Nivel calculado por el servidor', () => pm.expect(pm.response.json().data.nivel_calculado.slug).to.eql('alto'));")
r(C, 'Crear riesgo para descartar', 'POST', '/api/matrices/{{matriz_id}}/riesgos', GESTION, 'Segundo riesgo, para el ejemplo de eliminación.', body={'actividad': 'Actividad', 'descripcion': 'Riesgo a descartar', 'probabilidad': 1, 'impacto': 1}, estado=201, guardar_js=guardar('riesgo_descartar_id', 'pm.response.json().data.id'))
r(C, 'Listar riesgos de la edición', 'GET', '/api/matrices/{{matriz_id}}/riesgos', TODOS, 'Riesgos en orden, con nivel calculado, ajustado y efectivo.', estado=200)
r(C, 'Ver riesgo', 'GET', '/api/riesgos/{{riesgo_id}}', TODOS, 'Un riesgo.', estado=200)
r(C, 'Modificar riesgo', 'PUT', '/api/riesgos/{{riesgo_id}}', GESTION, 'Cambios parciales en un borrador; el nivel se recalcula.', body={'probabilidad': 4, 'impacto': 4}, estado=200,
  pruebas="pm.test('Nivel recalculado', () => pm.expect(pm.response.json().data.nivel_calculado.slug).to.eql('critico'));")
r(C, 'Eliminar riesgo', 'DELETE', '/api/riesgos/{{riesgo_descartar_id}}', GESTION, 'Eliminación lógica, solo en borradores.', estado=204)
r(C, 'Publicar edición', 'POST', '/api/matrices/{{matriz_id}}/publicar', GESTION, 'Borrador → vigente (la vigente anterior se archiva). Exige todos los riesgos evaluados. Después es inmutable.', estado=200)
r(C, 'Nueva edición desde otra (clonar)', 'POST', '/api/matrices/{{matriz_id}}/clonar', GESTION, 'Borrador nuevo con copia de los riesgos, enlazados con sus originales (comparación).', body={'nombre': 'Julio-Septiembre 2026', 'fecha_revision': '2026-09-30'}, estado=201, guardar_js=guardar('matriz_clon_id', 'pm.response.json().data.id'))
r(C, 'Comparar ediciones', 'GET', '/api/matrices/{{matriz_id}}/comparar/{{matriz_clon_id}}', TODOS, 'Riesgos agregados, cerrados y cambios de nivel entre dos ediciones del mismo proceso (RF-5).', estado=200)
r(C, 'Descartar borrador', 'DELETE', '/api/matrices/{{matriz_clon_id}}', GESTION, 'Solo borradores nunca publicados (eliminación lógica).', estado=204)
r(C, 'Archivar edición', 'POST', '/api/matrices/{{matriz_id}}/archivar', GESTION, 'Vigente → archivada (el proceso queda sin vigente). No se ejecuta en el recorrido: el seguimiento siguiente necesita la edición vigente.', estado=200, omitir=True)

C = '6. Seguimiento y evidencias'
r(C, 'Registrar seguimiento', 'POST', '/api/riesgos/{{riesgo_id}}/seguimientos', SEGUIM, 'Avance con fecha (no futura) y descripción; puede cambiar el estado del tratamiento (pendiente / en_progreso / cumplido) y adjuntar hasta 5 archivos (`archivos[]`: PDF, imágenes o Excel, 10 MB).',
  form=[('fecha', 'text', '2026-07-15'), ('descripcion', 'text', 'Se revisaron los pliegos del trimestre.'), ('estado_tratamiento', 'text', 'en_progreso'), ('archivos[]', 'file', 'ejemplo-evidencia.pdf')],
  estado=201, guardar_js="const s = pm.response.json().data; pm.collectionVariables.set('seguimiento_id', s.id); if (s.evidencias && s.evidencias.length) { pm.collectionVariables.set('evidencia_id', s.evidencias[0].id); }")
r(C, 'Listar seguimientos del riesgo', 'GET', '/api/riesgos/{{riesgo_id}}/seguimientos', TODOS, 'Histórico del riesgo, más reciente primero (incluye anulados, marcados).', estado=200)
r(C, 'Ver seguimiento', 'GET', '/api/seguimientos/{{seguimiento_id}}', TODOS, 'Un seguimiento con sus evidencias.', estado=200)
r(C, 'Descargar evidencia', 'GET', '/api/evidencias/{{evidencia_id}}/descargar', TODOS, 'Archivo adjunto (disco privado; solo con permiso sobre el riesgo).', estado=200, descarga=True)
r(C, 'Anular seguimiento', 'POST', '/api/seguimientos/{{seguimiento_id}}/anular', SEGUIM, 'El historial no se edita: un error se anula con motivo y queda visible como anulado.', body={'motivo': 'Registrado por error (ejemplo de Postman).'}, estado=200)

C = '7. Importación y exportación'
r(C, 'Vista previa de importación', 'POST', '/api/procesos/{{proceso_importacion_id}}/importacion/vista-previa', ADMIN, 'Analiza un Excel institucional (RT-01-NI-ONA-156) sin guardar nada: ediciones, errores (con hoja y fila) y avisos. 20 por minuto.',
  form=[('archivo', 'file', 'ejemplo-importacion.xlsx')], estado=200,
  pruebas="pm.test('El archivo es válido', () => pm.expect(pm.response.json().data.valido).to.be.true);")
r(C, 'Importar Excel', 'POST', '/api/procesos/{{proceso_importacion_id}}/importacion', ADMIN, 'Importa el mismo archivo (se vuelve a analizar). Solo en procesos sin ediciones; con errores responde 422 y no importa nada.',
  form=[('archivo', 'file', 'ejemplo-importacion.xlsx')], estado=201)
r(C, 'Exportar a PDF', 'GET', '/api/matrices/{{matriz_id}}/exportar/pdf', TODOS, 'PDF con el formato institucional y el logo. 20 por minuto. En Postman: "Send and Download".', estado=200, descarga='application/pdf')
r(C, 'Exportar a Excel', 'GET', '/api/matrices/{{matriz_id}}/exportar/excel', TODOS, 'Excel con la hoja de la plantilla institucional y el logo; se puede volver a importar. 20 por minuto.', estado=200, descarga='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')

C = '8. Reportes y auditoría'
r(C, 'Dashboard', 'GET', '/api/dashboard', TODOS, 'Totales por nivel, mapa de calor, estados del tratamiento, prioritarios, atrasados y consolidado por departamento (solo ediciones vigentes, procesos activos).',
  query=[('departamento_id', '{{departamento_id}}', True, 'Opcional')], estado=200)
r(C, 'Reporte de riesgos', 'GET', '/api/reportes/riesgos', TODOS, 'Riesgos con filtros, más graves primero, 50 por página.',
  query=[('departamento_id', '{{departamento_id}}', True, ''), ('proceso_id', '{{proceso_id}}', True, ''), ('factor_id', '{{factor_id}}', True, ''), ('nivel_id', '{{nivel_id}}', True, ''), ('responsable', 'Compras', True, 'Texto contenido'),
         ('estado_tratamiento', 'pendiente', True, 'pendiente | en_progreso | cumplido'), ('desde', '2026-01-01', True, 'Fecha de revisión (incluye archivadas)'), ('hasta', '2026-12-31', True, ''), ('prioritarios', '1', True, 'Alto/Crítico no cumplidos'), ('atrasados', '1', True, 'Seguimiento vencido')], estado=200)
r(C, 'Auditoría', 'GET', '/api/auditoria', 'Administrador y Auditor', 'Registro de cambios (quién, qué, cuándo), paginado.',
  query=[('entidad', 'riesgo', True, 'matriz, riesgo, usuario…'), ('entidad_id', '{{riesgo_id}}', True, ''), ('evento', 'actualizado', True, 'creado, actualizado, publicado, exportado, importado, login…'), ('usuario_id', '', True, ''), ('desde', '2026-01-01', True, ''), ('hasta', '2026-12-31', True, '')], estado=200)
r(C, 'Historial de una edición', 'GET', '/api/matrices/{{matriz_id}}/historial', TODOS, 'Cambios de la edición y de sus riesgos.', estado=200)
r(C, 'Historial de un riesgo', 'GET', '/api/riesgos/{{riesgo_id}}/historial', TODOS, 'Cambios de un riesgo (incluye factores y seguimientos).', estado=200)

C = '9. Mi contraseña'
r(C, 'Cambiar mi contraseña', 'PUT', '/user/password', 'Cualquier usuario autenticado', 'Exige la contraseña actual. El ejemplo pone la misma: no cambia nada.', body={'current_password': '{{password}}', 'password': '{{password}}', 'password_confirmation': '{{password}}'}, estado=200)
C = '10. Cerrar sesión'
r(C, 'Cerrar sesión', 'POST', '/logout', 'Cualquier usuario autenticado', 'Termina la sesión.', estado=204)
r(C, 'Comprobar sesión cerrada', 'GET', '/api/user', 'Público', 'Sin sesión, la API responde 401.', estado=401)

C = '11. Recuperar contraseña (sin sesión)'
r(C, 'Olvidé mi contraseña', 'POST', '/forgot-password', 'Público', 'Solo sin sesión (con sesión, Fortify redirige). Envía el enlace de recuperación al correo. Responde lo mismo exista o no la cuenta (no revela usuarios). 5 por minuto.', body={'email': '{{email}}'}, estado=200,
  pruebas="pm.test('Respuesta JSON de Fortify', () => pm.expect(pm.response.json().message).to.be.a('string'));")
r(C, 'Restablecer contraseña', 'POST', '/reset-password', 'Público', 'Con el `token` y el `email` del enlace recibido por correo. Sin un token real responde 422 (es lo esperado en el recorrido).',
  body={'token': 'TOKEN_DEL_CORREO', 'email': '{{email}}', 'password': 'Nueva-Clave-2026!', 'password_confirmation': 'Nueva-Clave-2026!'}, estado=422)


PRE = """// Sanctum en modo SPA: sesión por cookies y protección CSRF.
// Laravel envía la cookie XSRF-TOKEN en cada respuesta (la guarda el script
// de pruebas de la colección); aquí se reenvía en la cabecera X-XSRF-TOKEN en
// las peticiones que modifican datos. Referer identifica a la SPA.
const base = pm.collectionVariables.get('baseUrl');
pm.request.headers.upsert({ key: 'Accept', value: 'application/json' });
pm.request.headers.upsert({ key: 'Referer', value: base + '/' });
const xsrf = pm.collectionVariables.get('xsrf');
if (!['GET', 'HEAD', 'OPTIONS'].includes(pm.request.method)) {
    if (xsrf) {
        pm.request.headers.upsert({ key: 'X-XSRF-TOKEN', value: xsrf });
    } else {
        console.warn('Sin cookie CSRF: ejecute primero "1. Autenticación → Cookie CSRF".');
    }
}"""

POST = """// Guarda la cookie XSRF-TOKEN de cada respuesta (cambia al iniciar sesión).
const token = pm.cookies.get('XSRF-TOKEN');
if (token) { pm.collectionVariables.set('xsrf', decodeURIComponent(token)); }"""

# Variables que son números: en el JSON van sin comillas ({{rol_id}}, no "{{rol_id}}").
VARIABLES_NUMERICAS = ['rol_id', 'periodicidad_id', 'factor_id', 'celda_nivel_id']

def js_body(body, numeros=None):
    texto = json.dumps(body, ensure_ascii=False, indent=2)
    for var in VARIABLES_NUMERICAS:
        texto = texto.replace('"{{' + var + '}}"', '{{' + var + '}}')
    return texto

def item(x):
    url_raw = '{{baseUrl}}' + x['ruta']
    partes = [p for p in x['ruta'].strip('/').split('/')]
    url = {'raw': url_raw, 'host': ['{{baseUrl}}'], 'path': partes}
    if x.get('query'):
        url['query'] = [{'key': k, 'value': v, 'disabled': d, **({'description': desc} if desc else {})} for k, v, d, desc in x['query']]
    req = {'method': x['metodo'], 'header': [], 'url': url,
           'description': f"{x['desc']}\n\n**Quién puede:** {x['quien']}."}
    if 'body' in x:
        req['header'].append({'key': 'Content-Type', 'value': 'application/json'})
        req['body'] = {'mode': 'raw', 'raw': js_body(x['body'], x.get('numeros')), 'options': {'raw': {'language': 'json'}}}
    if 'form' in x:
        req['body'] = {'mode': 'formdata', 'formdata': [
            {'key': k, 'type': t, **({'src': v} if t == 'file' else {'value': v})} for k, t, v in x['form']]}
    pruebas = [] if x.get('omitir') else [f"pm.test('HTTP {x['estado']}', () => pm.response.to.have.status({x['estado']}));"]
    if x.get('descarga') and isinstance(x['descarga'], str):
        pruebas.append(f"pm.test('Archivo {x['descarga'].split('/')[-1][:20]}', () => pm.expect(pm.response.headers.get('Content-Type')).to.include('{x['descarga']}'));")
    if x.get('pruebas'):
        pruebas.append(x['pruebas'])
    if x.get('guardar_js'):
        pruebas.append(f"if (pm.response.code < 300) {{ {x['guardar_js']} }}")
    it = {'name': x['nombre'], 'request': req, 'event': [{'listen': 'test', 'script': {'type': 'text/javascript', 'exec': pruebas}}]}
    return it

carpetas = {}
for x in R:
    if x.get('omitir'):
        x2 = dict(x); x2['nombre'] = x['nombre'] + ' (no se ejecuta en el recorrido)'
        carpetas.setdefault(x['carpeta'], []).append(('omitir', x2))
    else:
        carpetas.setdefault(x['carpeta'], []).append(('normal', x))

coleccion = {
    'info': {
        'name': 'Sistema de Matrices de Riesgo — API',
        'description': open(os.path.join(REPO, 'docs/api/descripcion-coleccion.md'), encoding='utf-8').read(),
        'schema': 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    },
    'variable': [
        {'key': 'baseUrl', 'value': 'https://localhost:8443', 'description': 'URL del sistema (SPA y API en el mismo dominio)'},
        {'key': 'email', 'value': 'admin@prueba.local', 'description': 'Correo de un Administrador'},
        {'key': 'password', 'value': '', 'description': 'Contraseña de ese usuario (no se guarda en el repositorio)'},
        {'key': 'xsrf', 'value': '', 'description': 'Token CSRF; lo actualiza la colección en cada respuesta'},
    ],
    'event': [
        {'listen': 'prerequest', 'script': {'type': 'text/javascript', 'exec': PRE.split('\n')}},
        {'listen': 'test', 'script': {'type': 'text/javascript', 'exec': POST.split('\n')}},
    ],
    'item': [],
}
for nombre, lista in carpetas.items():
    normales = [item(x) for tipo, x in lista if tipo == 'normal']
    omitidos = [item(x) for tipo, x in lista if tipo == 'omitir']
    coleccion['item'].append({'name': nombre, 'item': normales})
    if omitidos:
        coleccion.setdefault('_omitidos', []).extend(omitidos)
# Las peticiones que no forman parte del recorrido van en una carpeta final.
omitidos = coleccion.pop('_omitidos', [])
if omitidos:
    coleccion['item'].append({'name': '12. Otras operaciones (fuera del recorrido)', 'item': omitidos})

json.dump(coleccion, open(os.path.join(REPO, 'docs/api/SistemaMatrizRiesgo.postman_collection.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=2)

filas = ['| Carpeta | Método | Ruta | Para qué | Quién puede |', '|---|---|---|---|---|']
for x in R:
    ruta = x['ruta'].replace('{{', '{').replace('}}', '}')
    filas.append(f"| {x['carpeta'].split('. ',1)[1]} | `{x['metodo']}` | `{ruta}` | {x['desc'].split('. ')[0].rstrip('.')}. | {x['quien']} |")
open(os.path.join(REPO, 'docs/api/.tabla.md'), 'w', encoding='utf-8').write('\n'.join(filas))
print(len(R), 'peticiones en', len(coleccion['item']), 'carpetas')
