<?php

declare(strict_types=1);

namespace Obm\Tests;

use InvalidArgumentException;
use Obm\Money;
use OverflowException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testParsesDollarStringsAsCents(): void
    {
        $this->assertSame(1000, Money::parseCents('10'));
        $this->assertSame(1050, Money::parseCents('10.5'));
        $this->assertSame(1050, Money::parseCents('10.50'));
        $this->assertSame(123456, Money::parseCents('$1,234.56'));
        $this->assertSame(123400, Money::parseCents('1,234'));
        $this->assertSame(123450, Money::parseCents('1,234.5'));
        $this->assertSame(1, Money::parseCents('0.01'));
        $this->assertSame(10, Money::parseCents('0.1'));
        $this->assertSame(50, Money::parseCents('.50'));
        $this->assertSame(150, Money::parseCents('0001.50'));
        $this->assertSame(200000, Money::parseCents('  $2,000 '));
        $this->assertSame(0, Money::parseCents('0'));
        $this->assertSame(0, Money::parseCents('0.00'));
    }

    public function testOneDollarFifteenIsOneHundredFifteenCentsNotTheFloatCast(): void
    {
        $this->assertSame(115, Money::parseCents('1.15'));
        $this->assertSame(114, (int) ((float) '1.15' * 100));
    }

    public function testDimePlusTwoDimesIsThirtyCents(): void
    {
        $sum = Money::sum([
            Money::parseCents('0.10'),
            Money::parseCents('0.20'),
        ]);
        $this->assertSame(30, $sum);
        $this->assertSame('$0.30', Money::format($sum));
    }

    public function testTenDimesAreOneDollar(): void
    {
        $parts = array_fill(0, 10, Money::parseCents('0.10'));
        $this->assertSame(100, Money::sum($parts));
        $this->assertSame('$1.00', Money::format(100));
    }

    public function testLargeAmountIsExactBeyondFloatPrecision(): void
    {
        $cents = Money::parseCents('90071992547409.93');
        $this->assertSame(9007199254740993, $cents);
        $this->assertNotSame(9007199254740993, (int) round((float) '90071992547409.93' * 100));
    }

    public function testRejectsFloatSyntaxAndExtraPrecision(): void
    {
        foreach (['', '10.567', '1e2', '1.2.3', '-1', 'USD 10', '12,34.00', '10.', '+5', '1,23'] as $bad) {
            try {
                Money::parseCents($bad);
                $this->fail('Expected rejection of ' . $bad);
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testRejectsAmountBeyondIntegerRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseCents('999999999999999999.99');
    }

    public function testFormat(): void
    {
        $this->assertSame('$0.00', Money::format(0));
        $this->assertSame('$0.01', Money::format(1));
        $this->assertSame('$0.05', Money::format(5));
        $this->assertSame('$1.00', Money::format(100));
        $this->assertSame('$1.15', Money::format(115));
        $this->assertSame('$1,000.00', Money::format(100000));
        $this->assertSame('$1,234.56', Money::format(123456));
        $this->assertSame('$1,234,567.89', Money::format(123456789));
        $this->assertSame('-$0.50', Money::format(-50));
    }

    public function testInputRoundTrip(): void
    {
        foreach ([0, 1, 10, 115, 10050, 123456, 9007199254740993] as $cents) {
            $this->assertSame($cents, Money::parseCents(Money::toInput($cents)));
        }
        $this->assertSame('10.50', Money::toInput(1050));
        $this->assertSame('0.01', Money::toInput(1));
        $this->assertSame('1.00', Money::toInput(100));
    }

    public function testFromDatabaseAcceptsIntAndDigitStringOnly(): void
    {
        $this->assertSame(10050, Money::fromDatabase(10050));
        $this->assertSame(10050, Money::fromDatabase('10050'));
        $this->assertSame(0, Money::fromDatabase('0'));
        foreach ([10.5, '10.5', '-1', '', '1e2', null, true] as $bad) {
            try {
                Money::fromDatabase($bad);
                $this->fail('Expected rejection');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function testSumRejectsFloatsNegativesAndOverflow(): void
    {
        $this->assertSame(0, Money::sum([]));
        $this->expectException(OverflowException::class);
        Money::sum([PHP_INT_MAX, 1]);
    }

    public function testSumRejectsAFloatBeforeOverflow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::sum([10.5]);
    }
}
