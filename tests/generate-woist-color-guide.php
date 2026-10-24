<?php

declare(strict_types=1);

// Build-time reuse only; the generated image has no extra runtime dependency.
$helperFile = $argv[1] ?? '';
if ($helperFile === '' || !is_file($helperFile)) {
    fwrite(STDERR, "Usage: php tests/generate-woist-color-guide.php <SVGPreviewHelper.php>\n");
    exit(1);
}
require_once $helperFile;
require dirname(__DIR__) . '/libs/WoIstColorGuide.php';
use Burki24\SymconModuleHelper\SVGPreviewHelper as SVG;

$root = dirname(__DIR__);
$labels = (new ReflectionClass(SymconiCloud\WoIstColorGuide::class))->getConstant('WOIST_GUIDE_LABELS');
$catalogs = [];
foreach (['IPSViewStyleHelper', 'OpenFreeMapHelper'] as $name) {
    $catalogs[$name] = json_decode(file_get_contents($root . '/libs/helper/translations/' . $name . '.json'), true)['translations'];
}
$locale = json_decode(file_get_contents($root . '/iCloud WoIst/locale.json'), true)['translations']['de'];
foreach (['en', 'de'] as $lang) {
    $de = $lang === 'de';
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="1160" viewBox="0 0 1400 1160"><title>WoIst: IPSView / OpenFreeMap</title><defs><style>text{font-family:Arial,sans-serif;font-size:17px;fill:#263448}.heading{font-size:23px;font-weight:bold}.small{font-size:13px;fill:#6c798b}.label{font-size:16px}.pin{fill:#263448;stroke:#fff;stroke-width:2}.number{font-size:13px;fill:#fff;font-weight:bold;text-anchor:middle;dominant-baseline:central}</style></defs>';
    $svg .= '<rect width="1400" height="1160" rx="16" fill="#fff"/><rect x="22" y="22" width="836" height="658" rx="12" fill="#f4f6f9"/>';
    $svg .= '<text x="46" y="65" class="heading">WoIst</text><text x="46" y="91" class="small">' . ($de ? 'Konto aktualisiert: 1 min zuvor' : 'Account updated: 1 min ago') . '</text>';
    $svg .= '<rect x="420" y="45" width="212" height="44" rx="7" fill="#e9eef5" stroke="#bcc9d9"/><text x="434" y="73">' . ($de ? 'Alle Positionen anzeigen' : 'Show all positions') . '</text><rect x="646" y="45" width="188" height="44" rx="7" fill="#e9eef5" stroke="#bcc9d9"/><text x="660" y="73">' . ($de ? 'Bei Apple aktualisieren' : 'Refresh from Apple') . '</text>';
    $svg .= '<rect x="46" y="112" width="788" height="36" rx="5" fill="#faf0da"/><path d="M48 117V143" stroke="#bf8b30" stroke-width="4"/><text x="67" y="136">' . ($de ? 'Beispiel: Verbindungs- oder Adresshinweis' : 'Example: connection or address warning') . '</text>';
    $svg .= '<rect x="49" y="171" width="557" height="460" rx="9" fill="#263448" opacity=".22"/><rect x="44" y="166" width="557" height="460" rx="9" fill="#eeede4" stroke="#bcc9d9" stroke-width="2"/>';
    $svg .= '<path d="M48 355C180 300 77 445 162 480S71 583 48 602" fill="none" stroke="#a6cde0" stroke-width="66"/><rect x="135" y="189" width="141" height="91" rx="18" fill="#c5ddaa"/>';
    $svg .= '<path d="M49 312L598 277M256 170L340 623M121 620L575 178" fill="none" stroke="#d7d7cf" stroke-width="20"/><path d="M49 312L598 277M256 170L340 623M121 620L575 178" fill="none" stroke="#fff" stroke-width="14"/>';
    $svg .= '<g fill="#c5bbb1"><rect x="154" y="386" width="39" height="27" transform="rotate(-10 154 386)"/><rect x="211" y="397" width="44" height="37" transform="rotate(-10 211 397)"/><rect x="489" y="211" width="54" height="35"/><rect x="485" y="557" width="46" height="38"/></g>';
    $svg .= '<text x="288" y="575" style="fill:#35465c">Zone A</text><rect x="58" y="180" width="36" height="74" rx="4" fill="#e9eef5" stroke="#bcc9d9"/><text x="69" y="205" style="fill:#455e82;font-size:28px">+</text><path d="M58 217H94" stroke="#7f91ad"/><text x="70" y="242" style="fill:#455e82;font-size:28px">−</text>';
    $svg .= '<circle cx="410" cy="455" r="66" fill="#168d80" fill-opacity=".08" stroke="#168d80"/><circle cx="185" cy="528" r="10" fill="#168d80" stroke="#fff" stroke-width="2"/><circle cx="410" cy="455" r="15" fill="#b47722" stroke="#fff" stroke-width="4"/>';
    $svg .= '<rect x="260" y="300" width="270" height="113" rx="9" fill="#263448" opacity=".18" transform="translate(3 5)"/><rect x="260" y="300" width="270" height="113" rx="9" fill="#fffdf7" stroke="#8c9ab1" stroke-width="2"/><path d="M393 413L410 431L427 413" fill="#fffdf7" stroke="#8c9ab1" stroke-width="2"/><text x="277" y="329" font-weight="bold">Blake</text><text x="277" y="357" class="small">' . ($de ? 'Letzte bekannte Position' : 'Last known position') . '</text><text x="277" y="384" class="small">' . ($de ? 'Genauigkeit: 20 m' : 'Accuracy: 20 m') . '</text>';
    $svg .= '<rect x="617" y="166" width="218" height="460" rx="9" fill="#e9eef5"/><text x="631" y="196" font-weight="bold">' . ($de ? 'Geräte' : 'Devices') . '</text>';
    $svg .= '<rect x="631" y="210" width="190" height="89" rx="8" fill="#fff" stroke="#bcc9d9"/><path d="M634 219V290" stroke="#168d80" stroke-width="4"/><text x="646" y="235" font-weight="bold">Alex</text><text x="646" y="260" class="small">' . ($de ? 'Aktuelle Position' : 'Current position') . '</text><text x="646" y="281" class="small">1 min</text>';
    $svg .= '<rect x="631" y="313" width="190" height="89" rx="8" fill="#d8e3f4" stroke="#5573cd" stroke-width="3"/><path d="M634 322V393" stroke="#b47722" stroke-width="4"/><text x="646" y="338" style="fill:#274880;font-weight:bold">Blake</text><text x="646" y="363" class="small">' . ($de ? 'Letzte bekannte Position' : 'Last known position') . '</text>';
    $svg .= '<rect x="631" y="416" width="190" height="89" rx="8" fill="#edf0f4" stroke="#bcc9d9"/><path d="M634 425V496" stroke="#6c798b" stroke-width="4"/><text x="646" y="441" style="fill:#6c798b;font-weight:bold">Casey</text><text x="646" y="470" class="small">' . ($de ? 'Keine Position' : 'No position') . '</text>';
    $pins = [[33, 47], [812, 600], [800, 282], [795, 384], [795, 487], [159, 61], [609, 99], [730, 336], [795, 258], [598, 621], [821, 354], [48, 132], [500, 385], [529, 338], [113, 193], [113, 221], [242, 638], [211, 545], [440, 477], [550, 600], [83, 481], [210, 224], [378, 292], [190, 423], [368, 574]];
    foreach ($pins as $i => [$x,$y]) {
        $svg .= '<circle class="pin" cx="' . $x . '" cy="' . $y . '" r="12"/><text class="number" x="' . $x . '" y="' . $y . '">' . ($i + 1) . '</text>';
    }
    $svg .= '<text x="887" y="47" class="heading">' . ($de ? 'IPSView-Stil' : 'IPSView style') . '</text>';
    $svg .= '<text x="46" y="742" class="heading">' . ($de ? 'Karteneinstellungen und OpenFreeMap' : 'Map settings and OpenFreeMap') . '</text>';
    foreach (array_values(array_keys($labels)) as $i => $role) {
        [$key,$fallback] = $labels[$role];
        $catalog = $labels[$role][2] ?? 'IPSViewStyleHelper';
        $caption = $key === '' ? ($de ? ($locale[$fallback] ?? $fallback) : $fallback) : $catalogs[$catalog][$key][$lang];
        if ($i < 17) {
            $x = 887;
            $y = 82 + 34 * $i;
        }
        else {
            $x = ($i - 17) % 2 ? 724 : 46;
            $y = 791 + 76 * intdiv($i - 17, 2);
        }
        $svg .= '<g data-role="' . $role . '"><text x="' . $x . '" y="' . $y . '" class="label">' . ($i + 1) . ' ' . SVG::escape($caption) . '</text><text x="' . ($x + 20) . '" y="' . ($y + 17) . '" class="small">' . $role . '</text></g>';
    }
    $svg .= '<text x="46" y="1110" class="small">' . ($de ? 'Schematische Farben. Eigene Kartenfarben wirken nur auf passende sichtbare Vektorebenen.' : 'Schematic colors. Custom map colors apply only to compatible visible vector layers.') . '</text>';
    $svg .= '<text x="46" y="1133" class="small">' . ($de ? 'Positionsfarben folgen bei aktivierter Übernahme dem Kachelprofil. Weiße Markerränder sind fest vorgegeben.' : 'Position colors follow the Tile profile when linked. White marker outlines are fixed.') . '</text></svg>';
    $stem = $root . '/iCloud WoIst/visualization/color-guide' . ($de ? '-de' : '');
    file_put_contents($stem . '.svg', $svg . "\n");
    file_put_contents($stem . '.image', SVG::dataUri($svg . "\n") . "\n");
}
