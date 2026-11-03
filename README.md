# NFS-e Nacional Automation

**Autor:** Bruno Liberato Girardi
**Licenciamento:** repositório privado; distribuição sob autorização do proprietário.

Emissor e cancelador de NFS-e no Sistema Nacional brasileiro, com configuração guiada por ChatGPT, Codex e Claude. Integração PHP com `nfse-nacional/nfse-php`, certificado digital A1 (mTLS) e geração de XML + DANFSe PDF. Sem CNPJ, dados fiscais, tomadores ou chaves de terceiros no repositório.

> Compatibilidade condicionada à integração do município ao Sistema Nacional, ao certificado do prestador e às regras fiscais vigentes. Não substitui validação contábil. Este pacote ainda requer teste de integração antes de produção.

## Comece com IA

Abra esta pasta no Codex, Claude Code ou ChatGPT Work e peça:

> Leia AGENTS.md, docs/AI_SETUP_PROMPT.md e docs/INSTALLATION.md. Instale meu emissor de NFS-e passo a passo, perguntando o que falta. Nunca solicite minha senha ou certificado no chat. Não transmita notas reais sem minha aprovação.

O agente verifica PHP/Composer, solicita os dados fiscais do seu prestador, orienta o registro de um certificado A1 privado, testa a instalação e apresenta a emissão de teste real **apenas com autorização expressa**. Cancelamento requer aprovação separada.

## Requisitos

PHP 8.4+, Composer 2, extensões curl/dom/fileinfo/gd/libxml/mbstring/openssl/simplexml/xml/zip, certificado e-CNPJ A1, acesso à API nacional e informações fiscais validadas.

## Instalação rápida

```bash
git clone https://github.com/brlgirardi/nfse-nacional-automation.git
cd nfse-nacional-automation
composer install
python3 scripts/setup.py
```

**Para uso local:** configure variáveis `NFSE_CERT_PATH` ou `NFSE_CERT_PFX_BASE64` e `NFSE_CERT_PASSWORD` fora do repositório, crie uma solicitação local e execute `bash scripts/run-local.sh caminho.json`.

**Para GitHub Actions:** execute `bash scripts/configure-github.sh` para cadastrar os secrets diretamente no seu repositório e depois `bash scripts/send-request.sh caminho.json`. Consulte [docs/GITHUB_ACTIONS.md](docs/GITHUB_ACTIONS.md).

Nenhum certificado ou senha deve ser colado em chat, issue, commit, PR ou exemplo. `config/modelo-fiscal.json` é privado e ignorado pelo Git.

## Estrutura

- `src/`: PHP de emissão e cancelamento; exige aprovação vinculada ao hash do JSON em produção.
- `config/modelo-fiscal.example.json`: campos de configuração sem dados reais.
- `scripts/`: instalador fiscal, solicitação, execução local, configuração segura e disparo do Actions.
- `examples/`: modelos totalmente fictícios, **não executar em produção**.
- `.github/workflows/`: validação CI e fluxo manual protegido para operações fiscais.
- `docs/`: instalação, segurança, prompts de agentes e resolução de falhas.

## Segurança operacional

1. Todo documento de produção é real, inclusive uma nota de R$ 1,00; valide previamente com contabilidade e com o tomador.
2. O cancelamento pode ter prazo/motivo restrito; não ocorre automaticamente.
3. Cada tentativa de emissão tem DPS persistida. Não gere DPS nova em retry sem conciliação no portal.
4. Não compartilhe XML/PDF fiscais: artefatos do GitHub ficam acessíveis a quem tem permissão e são retidos por prazo limitado.
5. O exemplo de configuração é intencionalmente incompleto: o software não presume regime tributário, código fiscal, NBS ou retenção.

**Estado:** a criação e publicação do código não equivalem à validação de emissão em produção; testes reais só com dados e aprovação individuais.

## Documentação

[Instalação](docs/INSTALLATION.md) · [GitHub Actions](docs/GITHUB_ACTIONS.md) · [Local](docs/LOCAL.md) · [Prompt para IA](docs/AI_SETUP_PROMPT.md) · [Segurança](docs/SECURITY.md) · [Problemas](docs/TROUBLESHOOTING.md)
