<?php

declare(strict_types=1);

use RssApp\Bootstrap;
use RssApp\Controller;
use RssApp\RequestBootstrap;
use Spiral\Debug;

define("BASEPATH", dirname(__FILE__));
define("DS", DIRECTORY_SEPARATOR);

require_once BASEPATH.DS.'external'.DS.'autoload.php';

define("APPLICATION_ENV", getenv("APPLICATION_ENV") ?? 'dev');

$dumper = new Debug\Dumper();
$dumper->setRenderer(Debug\Dumper::ERROR_LOG, new Debug\Renderer\ConsoleRenderer());
function dump($msg) {
    global $dumper;
    $dumper->dump($msg, Debug\Dumper::ERROR_LOG);
}

try {
    Bootstrap::initialize();
} catch (Exception $e) {
    dump("Problem with application bootstrap: ".$e->getMessage());
}

$relay = new Spiral\Goridge\StreamRelay(STDIN, STDOUT);
$psr7  = new Spiral\RoadRunner\PSR7Client(new Spiral\RoadRunner\Worker($relay));
while ($req = $psr7->acceptRequest()) {
    try {
        RequestBootstrap::initialize();
        $resp = Controller::handle();
        $psr7->respond($resp);
    } catch (Throwable $e) {
        $psr7->getWorker()->error((string)$e);
    }
}
