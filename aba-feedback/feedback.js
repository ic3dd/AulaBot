// Inicializa o script quando o DOM estiver completamente carregado
document.addEventListener("DOMContentLoaded", () => {
  // Referências aos elementos do DOM
  const form = document.getElementById("feedbackForm");
  const mensagem = document.getElementById("mensagem");

  if (!form) {
    console.error("Formulário 'feedbackForm' não encontrado.");
    return;
  }

  // Manipulador do evento de envio do formulário
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    // Recolhe os valores dos campos
    const getValue = (id) => document.getElementById(id)?.value.trim() || "";
    const nome = getValue("nome");
    const email = getValue("email");
    const gostou = getValue("gostou");
    const melhoria = getValue("melhoria");
    const autorizacao = document.querySelector("input[name='autorizacao']")?.checked;
    const rating = document.querySelector("input[name='rating']:checked")?.value || "";

    // Validações básicas
    if (!autorizacao) return alert("Autorize o uso do feedback.");
    if (!rating) return alert("Selecione uma classificação.");
    if (!nome || !email) return alert("Preencha nome e email.");

    // Prepara os dados para envio
    // Usa FormData para facilitar o envio via POST
    const formData = new FormData();
    formData.append("nome", nome);
    formData.append("email", email);
    formData.append("rating", rating);
    formData.append("gostou", gostou);
    formData.append("melhoria", melhoria);
    formData.append("autorizacao", "sim");

    try {
      // Envia os dados para o script PHP
      const response = await fetch("enviardadosfeedback.php", {
        method: "POST",
        body: formData,
      });

      // Processa a resposta
      if (response.ok) {
        const result = await response.json(); 
        if (result.sucesso) {
          mensagem.textContent = "✅ Feedback enviado com sucesso! Obrigado pela sua opinião.";
          mensagem.style.color = "#16b06f";
          form.reset();
        } else if (result.erro) {
          mensagem.textContent = "❌ " + result.erro;
          mensagem.style.color = "red";
        }
      } else {
        mensagem.textContent = "❌ Erro ao enviar feedback.";
        mensagem.style.color = "red";
      }
    } catch (error) {
      console.error("Erro na requisição:", error);
      mensagem.textContent = "Erro de conexão com o servidor.";
      mensagem.style.color = "red";
    }
  });
});
