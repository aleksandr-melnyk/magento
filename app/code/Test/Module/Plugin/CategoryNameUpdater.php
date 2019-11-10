<?php


namespace Test\Module\Plugin;


use Magento\Catalog\Model\Category;

class CategoryNameUpdater
{
    /**
     * @param Category $subject
     * @param $result
     * @return string
     */
    public function afterGetName(Category $subject, $result): string
    {
        $name = $result;
        $name = 'Test Category-Hello World';
        return $name;
    }
}