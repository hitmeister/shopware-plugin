<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;
use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Transfers\AddressTransfer;
use Hitmeister\Component\Api\Transfers\Constants;
use Hitmeister\Component\Api\Transfers\OrderUnitTransfer;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail as ArticleDetail;
use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Customer\Address as CustomerBilling;
use Shopware\Models\Customer\Address as CustomerShipping;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Billing as OrderBilling;
use Shopware\Models\Order\Shipping as OrderShipping;
use Shopware\Models\Order\Detail as OrderDetail;
use Shopware\Models\Order\DetailStatus;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Payment\PaymentInstance;
use Shopware\Models\Shop\Shop;

class Ordering
{
    /**
     * @var Connection
     */
    private $connection;

    /** @var Client */
    private $apiClient;

    /** @var int|string */
    private $deliveryMethodId;

    /** @var int|string */
    private $paymentMethodId;

    /** @var int|string */
    private $shop;

    /**
     * Order constructor.
     * @param Connection $connection
     * @param Client $apiClient
     * @param int|string $deliveryMethod
     * @param int|string $paymentMethod
     * @param Shop $shop
     */
    public function __construct(Connection $connection, Client $apiClient, $deliveryMethod, $paymentMethod, Shop $shop)
    {
        $this->connection = $connection;
        $this->apiClient = $apiClient;
        $this->deliveryMethodId = $deliveryMethod;
        $this->paymentMethodId = $paymentMethod;
        $this->shop = Shopware()->Models()->find('Shopware\Models\Shop\Shop', $shop->getId());
    }

    /**
     * @param string $orderId
     * @return bool
     * @throws \Exception
     */
    public function process($orderId)
    {
        if ($this->isProcessed($orderId)) {
            return true;
        }

        $hmOrder = $this->apiClient->orders()->get($orderId, array('billing_address', 'buyer', 'seller_units', 'shipping_address'));
        if (!$hmOrder) {
            throw new \Exception('Order not found');
        }

        $orderId = $this->getOrderId();

        /** @var Order $orderModel */
        $orderModel = Shopware()->Models()->find('Shopware\Models\Order\Order', $orderId);
        $customerModel = $this->getCustomer($hmOrder->buyer->email, $hmOrder->billing_address, $hmOrder->shipping_address);

        $orderModel->setCustomer($customerModel);

        $dispatchModel = $this->getDeliveryMethod();
        $orderModel->setDispatch($dispatchModel);

        $paymentModel = $this->getPaymentMethod();
        $orderModel->setPayment($paymentModel);

        // 0 = order status open
        $orderStatusModel = Shopware()->Models()->getReference('Shopware\Models\Order\Status', 0);
        $orderModel->setOrderStatus($orderStatusModel);

        // 17 = payment status open
        $paymentStatusModel = Shopware()->Models()->getReference('Shopware\Models\Order\Status', 17);
        $orderModel->setPaymentStatus($paymentStatusModel);

        $languageSubShopModel = $this->getShop();
        $orderModel->setLanguageSubShop($languageSubShopModel);
        $orderModel->setShop($languageSubShopModel);

        $orderModel->setInvoiceShippingNet(0);
        $orderModel->setInvoiceShipping(0);

        $orderModel->setOrderTime(new \DateTime('now'));
        $orderModel->setDeviceType('Hitmeister.de');

        $orderModel->setTransactionId('');
        $orderModel->setComment('');
        $orderModel->setCustomerComment('');
        $orderModel->setInternalComment('');
        $orderModel->setNet(0);
        $orderModel->setTemporaryId('');
        $orderModel->setReferer('');
        $orderModel->setTrackingCode('');
        $orderModel->setRemoteAddress('');

        $currencyModel = $languageSubShopModel->getCurrency();
        $orderModel->setCurrencyFactor($currencyModel->getFactor());
        $orderModel->setCurrency($currencyModel->getCurrency());

        $total = $totalNet = 0;

        /** @var OrderDetail[] $details */
        $details = array();
        foreach ($hmOrder->seller_units as $hmUnit) {
            list($detail, $price, $priceNet) = $this->createOrderDetail($hmUnit, $orderModel);;
            $details[] = $detail;
            $total += $price;
            $totalNet += $priceNet;
        }

        $orderModel->setInvoiceAmount(round($total / 100, 2));
        $orderModel->setInvoiceAmountNet(round($totalNet / 100, 2));

        $orderModel->setDetails($details);

        $orderAttributeModel = new \Shopware\Models\Attribute\Order();
        $orderAttributeModel->setAttribute1('');
        $orderAttributeModel->setAttribute2('');
        $orderAttributeModel->setAttribute3('');
        $orderAttributeModel->setAttribute4('');
        $orderAttributeModel->setAttribute5('');
        $orderAttributeModel->setAttribute6('');
        $orderAttributeModel->setHmOrderId($hmOrder->id_order?:'');

        $orderModel->setAttribute($orderAttributeModel);

        $billingModel = $this->createBillingAddress($customerModel->getDefaultBillingAddress());
        $orderModel->setBilling($billingModel);

        $shippingModel = $this->createShippingAddress($customerModel->getDefaultShippingAddress());
        $orderModel->setShipping($shippingModel);

        $paymentInstance = $this->preparePaymentInstance($orderModel);
        $orderModel->setPaymentInstances($paymentInstance);

        Shopware()->Models()->persist($orderModel);
        Shopware()->Models()->flush();

        if (is_null($billingModel->getState())) {
            Shopware()->Db()->update('s_order_billingaddress', ['stateID' => 0], ['id' => $billingModel->getId()]);
        }
        if (is_null($shippingModel->getState())) {
            Shopware()->Db()->update('s_order_shippingaddress', ['stateID' => 0], ['id' => $shippingModel->getId()]);
        }

        return true;
    }

    /**
     * @param string $orderId
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    private function isProcessed($orderId)
    {
        $id = Shopware()->Db()->fetchOne('SELECT id FROM s_order_attributes WHERE hm_order_id = ?', array($orderId));
        return !empty($id);
    }

    private function getCustomer($email, AddressTransfer $billing, AddressTransfer $shipping)
    {
        $customer = new Customer();
        $customer->setShop($this->getShop());
        $customer->setAccountMode(1);
        $customer->setActive(true);
        $customer->setEmail($email);
        $customer->setGroup($this->getShop()->getCustomerGroup());
        $customer->setPaymentId(Shopware()->Config()->get('paymentdefault'));
        $customer->setSalutation($billing->gender=='female'?'ms':'mr');
        $customer->setFirstName($billing->first_name?:'');
        $customer->setLastName($billing->last_name?:'');

        $billAddress = new Address();
        $billAddress->setSalutation($billing->gender=='female'?'ms':'mr');
        $billAddress->setCountry(Shopware()->Models()->find('Shopware\Models\Country\Country', $this->getCountryId($billing->country)));
        $billAddress->setFirstName($billing->first_name?:'');
        $billAddress->setLastName($billing->last_name?:'');
        $billAddress->setCompany($billing->company_name?:'');
        $billAddress->setStreet(sprintf('%s %s', $billing->street, $billing->house_number));
        $billAddress->setZipCode($billing->postcode?:'');
        $billAddress->setCity($billing->city?:'');
        $billAddress->setPhone($billing->phone?:'');
        $billAddress->setAdditionalAddressLine1($billing->additional_field?:'');

        $shipAddress = new Address();
        $shipAddress->setSalutation($shipping->gender=='female'?'ms':'mr');
        $shipAddress->setCountry(Shopware()->Models()->find('Shopware\Models\Country\Country', $this->getCountryId($shipping->country)));
        $shipAddress->setFirstName($shipping->first_name?:'');
        $shipAddress->setLastName($shipping->last_name?:'');
        $shipAddress->setCompany($shipping->company_name?:'');
        $shipAddress->setStreet(sprintf('%s %s', $shipping->street, $shipping->house_number));
        $shipAddress->setZipCode($shipping->postcode?:'');
        $shipAddress->setCity($shipping->city?:'');
        $shipAddress->setAdditionalAddressLine1($shipping->additional_field?:'');

        // Create Customer
        $registerService = Shopware()->Container()->get('shopware_account.register_service');

        /** @var Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context */
        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();

        /** @var Shopware\Bundle\StoreFrontBundle\Struct\Shop $shop */
        $context = $context->getShop();

        $registerService->register($context, $customer, $billAddress, $shipAddress);

        return $customer;
    }

    /**
     * @param string $iso
     * @return int
     * @throws \Exception
     */
    private function getCountryId($iso)
    {
        /** @var Country $country */
        $country = Shopware()->Models()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $iso));
        if (!$country) {
            throw new \Exception('Country not found');
        }
        return $country->getId();
    }

    /**
     * @return string
     */
    private function getOrderId()
    {
        $number = Shopware()->Db()->fetchOne("/*NO LIMIT*/ SELECT number FROM s_order_number WHERE name='invoice' FOR UPDATE");
        $number += 1;

        Shopware()->Db()->executeUpdate("UPDATE s_order_number SET number = number + 1 WHERE name='invoice'");
        Shopware()->Db()->query('INSERT INTO s_order (ordernumber) VALUES (?)', array($number));

        return Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE ordernumber = ?', array($number));
    }

    /**
     * @return Dispatch
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    private function getDeliveryMethod()
    {
        if (empty($this->deliveryMethodId)) {
            throw new \Exception('Delivery method is not set');
        }
        return Shopware()->Models()->getReference('Shopware\Models\Dispatch\Dispatch', $this->deliveryMethodId);
    }

    /**
     * @return Payment
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    private function getPaymentMethod()
    {
        if (empty($this->paymentMethodId)) {
            throw new \Exception('Payment method is not set');
        }
        return Shopware()->Models()->getReference('Shopware\Models\Payment\Payment', $this->paymentMethodId);
    }


    /**
     * @return Shop
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    private function getShop()
    {
        if (empty($this->shop)) {
            throw new \Exception('Shop is not set');
        }
        return $this->shop;
    }

    /**
     * @param OrderUnitTransfer $hmUnit
     * @param Order $orderModel
     * @return OrderDetail
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    private function createOrderDetail(OrderUnitTransfer $hmUnit, Order $orderModel)
    {
        if (empty($hmUnit->id_offer)) {
            throw new \Exception('Id offer is not set');
        }

        $articleIds = Shopware()->Db()->fetchRow(
            'SELECT a.id, ad.id AS detailId FROM s_articles a, s_articles_details ad WHERE a.id = ad.articleID AND ad.ordernumber = ?',
            array($hmUnit->id_offer)
        );

        if (empty($articleIds)) {
            throw new \Exception(sprintf('Article %s not found', $hmUnit->id_offer));
        }

        $articleId = $articleIds['id'];
        $articleDetailId = $articleIds['detailId'];

        /** @var Article $articleModel */
        $articleModel = Shopware()->Models()->find('Shopware\Models\Article\Article', $articleId);

        /** @var ArticleDetail $articleDetailModel */
        $articleDetailModel = Shopware()->Models()->find('Shopware\Models\Article\Detail', $articleDetailId);

        $taxModel = $articleModel->getTax();

        $tax = $taxModel->getTax();

        $orderDetailModel = new OrderDetail();
        $orderDetailModel->setTax($taxModel);
        $orderDetailModel->setTaxRate($taxModel->getTax());

        $orderDetailModel->setEsdArticle(0);

        /** @var DetailStatus $detailStatusModel */
        $detailStatusModel = Shopware()->Models()->find('Shopware\Models\Order\DetailStatus', 0);
        $orderDetailModel->setStatus($detailStatusModel);

        if (is_object($articleDetailModel->getUnit())) {
            $unitName = $articleDetailModel->getUnit()->getName();
        } else {
            $unitName = 0;
        }

        $price = $hmUnit->price;
        $priceNet = $price * 100 / (100 + $tax);

        $orderDetailModel->setArticleId($articleModel->getId());
        $orderDetailModel->setArticleName($articleModel->getName());
        $orderDetailModel->setArticleNumber($articleDetailModel->getNumber());
        $orderDetailModel->setPrice(round($price / 100, 2));
        $orderDetailModel->setMode(4);
        $orderDetailModel->setQuantity(1);
        $orderDetailModel->setShipped(0);
        $orderDetailModel->setUnit($unitName);
        $orderDetailModel->setPackUnit($articleDetailModel->getPackUnit());

        $orderDetailModel->setNumber($orderModel->getNumber());
        $orderDetailModel->setOrder($orderModel);

        $orderDetailAttributeModel = new \Shopware\Models\Attribute\OrderDetail();
        $orderDetailAttributeModel->setAttribute1('');
        $orderDetailAttributeModel->setAttribute2('');
        $orderDetailAttributeModel->setAttribute3('');
        $orderDetailAttributeModel->setAttribute4('');
        $orderDetailAttributeModel->setAttribute5('');
        $orderDetailAttributeModel->setAttribute6('');
        $orderDetailAttributeModel->setHmOrderUnitId($hmUnit->id_order_unit?:'');
        $orderDetailModel->setAttribute($orderDetailAttributeModel);

        return array($orderDetailModel, $price, $priceNet);
    }

    /**
     * @param CustomerBilling $billingCustomerModel
     * @return OrderBilling
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function createBillingAddress(CustomerBilling $billingCustomerModel)
    {
        $billingOrderModel = new OrderBilling();
        $billingOrderModel->setCity($billingCustomerModel->getCity());
        $billingOrderModel->setStreet($billingCustomerModel->getStreet());
        $billingOrderModel->setSalutation($billingCustomerModel->getSalutation());
        $billingOrderModel->setZipCode($billingCustomerModel->getZipCode());
        $billingOrderModel->setFirstName($billingCustomerModel->getFirstName());
        $billingOrderModel->setLastName($billingCustomerModel->getLastName());
        $billingOrderModel->setAdditionalAddressLine1($billingCustomerModel->getAdditionalAddressLine1());
        $billingOrderModel->setAdditionalAddressLine2($billingCustomerModel->getAdditionalAddressLine2());
        $billingOrderModel->setVatId($billingCustomerModel->getVatId());
        $billingOrderModel->setPhone($billingCustomerModel->getPhone());
        $billingOrderModel->setCompany($billingCustomerModel->getCompany());
        $billingOrderModel->setDepartment("");
        $billingOrderModel->setCustomer($billingCustomerModel->getCustomer());

        if ($billingCustomerModel->getCountry()) {
            $billingOrderModel->setCountry($billingCustomerModel->getCountry());
        }

        if ($billingCustomerModel->getState()) {
            $billingOrderModel->setState($billingCustomerModel->getState());
        }

        return $billingOrderModel;
    }

    /**
     * @param Shipping $addressHolderModel
     * @return OrderShipping
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function createShippingAddress(CustomerShipping $addressHolderModel)
    {
        $shippingOrderModel = new OrderShipping();
        $shippingOrderModel->setCity($addressHolderModel->getCity());
        $shippingOrderModel->setStreet($addressHolderModel->getStreet());
        $shippingOrderModel->setSalutation($addressHolderModel->getSalutation());
        $shippingOrderModel->setZipCode($addressHolderModel->getZipCode());
        $shippingOrderModel->setFirstName($addressHolderModel->getFirstName());
        $shippingOrderModel->setLastName($addressHolderModel->getLastName());
        $shippingOrderModel->setAdditionalAddressLine1($addressHolderModel->getAdditionalAddressLine1());
        $shippingOrderModel->setAdditionalAddressLine2($addressHolderModel->getAdditionalAddressLine2());
        $shippingOrderModel->setCompany($addressHolderModel->getCompany());
        $shippingOrderModel->setDepartment("");
        $shippingOrderModel->setCustomer($addressHolderModel->getCustomer());

        if ($addressHolderModel->getCountry()) {
            $shippingOrderModel->setCountry($addressHolderModel->getCountry());
        }

        if ($addressHolderModel->getState()) {
            $shippingOrderModel->setState($addressHolderModel->getState());
        }

        return $shippingOrderModel;
    }

    /**
     * @param Order $orderModel
     * @return PaymentInstance
     */
    private function preparePaymentInstance(Order $orderModel)
    {
        $paymentInstanceModel = new PaymentInstance();
        $paymentInstanceModel->setPaymentMean($orderModel->getPayment());
        $paymentInstanceModel->setOrder($orderModel);
        $paymentInstanceModel->setCreatedAt($orderModel->getOrderTime());
        $paymentInstanceModel->setCustomer($orderModel->getCustomer());
        $paymentInstanceModel->setFirstName($orderModel->getBilling()->getFirstName());
        $paymentInstanceModel->setLastName($orderModel->getBilling()->getLastName());
        $paymentInstanceModel->setAddress($orderModel->getBilling()->getStreet());
        $paymentInstanceModel->setZipCode($orderModel->getBilling()->getZipCode());
        $paymentInstanceModel->setCity($orderModel->getBilling()->getCity());
        $paymentInstanceModel->setAmount($orderModel->getInvoiceAmount());

        return $paymentInstanceModel;
    }

    /**
     * @param $hmOrderUnitId
     * @return bool
     * @throws \Exception
     */
    public function cancelOrderUnit($hmOrderUnitId)
    {
        $ids = Shopware()->Db()->fetchRow(
            'SELECT d.id, d.orderID FROM s_order_details_attributes a, s_order_details d WHERE a.detailID = d.id AND a.hm_order_unit_id = ?',
            array($hmOrderUnitId)
        );
        if (empty($ids)) {
            return false;
        }

        $hmOrderUnit = $this->apiClient->orderUnits()->get($hmOrderUnitId);
        if (Constants::STATUS_CANCELLED != $hmOrderUnit->status) {
            return false;
        }

        Shopware()->Db()->executeUpdate('UPDATE s_order_details_attributes SET hm_status = ? WHERE detailID = ?', array('canceled', $ids['id']));
        Shopware()->Db()->executeUpdate('UPDATE s_order_details SET status = ? WHERE id = ?', array(2, $ids['id']));

        // More items?
        $count = (int)Shopware()->Db()->fetchOne(
            'SELECT COUNT(d.id) FROM s_order_details d, s_order_details_attributes a WHERE a.detailID = d.id AND a.hm_status != ? AND d.orderID = ?',
            array('canceled', $ids['orderID'])
        );

        // Cancel whole order
        if (0 == $count) {
            Shopware()->Db()->executeUpdate('UPDATE s_order SET status = ? WHERE id = ?', array(4, $ids['orderID']));
        }

        return true;
    }
}