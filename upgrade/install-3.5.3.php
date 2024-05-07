<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2016 PrestaShop SA
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * @param MailAlerts $object
 *
 * @return bool
 * @throws PrestaShopException
 */
function upgrade_module_3_5_3($object)
{
    $success = true;

    if (!$object->isRegisteredInHook('actionOrderEdited')) {
        $success = $object->registerHook('actionOrderEdited');
    }

    if (!$object->isRegisteredInHook('actionOrderReturn')) {
        $success = $object->registerHook('actionOrderReturn') && $success;
    }

    Configuration::updateValue('MA_ORDER_EDIT', 1);
    Configuration::updateValue('MA_RETURN_SLIP', 1);

    return $success;
}
