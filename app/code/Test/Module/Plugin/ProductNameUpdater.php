<?php

namespace Test\Module\Plugin;

use \Magento\Catalog\Model\Product;


class ProductNameUpdater
{

    /**
     * @param Product $subject
     * @return string
     */
    public function afterGetName(Product $subject): string
    {
        return 'Test Product-Hello World';
    }
}