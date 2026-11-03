<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nfse\Dto\Nfse\DpsData;
use Nfse\Enums\TipoAmbiente;
use Nfse\Http\NfseContext;
use Nfse\Nfse;
use Nfse\Support\IdGenerator;
use Danfse\Danfse\Danfse;

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function digits(string $value): string
{
    return preg_replace('/\\D+/', '', $value) ?? '';
}

function required(array $data, string $key): mixed
{
    if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
        fail("Campo obrigatório ausente: {$key}");
    }
    return $data[$key];
}

function nested(array $data, array $path, mixed $default = null): mixed
{
    $cursor = $data;
    foreach ($path as $key) {
        if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
            return $default;
        }
        $cursor = $cursor[$key];
    }
    return $cursor;
}

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fail('Arquivo JSON da solicitação não encontrado.');
}

$modelPath = __DIR__ . '/../config/modelo-fiscal.json';
if (!is_file($modelPath)) {
    fail('Modelo fiscal não encontrado.');
}

try {
    $input = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $model = json_decode((string) file_get_contents($modelPath), true, 512, JSON_THROW_ON_ERROR);

    date_default_timezone_set('America/Sao_Paulo');

    $ambiente = strtolower((string) ($input['ambiente'] ?? nested($model, ['dps', 'ambientePadrao'], 'homologation')));
    if (!in_array($ambiente, ['homologation', 'production'], true)) {
        fail('ambiente deve ser homologation ou production.');
    }
    if ($ambiente === 'production' && ($input['confirmarProducao'] ?? false) !== true) {
        fail('Produção exige confirmarProducao=true.');
    }

    $cnpjPrestador = digits((string) ($input['prestadorCnpj'] ?? nested($model, ['prestador', 'cnpj'], '')));
    $nomePrestador = trim((string) ($input['prestadorNome'] ?? nested($model, ['prestador', 'nome'], '')));
    $codigoMunicipio = digits((string) ($input['municipioEmissaoIbge'] ?? nested($model, ['prestador', 'municipioEmissaoIbge'], '')));
    $opSimpNac = (int) ($input['opSimpNac'] ?? nested($model, ['prestador', 'regimeTributario', 'opSimpNac'], 0));
    $regApTribSN = isset($input['regApTribSN'])
        ? (int) $input['regApTribSN']
        : (int) nested($model, ['prestador', 'regimeTributario', 'regApTribSN'], 0);
    $regEspTrib = (int) ($input['regEspTrib'] ?? nested($model, ['prestador', 'regimeTributario', 'regEspTrib'], 0));

    if (strlen($cnpjPrestador) !== 14) fail('prestadorCnpj inválido.');
    if ($nomePrestador === '') fail('prestadorNome inválido.');
    if (strlen($codigoMunicipio) !== 7) fail('municipioEmissaoIbge inválido.');
    if (!in_array($opSimpNac, [1, 2, 3], true)) fail('opSimpNac inválido.');
    if ($opSimpNac === 3 && !in_array($regApTribSN, [1, 2, 3], true)) {
        fail('regApTribSN é obrigatório para ME/EPP do Simples Nacional.');
    }

    $tomadorDoc = digits((string) required($input, 'tomadorDocumento'));
    $tomadorNome = trim((string) required($input, 'tomadorNome'));
    if (!in_array(strlen($tomadorDoc), [11, 14], true)) fail('Documento do tomador inválido.');

    $valor = (float) required($input, 'valor');
    if ($valor <= 0) fail('valor deve ser maior que zero.');

    $descriptionIsRequired = nested($model, ['defaults','confirmEachInvoice'], true) !== false;
    $descricao = trim((string) ($input['descricaoServico'] ?? ''));
    if ($descricao === '' && !$descriptionIsRequired) {
        $descricao = trim((string) nested($model,['defaults','serviceDescription'], ''));
    }
    if ($descricao === '') fail('descricaoServico obrigatória nesta nota ou descrição padrão explicitamente autorizada.');

    if (!isset($model['servico']['issRetido']) || !is_bool($model['servico']['issRetido'])) {
        if (!array_key_exists('issRetido', $input) || !is_bool($input['issRetido'])) fail('ISS retido deve ser confirmado como true/false.');
    }
    if (!isset($model['servico']['tribISSQN']) || !is_int($model['servico']['tribISSQN'])) {
        if (!isset($input['tribISSQN']) || !is_int($input['tribISSQN'])) fail('Tributação de ISS não confirmada.');
    }
    $cTribNac = digits((string) ($input['codigoTributacaoNacional'] ?? nested($model, ['servico', 'codigoTributacaoNacional'], '')));
    if (!preg_match('/^\\d{6}$/', $cTribNac)) fail('codigoTributacaoNacional deve ter 6 dígitos.');

    $cNBS = digits((string) ($input['codigoNbs'] ?? nested($model, ['servico', 'codigoNbs'], '')));
    if ($cNBS !== '' && !preg_match('/^\\d{9}$/', $cNBS)) fail('codigoNbs deve ter 9 dígitos.');

    $cLocPrestacao = digits((string) ($input['municipioPrestacaoIbge'] ?? nested($model, ['prestador', 'municipioPrestacaoIbge'], $codigoMunicipio)));
    if (!preg_match('/^\\d{7}$/', $cLocPrestacao)) fail('municipioPrestacaoIbge inválido.');

    $serie = (string) ($input['serieDps'] ?? nested($model, ['dps', 'serieAutomacao'], '10001'));
    // Se o agente não informar um número, gera um identificador temporal de 12 dígitos.
    // Isso reduz atrito e evita reaproveitar a mesma DPS por engano.
    $numero = (string) required($input, 'numeroDps');
    if (!preg_match('/^\\d{1,5}$/', $serie)) fail('serieDps inválida.');
    if (!preg_match('/^\\d{1,15}$/', $numero)) fail('numeroDps inválido.');

    $competencia = (string) ($input['competencia'] ?? date('Y-m-d'));
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $competencia)) fail('competencia inválida.');

    $empresaSlug = strtoupper(trim((string) ($input['identificador'] ?? 'SERVICO')));
    if (!preg_match('/^[A-Z0-9_-]{1,30}$/', $empresaSlug)) {
        fail('identificador deve conter 1 a 30 caracteres [A-Z0-9_-].');
    }
    $competenciaDate = new DateTimeImmutable($competencia);
    $meses = [1 => 'JAN', 2 => 'FEV', 3 => 'MAR', 4 => 'ABR', 5 => 'MAI', 6 => 'JUN',
        7 => 'JUL', 8 => 'AGO', 9 => 'SET', 10 => 'OUT', 11 => 'NOV', 12 => 'DEZ'];
    $nomeArquivoBase = sprintf('NF-%s-%s-%s-DPS%s', $empresaSlug, $meses[(int) $competenciaDate->format('n')], $competenciaDate->format('y'), $numero);


    // Aprovação humana vinculada ao conteúdo exato da solicitação.
    if ($ambiente === 'production') {
        $hash12 = substr(hash_file('sha256', $path), 0, 12);
        $expected = 'EMITIR-' . $hash12;
        if ((string) getenv('NFSE_APPROVAL') !== $expected) {
            fail('Produção exige autorização explícita: ' . $expected);
        }
    }

    $certB64 = (string) getenv('NFSE_CERT_PFX_BASE64');
    $certPassword = (string) getenv('NFSE_CERT_PASSWORD');
    if ($certB64 === '' || $certPassword === '') {
        fail('Secrets NFSE_CERT_PFX_BASE64 e/ou NFSE_CERT_PASSWORD não configurados.');
    }

    $certBytes = base64_decode($certB64, true);
    if ($certBytes === false) fail('NFSE_CERT_PFX_BASE64 inválido.');

    $certPath = sys_get_temp_dir() . '/nfse-' . bin2hex(random_bytes(8)) . '.p12';
    if (file_put_contents($certPath, $certBytes) === false) fail('Falha ao materializar certificado.');
    chmod($certPath, 0600);

    try {
        $context = new NfseContext(
            ambiente: $ambiente === 'production' ? TipoAmbiente::Producao : TipoAmbiente::Homologacao,
            certificatePath: $certPath,
            certificatePassword: $certPassword,
            codigoMunicipio: $codigoMunicipio
        );
        $nfse = new Nfse($context);

        $idDps = IdGenerator::generateDpsId(
            cpfCnpj: $cnpjPrestador,
            codIbge: $codigoMunicipio,
            serieDps: $serie,
            numDps: $numero
        );

        $prest = [
            'CNPJ' => $cnpjPrestador,
            // tpEmit=1: xNome do prestador NÃO deve ser enviado (regra E0121).
            'regTrib' => [
                'opSimpNac' => $opSimpNac,
                'regEspTrib' => $regEspTrib,
            ],
        ];
        if ($opSimpNac === 3) {
            $prest['regTrib']['regApTribSN'] = $regApTribSN;
        }

        $im = digits((string) ($input['prestadorInscricaoMunicipal'] ?? nested($model, ['prestador', 'inscricaoMunicipal'], '')));
        if ($im !== '') $prest['IM'] = $im;

        $fone = digits((string) ($input['prestadorTelefone'] ?? nested($model, ['prestador', 'telefone'], '')));
        if ($fone !== '') $prest['fone'] = $fone;

        $email = trim((string) ($input['prestadorEmail'] ?? nested($model, ['prestador', 'email'], '')));
        if ($email !== '') $prest['email'] = $email;

        // tpEmit=1: o próprio prestador é o emitente.
        // No leiaute 1.01, o endereço do prestador não deve ser enviado nesse cenário.

        $toma = ['xNome' => $tomadorNome];
        if (strlen($tomadorDoc) === 14) $toma['CNPJ'] = $tomadorDoc;
        else $toma['CPF'] = $tomadorDoc;

        $tomadorCep = digits((string) ($input['tomadorCep'] ?? ''));
        $tomadorMunicipio = digits((string) ($input['tomadorMunicipioIbge'] ?? ''));
        $tomadorLogradouro = trim((string) ($input['tomadorLogradouro'] ?? ''));
        $tomadorNumero = trim((string) ($input['tomadorNumero'] ?? ''));
        $tomadorBairro = trim((string) ($input['tomadorBairro'] ?? ''));
        if (strlen($tomadorDoc) === 14 && (
            $tomadorCep === '' ||
            $tomadorMunicipio === '' ||
            $tomadorLogradouro === '' ||
            $tomadorNumero === '' ||
            $tomadorBairro === ''
        )) {
            fail('Tomador identificado por CNPJ exige endereço nacional completo: município IBGE, CEP, logradouro, número e bairro.');
        }

        if ($tomadorCep !== '' && $tomadorMunicipio !== '' && $tomadorLogradouro !== '' && $tomadorNumero !== '' && $tomadorBairro !== '') {
            if (!preg_match('/^\\d{8}$/', $tomadorCep)) fail('tomadorCep deve ter 8 dígitos.');
            if (!preg_match('/^\\d{7}$/', $tomadorMunicipio)) fail('tomadorMunicipioIbge deve ter 7 dígitos.');
            $toma['end'] = [
                'endNac' => ['cMun' => $tomadorMunicipio, 'CEP' => $tomadorCep],
                'xLgr' => $tomadorLogradouro,
                'nro' => $tomadorNumero,
                'xBairro' => $tomadorBairro,
            ];
            $tomadorComplemento = trim((string) ($input['tomadorComplemento'] ?? ''));
            if ($tomadorComplemento !== '') $toma['end']['xCpl'] = $tomadorComplemento;
        }

        $cServ = ['cTribNac' => $cTribNac, 'xDescServ' => $descricao];
        if ($cNBS !== '') $cServ['cNBS'] = $cNBS;

        $tribISSQN = (int) ($input['tribISSQN'] ?? nested($model, ['servico', 'tribISSQN'], 1));
        $issRetido = array_key_exists('issRetido', $input)
            ? (bool) $input['issRetido']
            : (bool) nested($model, ['servico', 'issRetido'], true);

        $tribMun = [
            'tribISSQN' => $tribISSQN,
            'tpRetISSQN' => $issRetido ? 2 : 1,
        ];
        if (isset($input['aliquotaIss']) && $input['aliquotaIss'] !== null && $input['aliquotaIss'] !== '') {
            $tribMun['pAliq'] = (float) $input['aliquotaIss'];
        }

        $trib = ['tribMun' => $tribMun];
        if ($opSimpNac === 3) {
            $pTotTribSN = (float) ($input['percentualTotalTributosSN'] ?? nested($model, ['servico', 'percentualTotalTributosSN'], 0));
            if ($pTotTribSN > 0) {
                $trib['totTrib'] = ['pTotTribSN' => $pTotTribSN];
            } else {
                $trib['totTrib'] = ['indTotTrib' => 0];
            }
        } else {
            $trib['totTrib'] = ['indTotTrib' => 0];
        }

        $dps = new DpsData([
            '@attributes' => ['versao' => '1.01'],
            'infDPS' => [
                '@attributes' => ['Id' => $idDps],
                'tpAmb' => $ambiente === 'production' ? 1 : 2,
                'dhEmi' => date('c'),
                'verAplic' => 'NFSEAUTO01',
                'serie' => $serie,
                'nDPS' => $numero,
                'dCompet' => $competencia,
                'tpEmit' => 1,
                'cLocEmi' => $codigoMunicipio,
                'prest' => $prest,
                'toma' => $toma,
                'serv' => [
                    'locPrest' => ['cLocPrestacao' => $cLocPrestacao],
                    'cServ' => $cServ,
                ],
                'valores' => [
                    'vServPrest' => ['vServ' => $valor],
                    'trib' => $trib,
                ],
            ],
        ]);

        // Registro local pré-transmissão: um timeout NÃO autoriza nova emissão automática.
        $guardDir = dirname(__DIR__) . '/output';
        if (!is_dir($guardDir) && !mkdir($guardDir, 0700, true) && !is_dir($guardDir)) {
            fail('Não foi possível criar diretório de controle de tentativas.');
        }
        $guard = $guardDir . '/.attempt-emitir-' . hash_file('sha256', $path);
        $handle = @fopen($guard, 'x');
        if ($handle === false) {
            fail('Esta solicitação já teve tentativa de emissão. Concilie no portal antes de novo processamento.');
        }
        fwrite($handle, json_encode(['status'=>'attempted','numeroDps'=>$numero,'registeredAt'=>date(DATE_ATOM)]));
        fclose($handle);
        @chmod($guard, 0600);

        $nfseData = $nfse->contribuinte()->emitir($dps);
        $inf = $nfseData->infNfse ?? null;
        if ($inf === null) fail('Resposta sem infNFSe.');

        // Preserve o XML original assinado/autorizado retornado pela SEFIN.
        $xmlNfse = (string) ($nfseData->nfseXml ?? '');
        if ($xmlNfse === '') fail('A emissão retornou NFS-e sem XML original.');

        // A API já pode ter autorizado a NFS-e: preservar o XML e a chave
        // ANTES de tentar gerar o PDF. Nunca retransmitir apenas por falha do DANFSe.
        $earlyId = (string) ($inf->id ?? '');
        $earlyKey = preg_replace('/^NFS/', '', $earlyId) ?? '';
        $safeDir = dirname(__DIR__) . '/output';
        if (!is_dir($safeDir) && !mkdir($safeDir, 0700, true) && !is_dir($safeDir)) {
            fail('API respondeu, mas falhou ao criar diretório para XML; NÃO reemitir.');
        }
        $earlyXmlFile = $safeDir . '/' . $nomeArquivoBase . '.xml';
        if (file_put_contents($earlyXmlFile, $xmlNfse, LOCK_EX) === false) {
            fail('API respondeu, mas falhou ao gravar XML; NÃO reemitir.');
        }
        @chmod($earlyXmlFile, 0600);
        $earlyReceipt = [
            'idNfse' => $earlyId, 'chaveAcesso' => $earlyKey,
            'numeroNfse' => $inf->numeroNfse ?? null, 'numeroDps' => $numero,
            'status_artefatos' => 'xml_salvo_pdf_pendente',
        ];
        $earlyReceiptFile = $safeDir . '/' . $nomeArquivoBase . '.receipt.json';
        file_put_contents($earlyReceiptFile, json_encode($earlyReceipt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($earlyReceiptFile, 0600);


        // O DANFSe é gerado localmente pelo pacote oficial, conforme a mudança do leiaute nacional.
        $pdfNfse = (new Danfse())->render($nfseData);
        if (!str_starts_with($pdfNfse, '%PDF-')) fail('O gerador DANFSe não retornou um PDF válido.');

        $outputDir = dirname(__DIR__) . '/output';
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            fail('Não foi possível criar a pasta de artefatos.');
        }
        $xmlFilename = $nomeArquivoBase . '.xml';
        $pdfFilename = $nomeArquivoBase . '.pdf';
        if (file_put_contents($outputDir . '/' . $xmlFilename, $xmlNfse) === false) {
            fail('Falha ao salvar o XML da NFS-e.');
        }
        if (file_put_contents($outputDir . '/' . $pdfFilename, $pdfNfse) === false) {
            fail('Falha ao salvar o PDF da NFS-e.');
        }

        $idNfse = (string) ($inf->id ?? '');
        $chaveAcesso = preg_replace('/^NFS/', '', $idNfse) ?? '';
        if (!preg_match('/^\\d{50}$/', $chaveAcesso)) {
            fail('Chave de acesso retornada em formato inesperado: ' . $idNfse);
        }

        $earlyReceipt['status_artefatos'] = 'xml_e_pdf_salvos';
        file_put_contents($earlyReceiptFile, json_encode($earlyReceipt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($earlyReceiptFile, 0600);

        echo json_encode([
            'ok' => true,
            'ambiente' => $ambiente,
            'idDps' => $idDps,
            'serieDps' => $serie,
            'numeroDps' => $numero,
            'numeroNfse' => $inf->numeroNfse ?? null,
            'idNfse' => $idNfse,
            'chaveAcesso' => $chaveAcesso,
            'tomador' => $tomadorNome,
            'valor' => $valor,
            'codigoTributacaoNacional' => $cTribNac,
            'codigoNbs' => $cNBS,
            'issRetido' => $issRetido,
            'empresaSlug' => $empresaSlug,
            'arquivos' => [
                'xml' => $xmlFilename,
                'pdf' => $pdfFilename,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } finally {
        @unlink($certPath);
    }
} catch (Throwable $e) {
    fail('Falha de integração: verifique a DPS no portal antes de tentar novamente (detalhes da API omitidos para proteger dados).');
}
