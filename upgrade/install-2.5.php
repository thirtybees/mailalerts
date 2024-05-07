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
 * @return bool
 * @throws PrestaShopException
 */
function upgrade_module_2_5($object)
{
    Db::getInstance()->execute(
        '
		ALTER TABLE `'._DB_PREFIX_.'mailalert_customer_oos` 
		ADD `id_lang` INT( 10 ) UNSIGNED NOT NULL 
	'
    );

    Db::getInstance()->execute(
        '
		ALTER TABLE `'._DB_PREFIX_.'mailalert_customer_oos` 
		DROP PRIMARY KEY , 
		ADD PRIMARY KEY (`id_customer` , `customer_email` , `id_product` , `id_product_attribute` , `id_shop`)
	'
    );

    return true;
}
