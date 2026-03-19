
let ultimoStatusBloqueio = null;
let intervaloVerificacao = null;
let caminhoChecagem = null;

function obterCaminhoChecagem() {
  if (caminhoChecagem === null) {
    const path = window.location.pathname;
    // Verifica se está numa subpasta (aba-ajuda, conta, admin, etc.) e ajusta o caminho
    if (path.includes('/aba-') || path.includes('/conta/') || path.includes('/admin/')) {
      caminhoChecagem = '../scripts/check_site_status.php';
    } else {
      caminhoChecagem = './scripts/check_site_status.php';
    }
  }
  return caminhoChecagem;
}

function verificarStatusSite() {
  const caminho = obterCaminhoChecagem();
  
  (async function() {
    try {
      const res = await fetch(caminho, { credentials: 'include', cache: 'no-store' });
      if (!res.ok) {
        const txt = await res.text().catch(() => '');
        console.warn('check_site_status HTTP', res.status, txt);
        return;
      }
      let data;
      try {
        data = await res.json();
      } catch (e) {
        const txt = await res.text().catch(() => '');
        console.warn('check_site_status invalid JSON', e, txt);
        return;
      }

      if (data.bloqueado && !data.isAdmin && data.isAuthenticated) {
        if (ultimoStatusBloqueio !== true) {
          ultimoStatusBloqueio = true;
          redireccionarParaBloqueio();
        }
      } else if (!data.bloqueado) {
        ultimoStatusBloqueio = false;
      }
    } catch (error) {
      console.warn('Erro ao verificar status do site:', error);
    }
  })();
}

function redireccionarParaBloqueio() {
  const urlAtual = window.location.pathname;
  const isJaEmBloqueio = 
    urlAtual.includes('site_bloqueado.php') || 
    urlAtual.includes('blocked.php');
  
  if (!isJaEmBloqueio) {
    localStorage.setItem('motivo_bloqueio', 'site_foi_bloqueado');
    
    if (window.location.pathname.includes('/aba-')) {
      window.location.href = '../site_bloqueado.php';
    } else {
      window.location.href = './site_bloqueado.php';
    }
  }
}

function iniciarMonitorizacao() {
  if (intervaloVerificacao === null) {
    verificarStatusSite();
    intervaloVerificacao = setInterval(verificarStatusSite, 10000);
  }
}

function pararMonitorizacao() {
  if (intervaloVerificacao !== null) {
    clearInterval(intervaloVerificacao);
    intervaloVerificacao = null;
  }
}

window.addEventListener('focus', () => {
  iniciarMonitorizacao();
});

window.addEventListener('blur', () => {
  pararMonitorizacao();
});

document.addEventListener('DOMContentLoaded', () => {
  iniciarMonitorizacao();
});

window.addEventListener('beforeunload', () => {
  pararMonitorizacao();
});
