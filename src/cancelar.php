<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nfse\Dto\Nfse\PedRegEventoData;
use Nfse\Enums\TipoAmbiente;
use Nfse\Http\NfseContext;
use Nfse\Nfse;

function failCancel(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function onlyDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    failCancel('Arquivo JSON da solicitação não encontrado.');
}

$modelPath = __DIR__ . '/../config/modelo-fiscal.json';
if (!is_file($modelPath)) {
    failCancel('Modelo fiscal não encontrado.');
}

try {
    $input = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $model = json_decode((string) file_get_contents($modelPath), true, 512, JSON_THROW_ON_ERROR);

    date_default_timezone_set('America/Sao_Paulo');

    $ambiente = strtolower((string) ($input['ambiente'] ?? 'production'));
    if (!in_array($ambiente, ['homologation', 'production'], true)) {
        failCancel('ambiente deve ser homologation ou production.');
    }
    if ($ambiente === 'production' && ($input['confirmarProducao'] ?? false) !== true) {
        failCancel('Produção exige confirmarProducao=true.');
    }

    $chave = onlyDigits((string) ($input['chaveAcesso'] ?? ''));
    if (!preg_match('/^\d{50}$/', $chave)) {
        failCancel('chaveAcesso deve conter exatamente 50 dígitos.');
    }

    $cnpjAutor = onlyDigits((string) ($input['cnpjAutor'] ?? ($model['prestador']['cnpj'] ?? '')));
    if (!preg_match('/^\d{14}$/', $cnpjAutor)) {
        failCancel('cnpjAutor inválido.');
    }

    $codigoMunicipio = onlyDigits((string) ($input['municipioEmissaoIbge'] ?? ($model['prestador']['municipioEmissaoIbge'] ?? '')));
    if (!preg_match('/^\d{7}$/', $codigoMunicipio)) {
        failCancel('municipioEmissaoIbge inválido.');
    }

    $codigoMotivo = (string) ($input['codigoMotivo'] ?? '2');
    if (!in_array($codigoMotivo, ['1', '2', '9'], true)) {
        failCancel('codigoMotivo deve ser 1, 2 ou 9.');
    }

    $motivo = trim((string) ($input['motivo'] ?? 'Serviço não prestado.'));
    if ($motivo === '') {
        failCancel('motivo é obrigatório.');
    }


    // Aprovação humana vinculada ao conteúdo exato da solicitação.
    if ($ambiente === 'production') {
        $hash12 = substr(hash_file('sha256', $path), 0, 12);
        $expected = 'CANCELAR-' . $hash12;
        if ((string) getenv('NFSE_APPROVAL') !== $expected) {
            failCancel('Produção exige autorização explícita: ' . $expected);
        }
    }

    $certB64 = (string) getenv('NFSE_CERT_PFX_BASE64');
    $certPassword = (string) getenv('NFSE_CERT_PASSWORD');
    if ($certB64 === '' || $certPassword === '') {
        failCancel('Secrets NFSE_CERT_PFX_BASE64 e/ou NFSE_CERT_PASSWORD não configurados.');
    }

    $certBytes = base64_decode($certB64, true);
    if ($certBytes === false) {
        failCancel('NFSE_CERT_PFX_BASE64 inválido.');
    }

    $certPath = sys_get_temp_dir() . '/nfse-' . bin2hex(random_bytes(8)) . '.p12';
    if (file_put_contents($certPath, $certBytes) === false) {
        failCancel('Falha ao materializar certificado.');
    }
    chmod($certPath, 0600);

    try {
        $context = new NfseContext(
            ambiente: $ambiente === 'production' ? TipoAmbiente::Producao : TipoAmbiente::Homologacao,
            certificatePath: $certPath,
            certificatePassword: $certPassword,
            codigoMunicipio: $codigoMunicipio
        );

        $nfse = new Nfse($context);

        $evento = new PedRegEventoData([
            'versao' => '1.01',
            'infPedReg' => [
                'tpAmb' => $ambiente === 'production' ? 1 : 2,
                'verAplic' => 'NFSEAUTO01',
                'dhEvento' => date('c'),
                'CNPJAutor' => $cnpjAutor,
                'chNFSe' => $chave,
                'e101101' => [
                    'xDesc' => 'Cancelamento de NFS-e',
                    'cMotivo' => $codigoMotivo,
                    'xMotivo' => $motivo,
                ],
            ],
        ]);

        // Marcador pré-transmissão para impedir cancelamento local duplicado.
        $out = dirname(__DIR__) . '/output';
        if (!is_dir($out) && !mkdir($out, 0700, true) && !is_dir($out)) {
            failCancel('Não foi possível criar diretório para controle de tentativa.');
        }
        $guard = $out . '/.attempt-cancelar-' . hash_file('sha256', $path);
        $h = @fopen($guard, 'x');
        if ($h === false) failCancel('Cancelamento já tentado. Concilie no portal antes de novo envio.');
        fwrite($h, json_encode(['status'=>'attempted','chaveAcesso'=>$chave,'date'=>date(DATE_ATOM)]));
        fclose($h);
        @chmod($guard, 0600);

        $response = $nfse->contribuinte()->cancelar($evento);

        // A API de eventos é síncrona: em HTTP 201 de sucesso ela já devolve
        // o XML do evento registrado. Não tratamos uma consulta GET imediata
        // como gate de sucesso porque ela pode responder 404 durante propagação.
        $eventoB64 = (string) ($response->eventoXmlGZipB64 ?? '');
        if ($eventoB64 === '') {
            failCancel('Cancelamento enviado, mas a API não retornou o XML do evento.');
        }

        $compressed = base64_decode($eventoB64, true);
        if ($compressed === false) {
            failCancel('Resposta do evento retornou Base64 inválido.');
        }

        $eventoXml = gzdecode($compressed);
        if ($eventoXml === false) {
            failCancel('Não foi possível descompactar o XML do evento.');
        }

        // Preservar comprovante de evento fiscal fora do Git.
        $eventFile = $out . '/CANCELAMENTO-' . $chave . '.xml';
        file_put_contents($eventFile, $eventoXml, LOCK_EX);
        @chmod($eventFile, 0600);

        if (!str_contains($eventoXml, '<e101101>')) {
            failCancel('A API respondeu, mas o XML não contém o evento de cancelamento 101101.');
        }

        $seq = null;
        if (preg_match('/<nSeqEvento>(\d+)<\/nSeqEvento>/', $eventoXml, $m)) {
            $seq = (int) $m[1];
        }

        echo json_encode([
            'ok' => true,
            'ambiente' => $ambiente,
            'status' => 'cancelamento_registrado',
            'chaveAcesso' => $chave,
            'tipoEvento' => 101101,
            'numeroSequencialEvento' => $seq,
            'codigoMotivo' => $codigoMotivo,
            'motivo' => $motivo,
            'dataHoraProcessamento' => $response->dataHoraProcessamento ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } finally {
        @unlink($certPath);
    }
} catch (Throwable $e) {
    failCancel('Falha de integração: confira a NFS-e e o evento no portal antes de tentar novamente.');
}
