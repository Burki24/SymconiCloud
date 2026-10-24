<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/map.php';
$root = dirname(__DIR__);
$guideTrait = new ReflectionClass(\SymconiCloud\WoIstColorGuide::class);
$roles = $guideTrait->getConstant('WOIST_GUIDE_ROLES');
$labels = $guideTrait->getConstant('WOIST_GUIDE_LABELS');
$locale = json_decode(file_get_contents($root . '/iCloud WoIst/locale.json'), true)['translations']['de'];
$catalogs = [];
foreach (['IPSViewStyleHelper', 'OpenFreeMapHelper'] as $catalog) {
    $catalogs[$catalog] = json_decode(file_get_contents($root . '/libs/helper/translations/' . $catalog . '.json'), true)['translations'];
}
$check(count($roles) === 25 && array_keys($roles) === array_keys($labels), 'The WoIst guide needs complete labels for its 25 actual color roles.');
foreach (['de', 'en'] as $language) {
    $guide = new class($language) {
        use \SymconiCloud\WoIstColorGuide;
        use \Burki24\SymconModuleHelper\HelperTranslationHelper;

        public function __construct(private string $language)
        {
        }

        public function form(): array
        {
            return $this->WoIstColorGuideForm();
        }

        protected function HelperTranslationLanguageOverride(): string
        {
            return $this->language;
        }

        protected function Translate(string $text): string
        {
            $translations = json_decode(file_get_contents(dirname(__DIR__) . '/iCloud WoIst/locale.json'), true)['translations']['de'];
            return $this->language === 'de' ? ($translations[$text] ?? $text) : $text;
        }

        protected function VisualizationAsset(string $name): string
        {
            return file_get_contents(dirname(__DIR__) . '/iCloud WoIst/visualization/' . $name);
        }
    };
    $panel = $guide->form();
    $image = $panel['items'][1];
    $check($image['type'] === 'Image' && $image['name'] === 'WoIstIPSViewColorGuide' && $image['width'] === '100%', 'The map guide must be responsive and distinctly identified.');
    $prefix = 'data:image/svg+xml;base64,';
    $check(str_starts_with($image['image'], $prefix), 'Guide is a local self-contained image.');
    $svg = base64_decode(substr($image['image'], strlen($prefix)), true);
    $stem = 'color-guide' . ($language === 'de' ? '-de' : '');
    $check($svg === file_get_contents($root . '/iCloud WoIst/visualization/' . $stem . '.svg'), 'Encoded guide and editable SVG source must stay identical.');
    $xml = new DOMDocument();
    $check($xml->loadXML($svg, LIBXML_NONET), 'Localized map guide must be valid SVG.');
    $check(!preg_match('/<(?:script|foreignObject|image)\b|\bhref\s*=|<!DOCTYPE/i', $svg), 'No scripts, external pictures or map-tile downloads in the guide.');
    $xpath = new DOMXPath($xml);
    $number = 0;
    foreach ($roles as $role => $description) {
        [$key, $fallback] = $labels[$role];
        $caption = $key === '' ? ($language === 'de' ? $locale[$fallback] : $fallback) : $catalogs[$labels[$role][2] ?? 'IPSViewStyleHelper'][$key][$language];
        $nodes = $xpath->query('//*[@data-role="' . $role . '"]');
        $check($nodes->length === 1 && str_contains($nodes->item(0)->textContent, $caption), 'SVG must use the designer caption for ' . $role . ' in ' . $language);
        $number++;
        $check(str_starts_with($panel['items'][$number + 1]['caption'], $number . '. ' . $caption . ' (' . $role . '):'), 'Text legend must use the same designer caption and number as the illustration.');
        $check(isset($locale[$description]), 'Map-specific guide descriptions must be translated.');
    }
    $check(!str_contains($svg, 'WidgetCompact') && str_contains($svg, 'WoIst'), 'Map illustration must not reuse an unchanged presence card illustration.');
}
$configuration = IPS_GetConfiguration($mapID);
$ids = IPS_GetChildrenIDs($mapID);
$guidePanel = (new ReflectionMethod($map, 'WoIstColorGuideForm'))->invoke($map);
foreach ($guidePanel['items'] as $item) {
    if (($item['type'] ?? '') !== 'Label') {
        continue;
    }
    $caption = $item['caption'];
    if (preg_match('/^\d+\. .* \([A-Za-z]+\): (.*)$/u', $caption, $match)) {
        $caption = $match[1];
    }
    $check(isset($locale[$caption]), 'All map guide hints, notes and descriptions need module translations.');
}
$form = json_decode($map->GetConfigurationForm(), true);
$findImage = static function (array $items) use (&$findImage): int
{
    $count = 0;
    foreach ($items as $item) {
        $count += ($item['name'] ?? '') === 'WoIstIPSViewColorGuide' ? 1 : 0;
        $count += $findImage($item['items'] ?? []);
    }
    return $count;
};
foreach ($form['elements'] as $item) {
    if (($item['caption'] ?? '') === 'IPSView designer') {
        $check($findImage($item['items']) === 1, 'The guide must appear once inside the IPSView designer.');
    }
    if (($item['caption'] ?? '') === 'Tile designer') {
        $check($findImage($item['items']) === 0, 'This request adds the guide only to the IPSView designer.');
    }
}
$css = preg_replace('/\s+/', '', file_get_contents($root . '/iCloud WoIst/visualization/style.css'));
foreach (['.device{background:var(--ipsview-role-page-background);}', 'background:var(--ipsview-role-control-active-background);', 'color:var(--ipsview-role-text-active);', 'background:var(--ipsview-role-popup-background);', 'color:var(--ipsview-role-icon);', 'border-color:var(--ipsview-role-line);', 'background:var(--ipsview-role-control-inactive-background);'] as $contract) {
    $check(str_contains($css, $contract), 'Guide must agree with actual map HTML/CSS role usage: ' . $contract);
}
$javascript = file_get_contents($root . '/iCloud WoIst/visualization/app.js');
$check(str_contains($javascript, "color: '#ffffff'") && str_contains($javascript, 'fillOpacity: 0.08'), 'Fixed white marker outlines and accuracy fill must match the map-specific explanation.');
$check(IPS_GetConfiguration($mapID) === $configuration && IPS_GetChildrenIDs($mapID) === $ids, 'Opening the color guide must not save settings or alter variables.');
$check(!str_contains($map->GetVisualizationTile(), 'WoIstIPSViewColorGuide'), 'The example belongs only to the designer.');
ob_end_clean();
echo "WoIst-specific color guide, central captions, SVG resources and CSS contracts passed.\n";
