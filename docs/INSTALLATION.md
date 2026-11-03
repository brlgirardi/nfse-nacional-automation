# Instalação guiada

## Requisitos
- Git, PHP 8.4+, Composer 2, Python 3.10+ (wizard opcional).
- Extensões PHP: curl, dom, fileinfo, gd, libxml, mbstring, openssl, simplexml, xml, zip.
- Certificado e-CNPJ A1 correspondente ao prestador; habilitação do município/serviço no Sistema Nacional.
- Código de serviço, regime, NBS quando aplicável e informações de ISS confirmados pela contabilidade.

## 1. Clonar e verificar ambiente
```bash
git clone https://github.com/brlgirardi/nfse-nacional-automation.git
cd nfse-nacional-automation
php -v
composer --version
composer install
composer validate --no-check-publish
php -l src/emitir.php
php -l src/cancelar.php
```

## 2. Configurar prestador localmente
```bash
python3 scripts/setup.py
```
Perguntas: CNPJ e razão social; inscrição municipal; município IBGE de emissão e prestação; contatos; regime Simples Nacional (opSimpNac, regApTribSN, regEspTrib); código nacional do serviço (6 dígitos); NBS (se aplicável); tributação ISS, retenção, percentual total Simples, alíquota quando cabível; série DPS; descrição padrão e política de confirmação. Todos esses valores dependem do enquadramento individual.

O resultado é `config/modelo-fiscal.json`, local e ignorado pelo Git. Guarde backup seguro. Não publique esse arquivo.

## 3. Configurar certificado digital
**Não envie o arquivo nem sua senha ao ChatGPT, Codex ou Claude.** O agente deve orientar a configuração local ou no Secret do GitHub. Certificado PFX/P12 e senha ficam com o usuário; não entram no repositório.

## 4. Executar validações
```bash
php scripts/preflight.php
```
O preflight verifica os campos preenchidos, mas não substitui validação contábil nem autenticação na API. Teste autenticação em homologação, quando disponível e permitido, antes de produção.

## 5. Teste de emissão real (opcional)
Uma nota de **R$ 1,00 é uma nota real**, e pode gerar obrigações. Valide com a contabilidade a possibilidade de emissão e cancelamento; use dados verdadeiros e uma prestação/documento legítimo. O fluxo exibe os campos para revisão e solicita confirmação inequívoca para transmitir. Não use os JSON fictícios de `examples/` em produção.

## 6. Cancelamento (opcional e separado)
Após autorização de emissão, verifique número/chave no portal e XML. Para cancelar, confirme que o motivo e prazo são legais, gere uma nova solicitação e dê autorização específica. Nunca cancelar automaticamente no onboarding.

## 7. Rotina
Escolha se a descrição padrão pode ser reutilizada; sempre confira tomador, competência, valor e documento. GitHub Actions e modo local possuem procedimentos separados, descritos na documentação.

## Descrição padrão x confirmação por nota
Na instalação, o assistente pergunta se o prestador quer salvar uma descrição padrão. É possível exigir confirmação em cada nota ou permitir reutilizá-la somente depois de validação contábil. Valores, competência e tomador continuam específicos de cada emissão. Essa escolha fica em config/modelo-fiscal.json, ignorado pelo Git.
