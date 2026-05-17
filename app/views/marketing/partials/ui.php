<?php
declare(strict_types=1);

use Modules\Marketing\Helpers\MarketingUi;

if (!function_exists('m_icon')) {
    function m_icon(string $name, ?string $extraClass = null): string
    {
        return MarketingUi::icon($name, $extraClass);
    }
}

if (!function_exists('m_btn')) {
    /**
     * @param array<string, string> $attrs
     */
    function m_btn(
        string $href,
        string $label,
        string $icon = '',
        string $variant = 'primary',
        bool $block = false,
        array $attrs = [],
    ): string {
        return MarketingUi::btn($href, $label, $icon, $variant, $block, $attrs);
    }
}

if (!function_exists('m_btn_submit')) {
    function m_btn_submit(string $label, string $icon = '', string $variant = 'primary', bool $disabled = false): string
    {
        return MarketingUi::btnSubmit($label, $icon, $variant, $disabled);
    }
}

if (!function_exists('m_feature_icon')) {
    function m_feature_icon(string $slug): string
    {
        return MarketingUi::icon(MarketingUi::featureIcon($slug), 'm-card__icon-svg');
    }
}

if (!function_exists('m_nav')) {
    function m_nav(string $href, string $label, string $icon = ''): string
    {
        return MarketingUi::navLink($href, $label, $icon);
    }
}
