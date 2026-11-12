// Núcleo de la página: carga los datos del backend, cambia de sección y ofrece
// utilidades comunes. Cada función del proyecto vive en su propio archivo y se
// registra aquí con App.registrar(nombre, modulo).

const NIVELES = {
  verde:    { nombre: 'Riesgo bajo',  color: '#16a34a', clase: 'nivel-verde' },
  amarillo: { nombre: 'Riesgo medio', color: '#eab308', clase: 'nivel-amarillo' },
  rojo:     { nombre: 'Riesgo alto',  color: '#dc2626', clase: 'nivel-rojo' }
};
// Estos nombres corresponden a los valores ENUM de la base aqua_sentinel.
const NOMBRE_TIPO = { inundacion: 'Inundación', encharcamiento: 'Encharcamiento', fuga: 'Fuga', falta_agua: 'Falta de agua', alcantarilla: 'Alcantarilla', otro: 'Otro' };
const NOMBRE_URGENCIA = { bajo: 'Baja', medio: 'Media', alto: 'Alta', critico: 'Crítica' };
const NOMBRE_METODO = { ia: 'IA', palabras_clave: 'Palabras clave (sin IA)' };
const SIN_ATENDER = ['pendiente', 'en_revision'];
const CENTRO_MAPA = [19.17, -96.15];

const App = {
  usuario: null,           // quien inició sesión: { id, nombre, rol, permisos }
  simulada: null,          // mm de lluvia simulada, o null para usar el pronóstico real
  seccion: 'radar',
  modulos: {},
  datos: { colonias: [], reportes: [], fugas: [], alertas: [], puntos: [], lluviaDisponible: true, radioFuga: 300 },

  registrar(nombre, modulo) { this.modulos[nombre] = modulo; },

  // Permisos del rol (tabla rol_permiso). Esto solo decide qué se MUESTRA;
  // quien de verdad autoriza cada acción es el servidor (lib/sesion.php).
  puede(permiso) { return !!this.usuario && this.usuario.permisos.includes(permiso); },
  gestiona(permiso) { return this.puede(permiso) && this.usuario.rol !== 'Ciudadano'; },

  // Crea un elemento con texto seguro: textContent evita que un reporte inyecte HTML.
  crear(etiqueta, texto, clase) {
    const el = document.createElement(etiqueta);
    if (texto !== undefined && texto !== null) el.textContent = texto;
    if (clase) el.className = clase;
    return el;
  },

  etiquetaNivel(nivel) {
    return this.crear('span', NIVELES[nivel].nombre, 'badge badge-sm text-white ' + NIVELES[nivel].clase);
  },

  // Agrega la lluvia simulada a la URL cuando la simulación está activa.
  url(ruta, parametros) {
    const p = new URLSearchParams(parametros || {});
    if (this.simulada !== null) p.set('lluvia_simulada', this.simulada);
    const cola = p.toString();
    return 'api/' + ruta + (cola ? '?' + cola : '');
  },

  async pedir(url, cuerpo) {
    const opciones = cuerpo === undefined ? {} : {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(cuerpo)
    };
    const respuesta = await fetch(url, opciones);
    let datos = null;
    try { datos = await respuesta.json(); } catch (e) { /* la respuesta no era JSON */ }
    // 401 = la sesión se cerró o venció: se vuelve a pedir el inicio de sesión.
    if (respuesta.status === 401 && this.usuario) location.reload();
    if (!respuesta.ok || datos === null) throw new Error((datos && datos.error) || 'Error del servidor');
    return datos;
  },

  crearMapa(id) {
    const mapa = L.map(id).setView(CENTRO_MAPA, 12);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; colaboradores de OpenStreetMap'
    }).addTo(mapa);
    // Si la caja del mapa cambia de tamaño (celular, menú, cambio de sección), Leaflet se reajusta.
    if ('ResizeObserver' in window) new ResizeObserver(() => mapa.invalidateSize()).observe(document.getElementById(id));
    return mapa;
  },

  // Aviso flotante en la esquina; se quita solo a los 9 segundos.
  avisar(titulo, mensaje, nivel) {
    const aviso = this.crear('div', undefined, 'aviso' + (nivel === 'rojo' ? ' rojo' : ''));
    aviso.append(this.crear('strong', titulo), this.crear('span', mensaje || ''));
    document.getElementById('avisos').append(aviso);
    setTimeout(() => aviso.remove(), 9000);
  },

  mostrar(seccion) {
    this.seccion = seccion;
    document.querySelectorAll('.seccion').forEach(el => {
      const activa = el.dataset.seccion === seccion;
      el.classList.toggle('oculta', !activa);
      if (activa) {
        document.getElementById('tituloSeccion').textContent = el.dataset.titulo;
        document.getElementById('migaSeccion').textContent = el.dataset.titulo;
      }
    });
    document.querySelectorAll('[data-ir]').forEach(a => a.classList.toggle('active', a.dataset.ir === seccion));
    document.body.classList.remove('g-sidenav-pinned'); // cierra el menú en celular
    const modulo = this.modulos[seccion];
    if (modulo && modulo.alMostrar) modulo.alMostrar();
    this.pintar();
  },

  // Pide todo al backend y vuelve a dibujar.
  async cargar() {
    try {
      // Los puntos seguros solo se piden si el rol tiene ese permiso (el Operador de Agua no lo tiene).
      const [estado, reportes, fugas, alertas, puntos] = await Promise.all([
        this.pedir(this.url('colonias.php')),
        this.pedir('api/reportes.php'),
        this.pedir('api/fugas.php'),
        this.pedir(this.url('alertas.php')),
        this.puede('puntos_seguros') ? this.pedir('api/puntos.php') : { puntos: [] }
      ]);
      this.datos = {
        colonias: estado.colonias, lluviaDisponible: estado.lluvia_disponible,
        reportes: reportes.reportes, fugas: fugas.fugas, radioFuga: fugas.radio_metros,
        alertas: alertas.alertas, puntos: puntos.puntos
      };
      document.getElementById('bannerError').classList.add('d-none');
      document.getElementById('horaActualizacion').textContent = 'Actualizado ' + estado.actualizado.slice(11, 16);
      this.pintar();
    } catch (error) {
      document.getElementById('bannerError').classList.remove('d-none');
    }
  },

  pintar() {
    const d = this.datos;
    // Avisos generales
    document.getElementById('bannerSimulacion').classList.toggle('d-none', this.simulada === null);
    document.getElementById('textoSimulacion').textContent = 'Se está usando una lluvia inventada de ' + this.simulada + ' mm en 6 horas.';
    document.getElementById('bannerLluvia').classList.toggle('d-none', d.lluviaDisponible);

    // Indicadores de arriba
    const lluvias = d.colonias.map(c => c.lluvia_mm_6h).filter(mm => mm !== null);
    document.getElementById('kpiRojas').textContent = d.colonias.filter(c => c.nivel === 'rojo').length + ' de ' + d.colonias.length;
    document.getElementById('kpiLluvia').textContent = lluvias.length ? Math.max(...lluvias) + ' mm' : 'Sin dato';
    document.getElementById('kpiReportes').textContent = d.reportes.filter(r => SIN_ATENDER.includes(r.estado)).length;
    document.getElementById('kpiFugas').textContent = d.fugas.filter(f => f.confianza !== 'aislado').length;

    // Las alertas se revisan siempre (campana y notificaciones); lo demás, solo la sección visible.
    if (this.modulos.alertas) this.modulos.alertas.revisar();
    const modulo = this.modulos[this.seccion];
    if (modulo && modulo.pintar) modulo.pintar();
  },

  simular(mm) {
    this.simulada = mm;
    // Se deja registro en la tabla simulaciones; si falla, la simulación en pantalla sigue.
    if (mm !== null) this.pedir('api/simulaciones.php', { lluvia_mm: mm }).catch(() => {});
    return this.cargar();
  },

  // ---------- Inicio de sesión ----------

  // Lo primero al abrir la página: preguntar al servidor si hay una sesión abierta.
  async iniciar() {
    this.prepararSesion();
    try {
      const sesion = await this.pedir('api/sesion.php');
      this.puedeReportar = sesion.puede_reportar === true;
      if (sesion.usuario) return this.arrancar(sesion.usuario);
      document.getElementById('avisoPrimerUsuario').classList.toggle('d-none', sesion.hay_usuarios);
      if (!sesion.hay_usuarios) this.verFormulario('registro');
      this.prepararRegistro(sesion);
    } catch (error) {
      document.getElementById('errorSesion').textContent = 'No se pudo conectar con el servidor. Revisa que Apache y MySQL estén encendidos.';
    }
    document.getElementById('pantallaSesion').classList.remove('d-none');
  },

  verFormulario(cual) {
    document.getElementById('formularioEntrar').classList.toggle('d-none', cual !== 'entrar');
    document.getElementById('formularioRegistro').classList.toggle('d-none', cual !== 'registro');
    document.getElementById('errorSesion').textContent = '';
  },

  // Llena el formulario de registro: colonias y tipos de cuenta que ofrece el servidor.
  prepararRegistro(sesion) {
    const colonia = document.getElementById('registroColonia');
    const ninguna = this.crear('option', 'Prefiero no decirlo');
    ninguna.value = '';
    colonia.append(ninguna);
    (sesion.colonias || []).forEach(c => {
      const opcion = this.crear('option', c.nombre);
      opcion.value = c.id;
      colonia.append(opcion);
    });
    document.getElementById('campoColonia').classList.toggle('d-none', !(sesion.colonias || []).length);

    // El tipo de cuenta solo se pregunta si hay más de una opción y ya existe un administrador.
    const rol = document.getElementById('registroRol');
    const roles = sesion.roles_registro || ['Ciudadano'];
    roles.forEach(nombre => rol.append(this.crear('option', nombre)));
    rol.value = 'Ciudadano';
    document.getElementById('campoRol').classList.toggle('d-none', roles.length < 2 || !sesion.hay_usuarios);
    rol.addEventListener('change', () => {
      // La clave de autoridad solo se pide si el servidor la tiene configurada (REGISTRO_CLAVE).
      const autoridad = rol.value !== 'Ciudadano';
      document.getElementById('campoClave').classList.toggle('d-none', !autoridad || !sesion.clave_configurada);
    });
  },

  prepararSesion() {
    const valor = id => document.getElementById(id).value;
    // Tras entrar o salir se recarga la página: así todo arranca limpio con el usuario nuevo.
    const enviar = async cuerpo => {
      try {
        await this.pedir('api/sesion.php', cuerpo);
        location.reload();
      } catch (error) {
        document.getElementById('errorSesion').textContent = error.message;
      }
    };
    document.getElementById('irARegistro').addEventListener('click', () => this.verFormulario('registro'));
    document.getElementById('irAEntrar').addEventListener('click', () => this.verFormulario('entrar'));
    document.getElementById('formularioEntrar').addEventListener('submit', e => {
      e.preventDefault();
      enviar({ accion: 'entrar', correo: valor('entrarCorreo'), contrasena: valor('entrarContrasena') });
    });
    document.getElementById('formularioRegistro').addEventListener('submit', e => {
      e.preventDefault();
      enviar({ accion: 'registrar', nombre: valor('registroNombre'), apellido: valor('registroApellido'),
               correo: valor('registroCorreo'), telefono: valor('registroTelefono'), contrasena: valor('registroContrasena'),
               id_colonia: Number(valor('registroColonia')) || 0, rol: valor('registroRol') || 'Ciudadano',
               clave_autoridad: valor('registroClave') });
    });
    document.getElementById('botonSalir').addEventListener('click', () => enviar({ accion: 'salir' }));
  },

  // Ya con sesión: se muestran solo las secciones que el rol puede usar y se cargan los datos.
  arrancar(usuario) {
    this.usuario = usuario;
    document.getElementById('nombreUsuario').textContent = usuario.nombre;
    document.getElementById('rolUsuario').textContent = usuario.rol;

    const permitidas = [];
    document.querySelectorAll('[data-ir]').forEach(a => {
      const permitido = a.dataset.gestion ? this.gestiona(a.dataset.gestion) : this.puede(a.dataset.permiso);
      a.closest('li').classList.toggle('d-none', !permitido);
      if (permitido) permitidas.push(a.dataset.ir);
      a.addEventListener('click', () => this.mostrar(a.dataset.ir));
    });
    document.getElementById('tituloAutoridades').classList.toggle('d-none',
      !permitidas.includes('centro') && !permitidas.includes('usuarios'));

    document.getElementById('botonQuitarSimulacion').addEventListener('click', () => this.simular(null));
    permitidas.forEach(nombre => this.modulos[nombre] && this.modulos[nombre].iniciar && this.modulos[nombre].iniciar());
    // Las alertas se revisan siempre (campana), aunque su sección no esté abierta.
    this.mostrar(permitidas[0]);
    this.cargar();
    setInterval(() => this.cargar(), 20000); // se actualiza sola cada 20 segundos
  }
};
