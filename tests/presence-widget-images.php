<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PresenceWidget.php';

function checkImage(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

final class StatusWidgetImageHarness
{
    use \SymconiCloud\PresenceWidget {
        PrepareRasterImage as private uncachedPrepareRasterImage;
    }
    public int $preparations = 0;

    private string $pictures = '{}';

    public function cacheSize(): int
    {
        return count(json_decode($this->pictures, true));
    }

    public function image(int $id): string
    {
        return $this->StatusWidgetImage($id);
    }

    public function upload(string $content): string
    {
        return $this->StatusWidgetUpload($content);
    }

    public function issue(string $content): string
    {
        return $this->StatusWidgetUploadIssue($content);
    }

    protected function PrepareRasterImage(string $content): array
    {
        $this->preparations++;
        return $this->uncachedPrepareRasterImage($content);
    }

    protected function ReadAttributeString(string $name): string
    {
        return $this->pictures;
    }

    protected function WriteAttributeString(string $name, string $value): void
    {
        $this->pictures = $value;
    }
}

$imageMedia = ['MediaType' => 1, 'MediaIsAvailable' => true, 'MediaSize' => 34];
$imageContent = 'R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=';
function IPS_MediaExists(int $id): bool
{
    return $id === 1;
}
function IPS_GetMedia(int $id): array
{
    return $GLOBALS['imageMedia'];
}
function IPS_GetMediaContent(int $id): string
{
    return $GLOBALS['imageContent'];
}
$widget = new StatusWidgetImageHarness();
checkImage('', $widget->issue($imageContent), 'Valid uploads do not show an error.');
checkImage('', $widget->issue(''), 'Removing an optional upload clears its error.');
checkImage(true, str_contains($widget->issue(base64_encode(str_repeat('x', 10485761))), '10 MiB'), 'Oversized source uploads must explain the input limit.');
checkImage(true, str_contains($widget->issue('invalid'), 'PNG'), 'Invalid contents must explain supported image formats.');
checkImage('data:image/gif;base64,' . $imageContent, $widget->upload($imageContent), 'Console file contents use the same safe inline image format.');
$preparedCount = $widget->preparations;
$widget->upload($imageContent);
$widget->issue($imageContent);
checkImage($preparedCount, $widget->preparations, 'Rendering and validation reuse the prepared image cache.');
foreach (['', 'not base64', base64_encode('<svg onload="alert(1)"></svg>'), base64_encode('https://example.invalid/image')] as $invalid) {
    checkImage('', $widget->upload($invalid), 'Empty or invalid uploads use initials without network or filesystem reads.');
}
checkImage('', $widget->upload(base64_encode(str_repeat('x', 131073))), 'Arbitrary bytes cannot bypass image validation.');
// A GIF header with excessive dimensions must be rejected before browser decoding.
$wide = base64_decode($imageContent);
$wide = substr_replace($wide, pack('v', 8193), 6, 2);
checkImage('', $widget->upload(base64_encode($wide)), 'Unsafe source dimensions are rejected before decoding.');
checkImage(true, str_contains($widget->issue(base64_encode($wide)), '8192'), 'Unsafe dimensions need a separate correction message.');
checkImage('', $widget->image(0), 'No picture uses initials.');
checkImage('', $widget->image(2), 'Deleted media uses initials.');
checkImage('data:image/gif;base64,' . $imageContent, $widget->image(1), 'Supported image content is embedded.');
$imageMedia['MediaType'] = 5;
checkImage('', $widget->image(1), 'Document media cannot be embedded.');
$imageMedia['MediaType'] = 1;
$imageMedia['MediaSize'] = 131073;
checkImage('', $widget->image(1), 'Oversized media is rejected before content reads.');
$imageMedia['MediaSize'] = 100;
$imageContent = base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
checkImage('', $widget->image(1), 'SVG cannot inject executable content.');
$imageContent = base64_encode('https://example.invalid/private-image');
checkImage('', $widget->image(1), 'Remote URLs are never loaded.');
$imageContent = 'not base64';
checkImage('', $widget->image(1), 'Malformed media fails safely.');
for ($index = 0; $index < 70; $index++) {
    $widget->upload(base64_encode(base64_decode('R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=') . str_repeat("\0", $index)));
}
checkImage(64, $widget->cacheSize(), 'Prepared image cache remains bounded.');
echo "Status widget image validation passed.\n";
