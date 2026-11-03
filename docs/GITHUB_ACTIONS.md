# Execução via GitHub Actions

O workflow `.github/workflows/nfse-manual.yml` só inicia manualmente (`workflow_dispatch`). Não depende de GitHub Issues para receber dados fiscais.

## Pré-requisitos
- Repositório **privado** e permissões de Actions.
- GH CLI instalado e autenticado: `gh auth login`.
- Configuração local validada em `config/modelo-fiscal.json`.
- Certificado A1 (PFX/P12) do usuário e senha, configurados diretamente nos Secrets.

## Secrets (configurados individualmente)
Em Settings → Secrets and variables → Actions, configure:
- `NFSE_PROVIDER_JSON`: JSON fiscal individual (privado).
- `NFSE_CERT_PFX_BASE64`: conteúdo Base64 do PFX A1, armazenado como Secret.
- `NFSE_CERT_PASSWORD`: senha do PFX.
- `NFSE_REQUEST_JSON`: JSON da operação fiscal, gerado por solicitação.

Ou rode **localmente**:
```bash
bash scripts/configure-github.sh
```
Esse assistente envia diretamente via `gh secret set`, sem imprimir os valores em logs. Nunca cole os Secrets em issue, chat ou commit.

## Como solicitar uma operação
```bash
python3 scripts/new-request.py
bash scripts/send-request.sh requests/<arquivo>.json
```
O script calcula SHA256 da solicitação e valida a confirmação da operação real. A transmissão ocorre no Actions, com comparação do hash e dados guardados em Secret. **Atenção:** o Secret `NFSE_REQUEST_JSON` é único para o repositório; execute uma solicitação por vez.

No GitHub: Actions → NFS-e Nacional - Operação manual → Run workflow. O input `request_sha256` deve ser o SHA256 exato do arquivo privado; `confirmacao` deve coincidir com a operação e os 12 primeiros caracteres do SHA para produção. Não use o exemplo fictício.

## Antes de produção
Ative proteção do environment `production` e, quando disponível no plano, revisores obrigatórios. O workflow exige `confirmarProducao=true` no JSON mais confirmação vinculada ao hash; essas verificações não substituem a aprovação humana legítima.

## Artefatos e idempotência
O workflow registra um marcador de tentativa **antes** de transmitir e impede repetição do mesmo hash durante a retenção do marcador. Guarde comprovantes e consulte o Emissor Nacional antes de qualquer nova tentativa. Emissão gera XML/PDF no artefato privado com retenção de 7 dias. Não anexe notas reais às issues.
