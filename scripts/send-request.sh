#!/usr/bin/env bash
set -euo pipefail
set +x
cd "$(dirname "$0")/.."
[[ $# -ge 1 ]] || { echo 'Uso: bash scripts/send-request.sh requests/solicitacao.json' >&2; exit 2; }
FILE="$1"
command -v gh >/dev/null || { echo 'Instale GitHub CLI.' >&2; exit 2; }
php scripts/preflight.php "$FILE"
REPO="$(gh repo view --json nameWithOwner -q .nameWithOwner)"
PRIVATE="$(gh repo view --json isPrivate -q .isPrivate)"
[[ "$PRIVATE" == true ]] || { echo 'Repositório precisa ser privado.' >&2; exit 3; }
OP="$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["operacao"]??"";' "$FILE")"
ENV="$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["ambiente"]??"";' "$FILE")"
SHA="$(shasum -a 256 "$FILE" | awk '{print $1}')"
APPROVAL="NENHUMA"
if [[ "$ENV" == production ]]; then
  SHA12="$(printf '%s' "$SHA" | cut -c1-12)"
  REQUIRED="$(printf '%s' "$OP" | tr '[:lower:]' '[:upper:]')-$SHA12"
  echo 'Operação FISCAL REAL. Revise os dados da solicitação e as regras de emissão/cancelamento.'
  printf 'Digite exatamente "%s" para autorizar: ' "$REQUIRED"
  IFS= read -r ANSWER
  [[ "$ANSWER" == "$REQUIRED" ]] || { echo 'Não autorizado. Secret inalterado, sem transmissão.' >&2; exit 3; }
  APPROVAL="$ANSWER"
fi
gh secret set NFSE_REQUEST_JSON --repo "$REPO" < "$FILE"
gh workflow run nfse-manual.yml --repo "$REPO" -f operacao="$OP" -f ambiente="$ENV" -f request_sha256="$SHA" -f confirmacao="$APPROVAL"
echo 'Execução solicitada: Actions → NFS-e Nacional - Operação manual.'
echo 'Não repita a solicitação após erro/timeout sem consultar o portal.'
