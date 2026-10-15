// js/marcaje.js
const nfcInput = document.getElementById('nfcInput');
const textoEspera = document.querySelector('.texto-espera-aula');
const listaHistorial = document.getElementById('listaHistorial');

// Mantener el foco siempre en el input oculto
function enfocar() { if (nfcInput) nfcInput.focus(); }
enfocar();
document.addEventListener('click', enfocar);
window.addEventListener('blur', enfocar);
setInterval(enfocar, 1000);

if (nfcInput) {
  nfcInput.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
      const uid = nfcInput.value.trim();
      nfcInput.value = '';
      if (!uid) return;
      await procesarMarcaje(uid);
    }
  });
}

async function procesarMarcaje(uid) {
  try {
    const res = await fetch('registrar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'uid=' + encodeURIComponent(uid)
    });
    const data = await res.json();
    mostrarResultado(data);
  } catch (err) {
    console.error('Error al registrar marcaje:', err);
  }
}

function mostrarResultado(data) {
  if (!textoEspera) return;

  if (data.encontrado) {
    const estadoTxt = data.duplicado ? ' (Ya registrado hoy)' : ' (Presente)';
    textoEspera.textContent = `${data.nombre} ${data.apellido} — ${data.seccion}${estadoTxt}`;
    textoEspera.style.color = '#2e7d32';
    agregarHistorial(`${data.nombre} ${data.apellido} — ${data.seccion}`, data.hora, true);
  } else {
    textoEspera.textContent = `Tarjeta desconocida (${data.uid})`;
    textoEspera.style.color = '#c62828';
    agregarHistorial('Tarjeta desconocida (' + data.uid + ')', data.hora, false);
  }

  // Volver a "Esperando tarjeta..." después de 3.5 segundos
  setTimeout(() => {
    textoEspera.textContent = 'Esperando tarjeta...';
    textoEspera.style.color = '#355E2B';
    enfocar();
  }, 3500);
}

function agregarHistorial(texto, hora, ok) {
  if (!listaHistorial) return;
  const li = document.createElement('li');
  li.innerHTML = `<span>${texto}</span><span>${hora}</span>`;
  li.style.borderLeft = ok ? '4px solid #2e7d32' : '4px solid #c62828';
  listaHistorial.prepend(li);
}

// Toggle del sidebar (compartido con admin.php y asistencia.php)
const btnMenu = document.getElementById('btnMenu');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const btnCerrarMenu = document.getElementById('btnCerrarMenu');

if (btnMenu) {
  btnMenu.addEventListener('click', () => { sidebar.classList.add('abierto'); overlay.classList.add('visible'); });
  btnCerrarMenu.addEventListener('click', cerrarSidebar);
  overlay.addEventListener('click', cerrarSidebar);
}
function cerrarSidebar() { if (sidebar) sidebar.classList.remove('abierto'); if (overlay) overlay.classList.remove('visible'); }
