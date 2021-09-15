<?php
/**
 * Copyright 2017 Lengow SAS
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category    Lengow
 * @package     Lengow_Connector
 * @subpackage  Model
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Model\Import;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Lengow\Connector\Helper\Data as DataHelper;
use Lengow\Connector\Model\Import\Order as LengowOrder;
use Lengow\Connector\Model\Import\OrdererrorFactory as LengowOrderErrorFactory;
use Lengow\Connector\Model\ResourceModel\Ordererror as LengowOrderErrorResource;
use Lengow\Connector\Model\ResourceModel\Ordererror\CollectionFactory as LengowOrderErrorCollectionFactory;

/**
 * Model import order error
 */
class Ordererror extends AbstractModel
{
    /**
     * @var string Lengow order error table name
     */
    const TABLE_ORDER_ERROR = 'lengow_order_error';

    /* Order error fields */
    const FIELD_ID = 'id';
    const FIELD_ORDER_LENGOW_ID = 'order_lengow_id';
    const FIELD_MESSAGE = 'message';
    const FIELD_TYPE = 'type';
    const FIELD_IS_FINISHED = 'is_finished';
    const FIELD_MAIL = 'mail';
    const FIELD_CREATED_AT = 'created_at';
    const FIELD_UPDATED_AT = 'updated_at';

    /* Order error types */
    const TYPE_ERROR_IMPORT = 1;
    const TYPE_ERROR_SEND = 2;

    /**
     * @var DateTime Magento datetime instance
     */
    private $dateTime;

    /**
     * @var LengowOrderErrorCollectionFactory Lengow order error collection factory
     */
    private $lengowOrderErrorCollection;

    /**
     * @var LengowOrderErrorFactory Lengow order error factory
     */
    private $lengowOrderErrorFactory;

    /**
     * @var array field list for the table lengow_order_line
     * required => Required fields when creating registration
     * update   => Fields allowed when updating registration
     */
    private $fieldList = [
        self::FIELD_ORDER_LENGOW_ID => [
            DataHelper::FIELD_REQUIRED => true,
            DataHelper::FIELD_CAN_BE_UPDATED => false,
        ],
        self::FIELD_MESSAGE => [
            DataHelper::FIELD_REQUIRED => true,
            DataHelper::FIELD_CAN_BE_UPDATED => false,
        ],
        self::FIELD_TYPE => [
            DataHelper::FIELD_REQUIRED => true,
            DataHelper::FIELD_CAN_BE_UPDATED => false,
        ],
        self::FIELD_IS_FINISHED => [
            DataHelper::FIELD_REQUIRED => false,
            DataHelper::FIELD_CAN_BE_UPDATED => true,
        ],
        self::FIELD_MAIL => [
            DataHelper::FIELD_REQUIRED => false,
            DataHelper::FIELD_CAN_BE_UPDATED => true,
        ],
    ];

    /**
     * Constructor
     *
     * @param Context $context Magento context instance
     * @param Registry $registry Magento registry instance
     * @param DateTime $dateTime Magento datetime instance
     * @param LengowOrderErrorCollectionFactory $orderErrorCollection
     * @param LengowOrderErrorFactory $orderErrorFactory
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        LengowOrderErrorCollectionFactory $orderErrorCollection,
        LengowOrderErrorFactory $orderErrorFactory
    ) {
        $this->dateTime = $dateTime;
        $this->lengowOrderErrorCollection = $orderErrorCollection;
        $this->lengowOrderErrorFactory = $orderErrorFactory;
        parent::__construct($context, $registry);
    }

    /**
     * Initialize order error model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(LengowOrderErrorResource::class);
    }

    /**
     * Create Lengow order error
     *
     * @param array $params order error parameters
     *
     * @return Ordererror|false
     */
    public function createOrderError($params = [])
    {
        foreach ($this->fieldList as $key => $value) {
            if (!array_key_exists($key, $params) && $value[DataHelper::FIELD_REQUIRED]) {
                return false;
            }
        }
        foreach ($params as $key => $value) {
            $this->setData($key, $value);
        }
        $this->setData(self::FIELD_CREATED_AT, $this->dateTime->gmtDate(DataHelper::DATE_FULL));
        try {
            return $this->save();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update Lengow order error
     *
     * @param array $params order error parameters
     *
     * @return Ordererror|false
     */
    public function updateOrderError($params = [])
    {
        if (!$this->getId()) {
            return false;
        }
        $updatedFields = $this->getUpdatedFields();
        foreach ($params as $key => $value) {
            if (in_array($key, $updatedFields, true)) {
                $this->setData($key, $value);
            }
        }
        $this->setData(self::FIELD_UPDATED_AT, $this->dateTime->gmtDate(DataHelper::DATE_FULL));
        try {
            return $this->save();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get updated fields
     *
     * @return array
     */
    public function getUpdatedFields()
    {
        $updatedFields = [];
        foreach ($this->fieldList as $key => $value) {
            if ($value[DataHelper::FIELD_CAN_BE_UPDATED]) {
                $updatedFields[] = $key;
            }
        }
        return $updatedFields;
    }

    /**
     * Removes all order error for one order lengow
     *
     * @param integer $orderLengowId Lengow order id
     * @param string $type order error type (import or send)
     *
     * @return boolean
     */
    public function finishOrderErrors($orderLengowId, $type = self::TYPE_ERROR_IMPORT)
    {
        // get all order errors
        $results = $this->lengowOrderErrorCollection->create()->load()
            ->addFieldToFilter(self::FIELD_ORDER_LENGOW_ID, $orderLengowId)
            ->addFieldToFilter(self::FIELD_IS_FINISHED, 0)
            ->addFieldToFilter(self::FIELD_TYPE, $type)
            ->addFieldToSelect(self::FIELD_ID)
            ->getData();
        if (!empty($results)) {
            foreach ($results as $result) {
                $orderError = $this->lengowOrderErrorFactory->create()->load((int) $result[self::FIELD_ID]);
                $orderError->updateOrderError([self::FIELD_IS_FINISHED => 1]);
                unset($orderError);
            }
            return true;
        }
        return false;
    }

    /**
     * Get all order errors
     *
     * @param integer $orderLengowId Lengow order id
     * @param string|null $type order error type (import or send)
     * @param boolean|null $finished log finished
     *
     * @return array|false
     *
     */
    public function getOrderErrors($orderLengowId, $type = null, $finished = null)
    {
        $collection = $this->lengowOrderErrorCollection->create()->load()
            ->addFieldToFilter(self::FIELD_ORDER_LENGOW_ID, $orderLengowId);
        if ($type !== null) {
            $collection->addFieldToFilter(self::FIELD_TYPE, $type);
        }
        if ($finished !== null) {
            $errorFinished = $finished ? 1 : 0;
            $collection->addFieldToFilter(self::FIELD_IS_FINISHED, $errorFinished);
        }
        $results = $collection->getData();
        if (!empty($results)) {
            return $results;
        }
        return false;
    }

    /**
     * Get order errors never sent by mail
     *
     * @return array|false
     */
    public function getOrderErrorsNotSent()
    {
        $results = $this->lengowOrderErrorCollection->create()->load()
            ->join(
                LengowOrder::TABLE_ORDER,
                '`lengow_order`.id=main_table.order_lengow_id',
                [LengowOrder::FIELD_MARKETPLACE_SKU => LengowOrder::FIELD_MARKETPLACE_SKU]
            )
            ->addFieldToFilter(self::FIELD_MAIL, ['eq' => 0])
            ->addFieldToFilter(self::FIELD_IS_FINISHED, ['eq' => 0])
            ->addFieldToSelect(self::FIELD_MESSAGE)
            ->addFieldToSelect(self::FIELD_ID)
            ->getData();
        if (empty($results)) {
            return false;
        }
        return $results;
    }
}
