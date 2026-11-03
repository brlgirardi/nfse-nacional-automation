#!/usr/bin/env bash
set -euo pipefail
set +x
cd "$(dirname "$0")/.."
[[ $# -ge 1 ]] || { echo 'Uso: bash scripts/run-local.sh requests/solicitacao.json' >&2; exit 2; }
FILE="$1"
php scripts/preflight.php "$FILE"
[[ -f vendor/autoload.php ]] || { echo 'Execute composer install primeiro.' >&2; exit 2; }
OP=$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["operacao"]??"";' "$FILE")
ENV=$(php -r 'echo json_decode(file_get_contents($argv[1]),true)["ambiente"]??"";' "$FILE")
case "$OP" in emitir|cancelar) ;; *) echo 'Operação inválida.' >&2; exit 2;; esac
if [[ "$ENV" == production ]]; then
  HASH12=$(php -r 'echo substr(hash_file("sha256",$argv[1]),0,12);' "$FILE")
  REQUIRED="$(printf '%s' "$OP" | tr '[:lower:]' '[:upper:]')-$HASH12"
  echo 'Operação FISCAL REAL. Verifique prestador, tomador, descrição, valor, competência, motivo e prazo.'
  printf 'Autorize somente ESTA operação digitando "%s": ' "$REQUIRED"
  IFS= read -r ANSWER
  [[ "$ANSWER" == "$REQUIRED" ]] || { echo 'Não autorizado. Nada foi transmitido.' >&2; exit 3; }
  export NFSE_APPROVAL="$ANSWER"
fi
if [[ -z "${NFSE_CERT_PFX_BASE64:-}" && -n "${NFSE_CERT_PATH:-}" ]]; then
  [[ -f "$NFSE_CERT_PATH" ]] || { echo 'NFSE_CERT_PATH não encontrado.' >&2; exit 2; }
  export NFSE_CERT_PFX_BASE64="$(base64 < "$NFSE_CERT_PATH" | tr -d '[:space:]')"
fi
[[ -n "${NFSE_CERT_PFX_BASE64:-}" && -n "${NFSE_CERT_PASSWORD:-}" ]] || { echo 'Configure certificado A1 e senha em variáveis/secret manager local.' >&2; exit 2; }
php "src/$OP.php" "$FILE"
