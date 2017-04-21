<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use ShopwarePlugins\HitmeMarketplace\Components\Shop as HmShop;

/**
 * Class Exporter
 * @package ShopwarePlugins\HitmeMarketplace\Components
 */
class Exporter
{
    /**
     * @var Connection
     */
    private $connection;
    
    /**
     * @var string
     */
    private $cacheDir;
    
    /**
     * @param Connection $connection
     * @param string $cacheDir
     */
    public function __construct(Connection $connection, $cacheDir)
    {
        $this->connection = $connection;
        $this->cacheDir = $cacheDir;
    }
    
    /**
     * @param string $id
     *
     * @return bool|string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getFeed($id)
    {
        $filename = $this->getFilename($id);
        
        if (file_exists($filename)) {
            return $filename;
        }
        
        $handler = fopen($filename, 'w');
        if (!$handler) {
            return false;
        }
        
        $this->buildFeed($handler);
        
        fclose($handler);
        
        return $filename;
    }
    
    /**
     * @param string $id
     *
     * @return string
     */
    private function getFilename($id)
    {
        if (!file_exists($this->cacheDir . '/hm/')) {
            mkdir($this->cacheDir . '/hm/', 0777);
        }
        
        return $this->cacheDir . sprintf('/hm/product_feed_%s.csv', $id);
    }
    
    /**
     * @param resource $handler
     *
     * @throws DBALException
     */
    private function buildFeed($handler)
    {
        /** @var \sSystem $system */
        $system = Shopware()->Bootstrap()->getResource('System');
        $imageDir = $system->sPathArticleImg;
        $shop = Shopware()->Shop();
        $shopId = $shop->getId();
        $categoryId = $shop->getCategory()->getId();
        $shopConfig = HmShop::getShopConfigByShopId($shopId);
        $shippingGroup = $shopConfig->get('defaultShippingGroup');
        
        $customArticleAttributes = $shopConfig->get('customArticleAttributes');
        $articlesAttributes = null;
        $innerJoin = '';
        
        $header = ['ean', 'title', 'description', 'short_description', 'category', 'mpn', 'manufacturer', 'content_volume', 'shipping_group'];
        $header = $this->insertPicturesHeader(5, $header);
        
        if ($customArticleAttributes !== '') {
            $articlesAttributes = $this->getArticlesAttributes($customArticleAttributes);
            $innerJoin = 'INNER JOIN s_articles_attributes saa ON a.id = saa.articleID';
            $header = $this->extendsHeader($header, $customArticleAttributes);
        }
        
        $limit = 100;
        $offset = 0;
        
        $sql = <<<SQL
SELECT DISTINCT
	d.id,
	a.id AS article_id,
	d.ean,
	CONCAT_WS(', ', a.name, v.variant_text) AS title,
	a.description_long AS description,
	a.description AS short_description,
	d.suppliernumber AS mpn,
	s.name AS manufacturer,
	CASE WHEN da.shippinggroup IS NOT NULL
       THEN da.shippinggroup
       ELSE ?
    END AS shipping_group,
	CASE WHEN u.unit IS NOT NULL
       THEN CONCAT_WS(' ', IFNULL(d.purchaseunit, 1), u.unit)
       ELSE ''
    END AS content_volume
    $articlesAttributes
FROM s_articles_details d
INNER JOIN s_articles a ON (a.id = d.articleID)
INNER JOIN s_articles_supplier s ON (s.id = a.supplierID)
$innerJoin
LEFT JOIN s_plugin_hitme_stock da ON (da.article_detail_id = d.id AND da.shop_id = ?)
LEFT JOIN s_core_units u ON (u.id = d.unitID)
LEFT JOIN (
	SELECT
		cor.article_id,
		GROUP_CONCAT(co.name ORDER BY cg.position SEPARATOR ', ') AS variant_text
	FROM s_article_configurator_option_relations cor
	INNER JOIN s_article_configurator_options co ON (co.id = cor.option_id)
	INNER JOIN s_article_configurator_groups cg ON (cg.id = co.group_id)
	GROUP BY cor.article_id
) v ON (v.article_id = d.id)
WHERE
	d.ean IS NOT NULL AND
	TRIM(d.ean) != '' AND
	d.active = 1 AND
	a.active = 1 AND
	a.supplierID IS NOT NULL AND
	(da.status NOT IN ('%s') OR da.status IS NULL) AND
	a.id IN (SELECT cat.articleID FROM s_articles_categories_ro cat WHERE cat.categoryID = ? GROUP BY cat.articleID)
SQL;
        
        $sql = sprintf($sql, StockManagement::STATUS_BLOCKED);
        $writeData = [];
        
        while (true) {
            $stmt = $this->connection->executeQuery(
                $sql . sprintf(' LIMIT %d,%d', $offset, $limit),
                [$shippingGroup, $shopId, $categoryId]
            );
            
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($data)) {
                break;
            }
            
            $ids = array_column($data, 'article_id', 'id');
            $categories = $this->getCategories($ids);
            
            $pictures = $this->getPictures(array_keys($ids));
            list($attributes, $attributeKeys) = $this->getAttributes(array_keys($ids));
            
            foreach ((array)$attributeKeys as $attributeKey) {
                if (!in_array($attributeKey, $header, true)) {
                    $header[] = $attributeKey;
                }
            }
            
            unset($attributeKeys);
            
            foreach ($data as $item) {
                $line = [];
                foreach ($header as $title) {
                    switch ($title) {
                        case (preg_match('/picture_\d/', $title) ? true : false):
                            $i = substr($title, -1);
                            $value = '';
                            if (!empty($pictures[$item['id']][$i]['img'])) {
                                $value = $imageDir . $pictures[$item['id']][$i]['img'];
                            }
                            break;
                        case 'category':
                            $value = $categories[$item['id']] ?: '';
                            break;
                        case 'content_volume':
                            $value = $this->formatContentVolume($item['content_volume']);
                            break;
                        default:
                            $value = $item[$title] ?: '';
                            if (empty($value) && isset($attributes[$item['id']])) {
                                foreach ((array)$attributes[$item['id']] as $k => $v) {
                                    if ($title === $k) {
                                        $value = $v;
                                        break;
                                    }
                                }
                            }
                            break;
                    }
                    
                    $line[] = $this->escape($value);
                }
                
                $writeData[] = $line;
            }
            unset($stmt, $data, $ids, $categories, $attributes, $pictures);
            $offset += $limit;
        }
        
        fwrite($handler, preg_replace('/picture_\d/', 'picture', implode(';', $header)) . ";\n");
        
        if (!empty($writeData)) {
            $headerLength = count($header);
            foreach ($writeData as $line) {
                $lineLength = count($line);
                if ($headerLength > $lineLength) {
                    for ($i = $lineLength; $i < $headerLength; $i++) {
                        $line[] = '';
                        $lineLength++;
                    }
                }
                
                fwrite($handler, implode(';', $line) . ";\n");
            }
        }
    }
    
    /**
     * inserts pictures header
     *
     * @param int $pos
     * @param array $header
     * @return array
     * @throws DBALException
     */
    private function insertPicturesHeader($pos, array $header)
    {
        $maxImgQt = $this->getMaxImgQt()[0];
        
        for ($i = $maxImgQt; $i >= 0; $i--) {
            array_splice($header, $pos, 0, 'picture_' . $i);
        }
        
        return $header;
    }
    
    /**
     * returns max article image qt
     * @return array
     * @throws DBALException
     */
    private function getMaxImgQt()
    {
        $sql = <<<SQL
SELECT
  count(sai.articleID) AS qt,
  sad.ean
FROM s_articles_img sai
  INNER JOIN s_articles_details sad ON sai.articleID = sad.articleID
WHERE NOT (sai.articleID <=> NULL)
      AND sad.ean != ''
GROUP BY sad.ean
ORDER BY qt DESC
LIMIT 0, 1
SQL;
        $stmt = $this->connection->executeQuery($sql, [Connection::PARAM_INT_ARRAY]);
        
        return $stmt->fetch(\PDO::FETCH_NUM);
    }
    
    /**
     * concatenate the articles attributes for the sql query
     *
     * @param $customArticleAttributes
     *
     * @return string
     */
    private function getArticlesAttributes($customArticleAttributes)
    {
        $select = [];
        foreach ((array)$customArticleAttributes as $attribute) {
            if ($attribute !== '' && strlen($attribute) > 0) {
                $select[] = 'saa.' . $attribute;
            }
        }
        
        return count(array_filter($select)) > 0 ? ', ' . implode(', ', $select) : '';
    }
    
    /**
     * extends the csv file header
     *
     * @param $header
     * @param $customArticleAttributes
     *
     * @return array
     */
    private function extendsHeader($header, $customArticleAttributes)
    {
        foreach ((array)$customArticleAttributes as $attribute) {
            $header[] = $attribute;
        }
        
        return $header;
    }
    
    /**
     * @param array $ids
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getCategories($ids)
    {
        $articleIds = array_unique(array_values($ids));
        $mapping = $this->getHmMapping($articleIds);
        
        $data = [];
        foreach ($ids as $detailId => $detailArticleId) {
            if (isset($mapping[$detailArticleId])) {
                $data[$detailId] = $mapping[$detailArticleId];
                unset($ids[$detailId]);
            }
        }
        
        if (count($ids) > 0) {
            $mapping = $this->getSwMapping($articleIds);
            foreach ($ids as $detailId => $detailArticleId) {
                if (isset($mapping[$detailArticleId])) {
                    $data[$detailId] = $mapping[$detailArticleId];
                    unset($ids[$detailId]);
                }
            }
            
            if (count($ids) > 0) {
                foreach ($ids as $detailId => $detailArticleId) {
                    $data[$detailId] = '';
                }
            }
        }
        
        return $data;
    }
    
    /**
     * @param array $articleIds
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getHmMapping(array $articleIds)
    {
        $sql = <<<SQL
SELECT cro.articleID, GROUP_CONCAT(DISTINCT ca.hm_category_title SEPARATOR '~|~')
FROM s_articles_categories_ro cro
LEFT JOIN s_categories_attributes ca ON (cro.categoryID = ca.categoryID)
WHERE cro.articleID IN (?) AND ca.hm_category_title IS NOT NULL
GROUP BY cro.articleID;
SQL;
        
        $stmt = $this->connection->executeQuery($sql, [$articleIds], [Connection::PARAM_INT_ARRAY]);
        $mapping = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        array_walk($mapping, function (&$value) {
            $items = explode('~|~', $value);
            $value = reset($items);
        });
        
        return $mapping;
    }
    
    /**
     * @param array $articleIds
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getSwMapping(array $articleIds)
    {
        $sql = <<<SQL
SELECT ac.articleID, GROUP_CONCAT(DISTINCT c.description SEPARATOR '~|~') AS name
FROM s_articles_categories ac
INNER JOIN s_categories c ON (ac.categoryID = c.id)
WHERE ac.articleID IN (?)
GROUP BY ac.articleID;
SQL;
        
        $stmt = $this->connection->executeQuery($sql, [$articleIds], [Connection::PARAM_INT_ARRAY]);
        $mapping = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        array_walk($mapping, function (&$value) {
            $items = explode('~|~', $value);
            $value = reset($items);
        });
        
        return $mapping;
    }
    
    /**
     * @param array $articleIds
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getPictures(array $articleIds)
    {
        $sql = <<<SQL
SELECT d.id, NULLIF(CONCAT_WS('.', i.img, i.extension), "") AS img, i.main
FROM s_articles_details d
  LEFT JOIN s_articles_img i ON (i.articleID = d.articleID)
WHERE d.id IN (?)
SQL;
        $stmt = $this->connection->executeQuery($sql, [$articleIds], [Connection::PARAM_INT_ARRAY]);
        $pictures = $stmt->fetchAll(\PDO::FETCH_GROUP);
        
        return $this->sortImgByMain($pictures);
    }
    
    /**
     * sort images array, returns main img as first
     * @param $pictures
     * @return mixed
     */
    private function sortImgByMain(array $pictures)
    {
        $sortedPics = [];
        foreach ($pictures as $key => $artPics) {
            usort($artPics, function ($a, $b) {
                return $a['main'] - $b['main'];
            });
            $sortedPics[$key] = $artPics;
        }
        
        return $sortedPics;
    }
    
    /**
     * @param array $articleIds
     *
     * @return array
     * @throws DBALException
     */
    private function getAttributes(array $articleIds)
    {
        $sql = <<<SQL
SELECT cor.article_id, cg.name AS 'key', co.name AS 'value'
FROM s_article_configurator_option_relations cor
INNER JOIN s_article_configurator_options co ON (co.id = cor.option_id)
INNER JOIN s_article_configurator_groups cg ON (cg.id = co.group_id)
WHERE cor.article_id IN (?)
SQL;
        $stmt = $this->connection->executeQuery($sql, [$articleIds], [Connection::PARAM_INT_ARRAY]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $keys = [];
        $result = [];
        foreach ($data as $item) {
            if (!isset($result[$item['article_id']])) {
                $result[$item['article_id']] = [];
            }
            $result[$item['article_id']][$item['key']] = $item['value'];
            $keys[] = $item['key'];
        }
        
        return [$result, array_unique($keys)];
    }
    
    /**
     * Formats content volume value
     * @param $value
     * @return int
     */
    private function formatContentVolume($value)
    {
        $val = $value;
        $ve = ['stück', 'stueck', 'stck.'];
        if ($val !== '') {
            $valAr = explode(' ', $val);
            if (in_array(strtolower($valAr[1]), $ve, true)) {
                $val = (float)$valAr[0] . ' ' . $valAr[1];
            } else {
                $val = number_format($valAr[0], 2, ',', ' '). ' ' . $valAr[1];
            }
        }
        
        return $val;
    }
    
    /**
     * @param string $line
     *
     * @return string
     */
    private function escape($line)
    {
        if (false !== strpos($line, ';')) {
            $line = '"' . str_replace('"', '""', $line) . '"';
        }
        
        return str_replace(["\n", "\r"], ['\n', '\t'], $line);
    }
    
    /**
     * @param $id
     */
    public function flushCache($id)
    {
        $filename = $this->getFilename($id);
        
        if (file_exists($filename)) {
            @unlink($filename);
        }
    }
}
