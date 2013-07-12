<?php
/**
 * @package opencloud
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/opencloudmediasource.class.php');
class OpencloudMediaSource_mysql extends OpencloudMediaSource {}
?>