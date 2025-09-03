<?php
/**
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2024 thirty bees
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require_once __DIR__.'/../../classes/autoload.php';

use MailAlertModule\MailAlert;

if (!defined('_TB_VERSION_')) {
    exit;
}

class AdminMailalertsOosController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_btn['settings'] = [
            'href' => $this->context->link->getAdminLink('AdminModules', true, ['configure' => 'mailalerts']),
            'desc' => $this->l('Settings'),
            'icon' => 'process-icon-cogs',
        ];
    }

    /**
     * Handle delete action for a subscription
     */
    public function postProcess()
    {
        if (Tools::isSubmit('delete' . MailAlert::$definition['table'])) {
            if (!$this->checkToken()) {
                $this->errors[] = $this->l('Invalid token.');
                return parent::postProcess();
            }
            if (!$this->access('delete')) {
                $this->errors[] = $this->l('You do not have permission to delete this item.');
                return parent::postProcess();
            }

            $id = (int) Tools::getValue(MailAlert::$definition['primary']);
            $idProduct = (int) Tools::getValue('id_product');

            if ($id) {
                Db::getInstance()->delete(MailAlert::$definition['table'], MailAlert::$definition['primary'].'='.(int)$id);
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminMailalertsOos', true, ['id_product' => $idProduct]));
            }
        }

        parent::postProcess();
    }

    /**
     * Render list of products or subscribers
     *
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderList()
    {
        MailAlert::pruneExpired();
        $idProduct = (int) Tools::getValue('id_product');

        if ($idProduct) {
            $product = new Product($idProduct, false, $this->context->language->id);
            $productName = Validate::isLoadedObject($product) ? $product->name : $this->l('Deleted product');

            $fieldsList = [
                'combination_name' => [
                    'title' => $this->l('Combination'),
                    'type' => 'text',
                ],
                'customer_name' => [
                    'title' => $this->l('Customer'),
                    'type' => 'text',
                    'callback_object' => $this,
                    'callback' => 'renderCustomer',
                ],
                'customer_email' => [
                    'title' => $this->l('Email'),
                ],
                'date_add' => [
                    'title' => $this->l('Date'),
                    'type' => 'datetime',
                ],
                'ip_address' => [
                    'title' => $this->l('IP address'),
                ],
                'ip_hash' => [
                    'title' => $this->l('IP hash'),
                ],
                'user_agent' => [
                    'title' => $this->l('User agent'),
                ],
            ];

            if (!$product->hasAttributes()) {
                unset($fieldsList['combination_name']);
            }

            $helper = new HelperList();
            $helper->shopLinkType = '';
            $helper->simple_header = true;
            $helper->identifier = MailAlert::$definition['primary'];
            $helper->actions = ['delete'];
            $helper->no_link = true;
            $helper->show_toolbar = false;
            $helper->table = MailAlert::$definition['table'];
            $helper->token = Tools::getAdminTokenLite('AdminMailalertsOos');
            $helper->currentIndex = $this->context->link->getAdminLink('AdminMailalertsOos', false, [
                'id_product' => $idProduct,
            ]);

            $title = sprintf($this->l('Notification for "%s". [1]Show all[/1]'), $productName, $idProduct);
            $helper->title = Translate::ppTags($title, ['<a href="' . htmlspecialchars($this->context->link->getAdminLink('AdminMailalertsOos')) . '">']);

            $list = $this->getProductListSubscribers($idProduct);
            $helper->listTotal = count($list);

            return $this->renderHint() . $helper->generateList($list, $fieldsList);
        }

        $fieldsList = [
            'id_product' => [
                'title' => $this->l('Product ID'),
            ],
            'reference' => [
                'title' => $this->l('Reference'),
                'callback_object' => $this,
                'callback' => 'renderProduct',
            ],
            'product_name' => [
                'title' => $this->l('Product Name'),
                'callback_object' => $this,
                'callback' => 'renderProduct',
            ],
            'combination_name' => [
                'title' => $this->l('Combination'),
                'type' => 'text',
            ],
            'cnt' => [
                'title' => $this->l('Number of subscriptions'),
                'callback_object' => $this,
                'callback' => 'renderCnt',
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
        $helper->table = MailAlert::$definition['table'];
        $helper->token = Tools::getAdminTokenLite('AdminMailalertsOos');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminMailalertsOos');

        $list = $this->getProductsSubscribers();
        $helper->listTotal = count($list);

        return $helper->generateList($list, $fieldsList);
    }

    /**
     * Get grouped list of subscribed products
     */
    protected function getProductsSubscribers()
    {
        $idLang = (int) $this->context->language->id;
        $hasShop = MailAlert::hasColumn('id_shop');

        $sql = (new DbQuery())
            ->select('oos.id_product')
            ->select('NULLIF(p.reference, "") AS reference')
            ->select('NULLIF(pl.name, "") AS product_name')
            ->select('COUNT(DISTINCT oos.' . MailAlert::$definition['primary'] . ') AS cnt')
            ->select('IF(oos.id_product_attribute > 0, GROUP_CONCAT(DISTINCT al.name ORDER BY agl.id_attribute_group SEPARATOR ", "), "") AS combination_name')
            ->from(MailAlert::$definition['table'], 'oos')
            ->leftJoin('product_lang', 'pl', 'pl.id_lang = ' . $idLang . ' AND pl.id_product = oos.id_product' . ($hasShop ? ' AND pl.id_shop = oos.id_shop' : ' AND pl.id_shop = ' . (int) $this->context->shop->id))
            ->leftJoin('product', 'p', 'p.id_product = oos.id_product')
            ->leftJoin('product_attribute_combination', 'pac', 'pac.id_product_attribute = oos.id_product_attribute')
            ->leftJoin('attribute', 'a', 'a.id_attribute = pac.id_attribute')
            ->leftJoin('attribute_lang', 'al', 'al.id_attribute = a.id_attribute AND al.id_lang = ' . $idLang)
            ->leftJoin('attribute_group_lang', 'agl', 'agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = ' . $idLang)
            ->where($hasShop ? '1 ' . Shop::addSqlRestriction(false, 'oos') : '1')
            ->groupBy('oos.id_product')
            ->orderBy('COUNT(DISTINCT oos.' . MailAlert::$definition['primary'] . ') DESC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get list of subscribers for given product/combination
     */
    protected function getProductListSubscribers($idProduct)
    {
        $idLang = (int) $this->context->language->id;
        $hasShop = MailAlert::hasColumn('id_shop');

        $sql = (new DbQuery())
            ->select('oos.' . MailAlert::$definition['primary'])
            ->select('oos.id_customer')
            ->select('IF(oos.id_product_attribute > 0, oos.id_product_attribute, NULL)')
            ->select('oos.customer_email')
            ->select('oos.date_add')
            ->select('COALESCE(INET6_NTOA(oos.ip_mask), "-") AS ip_address')
            ->select('LOWER(HEX(oos.ip_hash)) AS ip_hash')
            ->select('oos.user_agent')
            ->select('COALESCE((
                            SELECT GROUP_CONCAT(al.name ORDER BY agl.id_attribute_group SEPARATOR ", ")
                             FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.id_attribute = pac.id_attribute
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.id_attribute_group = a.id_attribute_group
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.id_attribute = al.id_attribute AND al.id_lang = ' . $idLang . ')
                             LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON (ag.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . $idLang . ')
                             WHERE pac.id_product_attribute  = oos.id_product_attribute
                             GROUP BY pac.id_product_attribute
                    ), "-") AS combination_name')
            ->select('IF(c.id_customer, CONCAT(c.firstname, " ", c.lastname), NULL) AS customer_name')
            ->from(MailAlert::$definition['table'], 'oos')
            ->leftJoin('product_lang', 'pl', 'pl.id_lang = ' . $idLang . ' AND pl.id_product = oos.id_product' . ($hasShop ? ' AND pl.id_shop = oos.id_shop' : ' AND pl.id_shop = ' . (int) $this->context->shop->id))
            ->leftJoin('customer', 'c', 'oos.id_customer = c.id_customer')
            ->where('oos.id_product = ' . (int) $idProduct . ($hasShop ? Shop::addSqlRestriction(false, 'oos') : ''))
            ->orderBy('oos.id_product')
            ->orderBy('oos.id_product_attribute')
            ->orderBy('oos.date_add');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Render customer cell with link
     */
    public function renderCustomer($value, $row)
    {
        $idCustomer = (int) $row['id_customer'];
        if (!$idCustomer) {
            return '-';
        }
        $url = $this->context->link->getAdminLink('AdminCustomers', true, [
            'id_customer' => $idCustomer,
            'viewcustomer' => 1,
        ]);
        return '<a href="' . htmlspecialchars($url) . '">' . Tools::safeOutput($value) . '</a>';
    }

    /**
     * Render product reference/name cell with link
     */
    public function renderProduct($value, $row)
    {
        $idProduct = (int) $row['id_product'];
        $url = $this->context->link->getAdminLink('AdminProducts', true, [
            'id_product' => $idProduct,
            'updateproduct' => 1,
        ]);
        return '<a href="' . htmlspecialchars($url) . '">' . Tools::safeOutput($value) . '</a>';
    }

    /**
     * Render number of subscriptions with link to subscriber list
     */
    public function renderCnt($value, $row)
    {
        $idProduct = (int) $row['id_product'];
        $url = $this->context->link->getAdminLink('AdminMailalertsOos', true, [
            'id_product' => $idProduct,
        ]);
        return '<a href="' . htmlspecialchars($url) . '">' . (int) $value . '</a>';
    }

    protected function renderHint()
    {
        return '<div class="alert alert-warning">' . $this->l('IP and user-agent information is collected for security audit purposes. The IP is saved in pseudonymised form (masked prefix + keyed hash); use the hash to detect repeat requests from the same IP without revealing the full address.') . '</div>';
    }
}

