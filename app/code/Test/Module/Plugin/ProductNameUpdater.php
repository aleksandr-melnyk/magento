<?php

namespace Test\Module\Plugin;

use \Magento\Catalog\Model\Product;


class ProductNameUpdater
{

    /**
     * @param Product $subject
     * @return Product
     */
    public function beforeGetName(Product $subject): Product
    {
        return $subject->setName('Test Product-Hello World');
    }
}