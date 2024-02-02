<?php
/**
 * Load Javascrip
 */
namespace OCA\Extract\AppInfo;

use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Util;

$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function(){
    Util::addScript('extract', 'extraction' );
    Util::addStyle('extract', 'style' );
});
