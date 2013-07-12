<?php
/**
 * @package opencloud
 */
/**
 * Handles adding OpenCloudMediaSource to Extension Packages
 *
 * @package opencloud
 * @subpackage build
 */

 if ($transport->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            /** @var modX $modx */
            $modx =& $transport->xpdo;
            $modelPath = $modx->getOption('opencloud.core_path');
            if (empty($modelPath)) {
                $modelPath = '[[++core_path]]components/opencloud/model/';
            }
            if ($modx instanceof modX) {
                $modx->addExtensionPackage('opencloud',$modelPath);
            }
            break;
        case xPDOTransport::ACTION_UNINSTALL:
            $modx =& $transport->xpdo;
            $modelPath = $modx->getOption('opencloud.core_path',null,$modx->getOption('core_path').'components/opencloud/').'model/';
            if ($modx instanceof modX) {
                $modx->removeExtensionPackage('opencloud');
            }
            break;
    }
}
return true;