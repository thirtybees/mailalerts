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
    // @codingStandardsIgnoreStart
    /**
     * @var int
     */
    public $id_product;
    public $id_product_attribute;
    // @codingStandardsIgnoreEnd

    /**
     * @return void
     */
    public function init()
    {
        parent::init();

        $this->id_product = (int) Tools::getValue('id_product');
        $this->id_product_attribute = (int) Tools::getValue('id_product_attribute');
    }

    /**
     * @return void
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
     */
    public function processRemove()
    {
        // check if product exists
        $product = new Product($this->id_product);
        if (!Validate::isLoadedObject($product)) {
            die('0');
        }

        $context = Context::getContext();
        if (MailAlert::deleteAlert(
            (int) $context->customer->id,
            (int) $context->customer->email,
            (int) $product->id,
            (int) $this->id_product_attribute,
            (int) $context->shop->id
        )
        ) {
            die('0');
        }

        die(1);
    }

    /**
     * Add a favorite product
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
            $customer = $context->customer->getByEmail($customerEmail);
            $idCustomer = (isset($customer->id) && ($customer->id != null)) ? (int) $customer->id : null;
        }

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $idShop = (int) $context->shop->id;
        $idLang = (int) $context->language->id;
        $product = new Product($idProduct, false, $idLang, $idShop, $context);

        $mailAlert = MailAlert::customerHasNotification($idCustomer, $idProduct, $idProductAttribute, $idShop, null, $customerEmail);

        if ($mailAlert) {
            die('2');
        } elseif (!Validate::isLoadedObject($product)) {
            die('0');
        }

        $mailAlert = new MailAlert();

        $mailAlert->id_customer = (int) $idCustomer;
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
     */
    public function processCheck()
    {
        if (!(int) $this->context->customer->logged) {
            die('0');
        }

        $idCustomer = (int) $this->context->customer->id;

        if (!$idProduct = (int) Tools::getValue('id_product')) {
            die('0');
        }

        $idProductAttribute = (int) Tools::getValue('id_product_attribute');

        if (MailAlert::customerHasNotification((int) $idCustomer, (int) $idProduct, (int) $idProductAttribute, (int) $this->context->shop->id)) {
            die('1');
        }

        die('0');
    }
}
