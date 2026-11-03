# Resolução de problemas

**PFX falha no OpenSSL 3 / cURL error 58:** PFX legado pode precisar ser reempacotado localmente no formato PKCS#12 moderno, com orientação de segurança. Nunca envie o certificado para chat ou terceiros.

**GitHub Actions termina sem emitir:** Confira se a execução foi manual, se houve validação do hash, configuração fiscal, credenciais e aprovação de produção. "Workflow success" sozinho não é prova de autorização fiscal.

**DPS repetida / timeout:** não reemitir automaticamente. Consulte no Emissor Nacional e preserve `numeroDps`.

**Código do serviço, NBS ou retenção inválida:** revisar classificação tributária com a contabilidade e documentação fiscal vigente. Não trocar códigos ao acaso.

**PDF não gerado após autorização:** a emissão pode ter ocorrido. Verificar nota pelo portal/chave; não transmitir nova DPS para tentar corrigir um PDF. Verificar versão do gerador de DANFSe.

**GitHub Actions sem Secrets:** use `bash scripts/configure-github.sh` no computador do prestador e confirme que o repositório ativo é o correto e privado.
