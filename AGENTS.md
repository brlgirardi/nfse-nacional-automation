# NFS-e Nacional Automation — instruções para agentes

**Primeira interação:** apresente boas-vindas, confirme se o usuário deseja configurar um emissor novo ou revisar uma instalação existente e **use a skill `nfse-setup`**. Leia `.agents/skills/nfse-setup/SKILL.md` e `docs/ONBOARDING.md` antes de conduzir o setup.

O objetivo é **adaptar-se ao prestador e à rotina de emissão**: perguntar o que falta, marcar respostas confirmadas/pendentes/não aplicáveis, registrar preferências apenas em configuração privada e manter aprovação humana para operações reais.

**Regras obrigatórias:** nunca solicitar/ler/imprimir certificado, senha, PFX/P12, Base64, token ou chave privada em chat; não versionar CNPJ, tomadores, documentos, descrições reais ou dados fiscais; não inventar parâmetros tributários; não emitir/cancelar em produção sem duas autorizações específicas e separadas; não repetir operações após timeout sem conciliação; nunca executar fluxo fiscal real no repositório público de código.

**Modos suportados:** local (`scripts/run-local.sh`) e GitHub Actions em **repositório de execução privado** (`scripts/configure-github.sh` / `scripts/send-request.sh`). O projeto contém exemplos fictícios que **não** devem ser enviados em produção.

**Verificação:** use `composer validate --no-check-publish`, `php -l`, `python3 -m unittest discover -s tests -v` e `php scripts/preflight.php` quando configurado. Diferencie testes offline, autenticação e transmissão real. Consulte `docs/SECURITY.md`, `docs/INSTALLATION.md`, `docs/GITHUB_ACTIONS.md` e `docs/LOCAL.md`.

Se o usuário pedir ajuda para "instalar", comece com: "Bem-vindo ao NFS-e Nacional Automation. Você prefere usar o emissor localmente ou via GitHub Actions em um repositório privado?"
