// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
  // Acordeão com animação melhorada
  document.querySelectorAll('.question').forEach(button => {
    button.addEventListener('click', () => {
      const answer = button.nextElementSibling;
      const isOpen = answer.classList.contains('open');
      
      // Fechar todas as outras respostas
      document.querySelectorAll('.answer.open').forEach(openAnswer => {
        if (openAnswer !== answer) {
          openAnswer.classList.remove('open');
          openAnswer.previousElementSibling.classList.remove('active');
        }
      });
      
      // Toggle da resposta atual
      answer.classList.toggle('open');
      button.classList.toggle('active');
      
      // Scroll suave para a pergunta se estiver a abrir
      if (!isOpen) {
        setTimeout(() => {
          button.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'nearest' 
          });
        }, 100);
      }
    });
  });

  // Filtro de pesquisa com animação e destaque
  const searchInput = document.getElementById('searchInput');
  searchInput.addEventListener('input', function () {
    const query = this.value.toLowerCase().trim();
    const faqItems = document.querySelectorAll('.faq-item');
    let visibleCount = 0;
    
    faqItems.forEach((item, index) => {
      const questionText = item.querySelector('.question').textContent.toLowerCase();
      const answerText = item.querySelector('.answer').textContent.toLowerCase();
      const matches = questionText.includes(query) || answerText.includes(query);
      
      if (matches || query === '') {
        item.style.display = 'block';
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('fade-in');
        visibleCount++;
      } else {
        item.style.display = 'none';
        item.classList.remove('fade-in');
      }
    });
    
    // Mostrar mensagem se não houver resultados
    showNoResultsMessage(visibleCount === 0 && query !== '');
  });
  
  // Limpar pesquisa ao pressionar Escape
  searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      this.value = '';
      this.dispatchEvent(new Event('input'));
      this.blur();
    }
  });
});

// Função para mostrar/ocultar mensagem de "sem resultados"
function showNoResultsMessage(show) {
  let noResultsMsg = document.querySelector('.no-results-message');
  
  if (show && !noResultsMsg) {
    noResultsMsg = document.createElement('div');
    noResultsMsg.className = 'no-results-message';
    noResultsMsg.innerHTML = `
      <div class="no-results-content">
        <div class="no-results-icon">🔍</div>
        <h3>Nenhum resultado encontrado</h3>
        <p>Tenta usar palavras-chave diferentes ou contacta o suporte para mais ajuda.</p>
      </div>
    `;
    document.querySelector('.faq-container').appendChild(noResultsMsg);
  } else if (!show && noResultsMsg) {
    noResultsMsg.remove();
  }
}