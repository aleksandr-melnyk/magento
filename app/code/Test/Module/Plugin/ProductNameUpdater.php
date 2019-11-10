<?php

namespace Test\Module\Plugin;

use \Magento\Catalog\Model\Product;


class ProductNameUpdater
{
    /**
     * @param Product $subject
     * @param $result
     * @return string
     */
    public function afterGetName(Product $subject, $result)
    {
        $name = $result;
        $name = 'Test Product-Hello World';
        return $name;
    }
}