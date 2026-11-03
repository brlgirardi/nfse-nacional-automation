# Instruções para agentes (Codex / ChatGPT Work)

Este projeto é um emissor fiscal, portanto transmissão/cancelamento são ações financeiras e legais. Siga docs/AI_SETUP_PROMPT.md e docs/SECURITY.md.

Nunca peça nem leia certificados, senhas ou tokens no chat. O usuário configura Secrets diretamente. Não versione CNPJ ou dados fiscais reais. Não faça testes em produção sem revisar a nota e receber confirmação inequívoca. O cancelamento exige aprovação independente. Nunca reemitir após timeout sem consultar a DPS. Não presumir regime/códigos de tributação; valide com contabilidade. A saída deve indicar evidências de conexão, autorização da NFS-e e artefatos, separando testes estáticos dos reais.
