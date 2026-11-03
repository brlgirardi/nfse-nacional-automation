# Execução local

Instale PHP 8.4+ / Composer 2. Rode `composer install`. Configure `config/modelo-fiscal.json` via `python3 scripts/setup.py`.

Defina as credenciais na sessão local ou em um secret manager, **nunca** em arquivo versionado:
- `NFSE_CERT_PFX_BASE64`: PFX em Base64.
- `NFSE_CERT_PASSWORD`: senha.
A configuração local pode usar variáveis temporárias ou integração com cofre; não cole segredo em chat.

Crie uma solicitação com `python3 scripts/new-request.py` e execute:
```bash
bash scripts/run-local.sh requests/<arquivo>.json
```

O script mostra a confirmação vinculada ao hash da solicitação antes de operação em produção. A aprovação de emitir não autoriza cancelar. Cada tentativa registra um marcador local. Não apague o marcador após timeout sem conciliação no portal. O XML e PDF ficam na pasta `output/` ignorada pelo Git.
