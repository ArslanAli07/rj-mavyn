<?php

namespace Tests\Unit;

use App\Services\Catalog\SizeChartParser;
use PHPUnit\Framework\TestCase;

class SizeChartParserTest extends TestCase
{
    private SizeChartParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SizeChartParser();
    }

    public function test_it_returns_null_for_empty_chart(): void
    {
        $this->assertNull($this->parser->parse(null));
        $this->assertNull($this->parser->parse([]));
        $this->assertNull($this->parser->parse(['tables' => []]));
    }

    public function test_it_parses_multi_table_charts_with_cm_and_inches_and_demographics(): void
    {
        $raw = [
            'name' => 'Jacket (men) / Jacket (women)',
            'diagram' => 'https://example.com/diagram.png',
            'how_to_measure' => 'Measure around chest.',
            'tables' => [
                [
                    'chart' => 'Jacket (men)',
                    'unit' => 'CENTIMETER',
                    'rows' => [
                        ['', 'PRODUCT SIZE', 'CONVERSION GUIDE'],
                        ['', '', ''],
                        ['', 'U.S. SIZE', 'EU SIZE', 'CHEST (CM)'],
                        ['38', '48', '96.0'],
                        ['40', '50', '100.0'],
                    ],
                ],
                [
                    'chart' => 'Jacket (men)',
                    'unit' => 'INCHES',
                    'rows' => [
                        ['', 'PRODUCT SIZE', 'CONVERSION GUIDE'],
                        ['', '', ''],
                        ['', 'U.S. SIZE', 'EU SIZE', 'CHEST (INCH)'],
                        ['38', '48', '37.8'],
                        ['40', '50', '39.4'],
                    ],
                ],
                [
                    'chart' => 'Jacket (women)',
                    'unit' => 'CENTIMETER',
                    'rows' => [
                        ['', 'PRODUCT SIZE', 'CONVERSION GUIDE'],
                        ['', '', ''],
                        ['', 'U.S. SIZE', 'EU SIZE', 'CHEST (CM)'],
                        ['6', '38', '74.0'],
                        ['8', '40', '78.0'],
                    ],
                ],
            ],
        ];

        $parsed = $this->parser->parse($raw);

        $this->assertNotNull($parsed);
        $this->assertSame('Jacket (men) / Jacket (women)', $parsed['name']);
        $this->assertSame('https://example.com/diagram.png', $parsed['diagram']);
        $this->assertSame('Measure around chest.', $parsed['how_to_measure']);
        $this->assertCount(3, $parsed['tables']);

        // Check Table 0: Men CM
        $this->assertSame('Jacket (men)', $parsed['tables'][0]['chart']);
        $this->assertSame('CENTIMETER', $parsed['tables'][0]['unit']);
        $this->assertSame('men', $parsed['tables'][0]['gender']);

        // Check Table 1: Men INCHES
        $this->assertSame('Jacket (men)', $parsed['tables'][1]['chart']);
        $this->assertSame('INCHES', $parsed['tables'][1]['unit']);

        // Check Table 2: Women CM
        $this->assertSame('Jacket (women)', $parsed['tables'][2]['chart']);
        $this->assertSame('CENTIMETER', $parsed['tables'][2]['unit']);
        $this->assertSame('women', $parsed['tables'][2]['gender']);

        // Check grouped sizes
        $this->assertArrayHasKey('Men', $parsed['grouped_sizes']);
        $this->assertArrayHasKey('Women', $parsed['grouped_sizes']);
        $this->assertContains('US 38 · EU 48', $parsed['grouped_sizes']['Men']);
        $this->assertContains('US 6 · EU 38', $parsed['grouped_sizes']['Women']);
    }
}

