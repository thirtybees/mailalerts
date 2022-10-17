<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

use MailAlertModule\MailAlert;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * @since 1.5.0
 */
class MailalertsActionsModuleFrontController extends ModuleFrontController
{
    /**
     * @return void
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if (Tools::getValue('process') == 'remove') {
            $this->processRemove();
        } else {
            if (Tools::getValue('process') == 'add') {
                $this->processAdd();
            } else {
                if (Tools::getValue('process') == 'check') {
                    $this->processCheck();
                }
            }
        }
    }

    /**
     * Remove a favorite product
     * @throws PrestaShopException
     */
    public function processRemove()
    {
        if (! $this->context->customer->isLogged()) {
            die('0');
        }

        // check if product exists
        $idProduct = (int)Tools::getValue('id_product');
        $idProductAttribute = (int)Tools::getValue('id_product_attribute');
        $idCustomer = (int)$this->context->customer->id;
        $customerEmail = $this->context->customer->email;

        if (MailAlert::deleteAlert($idCustomer, $customerEmail, $idProduct, $idProductAttribute)) {
            die('0');
        }

        die(1);
    }

    /**
     * Add a favorite product
     * @throws PrestaShopException
     */
    public function processAdd()
    {
        $context = Context::getContext();

        if ($context->customer->isLogged()) {
            $idCustomer = (int) $context->customer->id;
            $customer = new Customer($idCustomer);
            $customerEmail = (string) $customer->email;
        } else {
            $customerEmail = (string) Tools::getValue('customer_email');
            if (! Validate::isEmail($customerEmail)) {
                die('0');
            }
            $customer = $context->customer->getByEmail($customerEmail);
            $idCustomer = Validate::isLoadedObject($customer)
                ? (int)$customer->id
                : 0;
        }

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $idShop = (int) $context->shop->id;
        $idLang = (int) $context->language->id;

        // check that email alert does not exist yet
        if (MailAlert::customerHasNotification($idCustomer, $idProduct, $idProductAttribute, $idShop, $customerEmail)) {
            die('2');
        }

        // verify that product exists
        $product = new Product($idProduct);
        if (! Validate::isLoadedObject($product)) {
            die('0');
        }

        $mailAlert = new MailAlert();

        $mailAlert->id_customer = $idCustomer;
        $mailAlert->customer_email = (string) $customerEmail;
        $mailAlert->id_product = (int) $idProduct;
        $mailAlert->id_product_attribute = (int) $idProductAttribute;
        $mailAlert->id_shop = (int) $idShop;
        $mailAlert->id_lang = (int) $idLang;

        if ($mailAlert->add() !== false) {
            die('1');
        }

        die('0');
    }

    /**
     * Add a favorite product
     * @throws PrestaShopException
     */
    public function processCheck()
    {
        if (! $this->context->customer->isLogged()) {
            die('0');
        }

        $idCustomer = (int)$this->context->customer->id;
        $idProduct = (int)Tools::getValue('id_product');
        $idProductAttribute = (int)Tools::getValue('id_product_attribute');

        if (MailAlert::customerHasNotification($idCustomer, $idProduct, $idProductAttribute)) {
            die('1');
        }

        die('0');
    }
}
