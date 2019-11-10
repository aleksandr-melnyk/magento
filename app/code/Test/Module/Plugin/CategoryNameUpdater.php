<?php


namespace Test\Module\Plugin;


use Magento\Catalog\Model\Category;

class CategoryNameUpdater
{
    /**
     * @param Category $subject
     * @return Category
     */
    public function beforeGetName(Category $subject): Category
    {
        return $subject->setName('Test Category-Hello World');
    }
}