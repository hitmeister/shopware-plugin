<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Exception;
use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Transfers\AddressTransfer;
use Hitmeister\Component\Api\Transfers\Constants;
use Hitmeister\Component\Api\Transfers\OrderUnitTransfer;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail as ArticleDetail;
use Shopware\Models\Country\Country;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Address as CustomerBilling;
use Shopware\Models\Customer\Address as CustomerShipping;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Order\Billing as OrderBilling;
use Shopware\Models\Order\Detail as OrderDetail;
use Shopware\Models\Order\DetailStatus;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Shipping as OrderShipping;
use Shopware\Models\Payment\Payment;
use Shopware\Models\Shop\Shop;

/**
 * Class Ordering
 * @package ShopwarePlugins\HitmeMarketplace\Components
 */
class Ordering
{
    const COUNTRY_MODEL = 'Shopware\Models\Country\Country';

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
     *
     * @param Client     $apiClient
     * @param int|string $deliveryMethod
     * @param int|string $paymentMethod
     * @param Shop       $shop
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(Client $apiClient, $deliveryMethod, $paymentMethod, Shop $shop)
    {
        $this->apiClient = $apiClient;
        $this->deliveryMethodId = $deliveryMethod;
        $this->paymentMethodId = $paymentMethod;
        $this->shop = Shopware()->Models()->find('Shopware\Models\Shop\Shop', $shop->getId());
    }

    /**
     * @param string $orderId
     *
     * @return bool
     * @throws Exception
     */
    public function process($orderId)
    {
        if ($this->isProcessed($orderId)) {
            return true;
        }

        $hmOrder = $this->apiClient->orders()->get(
            $orderId,
            ['billing_address', 'buyer', 'seller_units', 'shipping_address']
        );

        if (!$hmOrder) {
            throw new Exception('Order not found');
        }

        $orderId = $this->getOrderId();

        /** @var Order $orderModel */
        $orderModel = Shopware()->Models()->find('Shopware\Models\Order\Order', $orderId);

        $customerModel = $this->getCustomer(
            $hmOrder->buyer->email,
            $hmOrder->billing_address,
            $hmOrder->shipping_address
        );

        $orderModel->setCustomer($customerModel);

        $dispatchModel = $this->getDeliveryMethod();
        $orderModel->setDispatch($dispatchModel);

        $paymentModel = $this->getPaymentMethod();
        $orderModel->setPayment($paymentModel);

        // 0 = order status open
        $orderStatusModel = Shopware()->Models()->getReference('Shopware\Models\Order\Status', 0);
        $orderModel->setOrderStatus($orderStatusModel);

        // 12 = payment status completely_paid
        $paymentStatusModel = Shopware()->Models()->getReference('Shopware\Models\Order\Status', 12);
        $orderModel->setPaymentStatus($paymentStatusModel);

        $languageSubShopModel = $this->getShop();
        $orderModel->setLanguageSubShop($languageSubShopModel);
        $orderModel->setShop($languageSubShopModel);

        $shippingRate = $hmOrder->seller_units[0]->shipping_rate;

        $orderModel->setOrderTime(new \DateTime('now'));
        $orderModel->setDeviceType('real.de');

        $orderModel->setTransactionId($hmOrder->id_order);
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
        $details = [];
        foreach ($hmOrder->seller_units as $hmUnit) {
            list($detail, $price, $priceNet) = $this->createOrderDetail($hmUnit, $orderModel);
            $details[] = $detail;
            $total += $price;
            $totalNet += $priceNet;
        }

        $total += $shippingRate;
        $totalNet += $shippingRate / 1.9;

        $orderModel->setInvoiceShipping(round($shippingRate / 100, 2));
        $orderModel->setInvoiceShippingNet(round(($shippingRate / 1.19) / 100, 2));

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
        $orderAttributeModel->setHmOrderId($hmOrder->id_order ?: '');

        $orderModel->setAttribute($orderAttributeModel);

        $billingModel = $this->createBillingAddress($customerModel->getDefaultBillingAddress());
        $orderModel->setBilling($billingModel);

        $shippingModel = $this->createShippingAddress($customerModel->getDefaultShippingAddress());
        $orderModel->setShipping($shippingModel);

        Shopware()->Models()->persist($orderModel);
        Shopware()->Models()->flush();

        if (null === $billingModel->getState()) {
            Shopware()->Db()->update('s_order_billingaddress', ['stateID' => 0], ['id' => $billingModel->getId()]);
        }
        if (null === $shippingModel->getState()) {
            Shopware()->Db()->update('s_order_shippingaddress', ['stateID' => 0], ['id' => $shippingModel->getId()]);
        }

        return true;
    }

    /**
     * @param string $orderId
     *
     * @return bool
     */
    private function isProcessed($orderId)
    {
        $id = Shopware()->Db()->fetchOne('SELECT id FROM s_order_attributes WHERE hm_order_id = ?', [$orderId]);

        return !empty($id);
    }

    /**
     * @return string
     * @throws \Zend_Db_Adapter_Exception
     */
    private function getOrderId()
    {
        $number = Shopware()->Db()->fetchOne("/*NO LIMIT*/ SELECT number FROM s_order_number WHERE name='invoice' FOR UPDATE");
        $number++;

        Shopware()->Db()->executeUpdate("UPDATE s_order_number SET number = number + 1 WHERE name='invoice'");
        Shopware()->Db()->query('INSERT INTO s_order (ordernumber) VALUES (?)', [$number]);

        return Shopware()->Db()->fetchOne('SELECT id FROM s_order WHERE ordernumber = ?', [$number]);
    }

    /**
     * returns sw customer
     *
     * @param                 $email
     * @param AddressTransfer $billing
     * @param AddressTransfer $shipping
     *
     * @return Customer
     * @throws Exception
     */
    private function getCustomer($email, AddressTransfer $billing, AddressTransfer $shipping)
    {
        $customer = new Customer();
        $customer->setShop($this->getShop());
        $customer->setAccountMode(1);
        $customer->setActive(true);
        $customer->setEmail($email);
        $customer->setGroup($this->getShop()->getCustomerGroup());
        $customer->setPaymentId(Shopware()->Config()->get('paymentdefault'));
        $customer->setSalutation($billing->gender === 'female' ? 'ms' : 'mr');
        $customer->setFirstname($billing->first_name ?: '');
        $customer->setLastname($billing->last_name ?: '');

        $billAddress = new Address();
        $billAddress->setSalutation($billing->gender === 'female' ? 'ms' : 'mr');
        $billAddress->setCountry(
            Shopware()->Models()->find(self::COUNTRY_MODEL, $this->getCountryId($billing->country))
        );
        $billAddress->setFirstname($billing->first_name ?: '');
        $billAddress->setLastname($billing->last_name ?: '');
        $billAddress->setCompany($billing->company_name ?: '');
        $billAddress->setStreet(sprintf('%s %s', $billing->street, $billing->house_number));
        $billAddress->setZipcode($billing->postcode ?: '');
        $billAddress->setCity($billing->city ?: '');
        $billAddress->setPhone($billing->phone ?: '000 000 000');
        $billAddress->setAdditionalAddressLine1($billing->additional_field ?: '');

        $shipAddress = new Address();
        $shipAddress->setSalutation($shipping->gender === 'female' ? 'ms' : 'mr');
        $shipAddress->setCountry(
            Shopware()->Models()->find(self::COUNTRY_MODEL, $this->getCountryId($shipping->country))
        );
        $shipAddress->setFirstname($shipping->first_name ?: '');
        $shipAddress->setLastname($shipping->last_name ?: '');
        $shipAddress->setCompany($shipping->company_name ?: '');
        $shipAddress->setStreet(sprintf('%s %s', $shipping->street, $shipping->house_number));
        $shipAddress->setZipcode($shipping->postcode ?: '');
        $shipAddress->setCity($shipping->city ?: '');
        $shipAddress->setPhone($billing->phone ?: '000 000 000');
        $shipAddress->setAdditionalAddressLine1($shipping->additional_field ?: '');

        // Create Customer
        $registerService = Shopware()->Container()->get('shopware_account.register_service');

        /** @var ShopContextInterface $context */
        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();

        /** @var \Shopware\Bundle\StoreFrontBundle\Struct\Shop $shop */
        $context = $context->getShop();

        $registerService->register($context, $customer, $billAddress, $shipAddress);

        return $customer;
    }

    /**
     * @return Shop
     * @throws Exception
     */
    private function getShop()
    {
        if (empty($this->shop)) {
            throw new Exception('Shop is not set');
        }

        return $this->shop;
    }

    /**
     * @param string $iso
     *
     * @return int
     * @throws Exception
     */
    private function getCountryId($iso)
    {
        /** @var Country $country */
        $country = Shopware()->Models()->getRepository(self::COUNTRY_MODEL)->findOneBy(['iso' => $iso]);
        if (!$country) {
            throw new Exception('Country not found');
        }

        return $country->getId();
    }

    /**
     * @return Dispatch
     * @throws Exception
     */
    private function getDeliveryMethod()
    {
        if (empty($this->deliveryMethodId)) {
            throw new Exception('Delivery method is not set');
        }

        return Shopware()->Models()->getReference('Shopware\Models\Dispatch\Dispatch', $this->deliveryMethodId);
    }

    /**
     * @return Payment
     * @throws Exception
     */
    private function getPaymentMethod()
    {
        if (empty($this->paymentMethodId)) {
            throw new Exception('Payment method is not set');
        }

        return Shopware()->Models()->getReference('Shopware\Models\Payment\Payment', $this->paymentMethodId);
    }

    /**
     * @param OrderUnitTransfer $hmUnit
     * @param Order             $orderModel
     *
     * @return array
     * @throws Exception
     * @throws Exception
     */
    private function createOrderDetail(OrderUnitTransfer $hmUnit, Order $orderModel)
    {
        if (empty($hmUnit->id_offer)) {
            throw new Exception('Id offer is not set');
        }

        $articleIds = Shopware()->Db()->fetchRow(
            'SELECT a.id, ad.id AS detailId FROM s_articles a, s_articles_details ad WHERE a.id = ad.articleID AND ad.ordernumber = ?',
            [$hmUnit->id_offer]
        );

        if (empty($articleIds)) {
            throw new Exception(sprintf('Article %s not found', $hmUnit->id_offer));
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
        $orderDetailAttributeModel->setHmOrderUnitId($hmUnit->id_order_unit ?: '');
        $orderDetailModel->setAttribute($orderDetailAttributeModel);

        return [$orderDetailModel, $price, $priceNet];
    }

    /**
     * @param CustomerBilling $billingCustomerModel
     *
     * @return OrderBilling
     */
    private function createBillingAddress(CustomerBilling $billingCustomerModel)
    {
        $billingOrderModel = new OrderBilling();
        $billingOrderModel->setCity($billingCustomerModel->getCity());
        $billingOrderModel->setStreet($billingCustomerModel->getStreet());
        $billingOrderModel->setSalutation($billingCustomerModel->getSalutation());
        $billingOrderModel->setZipCode($billingCustomerModel->getZipcode());
        $billingOrderModel->setFirstName($billingCustomerModel->getFirstname());
        $billingOrderModel->setLastName($billingCustomerModel->getLastname());
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
     * @param CustomerShipping $addressHolderModel
     *
     * @return OrderShipping
     */
    private function createShippingAddress(CustomerShipping $addressHolderModel)
    {
        $shippingOrderModel = new OrderShipping();
        $shippingOrderModel->setCity($addressHolderModel->getCity());
        $shippingOrderModel->setStreet($addressHolderModel->getStreet());
        $shippingOrderModel->setSalutation($addressHolderModel->getSalutation());
        $shippingOrderModel->setZipCode($addressHolderModel->getZipcode());
        $shippingOrderModel->setFirstName($addressHolderModel->getFirstname());
        $shippingOrderModel->setLastName($addressHolderModel->getLastname());
        $shippingOrderModel->setAdditionalAddressLine1($addressHolderModel->getAdditionalAddressLine1());
        $shippingOrderModel->setAdditionalAddressLine2($addressHolderModel->getAdditionalAddressLine2());
        $shippingOrderModel->setCompany($addressHolderModel->getCompany());
        $shippingOrderModel->setDepartment('');
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
     * @param $hmOrderUnitId
     *
     * @return bool
     * @throws Exception
     */
    public function cancelOrderUnit($hmOrderUnitId)
    {
        $ids = Shopware()->Db()->fetchRow(
            'SELECT d.id, d.orderID FROM s_order_details_attributes a, s_order_details d WHERE a.detailID = d.id AND a.hm_order_unit_id = ?',
            [$hmOrderUnitId]
        );
        if (empty($ids)) {
            return false;
        }

        $hmOrderUnit = $this->apiClient->orderUnits()->get($hmOrderUnitId);
        if (Constants::STATUS_CANCELLED !== $hmOrderUnit->status) {
            return false;
        }

        Shopware()->Db()->executeUpdate('UPDATE s_order_details_attributes SET hm_status = ? WHERE detailID = ?', ['canceled', $ids['id']]);
        Shopware()->Db()->executeUpdate('UPDATE s_order_details SET status = ? WHERE id = ?', [2, $ids['id']]);

        // More items?
        $count = (int)Shopware()->Db()->fetchOne(
            'SELECT COUNT(d.id) FROM s_order_details d, s_order_details_attributes a WHERE a.detailID = d.id AND a.hm_status != ? AND d.orderID = ?',
            ['canceled', $ids['orderID']]
        );

        // Cancel whole order
        if (0 === $count) {
            Shopware()->Db()->executeUpdate('UPDATE s_order SET status = ? WHERE id = ?', [4, $ids['orderID']]);
        }

        return true;
    }
}
