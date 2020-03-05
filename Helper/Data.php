<?php

namespace Younify\PreviousNextNavigation\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Url;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;

/**
 * Class Data
 *
 * @package SR\PreviousNextNavigation\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const NEXT = true;
    const PREV = false;

    /**
     * Registry model
     *
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * Product repository model
     *
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var ResourceConnection
     */
    protected $_resource;

    /**
     * @var Url
     */
    protected $_catalogUrl;

    /**
     * Class constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param ProductRepositoryInterface $productRepository
     * @param Url $catalogUrl
     * @param ResourceConnection $resource
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        ProductRepositoryInterface $productRepository,
        Url $catalogUrl,
        ResourceConnection $resource
    ) {
        $this->_coreRegistry = $coreRegistry;
        $this->_productRepository = $productRepository;
        $this->_resource = $resource;
        $this->_catalogUrl = $catalogUrl;

        parent::__construct($context);
    }

    /**
     * Return previous model in category.
     *
     * @return bool|ProductInterface
     * @throws NoSuchEntityException
     */
    public function getNextProduct()
    {
        return $this->getSiblingProduct(self::NEXT);
    }

    /**
     * Return next model in category.
     *
     * @return bool|ProductInterface
     * @throws NoSuchEntityException
     */
    public function getPreviousProduct()
    {
        return $this->getSiblingProduct(self::PREV);
    }

    /**
     * Return next or previous product model in category.
     *
     * @param bool $isNext
     *
     * @return bool|ProductInterface
     * @throws NoSuchEntityException
     */
    protected function getSiblingProduct($isNext)
    {
        $prodId = $this->_coreRegistry->registry('current_product')->getId();

        $category =  $this->_coreRegistry->registry('current_category');

        if ($category) {
            $catArray = $this->getProductsPosition($category);
            //$catArray = $category->getProductsPosition();

            $keys = array_flip(array_keys($catArray));
            $values = array_keys($catArray);

            if ($isNext) {
                $siblingId = $keys[$prodId] + 1;
            } else {
                $siblingId = $keys[$prodId] - 1;
            }

            if (!isset($values[$siblingId])) {
                return false;
            }
            $productId = $values[$siblingId];

            $product = $this->_productRepository->getById($productId);

            $product->setCategoryId($category->getId());
            $urlData = $this->_catalogUrl->getRewriteByProductStore([$product->getId() => $category->getStoreId()]);
            if (!isset($urlData[$product->getId()])) {
                $product->setUrlDataObject(new DataObject($urlData[$product->getId()]));
            }

            if ($product->getId()) {
                return $product;
            }
            return false;
        }

        return false;
    }

    public function getProductsPosition($category)
    {
        $connection = $this->_resource->getConnection();
        $tableName = $this->_resource->getTableName('catalog_category_product_index');

        $select = $connection->select()->from(
            $tableName,
            ['product_id', 'position']
        )->where(
            'category_id = :category_id'
        )->where(
            'store_id = :store_id'
        )->order('position', 'ASC');

        $bind = [
            'category_id' => (int)$category->getId(),
            'store_id' => $category->getStoreId(),
        ];

        return $connection->fetchPairs($select, $bind);
    }
}
