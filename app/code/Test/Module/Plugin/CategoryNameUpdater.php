<?php


namespace Test\Module\Plugin;


use Magento\Catalog\Model\Category;

class CategoryNameUpdater
{
    /**
     * @param Category $subject
     * @return string
     */
    public function afterGetName(Category $subject): string
    {
        return 'Test Category-Hello World';
    }
}