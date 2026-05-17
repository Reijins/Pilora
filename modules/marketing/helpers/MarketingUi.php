<?php
declare(strict_types=1);

namespace Modules\Marketing\Helpers;

/**
 * Composants UI du site marketing public (icônes, boutons).
 */
final class MarketingUi
{
    public static function icon(string $bootstrapIcon, ?string $extraClass = null): string
    {
        $name = preg_replace('/[^a-z0-9-]/', '', strtolower($bootstrapIcon)) ?? '';
        if ($name === '') {
            return '';
        }
        $class = 'bi bi-' . $name;
        if ($extraClass !== null && $extraClass !== '') {
            $class .= ' ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8');
        }

        return '<i class="' . $class . '" aria-hidden="true"></i>';
    }

    public static function featureIcon(string $slug): string
    {
        return match ($slug) {
            'devis' => 'file-earmark-text',
            'factures' => 'receipt',
            'chantiers' => 'building',
            'planning' => 'calendar-week',
            'rh' => 'people',
            'rentabilite' => 'graph-up-arrow',
            default => 'grid',
        };
    }

    /**
     * @param array<string, string> $attrs
     */
    public static function btn(
        string $href,
        string $label,
        string $icon = '',
        string $variant = 'primary',
        bool $block = false,
        array $attrs = [],
    ): string {
        $variant = in_array($variant, ['primary', 'secondary', 'ghost', 'light'], true) ? $variant : 'primary';
        $classes = 'm-btn m-btn--' . $variant;
        if ($block) {
            $classes .= ' m-btn--block';
        }
        if (isset($attrs['class']) && is_string($attrs['class']) && $attrs['class'] !== '') {
            $classes .= ' ' . $attrs['class'];
            unset($attrs['class']);
        }

        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }

        $iconHtml = $icon !== '' ? self::icon($icon, 'm-btn__icon') : '';
        $hrefEsc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<a class="' . $classes . '" href="' . $hrefEsc . '"' . $attrStr . '>'
            . $iconHtml
            . '<span>' . $labelEsc . '</span></a>';
    }

    public static function btnSubmit(
        string $label,
        string $icon = '',
        string $variant = 'primary',
        bool $disabled = false,
    ): string {
        $variant = in_array($variant, ['primary', 'secondary', 'ghost', 'light'], true) ? $variant : 'primary';
        $classes = 'm-btn m-btn--' . $variant;
        if ($disabled) {
            $classes .= ' is-disabled';
        }
        $iconHtml = $icon !== '' ? self::icon($icon, 'm-btn__icon') : '';
        $disabledAttr = $disabled ? ' disabled' : '';

        return '<button type="submit" class="' . $classes . '"' . $disabledAttr . '>'
            . $iconHtml
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></button>';
    }

    public static function navLink(string $href, string $label, string $icon = ''): string
    {
        $iconHtml = $icon !== '' ? self::icon($icon, 'm-nav__icon') : '';

        return '<a class="m-nav__link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
            . '>'
            . $iconHtml
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
    }
}
