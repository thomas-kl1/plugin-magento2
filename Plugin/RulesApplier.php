<?php
/**
 * Copyright 2020 Lengow SAS
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
 * @subpackage  Plugin
 * @author      Team module <team-module@lengow.com>
 * @copyright   2020 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Lengow\Connector\Plugin;

use Closure;
use Magento\Backend\Model\Session as BackendSession;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection as RuleCollection;

/*
 * Class RulesApplier
 * this class is used to prevent magento from applying the discounts
 * to the orders imported by Lengow
 */
class RulesApplier
{
    /**
     * @var BackendSession $_backendSession Backend session instance
     */
    protected $backendSession;

    /**
     * @var RuleCollectionFactory Magento Rule Factory
     */
    protected $ruleFactory;

    /**
     * @param BackendSession $backendSession Backend session instance
     * @param RuleCollectionFactory $rulesFactory Magento Rules Factory
     */
    public function __construct(
        RuleCollectionFactory $rulesFactory,
        BackendSession $backendSession
    ) {
        $this->ruleFactory = $rulesFactory;
        $this->backendSession = $backendSession;
    }

    /**
     * @param \Magento\SalesRule\Model\RulesApplier $subject
     * @param Closure $proceed
     * @param AbstractItem $item
     * @param RuleCollection $rules
     * @param bool $skipValidation
     * @param mixed $couponCode
     *
     * @return mixed
     */
    public function aroundApplyRules(
        \Magento\SalesRule\Model\RulesApplier $subject,
        Closure $proceed,
        $item,
        $rules,
        $skipValidation,
        $couponCode
    ) {
        if ((bool)$this->backendSession->getIsFromlengow()) {
            $nRules = $this->ruleFactory->create()->addFieldToFilter('rule_id', ['eq' => 0]);
            return $proceed($item, $nRules, $skipValidation, $couponCode);
        }
        return $proceed($item, $rules, $skipValidation, $couponCode);
    }
}
