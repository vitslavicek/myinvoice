<?php

declare(strict_types=1);

namespace MyInvoice\Tests\Unit\Service\Branding;

use MyInvoice\Service\Pdf\PdfBranding;
use PHPUnit\Framework\TestCase;

/**
 * `pdf_theme` (migrace 0136): `simple` = jednobarevný doklad. Test fixuje gating
 * (theme má přednost před akcentní barvou; default/legacy řádky = base CSS beze
 * změny) a to, že override skutečně ruší všechny podbarvené plochy base stylu.
 */
final class PdfThemeTest extends TestCase
{
    public function testNormalizeThemeFallsBackToDefault(): void
    {
        self::assertSame('default', PdfBranding::normalizeTheme(null));
        self::assertSame('default', PdfBranding::normalizeTheme(''));
        self::assertSame('default', PdfBranding::normalizeTheme('fancy'));
        self::assertSame('simple', PdfBranding::normalizeTheme(' Simple '));
    }

    public function testDefaultThemeEmitsNothing(): void
    {
        self::assertSame('', PdfBranding::themeCss('default'));
        self::assertSame('', PdfBranding::themeCss('nonsense'));
    }

    public function testSimpleThemeRemovesEveryFilledSurfaceOfBaseCss(): void
    {
        $css = PdfBranding::themeCss('simple');
        // Každá plocha, kterou base invoice.css podbarvuje, musí být v simple průhledná.
        foreach ([
            'table.items th { background: transparent',
            'table.totals-table tr.grand td { background: transparent',
            'table.totals-table tr.to-pay td { background: transparent',
            '.parties td { background: transparent',
            '.parties td.party-customer { background: transparent',
            '.pay-panel { background: transparent',
            '.pay-panel.pay-panel-paid { background: transparent',
            '.note { background: transparent',
            '.note.rc-note { background: transparent',
            'table.czk-recap, table.czk-recap td { background: transparent',
            'table.items tr:nth-child(even) td { background: transparent',
        ] as $rule) {
            self::assertStringContainsString($rule, $css, "chybí override: $rule");
        }
        // Monochrome: žádná fialová (#3B2D83 / #6753AE) ani jiná sytá barva.
        self::assertStringNotContainsString('#3B2D83', $css);
        self::assertStringNotContainsString('#6753AE', $css);
        self::assertMatchesRegularExpression('/^[^#]*(#(111111|555555|8A8A8A|CCCCCC)[^#]*)+$/s', $css);
    }

    public function testSupplierCssSimpleThemeWinsOverAccentColor(): void
    {
        $supplier = ['email_branding_enabled' => 1, 'email_accent_color' => '#C03F26', 'pdf_theme' => 'simple'];
        $css = PdfBranding::supplierCss($supplier);
        self::assertStringContainsString('Theme override: simple', $css);
        self::assertStringNotContainsString('#C03F26', $css);
        self::assertStringNotContainsString('Branding override', $css);
    }

    public function testSupplierCssDefaultThemeKeepsAccentBehaviour(): void
    {
        $branded = ['email_branding_enabled' => 1, 'email_accent_color' => '#C03F26', 'pdf_theme' => 'default'];
        self::assertStringContainsString('#C03F26', PdfBranding::supplierCss($branded));

        // Legacy řádek bez sloupce pdf_theme → chová se jako dřív.
        $legacy = ['email_branding_enabled' => 1, 'email_accent_color' => '#C03F26'];
        self::assertStringContainsString('#C03F26', PdfBranding::supplierCss($legacy));

        // Simple bez brandingu: theme se uplatní i tak (logo řeší gate zvlášť).
        $unbranded = ['email_branding_enabled' => 0, 'pdf_theme' => 'simple'];
        self::assertStringContainsString('Theme override: simple', PdfBranding::supplierCss($unbranded));
    }
}
