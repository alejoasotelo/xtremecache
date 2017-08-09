<?php

/**
 * Serve cached pages with no request processing
 * @author Salerno Simone
 * @version 1.0.6
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class XtremeCache extends Module
{
    /** @var int cache expire time in seconds **/
    const TTL = 604800;//3600*24*7;

    /** @var boolean wether cache should be cleaned on catalog updates **/
    const REACTIVE = true;

    /** @var boolean wether a custom header should be sent **/
    const CUSTOM_HEADER = true;

    /** @var array of int Don"t cache certain languages **/
    public static $EXCLUDED_LANGS = [];

    /** @var array of int Don"t cache certain countries **/
    public static $EXCLUDE_COUNTRIES = [];

    /** @var array of int Don"t cache certain currencies **/
    public static $EXCLUDE_CURRENCIES = [];

    /** @var array of int Don"t cache certain shops **/
    public static $EXCLUDE_SHOPS = [];

    /** @var CacheCore **/
    private $cache;


    public function __construct()
    {
        /*@ini_set('display_errors', 'on');
        @error_reporting(E_ALL | E_STRICT);
        @define('_PS_DEBUG_SQL_', true);*/

        $this->name = 'xtremecache';
        $this->tab = 'frontend_features';
        $this->version = '1.0.7';
        $this->author = 'Simone Salerno | Collaborator: Alejo Sotelo';

        parent::__construct();

        $this->displayName = $this->l('Xtreme cache');
        $this->description = $this->l('Cache non-dynamic pages in the front office.');
        $this->ps_versions_compliancy = array("min" => "1.6", "max" => "1.6.99.99");

        $this->cache = Cache::getInstance();
    }

    /**
     * Handle non-explicitly handled hooks
     * @param string $name hook name
     * @param array $arguments
     */
    public function __call($name, $arguments)
    {
        if (static::REACTIVE && (0 === strpos(strtolower($name), 'hookaction'))) {
            $this->cache->flush();
        } else {
            return parent::__call($name, $arguments);
        }
    }

    /**
     * Install and register hooks
     * @return bool
     */
    public function install()
    {
        return parent::install() &&
                $this->registerHook('actionRequestComplete') &&
                $this->registerHook('actionCategoryAdd') &&
                $this->registerHook('actionCategoryUpdate') &&
                $this->registerHook('actionCategoryDelete') &&
                $this->registerHook('actionProductAdd') &&
                $this->registerHook('actionProductUpdate') &&
                $this->registerHook('actionProductDelete') &&
                $this->registerHook('actionProductSave') &&
                $this->registerHook("displayHeader") &&
                $this->registerHook('actionResponse');
    }

    /**
     * Uninstall and clear cache
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall() &&
            $this->unregisterHook('actionRequestComplete') &&
            $this->unregisterHook("actionCategoryAdd") &&
            $this->unregisterHook("actionCategoryUpdate") &&
            $this->unregisterHook("actionCategoryDelete") &&
            $this->unregisterHook("actionProductAdd") &&
            $this->unregisterHook("actionProductUpdate") &&
            $this->unregisterHook("actionProductDelete") &&
            $this->unregisterHook("actionProductSave") &&
            $this->unregisterHook("displayHeader") &&
            $this->unregisterHook("actionResponse");
    }

    /**
     * If a cached page exists for the current request
     * return it and abort
     */
    public function hookDisplayHeader()
    {
        if ($this->isActive() && ($html = $this->load())) {
            ob_clean();

            if (static::CUSTOM_HEADER) {
                header("X-Xtremecached: True");
            }

            die($html);
        }
    }

    /**
     * Cache page content for front pages
     * @param string $params
     */
    public function hookActionRequestComplete($params)
    {
        if (!$this->isActive()) {
            return;
        }

        $controller = $params['controller'];

        if (is_subclass_of($controller, 'FrontController') &&
            !is_subclass_of($controller, 'OrderController') &&
            !is_subclass_of($controller, 'OrderOpcController')) {
            $this->store($params['output']);
        }
    }

    /**
     * Test if current page is to be cached
     *
     * @return boolean
     */
    private function isActive()
    {
        $cart = $this->context->cart;

        return     !_PS_DEBUG_PROFILING_                                            // skip when profiling
                && !_PS_MODE_DEV_                                                   // skip when debugging
                && is_a($this->context->controller, FrontControllerCore::class)     // skip on back-end
                && Configuration::get("PS_SHOP_ENABLE")                             // skip on catalogue mode
                && !Tools::getValue("ajax")                                         // skip on AJAX requests
                && filter_input(INPUT_SERVER, "REQUEST_METHOD") === "GET"           // skip on POST requests
                && $cart->id_customer < 1                      // skip if user is logged in
                && $cart->nbProducts() < 1                                          // skip if cart is not empty
                && $this->isNotExcluded($cart->id_lang, self::$EXCLUDED_LANGS)
                && $this->isNotExcluded($cart->id_shop, self::$EXCLUDE_SHOPS)
                && $this->isNotExcluded($cart->id_currency, self::$EXCLUDE_CURRENCIES)
                && $this->isNotExcluded($this->context->country->id, self::$EXCLUDE_COUNTRIES);
    }

    /**
     * Get cached response
     *
     * @return string
     */
    private function load()
    {
        return $this->cache->get($this->key());
    }

    /**
     * Store response
     *
     * @param string $html
     * @return mixed
     */
    private function store($html)
    {
        $key = $this->key();
        $reponse = sprintf("<!-- xtremecache on %s -->\n%s", date("Y-m-d H:i:s"), $html);

        return $this->cache->set($key, $reponse, static::TTL);
    }

    /**
     * Get unique key for current request
     *
     * @return string
     */
    private function key()
    {
        return implode("-", [
            filter_input(INPUT_SERVER, "REQUEST_URI"),
            (int) $this->context->cart->id_currency,
            (int) $this->context->cart->id_lang,
            (int) $this->context->cart->id_shop,
            (int) $this->context->country->id,
            (int) $this->context->getDevice()
        ]);
    }

    /**
     * Check if given id is excluded from cache
     *
     * @param int $id
     * @param array $pool
     * @return bool
     */
    private function isNotExcluded($id, array $pool)
    {
        return array_search($id, $pool) === false;
    }
}
