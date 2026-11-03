<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Danfse\Danfse\Danfse;
use Nfse\Dto\Nfse\NfseData;

$nfseData = new NfseData([
    'infNFSe' => [
        '@attributes' => ['Id' => 'NFS150600822123456780001990000000000042260700000001'],
        'nNFSe' => '42',
        'dhProc' => '2026-07-26T14:35:00-03:00',
        'xLocEmi' => 'São Paulo',
        'xLocPrestacao' => 'São Paulo',
        'xLocIncid' => 'São Paulo',
        'xNBS' => 'Serviços de consultoria',
        'emit' => [
            'CNPJ' => '12345678000199',
            'xNome' => 'EMPRESA PRESTADORA DE SERVIÇOS LTDA',
            'IM' => '123456',
            'enderNac' => ['xLgr' => 'Avenida Principal', 'nro' => '100', 'xBairro' => 'Centro', 'CEP' => '68707000', 'UF' => 'SP'],
        ],
        'DPS' => [
            '@attributes' => ['versao' => '1.01'],
            'infDPS' => [
                'dhEmi' => '2026-07-26T14:30:00-03:00',
                'serie' => '900',
                'nDPS' => '100',
                'dCompet' => '2026-07-01',
                'cLocEmi' => '3550308',
                'toma' => ['CPF' => '12345678901', 'xNome' => 'CLIENTE DE EXEMPLO'],
                'serv' => [
                    'cServ' => ['xDescServ' => 'Consultoria e assessoria em tecnologia da informação.', 'cTribNac' => '010101', 'cNBS' => '115029000'],
                    'locPrest' => ['cLocPrestacao' => '3550308'],
                ],
                'valores' => [
                    'vServPrest' => ['vServ' => 1500.00],
                    'trib' => ['tribMun' => ['pAliq' => 5, 'tribISSQN' => 1, 'tpRetISSQN' => 1]],
                ],
            ],
        ],
        'valores' => ['vLiq' => 1500, 'vBC' => 1500, 'pAliqAplic' => 5, 'vISSQN' => 75, 'vTotalRet' => 0],
    ],
]);

$pdf = (new Danfse())->render($nfseData);
if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-') || strlen($pdf) < 1000) {
    throw new RuntimeException('DANFSe não gerou um PDF válido a partir do DTO NfseData.');
}

fwrite(STDOUT, "DANFSe renderizou o DTO NfseData (" . strlen($pdf) . " bytes)." . PHP_EOL);
