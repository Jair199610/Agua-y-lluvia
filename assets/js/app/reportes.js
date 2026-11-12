// Historial de reportes por colonia (sección "Reportes", solo autoridades).
// Usa los reportes que ya cargó App (los últimos 100 de api/reportes.php): no pide nada extra.
// La lista de colonias del filtro la llena index.php desde la base; este archivo NO toca
// ningún otro selector de colonia (antes llenaba por error el del formulario del Radar).

const ORDEN_URGENCIA = { bajo: 1, medio: 2, alto: 3, critico: 4 };
const NOMBRE_ESTADO = { pendiente: 'Pendiente', en_revision: 'En revisión', atendido: 'Atendido', descartado: 'Descartado' };

App.registrar('reportes', {
  iniciar() {
    const tipo = document.getElementById('filtroTipoReporte');
    Object.entries(NOMBRE_TIPO).forEach(([valor, nombre]) => {
      const opcion = App.crear('option', nombre);
      opcion.value = valor;
      tipo.append(opcion);
    });
    tipo.addEventListener('change', () => this.pintar());
    document.getElementById('filtroColoniaReporte').addEventListener('change', () => this.pintar());
    document.getElementById('botonDescargarPDF').addEventListener('click', () => this.descargarPDF());
  },

  // Reportes que cumplen los dos filtros, del más nuevo al más viejo.
  filtrados() {
    const colonia = document.getElementById('filtroColoniaReporte').value;
    const tipo = document.getElementById('filtroTipoReporte').value;
    return App.datos.reportes.filter(r =>
      (colonia === '' || r.colonia_id === Number(colonia)) && (tipo === '' || r.tipo === tipo));
  },

  // Texto que describe los filtros elegidos (se usa en el resumen y en el PDF).
  descripcionFiltros() {
    const colonia = document.getElementById('filtroColoniaReporte');
    const tipo = document.getElementById('filtroTipoReporte');
    return colonia.selectedOptions[0].text.trim() + ' · ' + tipo.selectedOptions[0].text.trim();
  },

  // Una fila por tipo: cuántos, primero, último y urgencia más alta.
  resumenPorTipo(reportes) {
    const grupos = {};
    reportes.forEach(r => {
      const g = grupos[r.tipo] || (grupos[r.tipo] = { tipo: r.tipo, total: 0, primero: r.creado_en, ultimo: r.creado_en, urgencia: r.urgencia });
      g.total++;
      if (r.creado_en < g.primero) g.primero = r.creado_en;
      if (r.creado_en > g.ultimo) g.ultimo = r.creado_en;
      if ((ORDEN_URGENCIA[r.urgencia] || 0) > (ORDEN_URGENCIA[g.urgencia] || 0)) g.urgencia = r.urgencia;
    });
    return Object.values(grupos).sort((a, b) => b.total - a.total);
  },

  celda(texto) { return App.crear('td', texto, 'text-sm'); },

  pintar() {
    const reportes = this.filtrados();
    const resumen = this.resumenPorTipo(reportes);

    document.getElementById('resumenReportes').textContent = reportes.length
      ? reportes.length + ' reportes (' + this.descripcionFiltros() + '). Se consideran los últimos 100 reportes del sistema.'
      : 'No hay reportes con esos filtros.';

    const cuerpoResumen = document.getElementById('tablaHistorial');
    cuerpoResumen.replaceChildren();
    resumen.forEach(g => {
      const fila = document.createElement('tr');
      fila.append(this.celda(NOMBRE_TIPO[g.tipo] || g.tipo), this.celda(String(g.total)),
                  this.celda(g.primero.slice(0, 16)), this.celda(g.ultimo.slice(0, 16)),
                  this.celda(NOMBRE_URGENCIA[g.urgencia] || g.urgencia));
      cuerpoResumen.append(fila);
    });

    const cuerpo = document.getElementById('tablaReportesDescargar');
    cuerpo.replaceChildren();
    reportes.forEach(r => {
      const fila = document.createElement('tr');
      const descripcion = this.celda(r.texto);
      descripcion.style.whiteSpace = 'normal';
      fila.append(this.celda(r.creado_en.slice(0, 16)), this.celda(NOMBRE_TIPO[r.tipo] || r.tipo),
                  this.celda(r.colonia), descripcion,
                  this.celda(NOMBRE_URGENCIA[r.urgencia] || r.urgencia), this.celda(NOMBRE_ESTADO[r.estado] || r.estado));
      cuerpo.append(fila);
    });
  },

  // "Exportar a PDF": arma una página sencilla con las dos tablas y abre la impresión del
  // navegador, donde se elige "Guardar como PDF". No necesita librerías extra.
  descargarPDF() {
    this.pintar();
    const marco = document.createElement('iframe');
    marco.style.cssText = 'position:fixed;width:0;height:0;border:0;';
    document.body.append(marco);
    const doc = marco.contentDocument;
    doc.open();
    doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Historial de reportes</title></head><body></body></html>');
    doc.close();

    const estilo = doc.createElement('style');
    estilo.textContent = 'body{font-family:Arial,sans-serif;font-size:11px;margin:24px}h1{font-size:16px;margin:0 0 4px}'
      + 'p{margin:0 0 12px;color:#555}table{border-collapse:collapse;width:100%;margin-bottom:18px}'
      + 'caption{text-align:left;font-weight:bold;margin-bottom:4px}th,td{border:1px solid #ccc;padding:4px;text-align:left;vertical-align:top}th{background:#eee}';
    doc.head.append(estilo);

    const titulo = doc.createElement('h1');
    titulo.textContent = 'Aqua Sentinel · Historial de reportes';
    const datos = doc.createElement('p');
    datos.textContent = this.descripcionFiltros() + ' · Generado ' + new Date().toLocaleString('es-MX');
    doc.body.append(titulo, datos);
    // Se copian las tablas ya dibujadas (su texto se puso con textContent, así que es seguro).
    document.querySelectorAll('[data-seccion="reportes"] table').forEach(t => doc.body.append(doc.importNode(t, true)));

    marco.contentWindow.focus();
    marco.contentWindow.print();
    setTimeout(() => marco.remove(), 1000);
  }
});
