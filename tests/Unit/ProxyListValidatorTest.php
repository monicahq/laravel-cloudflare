<?php

namespace Monicahq\Cloudflare\Tests\Unit;

use Monicahq\Cloudflare\Exceptions\InvalidProxyListException;
use Monicahq\Cloudflare\ProxyListValidator;
use Monicahq\Cloudflare\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ProxyListValidatorTest extends FeatureTestCase
{
    public static function validEntries(): array
    {
        return [
            ['173.245.48.0/20'],
            ['104.16.0.0/13'],
            ['1.2.3.4'],
            ['10.0.0.0/8'],
            ['2400:cb00::/32'],
            ['2a06:98c0::/29'],
            ['2001:db8::1'],
            ['2001::/16'],
            [' 173.245.48.0/20 '],
        ];
    }

    public static function invalidEntries(): array
    {
        return [
            ['*'],
            ['0.0.0.0/0'],
            ['::/0'],
            ['10.0.0.0/7'],
            ['2001::/15'],
            ['1.2.3.4:443'],
            ['<html>'],
            ['fe80::1%eth0'],
            ['1.2.3.4/33'],
            ['2001:db8::/129'],
            ['1.2.3.4/abc'],
            ['1.2.3.4/'],
            ['/24'],
            ['1.2.3.4/24/1'],
            [''],
        ];
    }

    #[Test]
    #[DataProvider('validEntries')]
    public function it_accepts_valid_entries(string $entry)
    {
        $this->assertTrue((new ProxyListValidator)->isValidEntry($entry));
    }

    #[Test]
    #[DataProvider('invalidEntries')]
    public function it_rejects_invalid_entries(string $entry)
    {
        $this->assertFalse((new ProxyListValidator)->isValidEntry($entry));
    }

    #[Test]
    public function it_trims_drops_blank_lines_and_deduplicates()
    {
        $list = (new ProxyListValidator)->validate([
            ' 173.245.48.0/20 ',
            '',
            '   ',
            '2400:cb00::/32',
            '173.245.48.0/20',
        ]);

        $this->assertSame(['173.245.48.0/20', '2400:cb00::/32'], $list);
    }

    #[Test]
    public function it_rejects_the_whole_list_on_an_invalid_entry()
    {
        $this->expectException(InvalidProxyListException::class);
        $this->expectExceptionMessage('0.0.0.0/0');

        (new ProxyListValidator)->validate(['173.245.48.0/20', '0.0.0.0/0', '2400:cb00::/32']);
    }

    #[Test]
    public function it_rejects_non_string_entries()
    {
        $this->expectException(InvalidProxyListException::class);

        (new ProxyListValidator)->validate(['173.245.48.0/20', 123]);
    }

    #[Test]
    public function it_rejects_null_entries()
    {
        $this->expectException(InvalidProxyListException::class);

        (new ProxyListValidator)->validate([null]);
    }

    #[Test]
    public function it_truncates_long_entries_in_the_message()
    {
        try {
            (new ProxyListValidator)->validate([str_repeat('x', 200)]);
            $this->fail('Exception not thrown');
        } catch (InvalidProxyListException $e) {
            $this->assertStringNotContainsString(str_repeat('x', 65), $e->getMessage());
        }
    }

    #[Test]
    public function it_rejects_an_empty_list()
    {
        $this->expectException(InvalidProxyListException::class);
        $this->expectExceptionMessage('empty');

        (new ProxyListValidator)->validate(['', ' ']);
    }
}
