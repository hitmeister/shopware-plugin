<?php

use Shopware\Components\CSRFWhitelistAware;

/**
 * Class Shopware_Controllers_Backend_HmArticlesAttributes
 */
class Shopware_Controllers_Backend_HmArticlesAttributes extends Shopware_Controllers_Backend_ExtJs
    implements CSRFWhitelistAware
{
    /** @var Zend_Db_Adapter_Pdo_Mysql $db Pdo adapter for Mysql */
    private $db;

    /**
     * Disable template engine for all actions
     *
     * @throws Exception
     * @return void
     */
    public function preDispatch()
    {
        $this->db = $this->container->get('db');
        if (!in_array($this->Request()->getActionName(), ['index', 'load'], true)) {
            $this->Front()->Plugins()->Json()->setRenderer(true);
        }
    }

    /**
     * Whitelist notify- and webhook-actions
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'getList'
        ];
    }

    /**
     * returns attributes list
     *
     * @throws Exception
     */
    public function getListAction()
    {
        $data = $this->getArticlesAttributes();

        $this->View()->assign(['success' => true, 'data' => $data, 'total' => count($data)]);
    }

    /**
     * get extra Articles Attributes
     *
     * @return array|string
     * @throws Exception
     */
    private function getArticlesAttributes()
    {
        /** @var array $dbCfg */
        $dbCfg = $this->db->getConfig();
        $dbName = $dbCfg['dbname'];

        $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                      AND TABLE_NAME = 's_articles_attributes'
                      AND COLUMN_NAME NOT IN ('id', 'articledetailsID', 'articleID')";

        $attributes = Shopware()->Db()->fetchCol($sql, $dbName);

        $data = [];
        foreach ($attributes as $attribute) {
            $data[] = [
                'name' => $attribute,
                'label' => $this->getAttributeLabel($attribute)
            ];
        }

        return $data;
    }

    /**
     * get attribute label
     *
     * @param $attribute
     *
     * @return mixed
     */
    private function getAttributeLabel($attribute)
    {
        $sql = "SELECT
                  label
                FROM s_attribute_configuration
                WHERE table_name = 's_articles_attributes' 
                AND column_name = ?";

        return Shopware()->Db()->fetchOne($sql, $attribute);
    }
}
