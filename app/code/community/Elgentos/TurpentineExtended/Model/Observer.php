<?php

class Elgentos_TurpentineExtended_Model_Observer {
    
    public function __construct() {
        if(Mage::getStoreConfig('turpentineextended/general/disabled')) {
            return;
        }
    }
    
    /**
     * Ban a category page, and any subpages of a product on product save
     *
     * Events:
     *     catalog_product_save_commit_after
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banCategoryCache($eventObject) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() && Mage::getStoreConfig('turpentineextended/general/bancategorycache')) {
            $product = $eventObject->getProduct();
            foreach($product->getCategoryIds() as $categoryId) {
                $category = Mage::getModel('catalog/category')->load($categoryId);
                if(Mage::getStoreConfig('turpentineextended/general/shotgunmode')) {
                    $result = $this->_banShotgunMode($category->getUrlKey());
                } else {
                    $result = $this->_getVarnishAdmin()->flushUrl( $category->getUrlKey() );
                }
                Mage::dispatchEvent( 'turpentine_ban_category_cache', $result );
                $cronHelper = Mage::helper( 'turpentine/cron' );
                if( $this->_checkResult( $result ) &&
                        $cronHelper->getCrawlerEnabled() ) {
                    $cronHelper->addCategoryToCrawlerQueue( $category );
                }
            }
        }
    }
    
    /*
     * Some URL's are not banned when using normal banCategoryCache mode. Certain blocks for example.
     * This function runs the string through a regex and fires it at the Varnish Admin.
     * This function can potentially ban URL's that don't need to be banned (collateral damage). I.e. shotgun mode.
     */
    protected function _banShotgunMode($string) {
        $params = array('^/(?:'.$string.')?$');
        foreach( Mage::helper( 'turpentine/varnish' )->getSockets() as $socket ) {
            $response = call_user_func_array( array( $socket, 'ban_url' ), $params );
        }
        return $response;
    } 
    
    /**
     * Copied from Nexcessnet_Turpentine, can't use it from this extension since it's protected
     * 
     * Check a result from varnish admin action, log if result has errors
     *
     * @param  array $result stored as $socketName => $result
     * @return bool
     */
    protected function _checkResult( $result ) {
        $rvalue = true;
        foreach( $result as $socketName => $value ) {
            if( $value !== true ) {
                Mage::helper( 'turpentine/debug' )->logWarn(
                    'Error in Varnish action result for server [%s]: %s',
                    $socketName, $value );
                $rvalue = false;
            }
        }
        return $rvalue;
    }
    
    /**
     * Copied from Nexcessnet_Turpentine, can't use it from this extension since it's protected
     * 
     * Get the varnish admin socket
     *
     * @return Nexcessnet_Turpentine_Model_Varnish_Admin
     */
    protected function _getVarnishAdmin() {
        $this->_varnishAdmin = Mage::getModel( 'turpentine/varnish_admin' );
        return $this->_varnishAdmin;
    }
}
