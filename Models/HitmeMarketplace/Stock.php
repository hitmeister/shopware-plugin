<?php

namespace Shopware\CustomModels\HitmeMarketplace;

use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="s_plugin_hitme_stock")
 */
class Stock extends ModelEntity
{
    /**
     *
     * @ORM\ManyToOne(targetEntity="Shopware\Models\Shop\Shop")
     * @ORM\JoinColumn(name="shop_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $shopId;

    /**
     *
     * @ORM\ManyToOne(targetEntity="Shopware\Models\Article\Detail")
     * @ORM\JoinColumn(name="article_detail_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $articleDetailId;

    /**
     *
     * @ORM\Column(name="unit_id", length=20, type="string", nullable=true)
     */
    protected $unitId;

    /**
     *
     * @ORM\Column(name="last_access_date", type="datetime", nullable=true)
     */
    protected $lastAccessDate;

    /**
     *
     * @ORM\Column(name="status", length=20, type="string", nullable=true)
     */
    protected $status;

    /**
     *
     * @ORM\Column(name="shippinggroup", length=255, type="string", nullable=true)
     */
    protected $shippinggroup;

    /**
     * @return mixed
     */
    public function getShopId()
    {
        return $this->shopId;
    }

    /**
     * @param mixed $shopId
     */
    public function setShopId($shopId)
    {
        $this->shopId = $shopId;
    }

    /**
     * @return mixed
     */
    public function getArticleDetailId()
    {
        return $this->articleDetailId;
    }

    /**
     * @param mixed $articleDetailId
     */
    public function setArticleDetailId($articleDetailId)
    {
        $this->articleDetailId = $articleDetailId;
    }

    /**
     * @return mixed
     */
    public function getUnitId()
    {
        return $this->unitId;
    }

    /**
     * @param mixed $unitId
     */
    public function setUnitId($unitId)
    {
        $this->unitId = $unitId;
    }

    /**
     * @return mixed
     */
    public function getLastAccessDate()
    {
        return $this->lastAccessDate;
    }

    /**
     * @param mixed $lastAccessDate
     */
    public function setLastAccessDate($lastAccessDate)
    {
        $this->lastAccessDate = $lastAccessDate;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getShippinggroup()
    {
        return $this->shippinggroup;
    }

    /**
     * @param string $shippinggroup
     */
    public function setShippinggroup($shippinggroup)
    {
        $this->shippinggroup = $shippinggroup;
    }
}
