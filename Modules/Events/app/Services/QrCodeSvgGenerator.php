<?php

namespace Modules\Events\Services;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeSvgGenerator
{
    /**
     * Generate a self-contained SVG QR code for a pass token.
     *
     * The caller is responsible for ensuring the payload contains no personal data.
     */
    public static function generate(string $data): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 1,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new SvgWriter())->write($qrCode)->getString();
    }
}
