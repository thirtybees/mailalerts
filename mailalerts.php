<?php
/**
 * 2007-2016 PrestaShop
 * 2017 - thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @author	thirty bees <info@thirtybees.com>
 * @copyright 2007-2016 PrestaShop SA
 * @copyright 2017-2024 thirty bees
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 * U.S.A. Trademark of thirty bees
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

    const CUSTOMER_NOTIFICATION_DISABLED = 0;
    const CUSTOMER_NOTIFICATION_WHEN_CANT_ORDER = 1;
    const CUSTOMER_NOTIFICATION_WHEN_NOT_AVAILABLE = 2;

    /**
     * @var string[]
     */
    protected $merchant_mails;

    /**
     * @var int
     */
    protected $merchant_order;

    /**
     * @var int
     */
    protected $merchant_oos;

    /**
     * @var int
     */
    protected $customer_qty;

    /**
     * @var int
     */
    protected $merchant_coverage;

    /**
     * @var int
     */
    protected $product_coverage;

    /**
     * @var int
     */
    protected $order_edited;

    /**
     * @var int
     */
    protected $return_slip;

    /**
     * MailAlerts constructor.
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'mailalerts';
        $this->tab = 'administration';
        $this->version = '4.5.0';
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
     * @throws PrestaShopException
     */
    protected function init()
    {
        $this->merchant_mails = static::getMerchantEmails();
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
            if (! $this->uninstallDb()) {
                return false;
            }
        }

        return parent::uninstall();
    }

    /**
     * @param bool $deleteParams
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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

            if (! $this->installDb()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Module configuration page
     *
     * @return string Module HTML
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        if (Tools::isSubmit('delete' . $this->name)) {
            $subscriberId = (int)Tools::getValue('id_mailalert_customer_oos');
            $productId = (int)Tools::getValue('id_product');

            if ($subscriberId) {
                Db::getInstance()->delete('mailalert_customer_oos', 'id_mailalert_customer_oos = ' . $subscriberId);
                $this->context->controller->confirmations[] = $this->l('The notification has been successfully deleted.');

                Tools::redirectAdmin(Context::getContext()->link->getAdminLink('AdminModules', true, [
                    'configure' => 'mailalerts',
                    'module_name' => 'mailalerts',
                    'id_product' => $productId,
                ]) . '#subscribers');
            }
        }

        $html = $this->postProcess();
        $html .= $this->renderForm();

        if ($this->customer_qty) {
            $html .= "<a id='subscribers'></a>";
            $html .= $this->renderList();
        }

        return $html;
    }

    /**
     * Process module configuration
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    protected function postProcess()
    {
        if (Tools::isSubmit('submitMailAlert') || Tools::isSubmit('submitMAMerchant')) {
            $errors = [];

            if (Tools::isSubmit('submitMailAlert')) {
                if (!Configuration::updateValue('MA_CUSTOMER_QTY', (int)Tools::getValue('MA_CUSTOMER_QTY'))) {
                    $errors[] = $this->l('Cannot update settings');
                } elseif (!Configuration::updateGlobalValue('MA_ORDER_EDIT', (int)Tools::getValue('MA_ORDER_EDIT'))) {
                    $errors[] = $this->l('Cannot update settings');
                }
            }

            if (Tools::isSubmit('submitMAMerchant')) {
                $emails = (string)Tools::getValue('MA_MERCHANT_MAILS');

                if (!$emails) {
                    $errors[] = $this->l('Please type one (or more) e-mail address');
                } else {
                    $emails = str_replace(',', self::__MA_MAIL_DELIMITOR__, $emails);
                    $emails = explode(self::__MA_MAIL_DELIMITOR__, $emails);
                    $validEmails = [];
                    foreach ($emails as $email) {
                        $email = trim($email);
                        if ($email) {
                            if (Validate::isEmail($email)) {
                                $validEmails[] = $email;
                            } else {
                                $errors[] = $this->l('Invalid e-mail:') . ' ' . Tools::safeOutput($email);
                            }
                        }
                    }
                    $emails = implode(self::__MA_MAIL_DELIMITOR__, $validEmails);

                    if (!Configuration::updateValue('MA_MERCHANT_MAILS', (string)$emails)) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateValue('MA_MERCHANT_ORDER', (int)Tools::getValue('MA_MERCHANT_ORDER'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateValue('MA_MERCHANT_OOS', (int)Tools::getValue('MA_MERCHANT_OOS'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateValue('MA_LAST_QTIES', (int)Tools::getValue('MA_LAST_QTIES'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateGlobalValue('MA_MERCHANT_COVERAGE', (int)Tools::getValue('MA_MERCHANT_COVERAGE'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateGlobalValue('MA_PRODUCT_COVERAGE', (int)Tools::getValue('MA_PRODUCT_COVERAGE'))) {
                        $errors[] = $this->l('Cannot update settings');
                    } elseif (!Configuration::updateGlobalValue('MA_RETURN_SLIP', (int)Tools::getValue('MA_RETURN_SLIP'))) {
                        $errors[] = $this->l('Cannot update settings');
                    }
                }
            }

            $this->init();

            if (count($errors) > 0) {
                return $this->displayError(implode('<br />', $errors));
            } else {
                return $this->displayConfirmation($this->l('Settings updated successfully'));
            }
        }

        return '';
    }

    /**
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
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
                        'type'    => 'select',
                        'label'   => $this->l('Out of stock notification'),
                        'name'    => 'MA_CUSTOMER_QTY',
                        'hint'    => $this->l('Gives the customer the option to enter mail address and receive notification when an out-of-stock product is available again.'),
                        'options' => [
                            'query' => [
                                [
                                    'id' => static::CUSTOMER_NOTIFICATION_DISABLED,
                                    'name' => $this->l('Disabled')
                                ],
                                [
                                    'id' => static::CUSTOMER_NOTIFICATION_WHEN_CANT_ORDER,
                                    'name' => $this->l('When product can\'t be ordered')
                                ],
                                [
                                    'id' => static::CUSTOMER_NOTIFICATION_WHEN_NOT_AVAILABLE,
                                    'name' => $this->l('When product is not available')
                                ],
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        ],
                        'desc' => (
                            $this->l('Display email notification form when product is not available. You can choose:') . '<ul>' .
                            '<li>' . Translate::ppTags($this->l('[1]When product can\'t be ordered[/1]: display subscription form when product is not in stock, and customer can\'t order it'), ['<b>']) . '</li>' .
                            '<li>' . Translate::ppTags($this->l('[1]When product is not available[/1]: display subscription form when product is currently not in stock, even if customer can order it'), ['<b>']) . '</li>' .
                            '</ul>'
                        )
                    ],
                    [
                        'type'    => 'switch',
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

        /** @var AdminController $controller */
        $controller = $this->context->controller;
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
            'languages'    => $controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm1, $fieldsForm2]);
    }

    /**
     * @return false|string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function renderList()
    {
        $productId = (int)Tools::getValue('id_product');

        if ($productId) {
            $product = new Product($productId, false, $this->context->language->id);

            if (Validate::isLoadedObject($product)) {
                $productName = $product->name;
            } else {
                $productName = $this->l('Deleted product');
            }

            $listFields = [
                'combination_name' => [
                    'title' => $this->l('Combination'),
                    'type' => 'text',
                ],
                'customer_name' => [
                    'title' => $this->l('Customer'),
                    'type' => 'text',
                    'callback_object' => $this,
                    'callback' => 'renderCustomer'
                ],
                'customer_email' => [
                    'title' => $this->l('Email'),
                    'type' => 'text',
                ],
                'date_add' => [
                    'title' => $this->l('Date'),
                    'type' => 'text',
                ],
            ];

            if (! $product->hasAttributes()) {
                unset($listFields['combination_name']);
            }

            $helper = new HelperList();
            $helper->shopLinkType = '';
            $helper->simple_header = true;
            $helper->identifier = 'id_mailalert_customer_oos';
            $helper->actions = ['delete'];
            $helper->no_link = true;
            $helper->show_toolbar = false;
            $url = Context::getContext()->link->getAdminLink('AdminModules', true, [
                'configure' => 'mailalerts',
                'module_name' => 'mailalerts',
            ]);
            $helper->title = Translate::ppTags(sprintf($this->l('Notification for "%s". [1]Show all[/1]'), $productName, $productId),  ['<a href="'.$url.'#subscribers">']);
            $helper->table = $this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
            $content = $this->getProductListSubscribers($productId);
            $helper->listTotal = count($content);
            return $helper->generateList($content, $listFields);
        } else {
            $listFields = [
                'id_product' => [
                    'title' => $this->l('Product ID'),
                    'type' => 'text',
                ],
                'reference' => [
                    'title' => $this->l('Reference'),
                    'type' => 'text',
                    'callback_object' => $this,
                    'callback' => 'renderProduct'
                ],
                'product_name' => [
                    'title' => $this->l('Product Name'),
                    'type' => 'text',
                    'callback_object' => $this,
                    'callback' => 'renderProduct'
                ],
                'combination_name' => [
                    'title' => $this->l('Combination'),
                    'type' => 'text',
                ],
                'cnt' => [
                    'title' => $this->l('Number of subscribers'),
                    'type' => 'text',
                    'callback_object' => $this,
                    'callback' => 'renderCnt'
                ],
            ];

            $helper = new HelperList();
            $helper->shopLinkType = '';
            $helper->simple_header = true;
            $helper->identifier = 'id_product';
            $helper->actions = [];
            $helper->no_link = true;
            $helper->show_toolbar = false;
            $helper->title = $this->l('Products with notifications');
            $helper->table = $this->name;
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
            $content = $this->getProductsSubscribers();
            $helper->listTotal = count($content);
            return $helper->generateList($content, $listFields);
        }
    }

    /**
     * Get list content
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getProductsSubscribers()
    {
        $langId = (int)Context::getContext()->language->id;
        $conn = Db::getInstance();

        $sql = (new DbQuery())
            ->select('oos.id_product')
            ->select('NULLIF(p.reference, "") AS reference')
            ->select('NULLIF(pl.name, "") AS product_name')
            ->select('COUNT(DISTINCT oos.id_mailalert_customer_oos) as cnt')
            ->select('IF(oos.id_product_attribute > 0, GROUP_CONCAT(DISTINCT al.name ORDER BY agl.id_attribute_group SEPARATOR ", "), "") AS combination_name')
            ->from('mailalert_customer_oos', 'oos')
            ->leftJoin('product_lang', 'pl', 'pl.id_lang = '.$langId.' AND pl.id_product = oos.id_product AND pl.id_shop = oos.id_shop')
            ->leftJoin('product', 'p', 'p.id_product = oos.id_product')
            ->leftJoin('product_attribute_combination', 'pac', 'pac.id_product_attribute = oos.id_product_attribute')
            ->leftJoin('attribute', 'a', 'a.id_attribute = pac.id_attribute')
            ->leftJoin('attribute_lang', 'al', 'al.id_attribute = a.id_attribute AND al.id_lang = '.$langId)
            ->leftJoin('attribute_group_lang', 'agl', 'agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = '.$langId)
            ->where('1' . Shop::addSqlRestriction(false, 'oos'))
            ->groupBy('oos.id_product')
            ->orderBy('COUNT(DISTINCT oos.id_mailalert_customer_oos) DESC');

        return $conn->executeS($sql);
    }

    /**
     * Get list content
     *
     * @param int $productId
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getProductListSubscribers($productId)
    {
        $langId = (int)Context::getContext()->language->id;
        $conn = Db::getInstance();
        $sql = (new DbQuery())
            ->select('oos.id_mailalert_customer_oos')
            ->select('oos.id_customer')
            ->select('IF(oos.id_product_attribute > 0, oos.id_product_attribute, NULL)')
            ->select('oos.customer_email')
            ->select('oos.date_add')
            ->select('COALESCE((
                            SELECT GROUP_CONCAT(al.`name` ORDER BY agl.`id_attribute_group` SEPARATOR \', \')
                             FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.`id_attribute` = pac.`id_attribute`
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int)Context::getContext()->language->id . ')
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' . (int)Context::getContext()->language->id . ')
                             WHERE pac.id_product_attribute  = oos.id_product_attribute
                             GROUP BY pac.id_product_attribute
                    ), \'-\') AS combination_name')
            ->select('IF(cust.id_customer, CONCAT(cust.firstname, " ", cust.lastname), NULL) AS customer_name')
            ->from('mailalert_customer_oos', 'oos')
            ->leftJoin('product_lang', 'pl', 'pl.id_lang = ' . $langId . ' AND pl.id_product = oos.id_product AND pl.id_shop = oos.id_shop')
            ->leftJoin('customer', 'cust', 'oos.id_customer = cust.id_customer')
            ->where('oos.id_product = ' . $productId . Shop::addSqlRestriction(false, 'oos'))
            ->orderBy('oos.id_product')
            ->orderBy('oos.id_product_attribute')
            ->orderBy('oos.date_add');
        return $conn->executeS($sql);
    }

    /**
     * Configuration field values
     *
     * @return array
     * @throws PrestaShopException
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
            'MA_MERCHANT_MAILS'    => Tools::getValue('MA_MERCHANT_MAILS', implode(static::__MA_MAIL_DELIMITOR__, static::getMerchantEmails())),
            'MA_ORDER_EDIT'        => Tools::getValue('MA_ORDER_EDIT', Configuration::get('MA_ORDER_EDIT')),
            'MA_RETURN_SLIP'       => Tools::getValue('MA_RETURN_SLIP', Configuration::get('MA_RETURN_SLIP')),
        ];
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionValidateOrder($params)
    {
        if (!$this->merchant_order || !$this->merchant_mails) {
            return;
        }

        // Getting differents vars
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $currency = $params['currency'];
        /** @var Order $order */
        $order = $params['order'];
        $customer = $params['customer'];
        $configuration = Configuration::getMultiple(
            [
                'PS_SHOP_EMAIL',
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

        if (!$message) {
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

        $totalProducts = (Product::getTaxCalculationMethod($customer->id) == PS_TAX_EXC)
            ? $order->getTotalProductsWithoutTaxes()
            : $order->getTotalProductsWithTaxes();

        $orderState = $params['orderStatus'];

        // Filling-in vars for email
        $templateVars = [
            '{firstname}'            => $customer->firstname,
            '{lastname}'             => $customer->lastname,
            '{email}'                => $customer->email,
            '{delivery_block_txt}'   => MailAlert::getFormattedAddress($delivery, "\n"),
            '{invoice_block_txt}'    => MailAlert::getFormattedAddress($invoice, "\n"),
            '{delivery_block_html}'  => MailAlert::getFormattedAddress(
                $delivery, '<br />', [
                    'firstname' => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                    'lastname'  => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                ]
            ),
            '{invoice_block_html}'   => MailAlert::getFormattedAddress(
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
            '{delivery_state}'       => isset($deliveryState) ? $deliveryState->name : '',
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
            '{invoice_state}'        => isset($invoiceState) ? $invoiceState->name : '',
            '{invoice_phone}'        => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}'        => $invoice->other,
            '{order_name}'           => $order->reference,
            '{order_status}'         => $orderState->name,
            '{shop_name}'            => $configuration['PS_SHOP_NAME'],
            '{date}'                 => $orderDateText,
            '{carrier}'              => $this->getCarrierName($carrier),
            '{payment}'              => substr($order->payment, 0, 32),
            '{items}'                => $itemsTable,
            '{total_paid}'           => Tools::displayPrice($order->total_paid, $currency),
            '{total_products}'       => Tools::displayPrice($totalProducts, $currency),
            '{total_discounts}'      => Tools::displayPrice($order->total_discounts, $currency),
            '{total_shipping}'       => Tools::displayPrice($order->total_shipping_tax_excl, $currency),
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

        foreach ($this->merchant_mails as $merchantMail) {
            $mailIdLang = $this->getMerchantMailLanguageId($merchantMail);
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
                __DIR__ . '/mails/',
                null,
                $idShop
            );
        }
    }

    /**
     * Get all messages
     *
     * @param int $id
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookActionProductOutOfStock($params)
    {
        if (! Configuration::get('PS_STOCK_MANAGEMENT')) {
            return '';
        }

        if ($this->customer_qty === static::CUSTOMER_NOTIFICATION_DISABLED) {
            return '';
        }

        if ($this->customer_qty === static::CUSTOMER_NOTIFICATION_WHEN_CANT_ORDER &&
            Product::isAvailableWhenOutOfStock($params['product']->out_of_stock)
        ) {
            return '';
        }


        $context = Context::getContext();
        $idProduct = (int) $params['product']->id;
        $idProductAttribute = 0;
        $idCustomer = (int) $context->customer->id;

        if ($idCustomer === 0) {
            $this->context->smarty->assign('email', 1);
        } elseif (MailAlert::customerHasNotification($idCustomer, $idProduct, $idProductAttribute)) {
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
     *
     * @throws PrestaShopException
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
            $productName = Product::getProductName($idProduct, $idProductAttribute, $idLang);
            $templateVars = [
                '{qty}'       => $quantity,
                '{last_qty}'  => $maLastQties,
                '{product}'   => $productName,
                '{reference}' => $product->reference,
            ];


            // Do not send mail if multiples product are created / imported.
            if (!defined('PS_MASS_PRODUCT_CREATION')) {
                foreach ($this->merchant_mails as $merchantMail) {
                    $mailIdLang = $this->getMerchantMailLanguageId($merchantMail);
                    Mail::Send(
                        $mailIdLang,
                        'productoutofstock',
                        Mail::l('Product out of stock', $mailIdLang),
                        $templateVars,
                        $merchantMail,
                        null,
                        (string) $configuration['PS_SHOP_EMAIL'],
                        (string) $configuration['PS_SHOP_NAME'],
                        null,
                        null,
                        __DIR__ . '/mails/',
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookDisplayMyAccountBlock()
    {
        return $this->hookDisplayCustomerAccount();
    }

    /**
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookDisplayCustomerAccount()
    {
        return $this->customer_qty ? $this->display(__FILE__, 'my-account.tpl') : '';
    }

    /**
     * @param array $params
     * @throws PrestaShopException
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
     * @throws PrestaShopException
     */
    public function hookActionProductAttributeDelete($params)
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
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
            $coverage < $warningCoverage &&
            $this->merchant_mails &&
            Configuration::getGlobalValue('MA_MERCHANT_COVERAGE')
        ) {
            $context = Context::getContext();
            $idLang = (int) $context->language->id;
            $idShop = (int) $context->shop->id;
            $productName = Product::getProductName($idProduct, $idProductAttribute, $idLang);
            $templateVars = [
                '{current_coverage}' => $coverage,
                '{warning_coverage}' => $warningCoverage,
                '{product}'          => pSQL($productName),
            ];

            foreach ($this->merchant_mails as $merchantMail) {
                $mailIdLang = $this->getMerchantMailLanguageId($merchantMail);
                Mail::Send(
                    $mailIdLang,
                    'productcoverage',
                    Mail::l('Stock coverage', $mailIdLang),
                    $templateVars,
                    $merchantMail,
                    null,
                    (string) Configuration::get('PS_SHOP_EMAIL'),
                    (string) Configuration::get('PS_SHOP_NAME'),
                    null,
                    null,
                    __DIR__ . '/mails/',
                    null,
                    $idShop
                );
            }
        }
    }

    /**
     * @return void
     * @throws PrestaShopException
     */
    public function hookDisplayHeader()
    {
        $controller = Dispatcher::getInstance()->getController();
        if (in_array($controller, ['product', 'account'])) {
            $this->context->controller->addJS($this->_path.'js/mailalerts.js');
            $this->context->controller->addCSS($this->_path.'css/mailalerts.css', 'all');
        }
    }

    /**
     * Send a mail when a customer return an order.
     *
     * @param array $params Hook params.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionOrderReturn($params)
    {
        if (!$this->return_slip) {
            return;
        }

        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $configuration = Configuration::getMultiple(
            [
                'PS_SHOP_EMAIL',
                'PS_SHOP_NAME',
                'PS_MAIL_COLOR',
            ],
            $idLang,
            null,
            $idShop
        );

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
            '{delivery_block_txt}'   => MailAlert::getFormattedAddress($delivery, "\n"),
            '{invoice_block_txt}'    => MailAlert::getFormattedAddress($invoice, "\n"),
            '{delivery_block_html}'  => MailAlert::getFormattedAddress(
                $delivery, '<br />', [
                    'firstname' => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                    'lastname'  => '<span style="color:'.$configuration['PS_MAIL_COLOR'].'; font-weight:bold;">%s</span>',
                ]
            ),
            '{invoice_block_html}'   => MailAlert::getFormattedAddress(
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
            '{delivery_state}'       => isset($deliveryState) ? $deliveryState->name : '',
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
            '{invoice_state}'        => isset($invoiceState) ? $invoiceState->name : '',
            '{invoice_phone}'        => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}'        => $invoice->other,
            '{order_name}'           => $order->reference,
            '{shop_name}'            => $configuration['PS_SHOP_NAME'],
            '{date}'                 => $orderDateText,
            '{items}'                => $itemsTable,
            '{message}'              => Tools::purifyHTML($params['orderReturn']->question),
        ];

        foreach ($this->merchant_mails as $merchantMail) {
            $mailIdLang = $this->getMerchantMailLanguageId($merchantMail);
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
                __DIR__ . '/mails/',
                null,
                $idShop
            );
        }
    }

    /**
     * Send a mail when an order is modified.
     *
     * @param array $params Hook params.
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionOrderEdited($params)
    {
        if (!$this->order_edited) {
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
        if (! Validate::isLoadedObject($language)) {
            $language = Context::getContext()->language;
        }
        $languageId = (int)$language->id;

        Mail::Send(
            $languageId,
            'order_changed',
            Mail::l('Your order has been changed', $languageId),
            $data,
            $order->getCustomer()->email,
            $order->getCustomer()->firstname.' '.$order->getCustomer()->lastname,
            null,
            null,
            null,
            null,
            __DIR__ . '/mails/',
            true,
            (int) $order->id_shop
        );
    }

    /**
     * @param Carrier $carrier
     * @return string
     * @throws PrestaShopException
     * @noinspection PhpDeprecationInspection
     */
    protected function getCarrierName(Carrier $carrier)
    {
        if (method_exists($carrier, 'getName')) {
            return $carrier->getName();
        }

        $name = $carrier->name;
        if ($name == '0') {
            return Configuration::get('PS_SHOP_NAME');
        }
        return $name;

    }

    /**
     * Created databases tables
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function installDb()
    {
        return $this->executeSqlScript('install');
    }

    /**
     * Removes database tables
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function uninstallDb()
    {
        return $this->executeSqlScript('uninstall', false);
    }

    /**
     * Executes sql script
     * @param string $script
     * @param bool $check
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function executeSqlScript($script, $check = true)
    {
        $file = dirname(__FILE__) . '/sql/' . $script . '.sql';
        if (!file_exists($file)) {
            return false;
        }
        $sql = file_get_contents($file);
        if (!$sql) {
            return false;
        }
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE', 'CHARSET_TYPE', 'COLLATE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_, 'utf8mb4', 'utf8mb4_unicode_ci'], $sql);
        $sql = preg_split("/;\s*[\r\n]+/", $sql);
        foreach ($sql as $statement) {
            $stmt = trim($statement);
            if ($stmt) {
                try {
                    if (!Db::getInstance()->execute($stmt)) {
                        PrestaShopLogger::addLog("mailalerts: sql script $script: $stmt: error");
                        if ($check) {
                            return false;
                        }
                    }
                } catch (Exception $e) {
                    PrestaShopLogger::addLog("mailalerts: sql script $script: $stmt: exception: $e");
                    if ($check) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderCustomer($value, $row)
    {
        $customerId = (int)$row['id_customer'];
        $url = Context::getContext()->link->getAdminLink('AdminCustomers', true, [
            'id_customer' => $customerId,
            'viewcustomer' => 1
        ]);
        return '<a href="'.$url.'">'.Tools::safeOutput($value).'</a>';
    }

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderProduct($value, $row)
    {
        $productId = (int)$row['id_product'];
        $url = Context::getContext()->link->getAdminLink('AdminProducts', true, [
            'id_product' => $productId,
            'updateproduct' => 1
        ]);
        return '<a href="'.$url.'">'.Tools::safeOutput($value).'</a>';
    }

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderCnt($value, $row)
    {
        $productId = (int)$row['id_product'];
        $url = Context::getContext()->link->getAdminLink('AdminModules', true, [
            'configure' => 'mailalerts',
            'module_name' => 'mailalerts',
            'id_product' => $productId,
        ]);
        return '<a href="'.$url.'#subscribers">'.Tools::safeOutput($value).'</a>';
    }


    /**
     * @return string[]
     *
     * @throws PrestaShopException
     */
    protected static function getMerchantEmails()
    {
        $emailsStr = (string) Configuration::get('MA_MERCHANT_MAILS');
        $emailsStr = str_replace(',', self::__MA_MAIL_DELIMITOR__, $emailsStr);
        $emails = explode(static::__MA_MAIL_DELIMITOR__, $emailsStr);
        $emails = array_filter(array_map('trim', $emails));
        $emails = array_filter($emails, [Validate::class, 'isEmail']);
        $emails = array_unique($emails);
        return $emails;
    }

    /**
     * Detect email language that should be used for merchant notification emails.
     *
     * If employee with the same email address exists, employee preferred
     * language will be used. Otherwise, email will be sent in shop default
     * language
     *
     * @param string $merchantMail
     * @return int
     *
     * @throws PrestaShopException
     */
    public function getMerchantMailLanguageId(string $merchantMail): int
    {
        // Use the merchant lang if he exists as an employee
        $langId = (int)Db::getInstance()->getValue((new DbQuery())
            ->select('id_lang')
            ->from('employee')
            ->where('email = "'.pSQL($merchantMail) . '"')
        );
        if ($langId) {
            return $langId;
        }
        return (int)Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function renderDeleteButton($value, $row)
    {
        $productId = (int)$row['id_product'];
        $url = Context::getContext()->link->getAdminLink('AdminModules', true, [
            'configure' => 'mailalerts',
            'delete_mailalert' => 1,
            'id_product' => $productId,
        ]);

        return '<a href="'.$url.'" style="color: red; text-decoration: underline;"
                    onclick="return confirm(\''.$this->l('Are you sure you want to delete this notification?').'\')">
                    '.$this->l('Delete').'
                </a>';
    }
}
