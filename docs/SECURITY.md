# Segurança e privacidade

- Não armazenar ou publicar PFX/P12, senhas, Base64, tokens, dados pessoais e documentos fiscais.
- `config/modelo-fiscal.json`, `request.json`, `requests/`, `.env` e `output/` estão no `.gitignore`.
- Secrets configurados pelo usuário no Secret manager, nunca via chat.
- Não usar workflow de pull_request para executar ações fiscais ou ter acesso a segredos.
- GitHub Actions usa `workflow_dispatch`, hash da solicitação, confirmação de produção e marcador pré-transmissão.
- GitHub Actions Artifacts armazenam XML/PDF sensíveis; restrinja colaboradores e retenção.
- O cert temporário deve ser arquivo 0600 e ser eliminado após a operação, inclusive na falha.
- Não expor erros de SDK que possam conter payload fiscal. Em erro após tentativa, consultar DPS/chave no portal antes de repetir.
- Não usar dados fictícios em produção. Não emitir/cancelar sem autorização distinta e conhecimento das consequências legais.
- Ao compartilhar/forkar o projeto, cada usuário configura seus próprios dados e secrets; não reutilizar secrets de nenhum repositório original.
