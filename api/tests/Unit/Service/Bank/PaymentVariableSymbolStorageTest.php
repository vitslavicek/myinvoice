<?php

declare(strict_types=1);

namespace MyInvoice\Tests\Unit\Service\Bank;

use MyInvoice\Service\Bank\VariableSymbolNormalizer;
use PHPUnit\Framework\TestCase;

/** `invoices.payment_variable_symbol` (migrace 0137) — validace při uložení. */
final class PaymentVariableSymbolStorageTest extends TestCase
{
    public function testEmptyBecomesNull(): void
    {
        self::assertNull(VariableSymbolNormalizer::forStorage(null));
        self::assertNull(VariableSymbolNormalizer::forStorage(''));
        self::assertNull(VariableSymbolNormalizer::forStorage('   '));
    }

    public function testDigitsAreKeptVerbatim(): void
    {
        self::assertSame('7454902', VariableSymbolNormalizer::forStorage(' 7454902 '));
        self::assertSame('0000000001', VariableSymbolNormalizer::forStorage('0000000001'));
    }

    public function testRejectsNonDigitsAndOverlong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VariableSymbolNormalizer::forStorage('2026-0001');
    }

    public function testRejectsMoreThanTenDigits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VariableSymbolNormalizer::forStorage('12345678901');
    }
}
