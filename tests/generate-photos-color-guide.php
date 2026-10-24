<?php

declare(strict_types=1);

// Build-time use of the existing central SVG encoder, no new runtime subscription.
require_once $argv[1] ?? dirname(__DIR__, 2) . '/Symcon_ModuleHelper/src/SVGPreviewHelper.php';
require_once dirname(__DIR__) . '/libs/PhotosWidget.php';
use Burki24\SymconModuleHelper\SVGPreviewHelper as SVG;

class PhotosGuideFixture
{
    use SymconiCloud\PhotosWidget;
}
$trait = new ReflectionClass(PhotosGuideFixture::class);
foreach (['photos-color-guide' => 'PHOTO_GUIDE_LABELS', 'photos-tile-color-guide' => 'PHOTO_TILE_GUIDE_LABELS'] as $stem => $constant) {
    foreach (['en', 'de'] as $language) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1150" height="520" viewBox="0 0 1150 520"><style>text{font-family:Arial,sans-serif;font-size:17px;fill:#27344b}.pin{fill:#27344b;stroke:white;stroke-width:2}.number{fill:white;text-anchor:middle;dominant-baseline:central;font-weight:bold}</style><rect width="1150" height="520" fill="white"/>';
        $svg .= '<rect x="15" y="15" width="600" height="485" rx="12" fill="#eef2f7"/><rect x="35" y="45" width="560" height="335" rx="10" fill="#e3e7ed" stroke="#92a1b5" stroke-width="2"/>';
        $svg .= '<rect x="120" y="45" width="390" height="335" fill="#c3d8eb"/><circle cx="400" cy="115" r="35" fill="#ffe4a8"/><path d="M120 380L240 180L350 300L430 230L510 380" fill="#8eaea3"/><text x="60" y="410">Familie.jpg · 24.10.2026</text>';
        $svg .= '<rect x="402" y="391" width="50" height="39" rx="7" fill="#e7ecf5" stroke="#92a1b5"/><rect x="460" y="391" width="60" height="39" rx="7" fill="#b8cce6" stroke="#92a1b5"/><rect x="528" y="391" width="50" height="39" rx="7" fill="#e7ecf5" stroke="#92a1b5"/><text x="420" y="417">‹</text><text x="482" y="417" fill="#2e4c72">Ⅱ</text><text x="546" y="417">›</text>';
        $svg .= '<text x="35" y="471" style="fill:#b07d20">' . ($language === 'de' ? 'Beispiel: Verbindungshinweis' : 'Example: connection notice') . '</text>';
        $positions = $stem === 'photos-color-guide' ? [[45, 27], [580, 413], [70, 210], [185, 410], [491, 393], [517, 430], [595, 160], [320, 468]] : [[45, 27], [185, 410], [491, 393], [320, 468], [70, 210], [580, 413]];
        foreach (array_values($trait->getConstant($constant)) as $index => $label) {
            [$key, $fallback] = $label;
            $catalog = json_decode(file_get_contents(dirname(__DIR__) . '/libs/helper/translations/' . ($label[2] ?? 'IPSViewStyleHelper') . '.json'), true)['translations'];
            $text = $catalog[$key][$language] ?? $fallback;
            [$x, $y] = $positions[$index];
            $number = $index + 1;
            $svg .= '<circle class="pin" cx="' . $x . '" cy="' . $y . '" r="15"/><text class="number" x="' . $x . '" y="' . $y . '">' . $number . '</text><text x="650" y="' . (55 + $index * 51) . '">' . $number . ' ' . SVG::escape($text) . '</text>';
        }
        $svg .= '</svg>' . "\n";
        $path = dirname(__DIR__) . '/iCloud Photos/visualization/' . $stem . ($language === 'de' ? '-de' : '');
        file_put_contents($path . '.svg', $svg);
        file_put_contents($path . '.image', SVG::dataUri($svg) . "\n");
    }
}
