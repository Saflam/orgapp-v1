<?php

namespace Modules\Events\Tests\Unit;

use Modules\Events\Services\QrCodeSvgGenerator;
use PHPUnit\Framework\TestCase;

class QrCodeSvgGeneratorTest extends TestCase
{
    public function test_it_generates_an_svg_qr_code_for_the_supplied_payload(): void
    {
        $svg = QrCodeSvgGenerator::generate('opaque-event-pass-token');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringNotContainsString('opaque-event-pass-token', $svg);
    }
}
