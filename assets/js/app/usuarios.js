// Administración de usuarios (permiso "usuarios": solo el Administrador).
// Muestra los usuarios agrupados por colonia, con buscador y filtros, y permite crear
// usuarios y cambiar su estado. Usa las tablas usuarios, roles y colonias (api/usuarios.php).

const ESTADOS_USUARIO = { activo: 'Activo', inactivo: 'Inactivo', bloqueado: 'Bloqueado' };
const SIN_COLONIA = 'Sin colonia registrada';

App.registrar('usuarios', {
  lista: [], yo: null, primeraVez: true,

  iniciar() {
    document.getElementById('formularioUsuario').addEventListener('submit', e => this.crear(e));
    // Los filtros trabajan sobre la lista ya cargada: no hace falta volver a pedirla al servidor.
    ['buscarUsuario', 'filtroColonia', 'filtroRol'].forEach(id => {
      document.getElementById(id).addEventListener('input', () => this.pintarTabla());
    });
  },

  alMostrar() { this.cargar(); },

  async cargar() {
    try {
      const r = await App.pedir('api/usuarios.php');
      this.lista = r.usuarios;
      this.yo = r.yo;
      this.pintarRoles(r.roles);
      this.pintarFiltros(r.roles);
      this.pintarTabla();
    } catch (error) {
      document.getElementById('resultadoUsuario').textContent = error.message;
    }
  },

  pintarRoles(roles) {
    const selector = document.getElementById('usuarioRol');
    if (selector.options.length) return; // ya estaba llena
    roles.forEach(rol => {
      const opcion = App.crear('option', rol.nombre);
      opcion.value = rol.id;
      // Por seguridad, el rol que sale elegido no es el de Administrador.
      opcion.selected = rol.nombre === 'Proteccion Civil';
      selector.append(opcion);
    });
  },

  // Llena las listas de filtro conservando lo que ya estaba elegido.
  pintarFiltros(roles) {
    const llenar = (id, primera, opciones) => {
      const selector = document.getElementById(id);
      const elegido = selector.value;
      selector.replaceChildren();
      [[primera, ''], ...opciones].forEach(([texto, valor]) => {
        const opcion = App.crear('option', texto);
        opcion.value = valor;
        selector.append(opcion);
      });
      selector.value = [...selector.options].some(o => o.value === elegido) ? elegido : '';
    };
    // Cada colonia muestra cuántos usuarios tiene.
    // (el total de cada colonia cuenta solo ciudadanos)
    const conteo = {};
    this.lista.filter(u => u.rol === 'Ciudadano').forEach(u => { const c = u.colonia || SIN_COLONIA; conteo[c] = (conteo[c] || 0) + 1; });
    const colonias = Object.keys(conteo).sort((a, b) => a.localeCompare(b, 'es'));
    llenar('filtroColonia', 'Todas las colonias', colonias.map(c => [c + ' (' + conteo[c] + ')', c]));
    llenar('filtroRol', 'Todos los roles', roles.map(r => [r.nombre, r.nombre]));
    // Al abrir la sección se muestran solo los ciudadanos; el personal se ve cambiando el filtro de rol.
    if (this.primeraVez) {
      document.getElementById('filtroRol').value = 'Ciudadano';
      this.primeraVez = false;
    }
  },

  // Quita acentos y mayúsculas para que "jose" encuentre a "José".
  normalizar(texto) {
    return (texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  },

  pintarTabla() {
    const buscado = this.normalizar(document.getElementById('buscarUsuario').value.trim());
    const colonia = document.getElementById('filtroColonia').value;
    const rol = document.getElementById('filtroRol').value;

    const filtrados = this.lista.filter(u => {
      const texto = this.normalizar([u.nombre, u.apellido, u.correo, u.telefono].join(' '));
      return (!buscado || texto.includes(buscado))
        && (!colonia || (u.colonia || SIN_COLONIA) === colonia)
        && (!rol || u.rol === rol);
    });
    const ciudadanos = this.lista.filter(u => u.rol === 'Ciudadano').length;
    document.getElementById('resumenUsuarios').textContent = 'Mostrando ' + filtrados.length + ' de ' + this.lista.length
      + ' cuentas (' + ciudadanos + ' ciudadanos). Para ver al personal, cambia el filtro de rol.';

    // Agrupados por colonia, en orden alfabético; los que no tienen colonia van al final.
    const grupos = new Map();
    filtrados.forEach(u => {
      const c = u.colonia || SIN_COLONIA;
      if (!grupos.has(c)) grupos.set(c, []);
      grupos.get(c).push(u);
    });
    const nombres = [...grupos.keys()].sort((a, b) => (a === SIN_COLONIA) - (b === SIN_COLONIA) || a.localeCompare(b, 'es'));

    const tabla = document.getElementById('tablaUsuarios');
    tabla.replaceChildren();
    if (!filtrados.length) {
      const fila = App.crear('tr');
      const celda = App.crear('td', 'Ningún usuario coincide con la búsqueda.', 'text-sm text-secondary ps-4');
      celda.colSpan = 5;
      fila.append(celda);
      tabla.append(fila);
      return;
    }
    nombres.forEach(nombre => {
      const encabezado = App.crear('tr');
      const cuantos = grupos.get(nombre).length;
      const celda = App.crear('th', nombre + ' · ' + cuantos + (cuantos === 1 ? ' usuario' : ' usuarios'), 'text-sm ps-4 bg-gray-100');
      celda.colSpan = 5;
      celda.scope = 'colgroup';
      encabezado.append(celda);
      tabla.append(encabezado);
      grupos.get(nombre).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')).forEach(u => tabla.append(this.fila(u)));
    });
  },

  fila(u) {
    const fila = App.crear('tr');
    const nombre = App.crear('td', undefined, 'ps-4');
    nombre.append(App.crear('p', u.nombre + (u.apellido ? ' ' + u.apellido : '') + (u.id === this.yo ? ' (tú)' : ''), 'text-sm font-weight-bold mb-0'),
      App.crear('p', u.correo, 'text-xs text-secondary mb-0'));
    fila.append(nombre, App.crear('td', u.telefono || '—', 'text-sm'), App.crear('td', u.rol, 'text-sm'),
      App.crear('td', u.ultimo_acceso ? u.ultimo_acceso.slice(0, 16) : 'Nunca', 'text-xs'));

    if (u.id === this.yo) {
      fila.append(App.crear('td', ESTADOS_USUARIO[u.estado], 'text-sm')); // no puedes desactivarte a ti mismo
    } else {
      fila.append(App.modulos.centro.selector(ESTADOS_USUARIO, u.estado, 'Estado de ' + u.nombre, async estado => {
        await App.pedir('api/usuarios.php', { accion: 'estado', id: u.id, estado });
        await this.cargar();
      }));
    }
    return fila;
  },

  async crear(evento) {
    evento.preventDefault();
    const valor = id => document.getElementById(id).value;
    const resultado = document.getElementById('resultadoUsuario');
    try {
      await App.pedir('api/usuarios.php', {
        nombre: valor('usuarioNombre'), apellido: valor('usuarioApellido'), correo: valor('usuarioCorreo'),
        contrasena: valor('usuarioContrasena'), id_rol: Number(valor('usuarioRol'))
      });
      resultado.textContent = 'Usuario creado. Entrégale su correo y contraseña en persona.';
      evento.target.reset();
      await this.cargar();
    } catch (error) {
      resultado.textContent = 'No se pudo crear: ' + error.message;
    }
  }
});
