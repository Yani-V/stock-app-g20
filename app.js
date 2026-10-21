// ==================================================================
// ZAFE - APP.JS - Logica principal del gestor de stock
// Grupo 20 - UPSO - 2026
// ==================================================================

// ------------------------------------------------------------------
// 1. CONFIGURACION - URLs de la API - CORREGIDO PARA RAILWAY
// ------------------------------------------------------------------
const URL_API = 'api/productos.php';
const URL_COTIZACION = 'api/cotizacion.php';

// ------------------------------------------------------------------
// 2. ELEMENTOS DEL DOM - Referencias a elementos del HTML
// ------------------------------------------------------------------
const $ = (id) => document.getElementById(id);
const el = {
  aviso: $('aviso'), estado: $('estado'), tabla: $('tabla'), lista: $('lista'),
  dlgForm: $('dlg-form'), form: $('form'), formTitulo: $('form-titulo'),
  formError: $('form-error'), btnGuardar: $('btn-guardar'), fId: $('f-id'),
  dlgBorrar: $('dlg-borrar'), borrarTexto: $('borrar-texto'), borrarSi: $('borrar-si'),
  toast: $('toast'),
};
const campos = el.form.elements;

// ------------------------------------------------------------------
// 3. VARIABLES GLOBALES - Estado de la app
// ------------------------------------------------------------------
let productos = [];
let idParaBorrar = null;
let cotizacion = null;

// ------------------------------------------------------------------
// 4. API FETCH - Conexion con el backend
// ------------------------------------------------------------------
async function apiFetch(url, metodo, cuerpo) {
  let respuesta;
  try {
    respuesta = await fetch(url, {
      method: metodo || 'GET',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: cuerpo,
    });
  } catch (e) {
    throw new Error('No se pudo conectar con el servidor. Revisá tu conexión e intentá de nuevo.');
  }
  let datos = null;
  if (respuesta.status !== 204) {
    try { datos = await respuesta.json(); } catch (e) { datos = null; }
  }
  if (!respuesta.ok) {
    const err = new Error(mensajeDeEstado(respuesta.status, datos));
    err.status = respuesta.status;
    throw err;
  }
  return datos;
}

function mensajeDeEstado(status, datos) {
  if (datos && datos.error) return datos.error;
  if (status === 400 || status === 422) return 'Hay datos inválidos. Revisá el formulario.';
  if (status === 404) return 'El producto no existe (puede que ya se haya borrado).';
  if (status >= 500) return 'Falló el servidor. Probá de nuevo en unos minutos.';
  return 'Ocurrió un error inesperado (código ' + status + ').';
}

// ------------------------------------------------------------------
// 5. AVISOS Y TOAST - Mensajes al usuario
// ------------------------------------------------------------------
function mostrarAviso(texto) { el.aviso.textContent = texto; el.aviso.hidden = false; }
function ocultarAviso() { el.aviso.hidden = true; }
let toastTimer;
function mostrarToast(texto) {
  el.toast.textContent = texto;
  el.toast.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.toast.hidden = true; }, 3500);
}

// ------------------------------------------------------------------
// 6. COTIZACION - Obtiene valor del dolar
// ------------------------------------------------------------------
async function obtenerCotizacion() {
  try {
    const datos = await apiFetch(URL_COTIZACION);
    const valor = Number(datos && datos.cotizacion);
    return valor > 0 ? valor : null;
  } catch (e) {
    return null;
  }
}

// ------------------------------------------------------------------
// 7. CARGA DE PRODUCTOS - Trae datos del backend - CORREGIDO PARA NO FALLAR SI NO HAY COTIZACION
// ------------------------------------------------------------------
async function cargarProductos() {
  el.estado.textContent = 'Cargando productos…';
  el.estado.hidden = false;
  try {
    const datosProductos = await apiFetch(URL_API);
    productos = Array.isArray(datosProductos) ? datosProductos : [];
    if (!cotizacion) {
      const nuevaCoti = await obtenerCotizacion();
      if (nuevaCoti) cotizacion = nuevaCoti;
    }
    ocultarAviso();
    dibujarLista();
  } catch (e) {
    el.tabla.hidden = true;
    el.estado.hidden = true;
    $('panel').hidden = true;
    $('nota-cotizacion').hidden = true;
    mostrarAviso(e.message);
  }
}

// ------------------------------------------------------------------
// 8. FILTROS Y BUSCADOR - Logica de filtrado local
// ------------------------------------------------------------------
let filtro = 'todos';
let busqueda = '';
const esBajo = (p) => Number(p.cantidad_stock) <= Number(p.stock_minimo);
const esVencido = (p, hoy) => Boolean(p.fecha_vencimiento) && p.fecha_vencimiento < hoy;
function coincide(p, hoy) {
  if (filtro === 'bajo' && !esBajo(p)) return false;
  if (filtro === 'vencidos' && !esVencido(p, hoy)) return false;
  if (busqueda === '') return true;
  const texto = [p.nombre, p.codigo_barras, p.proveedor, p.ubicacion].join(' ').toLowerCase();
  return texto.includes(busqueda);
}
function actualizarResumen(hoy) {
  $('r-total').textContent = productos.length;
  $('r-bajo').textContent = productos.filter(esBajo).length;
  $('r-vencidos').textContent = productos.filter((p) => esVencido(p, hoy)).length;
  document.querySelectorAll('.tarjeta').forEach((t) => {
    t.classList.toggle('activa', t.dataset.filtro === filtro);
  });
}

// ------------------------------------------------------------------
// 9. RENDER DE LA TABLA - Dibuja productos en pantalla
// ------------------------------------------------------------------
function dibujarLista() {
  const hoy = fechaDeHoy();
  while (el.lista.firstChild) { el.lista.removeChild(el.lista.firstChild); }
  $('panel').hidden = productos.length === 0;
  actualizarNotaCotizacion();
  if (productos.length === 0) {
    el.tabla.hidden = true;
    el.estado.textContent = 'Todavía no hay productos. Agregá el primero con “+ Producto”.';
    el.estado.hidden = false; return;
  }
  actualizarResumen(hoy);
  const visibles = productos.filter((p) => coincide(p, hoy));
  if (visibles.length === 0) {
    el.tabla.hidden = true;
    el.estado.textContent = 'Ningún producto coincide con la búsqueda o el filtro.';
    el.estado.hidden = false; return;
  }
  el.estado.hidden = true;
  el.tabla.hidden = false;
  for (const p of visibles) {
    const tr = document.createElement('tr');
    const tdNombre = document.createElement('td');
    const nombre = document.createElement('strong'); nombre.textContent = p.nombre;
    const detalle = document.createElement('div'); detalle.className = 'detalle'; detalle.textContent = p.proveedor + ' · ' + p.ubicacion;
    tdNombre.appendChild(nombre); tdNombre.appendChild(detalle);
    const tdCodigo = celda(p.codigo_barras, 'codigo', 'Código');
    const cantidad = document.createElement('div');
    const cant = document.createElement('strong'); cant.textContent = p.cantidad_stock;
    const minimo = document.createElement('span'); minimo.className = 'detalle'; minimo.textContent = ' / mín. ' + p.stock_minimo;
    cantidad.appendChild(cant); cantidad.appendChild(minimo);
    const hijosStock = esBajo(p) ? [cantidad, etiqueta('Stock bajo', 'alerta')] : [cantidad];
    const tdStock = celdaValor('Stock', 'num', hijosStock);
    const tdPrecio = celda(formatearPrecio(p.precio_unitario), 'num', 'Precio');
    const tdUsd = celda(formatearUsd(p.precio_unitario), 'num', 'Precio USD (ref.)');
    const fecha = document.createElement('div'); fecha.textContent = formatearFecha(p.fecha_vencimiento);
    const hijosVence = !p.fecha_vencimiento ? [fecha] : esVencido(p, hoy) ? [fecha, etiqueta('Vencido', 'peligro')] : [fecha, etiqueta('Vigente', 'ok')];
    const tdVence = celdaValor('Vencimiento', '', hijosVence);
    const tdAcc = document.createElement('td'); tdAcc.className = 'acciones';
    tdAcc.appendChild(crearBoton('Editar', () => abrirFormulario(p)));
    tdAcc.appendChild(crearBoton('Borrar', () => pedirBorrado(p)));
    [tdNombre, tdCodigo, tdStock, tdPrecio, tdUsd, tdVence, tdAcc].forEach((td) => tr.appendChild(td));
    el.lista.appendChild(tr);
  }
}

// ------------------------------------------------------------------
// 10. HELPERS DE CELDAS Y BOTONES
// ------------------------------------------------------------------
function celda(texto, clase, rotulo) {
  const td = document.createElement('td'); td.textContent = texto;
  if (clase) td.className = clase; if (rotulo) td.dataset.label = rotulo; return td;
}
function celdaValor(rotulo, clase, hijos) {
  const td = document.createElement('td'); td.dataset.label = rotulo; if (clase) td.className = clase;
  const valor = document.createElement('span'); valor.className = 'valor'; hijos.forEach((hijo) => valor.appendChild(hijo)); td.appendChild(valor); return td;
}
function etiqueta(texto, tipo) {
  const s = document.createElement('span'); s.className = 'etiqueta ' + tipo; s.textContent = texto; return s;
}
function crearBoton(texto, onClick) {
  const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-chico'; b.textContent = texto; b.addEventListener('click', onClick); return b;
}

// ------------------------------------------------------------------
// 11. FORMATOS - Precios y fechas
// ------------------------------------------------------------------
function formatearPrecio(valor) {
  const n = Number(valor); if (isNaN(n)) return '—';
  return n.toLocaleString('es-AR', { style: 'currency', currency: 'ARS' });
}
function formatearUsd(precioPesos) {
  if (!cotizacion) return '—';
  const n = Number(precioPesos) / cotizacion; if (isNaN(n)) return '—';
  return n.toLocaleString('es-AR', { style: 'currency', currency: 'USD' });
}
function actualizarNotaCotizacion() {
  const nota = $('nota-cotizacion'); nota.hidden = productos.length === 0;
  nota.textContent = cotizacion ? 'Precio USD de referencia: 1 USD = ' + formatearPrecio(cotizacion) : 'No se pudo obtener la cotización: el precio en USD no está disponible.';
}
function formatearFecha(iso) {
  if (!iso) return 'Sin vencimiento';
  const partes = iso.split('-'); return partes[2] + '/' + partes[1] + '/' + partes[0];
}
function fechaDeHoy() {
  const f = new Date(); const mes = String(f.getMonth() + 1).padStart(2, '0'); const dia = String(f.getDate()).padStart(2, '0');
  return f.getFullYear() + '-' + mes + '-' + dia;
}

// ------------------------------------------------------------------
// 12. FORMULARIO - Crear y editar producto
// ------------------------------------------------------------------
function abrirFormulario(producto) {
  el.formError.hidden = true; el.form.reset();
  if (producto) {
    el.formTitulo.textContent = 'Editar producto'; el.fId.value = producto.id;
    campos.nombre.value = producto.nombre; campos.codigo_barras.value = producto.codigo_barras;
    campos.cantidad_stock.value = producto.cantidad_stock; campos.stock_minimo.value = producto.stock_minimo;
    campos.precio_unitario.value = producto.precio_unitario; campos.fecha_vencimiento.value = producto.fecha_vencimiento || '';
    campos.proveedor.value = producto.proveedor; campos.ubicacion.value = producto.ubicacion;
  } else { el.formTitulo.textContent = 'Nuevo producto'; el.fId.value = ''; }
  el.dlgForm.showModal(); campos.nombre.focus();
}

// ------------------------------------------------------------------
// 13. GUARDAR - CON VALIDACION TODO OBLIGATORIO MENOS VENCIMIENTO
// ------------------------------------------------------------------
const numero = (texto) => (texto === '' ? null : Number(texto));
function armarCuerpo() {
  return {
    nombre: campos.nombre.value.trim(),
    codigo_barras: campos.codigo_barras.value.trim(),
    cantidad_stock: numero(campos.cantidad_stock.value),
    stock_minimo: numero(campos.stock_minimo.value),
    fecha_vencimiento: campos.fecha_vencimiento.value || null,
    proveedor: campos.proveedor.value.trim(),
    ubicacion: campos.ubicacion.value.trim(),
    precio_unitario: numero(campos.precio_unitario.value),
  };
}

async function guardar(evento) {
  evento.preventDefault();
  el.formError.hidden = true;

  // VALIDACION: TODO MENOS VENCIMIENTO
  if (!campos.nombre.value.trim()) { el.formError.textContent = 'El nombre es obligatorio.'; el.formError.hidden = false; return; }
  if (!campos.codigo_barras.value.trim()) { el.formError.textContent = 'El código de barras es obligatorio.'; el.formError.hidden = false; return; }
  if (!campos.cantidad_stock.value.trim()) { el.formError.textContent = 'La cantidad de stock es obligatoria.'; el.formError.hidden = false; return; }
  if (!campos.stock_minimo.value.trim()) { el.formError.textContent = 'El stock mínimo es obligatorio.'; el.formError.hidden = false; return; }
  if (!campos.precio_unitario.value.trim()) { el.formError.textContent = 'El precio es obligatorio.'; el.formError.hidden = false; return; }
  if (!campos.proveedor.value.trim()) { el.formError.textContent = 'El proveedor es obligatorio.'; el.formError.hidden = false; return; }
  if (!campos.ubicacion.value.trim()) { el.formError.textContent = 'La ubicación es obligatoria.'; el.formError.hidden = false; return; }

  const id = el.fId.value; const editando = id !== '';
  const cuerpo = JSON.stringify(armarCuerpo());
  el.btnGuardar.disabled = true;
  try {
    const url = editando ? URL_API + '?id=' + encodeURIComponent(id) : URL_API;
    const datos = await apiFetch(url, editando ? 'PUT' : 'POST', cuerpo);
    el.dlgForm.close();
    const aviso = datos && datos.aviso;
    mostrarToast(aviso || (editando ? 'Producto actualizado' : 'Producto creado'));
    await cargarProductos();
  } catch (e) {
    el.formError.textContent = e.message; el.formError.hidden = false;
    if (e.status === 404) cargarProductos();
  } finally { el.btnGuardar.disabled = false; }
}

// ------------------------------------------------------------------
// 14. BORRAR - Confirmacion y eliminacion
// ------------------------------------------------------------------
function pedirBorrado(producto) {
  idParaBorrar = producto.id;
  el.borrarTexto.textContent = '¿Seguro que querés borrar “' + producto.nombre + '”? No se puede deshacer.';
  el.dlgBorrar.showModal();
}
async function confirmarBorrado() {
  el.borrarSi.disabled = true;
  try {
    await apiFetch(URL_API + '?id=' + encodeURIComponent(idParaBorrar), 'DELETE');
    el.dlgBorrar.close(); mostrarToast('Producto borrado'); await cargarProductos();
  } catch (e) {
    el.dlgBorrar.close(); if (e.status === 404) await cargarProductos(); mostrarAviso(e.message);
  } finally { el.borrarSi.disabled = false; idParaBorrar = null; }
}

// ------------------------------------------------------------------
// 15. EVENTOS
// ------------------------------------------------------------------
$('btn-nuevo').addEventListener('click', () => abrirFormulario());
$('btn-cancelar').addEventListener('click', () => el.dlgForm.close());
$('borrar-no').addEventListener('click', () => el.dlgBorrar.close());
el.borrarSi.addEventListener('click', confirmarBorrado);
el.form.addEventListener('submit', guardar);
$('buscador').addEventListener('input', (e) => { busqueda = e.target.value.trim().toLowerCase(); dibujarLista(); });
document.querySelectorAll('.tarjeta').forEach((t) => {
  t.addEventListener('click', () => { filtro = t.dataset.filtro === filtro ? 'todos' : t.dataset.filtro; dibujarLista(); });
});

// ------------------------------------------------------------------
// 16. EFECTO CABECERA
// ------------------------------------------------------------------
let ultimoScroll = 0;
window.addEventListener('scroll', () => {
  const cabecera = $('cabecera'); const y = window.scrollY;
  cabecera.classList.toggle('con-sombra', y > 0);
  if (Math.abs(y - ultimoScroll) < 8) return;
  const alto = cabecera.offsetHeight; cabecera.style.setProperty('--alto-cabecera', alto + 'px');
  cabecera.classList.toggle('oculta', y > ultimoScroll && y > alto); ultimoScroll = y;
}, { passive: true });

// ------------------------------------------------------------------
// 17. ARRANQUE
// ------------------------------------------------------------------
cargarProductos();
