<?php

declare(strict_types=1);

// Optional browser test fixture: entirely synthetic, never constructs an Apple client.
require_once __DIR__ . '/stubs/autoload.php';
IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary(dirname(__DIR__) . '/library.json');
$id = IPS_CreateInstance('{7F1383D4-5B82-4F95-A52B-664E56FA5C3A}');
foreach (['Enabled' => true, 'EnableIPSView' => true, 'AlbumID' => 'synthetic-album'] as $key => $value) {
    IPS_SetProperty($id, $key, $value);
}
IPS_ApplyChanges($id);
$module = IPS\InstanceManager::getInstanceInterface($id);
$ipsView = str_starts_with($argv[1] ?? '', 'ipsview');
if (($argv[1] ?? '') === 'ipsview-dark') {
    IPS_SetProperty($id, 'IPSViewStyleSource', 7);
    IPS_ApplyChanges($id);
}
$html = (new ReflectionMethod($module, 'PhotoPage'))->invoke($module, $ipsView, true);
preg_match('#<script type="application/json" id="photo-bootstrap">(.*?)</script>#s', $html, $match);
$bootstrap = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
$bootstrap['state'] = ['items' => [['id' => 'photo-1', 'name' => 'Synthetic first photo.jpg', 'date' => 1700000000], ['id' => 'photo-2', 'name' => 'Synthetic second photo.jpg', 'date' => 1700000000]], 'offset' => 0, 'next' => null, 'total' => 2];
$bootstrap['runtime'] = ['source' => 'browser-fixture'];
$bootstrap['options'] = array_replace($bootstrap['options'], ['endpoint' => '/hook/photos', 'token' => str_repeat('f', 64), 'fragmentToken' => $ipsView, 'mode' => 'slideshow', 'seconds' => 3600, 'instance' => 999]);
echo str_replace($match[1], json_encode($bootstrap, JSON_HEX_TAG | JSON_THROW_ON_ERROR), $html);
