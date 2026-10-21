<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

use InvalidArgumentException;
use JsonException;

/**
 * Creates reusable native Symcon presentation arrays for variables.
 *
 * Intended for current IPSModuleStrict modules on Symcon 9.0. The helper
 * deliberately returns presentation arrays only and has no dependency on a
 * concrete module class, device model, transport or expose structure.
 *
 * @version 2.0.0
 */
trait VariablePresentationHelper
{
    /**
     * JSON flags used for presentation option and gradient lists.
     */
    private const VARIABLE_PRESENTATION_JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * Creates a value presentation for a boolean variable with custom captions.
     *
     * @param string $trueCaption  Caption displayed for true.
     * @param string $falseCaption Caption displayed for false.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     *
     * @throws JsonException If the option list unexpectedly cannot be encoded.
     */
    protected function BooleanPresentation(string $trueCaption, string $falseCaption): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => $this->EncodePresentationData([
                [
                    'Value'   => false,
                    'Caption' => $falseCaption
                ],
                [
                    'Value'   => true,
                    'Caption' => $trueCaption
                ]
            ])
        ];
    }

    /**
     * Creates a value presentation for a string variable.
     *
     * @param bool $multiline True to allow multiline display.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function TextPresentation(bool $multiline = false): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'MULTILINE'    => $multiline
        ];
    }

    /**
     * Creates a generic numeric value presentation.
     *
     * @param string    $prefix             Prefix placed before the formatted value.
     * @param string    $suffix             Suffix placed after the formatted value.
     * @param int       $digits             Number of decimal digits.
     * @param bool      $percentage         True to render the value as percentage.
     * @param int|float $min                Minimum display value.
     * @param int|float $max                Maximum display value.
     * @param string    $icon               Native Symcon icon name.
     * @param int       $color              Default color, -1 for the client default.
     * @param int       $usageType          Value usage: 0 none, 1 temperature.
     * @param string    $thousandsSeparator Thousands separator or Client.
     * @param string    $decimalSeparator   Decimal separator or Client.
     * @param array     $intervals          Optional native interval definitions.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     *
     * @throws InvalidArgumentException If digits or usage type are invalid.
     * @throws JsonException            If intervals cannot be encoded.
     */
    protected function ValuePresentation(
        string $prefix = '',
        string $suffix = '',
        int $digits = 0,
        bool $percentage = false,
        int|float $min = 0,
        int|float $max = 0,
        string $icon = '',
        int $color = -1,
        int $usageType = 0,
        string $thousandsSeparator = '',
        string $decimalSeparator = 'Client',
        array $intervals = []
    ): array {
        if ($digits < 0) {
            throw new InvalidArgumentException('Digits must not be negative.');
        }
        if ($usageType < 0 || $usageType > 1) {
            throw new InvalidArgumentException('Value usage type must be 0 (none) or 1 (temperature).');
        }

        return [
            'PRESENTATION'        => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'                => $icon,
            'COLOR'               => $color,
            'PREFIX'              => $prefix,
            'SUFFIX'              => $suffix,
            'USAGE_TYPE'          => $usageType,
            'PERCENTAGE'          => $percentage,
            'MIN'                 => $min,
            'MAX'                 => $max,
            'THOUSANDS_SEPARATOR' => $thousandsSeparator,
            'DIGITS'              => $digits,
            'DECIMAL_SEPARATOR'   => $decimalSeparator,
            'INTERVALS_ACTIVE'    => $intervals !== [],
            'INTERVALS'           => $this->EncodePresentationData($intervals)
        ];
    }

    /**
     * Creates a numeric value presentation for temperatures.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function TemperaturePresentation(
        int|float $min = -40.0,
        int|float $max = 100.0,
        int $digits = 1,
        string $unit = '°C',
        string $icon = ''
    ): array {
        return $this->ValuePresentation(
            suffix: $unit === '' ? '' : ' ' . $unit,
            digits: $digits,
            min: $min,
            max: $max,
            icon: $icon,
            usageType: 1
        );
    }

    /**
     * Creates a numeric value presentation for percentage values.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function PercentPresentation(
        int|float $min = 0,
        int|float $max = 100,
        int $digits = 0,
        string $unit = '%',
        string $icon = ''
    ): array {
        return $this->ValuePresentation(
            suffix: $unit === '' ? '' : ' ' . $unit,
            digits: $digits,
            percentage: true,
            min: $min,
            max: $max,
            icon: $icon
        );
    }

    /**
     * Creates a numeric value presentation for rotational speed.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function RpmPresentation(
        int|float $min = 0,
        int|float $max = 10000,
        int $digits = 0,
        string $unit = 'U/min',
        string $icon = ''
    ): array {
        return $this->ValuePresentation(
            suffix: $unit === '' ? '' : ' ' . $unit,
            digits: $digits,
            min: $min,
            max: $max,
            icon: $icon
        );
    }

    /**
     * Creates a numeric value presentation for integer values.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function IntegerPresentation(
        string $unit = '',
        int|float $min = 0,
        int|float $max = 0,
        string $icon = ''
    ): array {
        return $this->ValuePresentation(
            suffix: $unit === '' ? '' : ' ' . $unit,
            min: $min,
            max: $max,
            icon: $icon
        );
    }

    /**
     * Creates a numeric value presentation for decimal values.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function DecimalPresentation(
        string $unit = '',
        int $digits = 1,
        int|float $min = 0,
        int|float $max = 0,
        string $icon = ''
    ): array {
        return $this->ValuePresentation(
            suffix: $unit === '' ? '' : ' ' . $unit,
            digits: $digits,
            min: $min,
            max: $max,
            icon: $icon
        );
    }

    /**
     * Creates a generic slider presentation.
     *
     * @param int|float $min                Minimum value.
     * @param int|float $max                Maximum value.
     * @param int|float $stepSize           Slider step size, 0 for no fixed step.
     * @param int       $gradientType       Gradient: 0 default, 1 temperature, 2 tuneable white, 3 custom.
     * @param array     $customGradient     Native custom gradient entries with Value and Color.
     * @param int       $usageType          Usage: 0 temperature, 1 tuneable white, 2 intensity, 3 volume, 4 progress, 5 none.
     * @param string    $prefix             Prefix placed before the formatted value.
     * @param string    $suffix             Suffix placed after the formatted value.
     * @param bool      $percentage         True to render the value as percentage.
     * @param string    $thousandsSeparator Thousands separator or Client.
     * @param int       $digits             Number of decimal digits.
     * @param string    $decimalSeparator   Decimal separator or Client.
     * @param string    $icon               Native Symcon icon name.
     * @param array     $intervals          Optional native interval definitions.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     *
     * @throws InvalidArgumentException If a native parameter is outside its valid range.
     * @throws JsonException            If gradient or interval data cannot be encoded.
     */
    protected function SliderPresentation(
        int|float $min = 0,
        int|float $max = 100,
        int|float $stepSize = 1,
        int $gradientType = 0,
        array $customGradient = [],
        int $usageType = 5,
        string $prefix = '',
        string $suffix = '',
        bool $percentage = false,
        string $thousandsSeparator = '',
        int $digits = 0,
        string $decimalSeparator = 'Client',
        string $icon = '',
        array $intervals = []
    ): array {
        if ($max <= $min) {
            throw new InvalidArgumentException('Slider maximum must be greater than minimum.');
        }
        if ($stepSize < 0) {
            throw new InvalidArgumentException('Slider step size must not be negative.');
        }
        if ($gradientType < 0 || $gradientType > 3) {
            throw new InvalidArgumentException('Slider gradient type must be between 0 and 3.');
        }
        if ($usageType < 0 || $usageType > 5) {
            throw new InvalidArgumentException('Slider usage type must be between 0 and 5.');
        }
        if ($digits < 0) {
            throw new InvalidArgumentException('Digits must not be negative.');
        }

        return [
            'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
            'MIN'                 => $min,
            'MAX'                 => $max,
            'STEP_SIZE'           => $stepSize,
            'GRADIENT_TYPE'       => $gradientType,
            'CUSTOM_GRADIENT'     => $this->EncodePresentationData($customGradient),
            'USAGE_TYPE'          => $usageType,
            'PREFIX'              => $prefix,
            'SUFFIX'              => $suffix,
            'PERCENTAGE'          => $percentage,
            'THOUSANDS_SEPARATOR' => $thousandsSeparator,
            'DIGITS'              => $digits,
            'DECIMAL_SEPARATOR'   => $decimalSeparator,
            'ICON'                => $icon,
            'INTERVALS_ACTIVE'    => $intervals !== [],
            'INTERVALS'           => $this->EncodePresentationData($intervals)
        ];
    }

    /**
     * Creates a percentage slider suitable for brightness or intensity values.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function BrightnessPresentation(
        int|float $min = 0,
        int|float $max = 100,
        int|float $stepSize = 1,
        string $icon = 'sun'
    ): array {
        return $this->SliderPresentation(
            min: $min,
            max: $max,
            stepSize: $stepSize,
            usageType: 2,
            suffix: ' %',
            percentage: true,
            icon: $icon
        );
    }

    /**
     * Creates a Kelvin color-temperature slider with a custom warm/cold gradient.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function ColorTemperaturePresentation(
        int $minKelvin = 2200,
        int $maxKelvin = 6500,
        int $stepSize = 1,
        string $icon = 'temperature-half'
    ): array {
        [$minKelvin, $maxKelvin] = $this->NormalizeColorTemperatureRange($minKelvin, $maxKelvin);

        return $this->SliderPresentation(
            min: $minKelvin,
            max: $maxKelvin,
            stepSize: $stepSize,
            gradientType: 3,
            customGradient: $this->CreateColorTemperatureGradient($minKelvin, $maxKelvin),
            usageType: 1,
            suffix: ' K',
            icon: $icon
        );
    }

    /**
     * Creates the native color presentation.
     *
     * @param int   $encoding         Color encoding: 0 RGB, 1 CMYK, 2 HSV, 3 HSL.
     * @param int   $colorSpace       Color space: 0 custom, 1 sRGB, 2 AdobeRGB, 3 DCI-P3, 4 Rec2020.
     * @param array $presetValues     Optional list of preset colors.
     * @param int   $colorCurve       Color curve: 0 none, 1 custom, 2 daylight, 3 spring, 4 summer, 5 winter.
     * @param array $customColorSpace Optional custom color-space coordinates.
     * @param array $customColorCurve Optional custom color-curve coordinates.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     *
     * @throws InvalidArgumentException If encoding, color space or curve are invalid.
     * @throws JsonException            If optional color data cannot be encoded.
     */
    protected function ColorPresentation(
        int $encoding = 0,
        int $colorSpace = 1,
        array $presetValues = [],
        int $colorCurve = 0,
        array $customColorSpace = [],
        array $customColorCurve = []
    ): array {
        if ($encoding < 0 || $encoding > 3) {
            throw new InvalidArgumentException('Color encoding must be between 0 and 3.');
        }
        if ($colorSpace < 0 || $colorSpace > 4) {
            throw new InvalidArgumentException('Color space must be between 0 and 4.');
        }
        if ($colorCurve < 0 || $colorCurve > 5) {
            throw new InvalidArgumentException('Color curve must be between 0 and 5.');
        }

        $presentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_COLOR,
            'ENCODING'     => $encoding,
            'COLOR_SPACE'  => $colorSpace
        ];

        if ($presetValues !== []) {
            $presentation['PRESET_VALUES'] = $this->EncodePresentationData($presetValues);
        }
        if ($colorCurve !== 0) {
            $presentation['COLOR_CURVE'] = $colorCurve;
        }
        if ($customColorSpace !== []) {
            $presentation['CUSTOM_COLOR_SPACE'] = $this->EncodePresentationData($customColorSpace);
        }
        if ($customColorCurve !== []) {
            $presentation['CUSTOM_COLOR_CURVE'] = $this->EncodePresentationData($customColorCurve);
        }

        return $presentation;
    }

    /**
     * Creates a native duration presentation.
     *
     * @param int  $countdownType Display type: 0 value, 1 until value, 2 since value.
     * @param int  $format        Format: 0 seconds, 1 minutes+seconds, 2 hours+minutes+seconds, 3 hours+minutes.
     * @param bool $milliseconds  True to display milliseconds.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function DurationPresentation(int $countdownType = 0, int $format = 2, bool $milliseconds = false): array
    {
        if ($countdownType < 0 || $countdownType > 2) {
            throw new InvalidArgumentException('Duration countdown type must be between 0 and 2.');
        }
        if ($format < 0 || $format > 3) {
            throw new InvalidArgumentException('Duration format must be between 0 and 3.');
        }

        return [
            'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
            'COUNTDOWN_TYPE' => $countdownType,
            'FORMAT'         => $format,
            'MILLISECONDS'   => $milliseconds
        ];
    }

    /**
     * Creates a native switch presentation.
     *
     * @param bool   $useIconFalse True to use separate icons for true and false.
     * @param string $iconTrue     Icon shown for true.
     * @param string $iconFalse    Icon shown for false.
     * @param int    $glowColor    Glow color while active.
     * @param int    $glowIntensity Glow intensity from 0 to 100.
     * @param int    $usageType     Switch usage: 0 on/off, 1 mute, 2 none.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function SwitchPresentation(
        bool $useIconFalse = true,
        string $iconTrue = 'power',
        string $iconFalse = 'power-off',
        int $glowColor = 0x00C853,
        int $glowIntensity = 60,
        int $usageType = 0
    ): array {
        if ($glowIntensity < 0 || $glowIntensity > 100) {
            throw new InvalidArgumentException('Switch glow intensity must be between 0 and 100.');
        }
        if ($usageType < 0 || $usageType > 2) {
            throw new InvalidArgumentException('Switch usage type must be between 0 and 2.');
        }

        return [
            'PRESENTATION'   => VARIABLE_PRESENTATION_SWITCH,
            'USE_ICON_FALSE' => $useIconFalse,
            'ICON_TRUE'      => $iconTrue,
            'ICON_FALSE'     => $iconFalse,
            'GLOW_COLOR'     => $glowColor,
            'GLOW_INTENSITY' => $glowIntensity,
            'USAGE_TYPE'     => $usageType
        ];
    }

    /**
     * Creates a native shutter presentation.
     *
     * @param int|float      $openOutsideValue    Value representing open/outside.
     * @param int|float      $closeInsideValue    Value representing closed/inside.
     * @param int            $usageType           Usage: 0 open/closed, 1 rotation.
     * @param int            $sunPosition         Sun position: 0 left, 1 right, 2 none.
     * @param int|float|null $maxRotationInside   Optional maximum inside rotation.
     * @param int|float|null $maxRotationOutside  Optional maximum outside rotation.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function ShutterPresentation(
        int|float $openOutsideValue = 100,
        int|float $closeInsideValue = 0,
        int $usageType = 0,
        int $sunPosition = 2,
        int|float|null $maxRotationInside = null,
        int|float|null $maxRotationOutside = null
    ): array {
        if ($usageType < 0 || $usageType > 1) {
            throw new InvalidArgumentException('Shutter usage type must be 0 or 1.');
        }
        if ($sunPosition < 0 || $sunPosition > 2) {
            throw new InvalidArgumentException('Shutter sun position must be between 0 and 2.');
        }

        $presentation = [
            'PRESENTATION'       => VARIABLE_PRESENTATION_SHUTTER,
            'USAGE_TYPE'         => $usageType,
            'OPEN_OUTSIDE_VALUE' => $openOutsideValue,
            'CLOSE_INSIDE_VALUE' => $closeInsideValue,
            'SUN_POSITION'       => $sunPosition
        ];

        if ($maxRotationInside !== null) {
            $presentation['MAX_ROTATION_INSIDE'] = $maxRotationInside;
        }
        if ($maxRotationOutside !== null) {
            $presentation['MAX_ROTATION_OUTSIDE'] = $maxRotationOutside;
        }

        return $presentation;
    }

    /**
     * Creates a native selectable enumeration presentation.
     *
     * @param list<array<string,mixed>> $options Native option definitions.
     * @param int                       $layout  Layout: 0 column, 1 row, 2 grid.
     * @param int                       $display Display: 0 caption, 1 icon, 2 caption and icon.
     * @param string                    $icon    Native Symcon icon name.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     *
     * @throws InvalidArgumentException If options are empty or layout/display are invalid.
     * @throws JsonException            If the options cannot be encoded.
     */
    protected function EnumerationPresentation(array $options, int $layout = 0, int $display = 0, string $icon = ''): array
    {
        if ($options === []) {
            throw new InvalidArgumentException('Enumeration options must not be empty.');
        }
        if ($layout < 0 || $layout > 2) {
            throw new InvalidArgumentException('Enumeration layout must be between 0 and 2.');
        }
        if ($display < 0 || $display > 2) {
            throw new InvalidArgumentException('Enumeration display must be between 0 and 2.');
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => $this->EncodePresentationData($options),
            'LAYOUT'       => $layout,
            'DISPLAY'      => $display,
            'ICON'         => $icon
        ];
    }

    /**
     * Creates a read-only value presentation with an option list.
     *
     * Useful for boolean/string states or enumerations that must not expose an
     * action in the visualization.
     *
     * @param list<array<string,mixed>> $options Native option definitions.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     *
     * @throws InvalidArgumentException If options are empty.
     * @throws JsonException            If the options cannot be encoded.
     */
    protected function OptionsPresentation(
        array $options,
        string $icon = '',
        int $color = -1,
        string $prefix = '',
        string $suffix = ''
    ): array {
        if ($options === []) {
            throw new InvalidArgumentException('Presentation options must not be empty.');
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'         => $icon,
            'COLOR'        => $color,
            'PREFIX'       => $prefix,
            'SUFFIX'       => $suffix,
            'OPTIONS'      => $this->EncodePresentationData($options)
        ];
    }

    /**
     * Creates a web-content presentation.
     *
     * @param int  $htmlType 0 for HTML content, 1 for a webpage URL.
     * @param bool $padding  True to use the presentation's padding setting.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function WebContentPresentation(int $htmlType = 0, bool $padding = true): array
    {
        if ($htmlType < 0 || $htmlType > 1) {
            throw new InvalidArgumentException('HTML type must be 0 (HTML content) or 1 (webpage).');
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT,
            'HTML_TYPE'    => $htmlType,
            'PADDING'      => $padding
        ];
    }

    /**
     * Creates a date/time presentation using a native Symcon template.
     *
     * This is useful when an existing module already relies on one of Symcon's
     * date/time templates and should keep that exact presentation behavior.
     *
     * @param string $template Native Symcon template GUID.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function DateTimeTemplatePresentation(string $template): array
    {
        if (trim($template) === '') {
            throw new InvalidArgumentException('Date/time template must not be empty.');
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
            'TEMPLATE'     => $template
        ];
    }

    /**
     * Creates a date/time presentation.
     *
     * @param int  $date         Date display: 0 none, 1 year/month/day, 2 month/day, 3 year/day.
     * @param bool $monthText    True to render the month as text.
     * @param bool $dayOfTheWeek True to include the day of the week.
     * @param int  $time         Time display: 0 none, 1 hours/minutes, 2 hours/minutes/seconds.
     *
     * @return array<string,mixed> Native Symcon presentation configuration.
     */
    protected function DateTimePresentation(
        int $date = 1,
        bool $monthText = true,
        bool $dayOfTheWeek = true,
        int $time = 1
    ): array {
        if ($date < 0 || $date > 3) {
            throw new InvalidArgumentException('Date display must be between 0 and 3.');
        }
        if ($time < 0 || $time > 2) {
            throw new InvalidArgumentException('Time display must be between 0 and 2.');
        }

        return [
            'PRESENTATION'    => VARIABLE_PRESENTATION_DATE_TIME,
            'DATE'            => $date,
            'MONTH_TEXT'      => $monthText,
            'DAY_OF_THE_WEEK' => $dayOfTheWeek,
            'TIME'            => $time
        ];
    }

    /**
     * Normalizes a Kelvin range for color-temperature presentations.
     *
     * @return array{0:int,1:int}
     */
    private function NormalizeColorTemperatureRange(int $minKelvin, int $maxKelvin): array
    {
        if ($minKelvin <= 0 || $maxKelvin <= 0) {
            throw new InvalidArgumentException('Color temperature values must be greater than zero Kelvin.');
        }
        if ($minKelvin > $maxKelvin) {
            [$minKelvin, $maxKelvin] = [$maxKelvin, $minKelvin];
        }
        if ($minKelvin === $maxKelvin) {
            ++$maxKelvin;
        }

        return [$minKelvin, $maxKelvin];
    }

    /**
     * Creates a custom warm/cold gradient for a Kelvin range.
     *
     * @return list<array{Value:int,Color:int}>
     */
    private function CreateColorTemperatureGradient(int $minKelvin, int $maxKelvin): array
    {
        $anchors = [
            ['Value' => 2200, 'Color' => 0xFFB36B],
            ['Value' => 2700, 'Color' => 0xFFD19A],
            ['Value' => 3000, 'Color' => 0xFFE1B8],
            ['Value' => 3500, 'Color' => 0xFFF0D6],
            ['Value' => 4000, 'Color' => 0xFFF8EB],
            ['Value' => 4500, 'Color' => 0xF4FAFF],
            ['Value' => 5000, 'Color' => 0xE6F3FF],
            ['Value' => 6500, 'Color' => 0xD6ECFF]
        ];

        $gradient = [];
        foreach ($anchors as $anchor) {
            if ($anchor['Value'] >= $minKelvin && $anchor['Value'] <= $maxKelvin) {
                $gradient[] = $anchor;
            }
        }

        if ($gradient === [] || $gradient[0]['Value'] !== $minKelvin) {
            array_unshift($gradient, [
                'Value' => $minKelvin,
                'Color' => $this->InterpolateColorTemperatureColor($minKelvin, $anchors)
            ]);
        }

        $lastIndex = count($gradient) - 1;
        if ($gradient[$lastIndex]['Value'] !== $maxKelvin) {
            $gradient[] = [
                'Value' => $maxKelvin,
                'Color' => $this->InterpolateColorTemperatureColor($maxKelvin, $anchors)
            ];
        }

        return $gradient;
    }

    /**
     * Interpolates a Kelvin value between color-temperature anchor colors.
     *
     * @param list<array{Value:int,Color:int}> $anchors
     */
    private function InterpolateColorTemperatureColor(int $kelvin, array $anchors): int
    {
        if ($kelvin <= $anchors[0]['Value']) {
            return $anchors[0]['Color'];
        }

        $lastIndex = count($anchors) - 1;
        if ($kelvin >= $anchors[$lastIndex]['Value']) {
            return $anchors[$lastIndex]['Color'];
        }

        for ($index = 1; $index <= $lastIndex; ++$index) {
            if ($kelvin > $anchors[$index]['Value']) {
                continue;
            }

            $lower = $anchors[$index - 1];
            $upper = $anchors[$index];
            $factor = ($kelvin - $lower['Value']) / ($upper['Value'] - $lower['Value']);

            return $this->InterpolateHexColor($lower['Color'], $upper['Color'], $factor);
        }

        return $anchors[$lastIndex]['Color'];
    }

    /**
     * Interpolates two 0xRRGGBB colors.
     */
    private function InterpolateHexColor(int $fromColor, int $toColor, float $factor): int
    {
        $fromRed = ($fromColor >> 16) & 0xFF;
        $fromGreen = ($fromColor >> 8) & 0xFF;
        $fromBlue = $fromColor & 0xFF;

        $toRed = ($toColor >> 16) & 0xFF;
        $toGreen = ($toColor >> 8) & 0xFF;
        $toBlue = $toColor & 0xFF;

        $red = (int) round($fromRed + (($toRed - $fromRed) * $factor));
        $green = (int) round($fromGreen + (($toGreen - $fromGreen) * $factor));
        $blue = (int) round($fromBlue + (($toBlue - $fromBlue) * $factor));

        return ($red << 16) | ($green << 8) | $blue;
    }

    /**
     * Encodes native presentation option, interval or gradient data.
     *
     * @param array<array-key,mixed> $data
     *
     * @return string JSON-encoded presentation data.
     *
     * @throws JsonException If the data cannot be encoded.
     */
    private function EncodePresentationData(array $data): string
    {
        return json_encode($data, self::VARIABLE_PRESENTATION_JSON_FLAGS);
    }
}
