<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Shared form structure inside this library; illustrations and mappings remain module-specific. */
trait DesignerColorGuide
{
    /**
     * Uses the existing central translation and asset contracts. Labels are
     * [key, English fallback, optional catalog]; an empty key is module-owned.
     */
    protected function BuildDesignerColorGuide(string $name, string $title, string $hint, string $asset, array $roles, array $labels, array $notes = []): array
    {
        $german = $this->NormalizeHelperTranslationLanguage($this->ResolveHelperTranslationLanguage()) === 'de';
        $items = [
            ['type' => 'Label', 'caption' => $this->Translate($hint)],
            ['type' => 'Image', 'name' => $name, 'width' => '100%', 'image' => trim($this->VisualizationAsset($asset . ($german ? '-de' : '') . '.image'))]
        ];
        $number = 1;
        foreach ($roles as $role => $description) {
            [$key, $fallback] = $labels[$role];
            $label = $key === '' ? $this->Translate($fallback)
                : $this->TranslateHelperText($labels[$role][2] ?? 'IPSViewStyleHelper', $key, $fallback);
            $items[] = ['type' => 'Label', 'caption' => $number++ . '. ' . $label . ' (' . $role . '): ' . $this->Translate($description)];
        }
        foreach ($notes as $note) {
            $items[] = ['type' => 'Label', 'caption' => $this->Translate($note)];
        }
        return ['type' => 'ExpansionPanel', 'caption' => $this->Translate($title), 'items' => $items];
    }
}
