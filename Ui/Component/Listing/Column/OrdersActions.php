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
 * @subpackage  UI
 * @author      Team module <team-module@lengow.com>
 * @copyright   2017 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Lengow\Connector\Helper\Data as DataHelper;
use Lengow\Connector\Model\Import\Action as LengowAction;
use Lengow\Connector\Model\Import\Order as LengowOrder;
use Lengow\Connector\Model\Import\Ordererror as LengowOrderError;
use Lengow\Connector\Model\Import\OrdererrorFactory as LengowOrderErrorFactory;

class OrdersActions extends Column
{
    /**
     * @var OrderRepositoryInterface Magento order repository instance
     */
    protected $_orderRepository;

    /**
     * @var UrlInterface Magento url interface
     */
    protected $urlBuilder;

    /**
     * @var DataHelper Lengow data helper instance
     */
    protected $_dataHelper;

    /**
     * @var LengowAction Lengow action instance
     */
    protected $_action;

    /**
     * @var LengowOrderErrorFactory Lengow order error factory instance
     */
    protected $_orderErrorFactory;

    /**
     * Constructor
     *
     * @param OrderRepositoryInterface $orderRepository Magento order repository instance
     * @param ContextInterface $context Magento ui context instance
     * @param UiComponentFactory $uiComponentFactory Magento ui factory instance
     * @param UrlInterface $urlBuilder
     * @param DataHelper $dataHelper Lengow data helper instance
     * @param LengowOrderErrorFactory $orderErrorFactory Lengow order factory instance
     * @param LengowAction $action Lengow action instance
     * @param array $components component data
     * @param array $data additional params
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        DataHelper $dataHelper,
        LengowOrderErrorFactory $orderErrorFactory,
        LengowAction $action,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->_orderRepository = $orderRepository;
        $this->_dataHelper = $dataHelper;
        $this->_orderErrorFactory = $orderErrorFactory;
        $this->_action = $action;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource row data source
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $dataSource = parent::prepareDataSource($dataSource);

        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if ((bool) $item[LengowOrder::FIELD_IS_IN_ERROR]
                    && (int) $item[LengowOrder::FIELD_ORDER_PROCESS_STATE] !== 2
                ) {
                    $orderLengowId = (int) $item[LengowOrder::FIELD_ID];
                    $errorType = (int) $item[LengowOrder::FIELD_ORDER_PROCESS_STATE] === 0
                        ? LengowOrderError::TYPE_ERROR_IMPORT
                        : LengowOrderError::TYPE_ERROR_SEND;
                    $url = $this->urlBuilder->getUrl('lengow/order/index') . '?isAjax=true';
                    $errorOrders = $this->_orderErrorFactory->create()
                        ->getOrderErrors($orderLengowId, $errorType, false);
                    $errorMessages = [];
                    if ($errorOrders) {
                        foreach ($errorOrders as $errorOrder) {
                            if ($errorOrder[LengowOrderError::FIELD_MESSAGE] !== '') {
                                $errorMessages[] = $this->_dataHelper->cleanData(
                                    $this->_dataHelper->decodeLogMessage($errorOrder[LengowOrderError::FIELD_MESSAGE])
                                );
                            } else {
                                $errorMessages[] = $this->_dataHelper->decodeLogMessage(
                                    "Unidentified error, please contact Lengow's support team for more information"
                                );
                            }
                        }
                    }
                    if ($errorType === LengowOrderError::TYPE_ERROR_IMPORT) {
                        $tooltip = $this->_dataHelper->decodeLogMessage("Order hasn't been imported into Magento")
                            . '<br/>' . join('<br/>', $errorMessages);
                        $item[LengowOrder::FIELD_IS_IN_ERROR] = '<a
                            class="lengow_action lengow_tooltip lgw-btn lgw-btn-white lgw_order_action_grid-js"
                            data-href="' . $url . '"
                            data-lgwAction="re_import"
                            data-lgwOrderId="' . $orderLengowId . '">'
                            . $this->_dataHelper->decodeLogMessage('not imported')
                            . '<span class="lengow_order_action">' . $tooltip . '</span>&nbsp<i class="fa fa-refresh"></i></a>';
                    } else {
                        $tooltip = $this->_dataHelper->decodeLogMessage("Action sent to the marketplace didn't work")
                            . '<br/>' . join('<br/>', $errorMessages);
                        $item[LengowOrder::FIELD_IS_IN_ERROR] = '<a
                            class="lengow_action lengow_tooltip lgw-btn lgw-btn-white lgw_order_action_grid-js"
                            data-href="' . $url . '"
                            data-lgwAction="re_send"
                            data-lgwOrderId="' . $orderLengowId . '">'
                            . $this->_dataHelper->decodeLogMessage('not sent')
                            . '<span class="lengow_order_action">' . $tooltip . '</span>&nbsp<i class="fa fa-refresh"></i></a>';
                    }
                } else {
                    //check if order actions in progress
                    if ($item[LengowOrder::FIELD_ORDER_ID] !== null
                        && (int) $item[LengowOrder::FIELD_ORDER_PROCESS_STATE] === 1
                    ) {
                        $lastActionType = $this->_action->getLastOrderActionType($item[LengowOrder::FIELD_ORDER_ID]);
                        if ($lastActionType) {
                            $item[LengowOrder::FIELD_IS_IN_ERROR] = '<a
                                class="lengow_action lengow_tooltip lgw-btn lgw-btn-white">'
                                . $this->_dataHelper->decodeLogMessage(
                                    'action %1 sent',
                                    true,
                                    [$lastActionType]
                                )
                                . '<span class="lengow_order_action">'
                                . $this->_dataHelper->decodeLogMessage('Action sent, waiting for response')
                                . '</span></a>';
                        } else {
                            $item[LengowOrder::FIELD_IS_IN_ERROR] = '';
                        }
                    } else {
                        $item[LengowOrder::FIELD_IS_IN_ERROR] = '';
                    }
                }
            }
        }

        return $dataSource;
    }
}
