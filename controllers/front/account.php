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

use MailAlertModule\MailAlert;

/**
 * @since 1.5.0
 */
class MailalertsAccountModuleFrontController extends ModuleFrontController
{
    /**
     * @return void
     * @throws PrestaShopException
     */
    public function initContent()
    {
        parent::initContent();

        $customer = Context::getContext()->customer;
        if (! $customer->isLogged()) {
            Tools::redirect('index.php?controller=authentication&redirect=module&module=mailalerts&action=account');
        } else {
            $langId = (int)Context::getContext()->language->id;
            $customerId = (int)$customer->id;
            $this->context->smarty->assign([
                'id_customer' => $customerId,
                'mailAlerts' => MailAlert::getMailAlerts($customerId, $langId)
            ]);
            $this->setTemplate('mailalerts-account.tpl');
        }
    }
}
