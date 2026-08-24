<?php

declare(strict_types=1);

namespace MyInvoice\Service\Pdf;

use MyInvoice\Service\Branding\AccentColor;
use MyInvoice\Service\Mail\SafeLogoPath;

/**
 * Sdílená brandingová logika pro PDF (faktura + výkaz víceprací):
 *   - resolveLogoPath: gate na email_branding_enabled, SafeLogoPath validace,
 *     preference SVG sidecaru (crisp), fallback PNG,
 *   - accentCss: override CSS přebarvující fialové akcenty na zvolenou barvu,
 *   - themeCss / supplierCss: per-supplier vzhled PDF (`pdf_theme`, migrace 0136):
 *     `simple` = jednobarevný doklad bez podbarvení; akcentní barva se pak neuplatní.
 *
 * Cíl: faktura i výkaz mají identické chování hlavičky (3 varianty: bez loga →
 * textový název, jen logo, logo + název) a stejné barevné branding změny.
 */
final class PdfBranding
{
    /** Povolené hodnoty `supplier.pdf_theme` (migrace 0136). */
    public const THEMES = ['default', 'simple'];
    public const THEME_DEFAULT = 'default';
    public const THEME_SIMPLE = 'simple';

    /** Normalizuje hodnotu theme — cokoli mimo THEMES (vč. null/legacy řádků) = default. */
    public static function normalizeTheme(?string $theme): string
    {
        $t = strtolower(trim((string) $theme));
        return in_array($t, self::THEMES, true) ? $t : self::THEME_DEFAULT;
    }

    /**
     * Kompletní per-supplier CSS override připojovaný ZA base invoice.css — jediný
     * vstupní bod pro oba renderery (faktura i výkaz). `simple` theme má přednost
     * před akcentní barvou: monochromatický doklad nemá co přebarvovat.
     */
    public static function supplierCss(array $supplier): string
    {
        if (self::normalizeTheme($supplier['pdf_theme'] ?? null) === self::THEME_SIMPLE) {
            return self::themeCss(self::THEME_SIMPLE);
        }
        return self::accentCss($supplier);
    }

    /**
     * CSS override pro zvolený theme. `default` = '' (base CSS stačí).
     *
     * `simple`: „raw" jednobarevný doklad — černý text, tenké černé/šedé linky,
     * žádné podbarvené plochy (strany, hlavička položek, celkem, platební panel,
     * poznámky). Odběratel je zvýrazněn tenkým rámečkem místo podbarvení.
     * Nezávislé na email_branding_enabled — to dál řídí jen logo.
     * Selektory zrcadlí accentCss, aby oba override pokrývaly stejné prvky
     * (u výkazu jsou přebytečné selektory no-op).
     */
    public static function themeCss(string $theme): string
    {
        if (self::normalizeTheme($theme) !== self::THEME_SIMPLE) {
            return '';
        }
        $ink   = '#111111';
        $muted = '#555555';
        $faint = '#8A8A8A';
        $rule  = '#111111';
        $hair  = '#CCCCCC';

        return "\n/* ─── Theme override: simple (monochrome, no fills) ─── */\n"
            . "body { color: {$ink}; }\n"
            . ".head { border-bottom: 1pt solid {$rule}; }\n"
            . ".brand-name, .doc-type, .varsymbol-big, td.logo-name { color: {$ink}; }\n"
            . ".parent-ref { color: {$muted}; }\n"
            . ".isdoc-badge { color: {$ink}; background: transparent; border-color: {$ink}; }\n"
            . ".parties td { background: transparent; }\n"
            . ".parties td.party-customer { background: transparent; border: 0.6pt solid {$rule}; }\n"
            . ".parties h2, td.meta-label, .bank-label { color: {$muted}; }\n"
            . ".party-name, .bank-value { color: {$ink}; }\n"
            . ".ic-dic, .bank-meta-line, td.tot-label, .czk-recap-rate { color: {$muted}; }\n"
            . ".meta-grid td { border-bottom-color: {$hair}; }\n"
            . "table.items th { background: transparent; color: {$ink}; border-bottom: 1pt solid {$rule}; }\n"
            . "table.items td { border-bottom-color: {$hair}; }\n"
            . "table.items tr:nth-child(even) td { background: transparent; }\n"
            . "table.totals-table tr.subtotal td { border-top-color: {$hair}; }\n"
            . "table.totals-table tr.grand td { background: transparent; color: {$ink}; border-top: 1pt solid {$rule}; border-bottom: 1pt solid {$rule}; }\n"
            . "table.totals-table tr.grand td.tot-label { color: {$ink}; }\n"
            . "table.totals-table tr.to-pay td { background: transparent; border-top-color: {$rule}; color: {$ink}; }\n"
            . "table.totals-table tr.advance td { color: {$muted}; }\n"
            . "table.totals-table tr.rate-note td.rate-note-cell { color: {$muted}; }\n"
            . "table.czk-recap, table.czk-recap td { background: transparent; }\n"
            . "table.czk-recap td.czk-recap-title { color: {$ink}; border-bottom-color: {$rule}; }\n"
            . "table.czk-recap tr.subtotal td { border-top-color: {$hair}; background: transparent; }\n"
            . "table.czk-recap tr.grand td { background: transparent; color: {$ink}; border-top: 1pt solid {$rule}; }\n"
            . ".pay-panel { background: transparent; border: 0.6pt solid {$rule}; border-radius: 0; }\n"
            . ".pay-panel.pay-panel-paid { background: transparent; border-color: {$rule}; }\n"
            . ".note { background: transparent; border-left: 1.5pt solid {$rule}; color: {$ink}; }\n"
            . ".note.rc-note { background: transparent; border-left-color: {$rule}; color: {$ink}; }\n"
            . ".footer { border-top-color: {$hair}; color: {$muted}; }\n"
            . ".footer-name { color: {$ink}; }\n"
            . ".footer-dot { color: {$faint}; }\n"
            . ".commercial-register { color: {$faint}; }\n"
            . ".page-attribution, .page-attribution a { color: {$faint}; }\n"
            . ".wr-title, .wr-link { color: {$ink}; }\n";
    }

    /**
     * Logo pro PDF — jen když má dodavatel zapnutý branding (email_branding_enabled).
     * Bez brandingu vrací null → šablona vykreslí textový brand-name fallback.
     * Preferuje SVG sidecar (vektor), pokud je mPDF-kompatibilní; jinak PNG.
     */
    public static function logoPath(array $supplier, int $supplierIdFallback = 0): ?string
    {
        if (empty($supplier['email_branding_enabled'])) {
            return null;
        }
        $logoPath = $supplier['logo_path'] ?? null;
        if (!$logoPath) {
            return null;
        }

        // SafeLogoPath: defense-in-depth proti podstrčenému logo_path (security #2).
        $supplierId = (int) ($supplier['id'] ?? $supplierIdFallback);
        $abs = SafeLogoPath::resolve((string) $logoPath, $supplierId);
        if ($abs === null) {
            return null;
        }

        // SVG sidecar preferujeme (crisp), pokud neobsahuje mPDF-problematické prvky.
        $svgSibling = preg_replace('/\.png$/i', '.svg', (string) $logoPath);
        if (is_string($svgSibling) && $svgSibling !== $logoPath) {
            $svgAbs = SafeLogoPath::resolve($svgSibling, $supplierId);
            if ($svgAbs !== null && self::svgIsMpdfCompatible($svgAbs)) {
                return $svgAbs;
            }
        }
        // PNG fallback: splácni alfa kanál na bílou — mPDF neumí SMask u truecolor
        // RGBA PNG a vykreslil by průhledné pozadí černě (issue #152).
        return PdfLogoFlattener::flattenedPath($abs);
    }

    /** True = supplier má logo, které lze v PDF zobrazit (pro gate `logo_show_name`). */
    private static function svgIsMpdfCompatible(string $svgPath): bool
    {
        $svg = (string) @file_get_contents($svgPath);
        if ($svg === '') {
            return false;
        }
        $bad = '/<(?:clipPath|use|mask|linearGradient|radialGradient|pattern|filter)\b/i';
        return !preg_match($bad, $svg);
    }

    /**
     * Per-supplier accent override CSS — přebarví fialové akcenty (#3B2D83 + světlé
     * varianty/linky) na zvolenou barvu. Gated na email_branding_enabled + nedefaultní
     * hex. Vrací '' pokud branding vypnutý nebo defaultní barva (ta je už v base CSS).
     *
     * Selektory pokrývají fakturu i výkaz (přebytečné selektory u výkazu jsou no-op:
     * .head border, .brand-name, .doc-type, .wr-title/.wr-link platí pro oba).
     */
    public static function accentCss(array $supplier): string
    {
        if (empty($supplier['email_branding_enabled'])) {
            return '';
        }
        $color = AccentColor::normalize($supplier['email_accent_color'] ?? null);
        if ($color === null || $color === AccentColor::DEFAULT) {
            return '';
        }

        $bgSoft     = AccentColor::tint($color, 0.08);
        $lineSoft   = AccentColor::tint($color, 0.24);
        $lineMedium = AccentColor::tint($color, 0.28);
        $badgeBorder = AccentColor::tint($color, 0.30);

        return "\n/* ─── Branding override (per-supplier accent color) ─── */\n"
            . ".head { border-bottom-color: {$color}; }\n"
            . ".brand-name, .doc-type { color: {$color}; }\n"
            . ".parties h2, td.meta-label, .bank-label { color: {$color}; }\n"
            . ".parties td.party-customer { background: {$bgSoft}; }\n"
            . ".pay-panel { background: {$bgSoft}; border-color: {$lineMedium}; }\n"
            . "table.items th { background: {$color}; }\n"
            . "table.totals-table tr.grand td { background: {$color}; }\n"
            . "table.totals-table tr.to-pay td { border-top-color: {$color}; color: {$color}; background: {$bgSoft}; }\n"
            . "table.totals-table tr.subtotal td { border-top-color: {$lineSoft}; }\n"
            . "table.czk-recap td.czk-recap-title, table.czk-recap tr.grand td { color: {$color}; }\n"
            . "table.czk-recap td.czk-recap-title { border-bottom-color: {$lineMedium}; }\n"
            . "table.czk-recap tr.subtotal td { border-top-color: {$lineSoft}; }\n"
            . ".isdoc-badge { color: {$color}; background: {$bgSoft}; border-color: {$badgeBorder}; }\n"
            . ".note { border-left-color: {$color}; }\n"
            . ".note.rc-note { border-left-color: #E8A547; }\n"
            . ".footer { border-top-color: {$lineSoft}; }\n"
            . ".footer-name { color: {$color}; }\n"
            . ".wr-title, .wr-link { color: {$color}; }\n";
    }
}
