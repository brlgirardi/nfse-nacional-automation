#!/usr/bin/env bash
set -euo pipefail
set +x
cd "$(dirname "$0")/.."
command -v gh >/dev/null || { echo 'Instale GitHub CLI e rode gh auth login.' >&2; exit 2; }
gh auth status >/dev/null 2>&1 || { echo 'gh auth login necessário.' >&2; exit 2; }
REPO="$(gh repo view --json nameWithOwner -q .nameWithOwner)"
PRIVATE="$(gh repo view --json isPrivate -q .isPrivate)"
[[ "$PRIVATE" == true ]] || { echo 'ERRO: o repositório precisa ser privado.' >&2; exit 3; }
[[ -f config/modelo-fiscal.json ]] || { echo 'Execute python3 scripts/setup.py antes.' >&2; exit 2; }
php scripts/preflight.php
echo "Repositório: $REPO (privado). Enviando dados fiscais somente para o Secret do repositório."
gh secret set NFSE_PROVIDER_JSON --repo "$REPO" < config/modelo-fiscal.json
printf 'Caminho local do certificado A1 (.pfx/.p12): '
IFS= read -r CERT_PATH
[[ -f "$CERT_PATH" ]] || { echo 'Certificado não encontrado.' >&2; exit 2; }
base64 < "$CERT_PATH" | tr -d '\r\n' | gh secret set NFSE_CERT_PFX_BASE64 --repo "$REPO"
printf 'Senha do certificado (entrada oculta): '
IFS= read -r -s PASSWORD
printf '\n'
[[ -n "$PASSWORD" ]] || { echo 'Senha vazia.' >&2; exit 2; }
printf '%s' "$PASSWORD" | gh secret set NFSE_CERT_PASSWORD --repo "$REPO"
unset PASSWORD CERT_PATH
echo 'Secrets configurados: NFSE_PROVIDER_JSON, NFSE_CERT_PFX_BASE64 e NFSE_CERT_PASSWORD. Valores não foram exibidos.'
