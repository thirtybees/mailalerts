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

if (!defined('_CAN_LOAD_FILES_')) {
    exit;
}

require_once __DIR__.'/classes/autoload.php';

/**
 * Class MailAlerts
 */
class MailAlerts extends Module
{
    const __MA_MAIL_DELIMITOR__ = "\n";
    protected $html = '';
    protected $merchant_mails;
    protected $merchant_order;
    protected $merchant_oos;
    protected $customer_qty;
    protected $merchant_coverage;
    protected $product_coverage;
    protected $order_edited;
    protected $return_slip;

    /**
     * MailAlerts constructor.
     */
    public function __construct()
    {
        $this->name = 'mailalerts';
        $this->tab = 'administration';
        $this->version = '4.0.4';
        $this->author = 'thirty bees';
        $this->need_instance = 0;

        $this->controllers = ['account'];

        $this->bootstrap = true;
        parent::__construct();

        if ($this->id) {
            $this->init();
        }

        $this->displayName = $this->l('Mail alerts');
        $this->description = $this->l('Sends e-mail notifications to customers and merchants.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete all customer notifications?');
    }

    /**
     * Initialize
     */
    protected function init()
    {
        $this->merchant_mails = str_replace(',', self::__MA_MAIL_DELIMITOR__, (string) Configuration::get('MA_MERCHANT_MAILS'));
        $this->merchant_order = (int) Configuration::get('MA_MERCHANT_ORDER');
        $this->merchant_oos = (int) Configuration::get('MA_MERCHANT_OOS');
        $this->customer_qty = (int) Configuration::get('MA_CUSTOMER_QTY');
        $this->merchant_coverage = (int) Configuration::getGlobalValue('MA_MERCHANT_COVERAGE');
        $this->product_coverage = (int) Configuration::getGlobalValue('MA_PRODUCT_COVERAGE');
        $this->order_edited = (int) Configuration::getGlobalValue('MA_ORDER_EDIT');
        $this->return_slip = (int) Configuration::getGlobalValue('MA_RETURN_SLIP');
    }

    /**
     * @return bool
     */
    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall this module
     *
     * @param bool $deleteParams
     *
     * @return bool
     */
    public function uninstall($deleteParams = true)
    {
        if ($deleteParams) {
            Configuration::deleteByName('MA_MERCHANT_ORDER');
            Configuration::deleteByName('MA_MERCHANT_OOS');
            Configuration::deleteByName('MA_CUSTOMER_QTY');
            Configuration::deleteByName('MA_MERCHANT_MAILS');
            Configuration::deleteByName('MA_LAST_QTIES');
            Configuration::deleteByName('MA_MERCHANT_COVERAGE');
            Configuration::deleteByName('MA_PRODUCT_COVERAGE');
            Configuration::deleteByName('MA_ORDER_EDIT');
            Configuration::deleteByName('MA_RETURN_SLIP');

            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.MailAlert::$definition['table'])) {
                return false;
            }
        }

        return parent::uninstall();
    }

    /**
     * @param bool $deleteParams
     *
     * @return bool
     */
    public function install($deleteParams = true)
    {
        if (!parent::install() ||
            !$this->registerHook('actionValidateOrder') ||
            !$this->registerHook('actionUpdateQuantity') ||
            !$this->registerHook('actionProductOutOfStock') ||
            !$this->registerHook('displayCustomerAccount') ||
            !$this->registerHook('displayMyAccountBlock') ||
            !$this->registerHook('actionProductDelete') ||
            !$this->registerHook('actionProductAttributeDelete') ||
            !$this->registerHook('actionProductAttributeUpdate') ||
            !$this->registerHook('actionProductCoverage') ||
            !$this->registerHook('actionOrderReturn') ||
            !$this->registerHook('actionOrderEdited') ||
            !$this->registerHook('displayHeader')
        ) {
            return false;
        }

        if ($deleteParams) {
            Configuration::updateValue('MA_MERCHANT_ORDER', 1);
            Configuration::updateValue('MA_MERCHANT_OOS', 1);
            Configuration::updateValue('MA_CUSTOMER_QTY', 1);
            Configuration::updateValue('MA_ORDER_EDIT', 1);
            Configuration::updateValue('MA_RETURN_SLIP', 1);
            Configuration::updateValue('MA_MERCHANT_MAILS', Configuration::get('PS_SHOP_EMAIL'));
            Configuration::updateValue('MA_LAST_QTIES', (int) Configuration::get('PS_LAST_QTIES'));
            Configuration::updateGlobalValue('MA_MERCHANT_COVERAGE', 0);
            Configuration::updateGlobalValue('MA_PRODUCT_COVERAGE', 0);

            $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.MailAlert::$definition['table'].'`
				(
					`id_customer` int(10) unsigned NOT NULL,
					`customer_email` varchar(128) NOT NULL,
					`id_product` int(10) unsigned NOT NULL,
					`id_product_attribute` int(10) unsigned NOT NULL,
					`id_shop` int(10) unsigned NOT NULL,
					`id_lang` int(10) unsigned NOT NULL,
					PRIMARY KEY  (`id_customer`,`customer_email`,`id_product`,`id_product_attribute`,`id_shop`)
				) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Module configuration page
     *
     * @return string Module HTML
     */
    public function getContent()
    {
        $this->html = '';

        $this->postProcess();

        $this->html .= $this->renderForm();

        return $this->html;
    }

    /**
     * Process module configuration
     */
    protected function postProcess()
    {
        $errors = [];

        if (Tools::isSubmit('submitMailAlert')) {
            if (!Configuration::updateValue('MA_CUSTOMER_QTY', (int) Tools::getValue('MA_CUSTOMER_QTY'))) {
                $errors[] = $this->l('Cannot update settings');
            }
        } else {
            if (Tools::isSubmit('submitMAMerchant')) {
                $emails = (string) Tools::getValue('MA_MERCHANT_MAILS');

                if (!$emails || empty($emails)) {
                    $errors[] = $this->l('Please type one (or more) e-mail address');
                } else {
                    $emails = str_replace(',', self::__MA_MAIL_DELIMITOR__, $emails);
                    $emails = explode(self::__MA_MAIL_DELIMITOR__, $emails);
                    foreach ($emails as $k => $email) {
                        $email = trim($email);
                        if (!empty($email) && !Validate::isEmail($email)) {
                            $errors[] = $this->l('Invalid e-mail:').' '.Tools::safeOutput($email);
                            break;
                        } elseif (!empty($email) && count($email) > 0) {
                            $emails[$k] = $email;
                        } else {
                            unset($emails[$k]);
                        }
                    }

                    $emails = implode(self::__MA_MAIL_DELIMITOR__, $emails);

                    if (!Configuration::updateValue('MA_MERCHANT_MAILS', (string) $emails)) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateValue('MA_MERCHANT_ORDER', (int) Tools::getValue('MA_MERCHANT_ORDER'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateValue('MA_MERCHANT_OOS', (int) Tools::getValue('MA_MERCHANT_OOS'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateValue('MA_LAST_QTIES', (int) Tools::getValue('MA_LAST_QTIES'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateGlobalValue('MA_MERCHANT_COVERAGE', (int) Tools::getValue('MA_MERCHANT_COVERAGE'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateGlobalValue('MA_PRODUCT_COVERAGE', (int) Tools::getValue('MA_PRODUCT_COVERAGE'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateGlobalValue('MA_ORDER_EDIT', (int) Tools::getValue('MA_ORDER_EDIT'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateGlobalValue('MA_RETURN_SLIP', (int) Tools::getValue('MA_RETURN_SLIP'))) {
                        $errors[] = $this->l('Cannot update settings');
                    }
                }
            }
        }

        if (count($errors) > 0) {
            $this->html .= $this->displayError(implode('<br />', $errors));
        } else {
            $this->html .= $this->displayConfirmation($this->l('Settings updated successfully'));
        }

        $this->init();
    }

    /**
     * @return string
     */
    public function renderForm()
    {
        $fieldsForm1 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Customer notifications'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('Product availability'),
                        'name'    => 'MA_CUSTOMER_QTY',
                        'desc'    => $this->l('Gives the customer the option of receiving a notification when an out-of-stock product is available again.'),
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('Order edit'),
                        'name'    => 'MA_ORDER_EDIT',
                        'desc'    => $this->l('Send a notification to the customer when an order is edited.'),
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitMailAlert',
                ],
            ],
        ];

        $fieldsForm2 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Merchant notifications'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('New order'),
                        'name'    => 'MA_MERCHANT_ORDER',
                        'desc'    => $this->l('Receive a notification when an order is placed.'),
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('Out of stock'),
                        'name'    => 'MA_MERCHANT_OOS',
                        'desc'    => $this->l('Receive a notification if the available quantity of a product is below the following threshold.'),
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Threshold'),
                        'name'  => 'MA_LAST_QTIES',
                        'class' => 'fixed-width-xs',
                        'desc'  => $this->l('Quantity for which a product is considered out of stock.'),
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('Coverage warning'),
                        'name'    => 'MA_MERCHANT_COVERAGE',
                        'desc'    => $this->l('Receive a notification when a product has insufficient coverage.'),
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Coverage'),
                        'name'  => 'MA_PRODUCT_COVERAGE',
                        'class' => 'fixed-width-xs',
                        'desc'  => $this->l('Stock coverage, in days. Also, the stock coverage of a given product will be calculated based on this number.'),
                    ],
                    [
                        'type'    => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label'   => $this->l('Returns'),
                        'name'    => 'MA_RETURN_SLIP',
                        'desc'    => $this->l('Receive a notification when a customer requests a merchandise return.'),
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type'  => 'textarea',
                        'cols'  => 36,
                        'rows'  => 4,
                        'label' => $this->l('E-mail addresses'),
                        'name'  => 'MA_MERCHANT_MAILS',
                        'desc'  => $this->l('One e-mail address per line (e.g. bob@example.com).'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitMAMerchant',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMailAlertConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name
            .'&tab_module='.$this->tab
            .'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm1, $fieldsForm2]);
    }

    /**
     * Configuration field values
     *
     * @return array
     */
    public function getConfigFieldsValues()
    {
        return [
            'MA_CUSTOMER_QTY'      => Tools::getValue('MA_CUSTOMER_QTY', Configuration::get('MA_CUSTOMER_QTY')),
            'MA_MERCHANT_ORDER'    => Tools::getValue('MA_MERCHANT_ORDER', Configuration::get('MA_MERCHANT_ORDER')),
            'MA_MERCHANT_OOS'      => Tools::getValue('MA_MERCHANT_OOS', Configuration::get('MA_MERCHANT_OOS')),
            'MA_LAST_QTIES'        => Tools::getValue('MA_LAST_QTIES', Configuration::get('MA_LAST_QTIES')),
            'MA_MERCHANT_COVERAGE' => Tools::getValue('MA_MERCHANT_COVERAGE', Configuration::get('MA_MERCHANT_COVERAGE')),
            'MA_PRODUCT_COVERAGE'  => Tools::getValue('MA_PRODUCT_COVERAGE', Configuration::get('MA_PRODUCT_COVERAGE')),
            'MA_MERCHANT_MAILS'    => Tools::getValue('MA_MERCHANT_MAILS', Configuration::get('MA_MERCHANT_MAILS')),
            'MA_ORDER_EDIT'        => Tools::getValue('MA_ORDER_EDIT', Configuration::get('MA_ORDER_EDIT')),
            'MA_RETURN_SLIP'       => Tools::getValue('MA_RETURN_SLIP', Configuration::get('MA_RETURN_SLIP')),
        ];
    }

    public function hookActionValidateOrder($params)
    {
        if (!$this->merchant_order || empty($this->merchant_mails)) {
            return;
        }

        // Getting differents vars
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $currency = $params['currency'];
        $order = $params['order'];
        $customer = $params['customer'];
        $configuration = Configuration::getMultiple(
            [
                'PS_SHOP_EMAIL',
                'PS_MAIL_METHOD',
                'PS_MAIL_SERVER',
                'PS_MAIL_USER',
                'PS_MAIL_PASSWD',
                'PS_SHOP_NAME',
                'PS_MAIL_COLOR',
            ],
            $idLang,
            null,
            $idShop
        );
        $delivery = new Address((int) $order->id_address_delivery);
        $invoice = new Address((int) $order->id_address_invoice);
        $orderDateText = Tools::displayDate($order->date_add);
        $carrier = new Carrier((int) $order->id_carrier);
        $message = $this->getAllMessages($order->id);

        if (!$message || empty($message)) {
            $message = $this->l('No message');
        }

        $itemsTable = '';

        $products = $params['order']->getProducts();
        $customizedDatas = Product::getAllCustomizedDatas((int) $params['cart']->id);
        Product::addCustomizationPrice($products, $customizedDatas);
        foreach ($products as $key => $product) {
            $unitPrice = Product::getTaxCalculationMethod($customer->id) == PS_TAX_EXC ? $product['product_price'] : $product['product_price_wt'];

            $customizationText = '';
            if (isset($customizedDatas[$product['product_id']][$product['product_attribute_id']])) {
                foreach ($customizedDatas[$product['product_id']][$product['product_attribute_id']][$order->id_address_delivery] as $customization) {
                    if (isset($customization['datas'][Product::CUSTOMIZE_TEXTFIELD])) {
                        foreach ($customization['datas'][Product::CUSTOMIZE_TEXTFIELD] as $text) {
                            $customizationText .= $text['name'].': '.$text['value'].'<br />';
                        }
                    }

                    if (isset($customization['datas'][Product::CUSTOMIZE_FILE])) {
                        $customizationText .= count($customization['datas'][Product::CUSTOMIZE_FILE]).' '.$this->l('image(s)').'<br />';
                    }

                    $customizationText .= '---<br />';
                }
                if (method_exists('Tools', 'rtrimString')) {
                    $customizationText = Tools::rtrimString($customizationText, '---<br />');
                } else {
                    $customizationText = preg_replace('/---<br \/>$/', '', $customizationText);
                }
            }

            $url = $context->link->getProductLink($product['product_id']);
            $itemsTable .=
                '<tr style="background-color:'.($key % 2 ? '#DDE2E6' : '#EBECEE').';">
					<td style="padding:0.6em 0.4em;">'.$product['product_reference'].'</td>
					<td style="padding:0.6em 0.4em;">
						<strong><a href="'.$url.'">'.$product['product_name'].'</a>'
                .(isset($product['attributes_small']) ? ' '.$product['attributes_small'] : '')
                .(!empty($customizationText) ? '<br />'.$customizationText : '')
                .'</strong>
					</td>
					<td style="padding:0.6em 0.4em; text-align:right;">'.Tools::displayPrice($unitPrice, $currency, false).'</td>
					<td style="padding:0.6em 0.4em; text-align:center;">'.(int) $product['product_quantity'].'</td>
					<td style="padding:0.6em 0.4em; text-align:right;">'
                .Tools::displayPrice(($unitPrice * $product['product_quantity']), $currency, false)
                .'</td>
				</tr>';
        }
        foreach ($params['order']->getCartRules() as $discount) {
            $itemsTable .=
                '<tr style="background-color:#EBECEE;">
						<td colspan="4" style="padding:0.6em 0.4em; text-align:right;">'.$this->l('Voucher code:').' '.$discount['name'].'</td>
					<td style="padding:0.6em 0.4em; text-align:right;">-'.Tools::displayPrice($discount['value'], $currency, false).'</td>
			</tr>';
        }
        if ($delivery->id_state) {
            $deliveryState = new State((int) $delivery->id_state);
        }
        if ($invoice->id_state) {
            $invoiceState = new State((int) $invoice->id_state);
        }

        /** @var Order $order */
        if (Product::getTaxCalculationMethod($customer->id) == PS_TAX_EXC) {
            $totalProducts = $order->getTotalProductsWithoutTaxes();
        } else {
            $totalProducts = $order->getTotalProductsWithTaxes();
        }

        $orderState = $params['orderStatus'];

        // Filling-in vars for email
        $templateVars = [
            '{firstname}'            => $customer->firstname,
            '{lastname}'             => $customer->lastname,
            '{email}'                => $customer->email,
            '{delivery_block_txt}'   => MailAlert::getFormatedAddress($delivery, "\n"),
            '{invoice_block_txt}'    => MailAlert::getFormatedAddress($invoice, "\n"),
            '{delivery_block_html}'  => MailAlert::getFormatedAddress(
                $delivery, '<br />', [
                    'firstname' => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                    'lastname'  => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                ]
            ),
            '{invoice_block_html}'   => MailAlert::getFormatedAddress(
                $invoice, '<br />', [
                    'firstname' => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                    'lastname'  => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                ]
            ),
            '{delivery_company}'     => $delivery->company,
            '{delivery_firstname}'   => $delivery->firstname,
            '{delivery_lastname}'    => $delivery->lastname,
            '{delivery_address1}'    => $delivery->address1,
            '{delivery_address2}'    => $delivery->address2,
            '{delivery_city}'        => $delivery->city,
            '{delivery_postal_code}' => $delivery->postcode,
            '{delivery_country}'     => $delivery->country,
            '{delivery_state}'       => $delivery->id_state ? $deliveryState->name : '',
            '{delivery_phone}'       => $delivery->phone ? $delivery->phone : $delivery->phone_mobile,
            '{delivery_other}'       => $delivery->other,
            '{invoice_company}'      => $invoice->company,
            '{invoice_firstname}'    => $invoice->firstname,
            '{invoice_lastname}'     => $invoice->lastname,
            '{invoice_address2}'     => $invoice->address2,
            '{invoice_address1}'     => $invoice->address1,
            '{invoice_city}'         => $invoice->city,
            '{invoice_postal_code}'  => $invoice->postcode,
            '{invoice_country}'      => $invoice->country,
            '{invoice_state}'        => $invoice->id_state ? $invoiceState->name : '',
            '{invoice_phone}'        => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}'        => $invoice->other,
            '{order_name}'           => $order->reference,
            '{order_status}'         => $orderState->name,
            '{shop_name}'            => $configuration['PS_SHOP_NAME'],
            '{date}'                 => $orderDateText,
            '{carrier}'              => (($carrier->name == '0') ? $configuration['PS_SHOP_NAME'] : $carrier->name),
            '{payment}'              => Tools::substr($order->payment, 0, 32),
            '{items}'                => $itemsTable,
            '{total_paid}'           => Tools::displayPrice($order->total_paid, $currency),
            '{total_products}'       => Tools::displayPrice($totalProducts, $currency),
            '{total_discounts}'      => Tools::displayPrice($order->total_discounts, $currency),
            '{total_shipping}'       => Tools::displayPrice($order->total_shipping, $currency),
            '{total_tax_paid}'       => Tools::displayPrice(
                ($order->total_products_wt - $order->total_products) + ($order->total_shipping_tax_incl - $order->total_shipping_tax_excl),
                $currency,
                false
            ),
            '{total_wrapping}'       => Tools::displayPrice($order->total_wrapping, $currency),
            '{currency}'             => $currency->sign,
            '{gift}'                 => (bool) $order->gift,
            '{gift_message}'         => $order->gift_message,
            '{message}'              => $message,
        ];

        // Shop iso
        $iso = Language::getIsoById((int) Configuration::get('PS_LANG_DEFAULT'));

        // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
        $merchantMails = explode(static::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
        foreach ($merchantMails as $merchantMail) {
            // Default language
            $mailIdLang = $idLang;
            $mailIso = $iso;

            // Use the merchant lang if he exists as an employee
            $results = Db::getInstance()->executeS(
                '
				SELECT `id_lang` FROM `'._DB_PREFIX_.'employee`
				WHERE `email` = \''.pSQL($merchantMail).'\'
			'
            );
            if ($results) {
                $userIso = Language::getIsoById((int) $results[0]['id_lang']);
                if ($userIso) {
                    $mailIdLang = (int) $results[0]['id_lang'];
                    $mailIso = $userIso;
                }
            }

            $dirMail = false;
            if (file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/new_order.txt") &&
                file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/new_order.html")) {
                $dirMail = _PS_THEME_DIR_."modules/$this->name/mails/";
            } elseif (file_exists(__DIR__."/mails/$mailIso/new_order.txt") &&
                file_exists(__DIR__."/mails/$mailIso/new_order.html")
            ) {
                $dirMail = __DIR__.'/mails/';
            } elseif (file_exists(_PS_MAIL_DIR_.$mailIso.'/new_order.txt') &&
                file_exists(_PS_MAIL_DIR_.$mailIso.'/new_order.html')) {
                $dirMail = _PS_MAIL_DIR_;
            } elseif (Language::getIdByIso('en')) {
                $mailIdLang = (int) Language::getIdByIso('en');
                $dirMail = __DIR__.'/mails/';
            }

            if ($dirMail) {
                Mail::Send(
                    $mailIdLang,
                    'new_order',
                    sprintf(Mail::l('New order : #%d - %s', $mailIdLang), $order->id, $order->reference),
                    $templateVars,
                    $merchantMail,
                    null,
                    $configuration['PS_SHOP_EMAIL'],
                    $configuration['PS_SHOP_NAME'],
                    null,
                    null,
                    $dirMail,
                    null,
                    $idShop
                );
            }
        }
    }

    /**
     * Get all messages
     *
     * @param int $id
     *
     * @return string
     */
    public function getAllMessages($id)
    {
        $messages = Db::getInstance()->executeS(
            '
			SELECT `message`
			FROM `'._DB_PREFIX_.'message`
			WHERE `id_order` = '.(int) $id.'
			ORDER BY `id_message` ASC'
        );
        $result = [];
        foreach ($messages as $message) {
            $result[] = $message['message'];
        }

        return implode('<br/>', $result);
    }

    /**
     *
     *
     * @param array $params
     *
     * @return string
     */
    public function hookActionProductOutOfStock($params)
    {
        if (!$this->customer_qty ||
            !Configuration::get('PS_STOCK_MANAGEMENT') ||
            Product::isAvailableWhenOutOfStock($params['product']->out_of_stock)
        ) {
            return '';
        }

        $context = Context::getContext();
        $idProduct = (int) $params['product']->id;
        $idProductAttribute = 0;
        $idCustomer = (int) $context->customer->id;

        if ((int) $context->customer->id <= 0) {
            $this->context->smarty->assign('email', 1);
        } elseif (MailAlert::customerHasNotification($idCustomer, $idProduct, $idProductAttribute, (int) $context->shop->id)) {
            return '';
        }

        $this->context->smarty->assign(
            [
                'id_product'           => $idProduct,
                'id_product_attribute' => $idProductAttribute,
            ]
        );

        return $this->display(__FILE__, 'product.tpl');
    }

    /**
     * @param array $params
     */
    public function hookActionUpdateQuantity($params)
    {
        $idProduct = (int) $params['id_product'];
        $idProductAttribute = (int) $params['id_product_attribute'];

        $quantity = (int) $params['quantity'];
        $context = Context::getContext();
        $idShop = (int) $context->shop->id;
        $idLang = (int) $context->language->id;
        $product = new Product($idProduct, false, $idLang, $idShop, $context);
        $productHasAttributes = $product->hasAttributes();
        $configuration = Configuration::getMultiple(
            [
                'MA_LAST_QTIES',
                'PS_STOCK_MANAGEMENT',
                'PS_SHOP_EMAIL',
                'PS_SHOP_NAME',
            ],
            null,
            null,
            $idShop
        );
        $maLastQties = (int) $configuration['MA_LAST_QTIES'];

        $checkOos = ($productHasAttributes && $idProductAttribute) || (!$productHasAttributes && !$idProductAttribute);

        if ($checkOos &&
            $product->active == 1 &&
            (int) $quantity <= $maLastQties &&
            !(!$this->merchant_oos || empty($this->merchant_mails)) &&
            $configuration['PS_STOCK_MANAGEMENT']
        ) {
            $mailIso = Language::getIsoById($idLang);
            $productName = Product::getProductName($idProduct, $idProductAttribute, $idLang);
            $templateVars = [
                '{qty}'      => $quantity,
                '{last_qty}' => $maLastQties,
                '{product}'  => $productName,
            ];

            $dirMail = false;
            if (file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/productoutofstock.txt") &&
                file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/productoutofstock.html")) {
                $dirMail = _PS_THEME_DIR_."modules/$this->name/mails/";
            } elseif (file_exists(__DIR__."/mails/$mailIso/productoutofstock.txt") &&
                file_exists(__DIR__."/mails/$mailIso/productoutofstock.html")
            ) {
                $dirMail = __DIR__.'/mails/';
            } elseif (file_exists(_PS_MAIL_DIR_.$mailIso.'/productoutofstock.txt') &&
                file_exists(_PS_MAIL_DIR_.$mailIso.'/productoutofstock.html')
            ) {
                $dirMail = _PS_MAIL_DIR_;
            } elseif (Language::getIdByIso('en')) {
                $dirMail = __DIR__."/mails/";
                $idLang = (int) Language::getIdByIso('en');
            }

            // Do not send mail if multiples product are created / imported.
            if (!defined('PS_MASS_PRODUCT_CREATION') && $dirMail) {
                // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
                $merchantMails = explode(static::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
                foreach ($merchantMails as $merchantMail) {
                    Mail::Send(
                        $idLang,
                        'productoutofstock',
                        Mail::l('Product out of stock', $idLang),
                        $templateVars,
                        $merchantMail,
                        null,
                        (string) $configuration['PS_SHOP_EMAIL'],
                        (string) $configuration['PS_SHOP_NAME'],
                        null,
                        null,
                        $dirMail,
                        false,
                        $idShop
                    );
                }
            }
        }

        if ($this->customer_qty && $quantity > 0) {
            MailAlert::sendCustomerAlert((int) $product->id, (int) $params['id_product_attribute']);
        }
    }

    /**
     * @param array $params
     */
    public function hookActionProductAttributeUpdate($params)
    {
        $sql = '
			SELECT `id_product`, `quantity`
			FROM `'._DB_PREFIX_.'stock_available`
			WHERE `id_product_attribute` = '.(int) $params['id_product_attribute'];

        $result = Db::getInstance()->getRow($sql);

        if ($this->customer_qty && $result['quantity'] > 0) {
            MailAlert::sendCustomerAlert((int) $result['id_product'], (int) $params['id_product_attribute']);
        }
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayMyAccountBlock($params)
    {
        return $this->hookDisplayCustomerAccount($params);
    }

    /**
     * @return string
     */
    public function hookDisplayCustomerAccount()
    {
        return $this->customer_qty ? $this->display(__FILE__, 'my-account.tpl') : '';
    }

    /**
     * @param array $params
     */
    public function hookActionProductDelete($params)
    {
        $sql = '
			DELETE FROM `'._DB_PREFIX_.MailAlert::$definition['table'].'`
			WHERE `id_product` = '.(int) $params['product']->id;

        Db::getInstance()->execute($sql);
    }

    /**
     * @param array $params
     */
    public function hookActionAttributeDelete($params)
    {
        if ($params['deleteAllAttributes']) {
            $sql = '
				DELETE FROM `'._DB_PREFIX_.MailAlert::$definition['table'].'`
				WHERE `id_product` = '.(int) $params['id_product'];
        } else {
            $sql = '
				DELETE FROM `'._DB_PREFIX_.MailAlert::$definition['table'].'`
				WHERE `id_product_attribute` = '.(int) $params['id_product_attribute'].'
				AND `id_product` = '.(int) $params['id_product'];
        }

        Db::getInstance()->execute($sql);
    }

    /**
     * @param array $params
     */
    public function hookActionProductCoverage($params)
    {
        // if not advanced stock management, nothing to do
        if (!Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
            return;
        }

        // retrieves informations
        $idProduct = (int) $params['id_product'];
        $idProductAttribute = (int) $params['id_product_attribute'];
        $warehouse = $params['warehouse'];
        $product = new Product($idProduct);

        if (!Validate::isLoadedObject($product)) {
            return;
        }

        if (!$product->advanced_stock_management) {
            return;
        }

        // sets warehouse id to get the coverage
        if (!Validate::isLoadedObject($warehouse)) {
            $idWarehouse = 0;
        } else {
            $idWarehouse = (int) $warehouse->id;
        }

        // coverage of the product
        $warningCoverage = (int) Configuration::getGlobalValue('MA_PRODUCT_COVERAGE');

        $coverage = StockManagerFactory::getManager()->getProductCoverage($idProduct, $idProductAttribute, $warningCoverage, $idWarehouse);

        // if we need to send a notification
        if ($product->active == 1 &&
            ($coverage < $warningCoverage) && !empty($this->merchant_mails) &&
            Configuration::getGlobalValue('MA_MERCHANT_COVERAGE')
        ) {
            $context = Context::getContext();
            $idLang = (int) $context->language->id;
            $idShop = (int) $context->shop->id;
            $mailIso = Language::getIsoById($idLang);
            $productName = Product::getProductName($idProduct, $idProductAttribute, $idLang);
            $templateVars = [
                '{current_coverage}' => $coverage,
                '{warning_coverage}' => $warningCoverage,
                '{product}'          => pSQL($productName),
            ];

            $dirMail = false;
            if (file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/productcoverage.txt") &&
                file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/productcoverage.html")) {
                $dirMail = _PS_THEME_DIR_."modules/$this->name/mails/";
            } elseif (file_exists(__DIR__."/mails/$mailIso/productcoverage.txt") &&
                file_exists(__DIR__."/mails/$mailIso/productcoverage.html")
            ) {
                $dirMail = __DIR__.'/mails/';
            } elseif (file_exists(_PS_MAIL_DIR_.$mailIso.'/productcoverage.txt') &&
                file_exists(_PS_MAIL_DIR_.$mailIso.'/productcoverage.html')
            ) {
                $dirMail = _PS_MAIL_DIR_;
            } elseif (Language::getIdByIso('en')) {
                $idLang = (int) Language::getIdByIso('en');
                $dirMail = __DIR__.'/mails/';
            }

            if ($dirMail) {
                // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
                $merchantMails = explode(static::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
                foreach ($merchantMails as $merchantMail) {
                    Mail::Send(
                        $idLang,
                        'productcoverage',
                        Mail::l('Stock coverage', $idLang),
                        $templateVars,
                        $merchantMail,
                        null,
                        (string) Configuration::get('PS_SHOP_EMAIL'),
                        (string) Configuration::get('PS_SHOP_NAME'),
                        null,
                        null,
                        $dirMail,
                        null,
                        $idShop
                    );
                }
            }
        }
    }

    public function hookDisplayHeader()
    {
        $this->page_name = Dispatcher::getInstance()->getController();
        if (in_array($this->page_name, ['product', 'account'])) {
            $this->context->controller->addJS($this->_path.'js/mailalerts.js');
            $this->context->controller->addCSS($this->_path.'css/mailalerts.css', 'all');
        }
    }

    /**
     * Send a mail when a customer return an order.
     *
     * @param array $params Hook params.
     */
    public function hookActionOrderReturn($params)
    {
        if (!$this->return_slip || empty($this->return_slip)) {
            return;
        }

        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $configuration = Configuration::getMultiple(
            [
                'PS_SHOP_EMAIL',
                'PS_MAIL_METHOD',
                'PS_MAIL_SERVER',
                'PS_MAIL_USER',
                'PS_MAIL_PASSWD',
                'PS_SHOP_NAME',
                'PS_MAIL_COLOR',
            ],
            $idLang,
            null,
            $idShop
        );

        // Shop iso
        $iso = Language::getIsoById((int) Configuration::get('PS_LANG_DEFAULT'));

        $order = new Order((int) $params['orderReturn']->id_order);
        $customer = new Customer((int) $params['orderReturn']->id_customer);
        $delivery = new Address((int) $order->id_address_delivery);
        $invoice = new Address((int) $order->id_address_invoice);
        $orderDateText = Tools::displayDate($order->date_add);
        if ($delivery->id_state) {
            $deliveryState = new State((int) $delivery->id_state);
        }
        if ($invoice->id_state) {
            $invoiceState = new State((int) $invoice->id_state);
        }

        $orderReturnProducts = OrderReturn::getOrdersReturnProducts($params['orderReturn']->id, $order);

        $itemsTable = '';
        foreach ($orderReturnProducts as $key => $product) {
            $url = $context->link->getProductLink($product['product_id']);
            $itemsTable .=
                '<tr style="background-color:'.($key % 2 ? '#DDE2E6' : '#EBECEE').';">
					<td style="padding:0.6em 0.4em;">'.$product['product_reference'].'</td>
					<td style="padding:0.6em 0.4em;">
						<strong><a href="'.$url.'">'.$product['product_name'].'</a>
					</strong>
					</td>
					<td style="padding:0.6em 0.4em; text-align:center;">'.(int) $product['product_quantity'].'</td>
				</tr>';
        }

        $templateVars = [
            '{firstname}'            => $customer->firstname,
            '{lastname}'             => $customer->lastname,
            '{email}'                => $customer->email,
            '{delivery_block_txt}'   => MailAlert::getFormatedAddress($delivery, "\n"),
            '{invoice_block_txt}'    => MailAlert::getFormatedAddress($invoice, "\n"),
            '{delivery_block_html}'  => MailAlert::getFormatedAddress(
                $delivery, '<br />', [
                    'firstname' => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                    'lastname'  => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                ]
            ),
            '{invoice_block_html}'   => MailAlert::getFormatedAddress(
                $invoice, '<br />', [
                    'firstname' => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                    'lastname'  => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                ]
            ),
            '{delivery_company}'     => $delivery->company,
            '{delivery_firstname}'   => $delivery->firstname,
            '{delivery_lastname}'    => $delivery->lastname,
            '{delivery_address1}'    => $delivery->address1,
            '{delivery_address2}'    => $delivery->address2,
            '{delivery_city}'        => $delivery->city,
            '{delivery_postal_code}' => $delivery->postcode,
            '{delivery_country}'     => $delivery->country,
            '{delivery_state}'       => $delivery->id_state ? $deliveryState->name : '',
            '{delivery_phone}'       => $delivery->phone ? $delivery->phone : $delivery->phone_mobile,
            '{delivery_other}'       => $delivery->other,
            '{invoice_company}'      => $invoice->company,
            '{invoice_firstname}'    => $invoice->firstname,
            '{invoice_lastname}'     => $invoice->lastname,
            '{invoice_address2}'     => $invoice->address2,
            '{invoice_address1}'     => $invoice->address1,
            '{invoice_city}'         => $invoice->city,
            '{invoice_postal_code}'  => $invoice->postcode,
            '{invoice_country}'      => $invoice->country,
            '{invoice_state}'        => $invoice->id_state ? $invoiceState->name : '',
            '{invoice_phone}'        => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}'        => $invoice->other,
            '{order_name}'           => $order->reference,
            '{shop_name}'            => $configuration['PS_SHOP_NAME'],
            '{date}'                 => $orderDateText,
            '{items}'                => $itemsTable,
            '{message}'              => Tools::purifyHTML($params['orderReturn']->question),
        ];

        // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
        $merchantMails = explode(static::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
        foreach ($merchantMails as $merchantMail) {
            // Default language
            $mailIdLang = $idLang;
            $mailIso = $iso;

            // Use the merchant lang if he exists as an employee
            $results = Db::getInstance()->executeS(
                '
				SELECT `id_lang` FROM `'._DB_PREFIX_.'employee`
				WHERE `email` = \''.pSQL($merchantMail).'\'
			'
            );
            if ($results) {
                $userIso = Language::getIsoById((int) $results[0]['id_lang']);
                if ($userIso) {
                    $mailIdLang = (int) $results[0]['id_lang'];
                    $mailIso = $userIso;
                }
            }

            $dirMail = false;
            if (file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/return_slip.txt") &&
                file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/return_slip.html")) {
                $dirMail = _PS_THEME_DIR_."modules/$this->name/mails/";
            } elseif (file_exists(__DIR__."/mails/$mailIso/return_slip.txt") &&
                file_exists(__DIR__."/mails/$mailIso/return_slip.html")
            ) {
                $dirMail = __DIR__.'/mails/';
            } elseif (file_exists(_PS_MAIL_DIR_.$mailIso.'/return_slip.txt') &&
                file_exists(_PS_MAIL_DIR_.$mailIso.'/return_slip.html')
            ) {
                $dirMail = _PS_MAIL_DIR_;
            } elseif (Language::getIdByIso('en')) {
                $mailIdLang = (int) Language::getIdByIso('en');
                $dirMail = __DIR__.'/mails/';
            }

            if ($dirMail) {
                Mail::Send(
                    $mailIdLang,
                    'return_slip',
                    sprintf(Mail::l('New return from order #%d - %s', $mailIdLang), $order->id, $order->reference),
                    $templateVars,
                    $merchantMail,
                    null,
                    $configuration['PS_SHOP_EMAIL'],
                    $configuration['PS_SHOP_NAME'],
                    null,
                    null,
                    $dirMail,
                    null,
                    $idShop
                );
            }
        }
    }

    /**
     * Send a mail when an order is modified.
     *
     * @param array $params Hook params.
     */
    public function hookActionOrderEdited($params)
    {
        if (!$this->order_edited || empty($this->order_edited)) {
            return;
        }

        $order = $params['order'];

        $data = [
            '{lastname}'   => $order->getCustomer()->lastname,
            '{firstname}'  => $order->getCustomer()->firstname,
            '{id_order}'   => (int) $order->id,
            '{order_name}' => $order->getUniqReference(),
        ];

        $language = new Language((int) $order->id_lang);
        if (Validate::isLoadedObject($language)) {
            $mailIso = $language->iso_code;
        } else {
            $mailIso = Context::getContext()->language->iso_code;
        }

        $dirMail = false;
        if (file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/order_changed.txt") &&
            file_exists(_PS_THEME_DIR_."modules/$this->name/mails/$mailIso/order_changed.html")) {
            $dirMail = _PS_THEME_DIR_."modules/$this->name/mails/";
        } elseif (file_exists(__DIR__."/mails/$mailIso/order_changed.txt") &&
            file_exists(__DIR__."/mails/$mailIso/order_changed.html")
        ) {
            $dirMail = __DIR__.'/mails/';
        } elseif (file_exists(_PS_MAIL_DIR_.$mailIso.'/order_changed.txt') &&
            file_exists(_PS_MAIL_DIR_.$mailIso.'/order_changed.html')
        ) {
            $dirMail = _PS_MAIL_DIR_;
        } elseif (Language::getIdByIso('en')) {
            $mailIso = 'en';
            $dirMail = __DIR__.'/mails/';
        }

        Mail::Send(
            Language::getIdByIso($mailIso),
            'order_changed',
            Mail::l(
                'Your order has been changed',
                (int) $order->id_lang
            ),
            $data,
            $order->getCustomer()->email,
            $order->getCustomer()->firstname.' '.$order->getCustomer()->lastname,
            null,
            null,
            null,
            null,
            $dirMail,
            true,
            (int) $order->id_shop
        );
    }
}
