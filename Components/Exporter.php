<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Doctrine\DBAL\Connection;

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
     * @return bool|string
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
     * @param $id
     */
    public function flushCache($id)
    {
        $filename = $this->getFilename($id);

        if (file_exists($filename)) {
            @unlink($filename);
        }
    }

    /**
     * @param string $id
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
     */
    private function buildFeed($handler)
    {
        /** @var \sSystem $system */
        $system = Shopware()->Bootstrap()->getResource('System');
        $imageDir = $system->sPathArticleImg;

        $limit = 100;
        $offset = 0;

        $sql = <<<SQL
SELECT
	d.id,
	a.id AS article_id,
	d.ean,
	CONCAT_WS(', ', a.name, v.variant_text) AS title,
	a.description_long AS description,
	a.description AS short_description,
	IFNULL(i.img, NULLIF(CONCAT_WS('.', ai.img, ai.extension), '') ) AS picture,
	d.suppliernumber AS mpn
FROM s_articles_details d
INNER JOIN s_articles a ON (a.id = d.articleID)
INNER JOIN s_articles_attributes da ON (da.articledetailsID = d.id AND da.articleID = a.id)
LEFT JOIN (
	SELECT
		cor.article_id,
		GROUP_CONCAT(co.name ORDER BY cg.position SEPARATOR ', ') AS variant_text
	FROM s_article_configurator_option_relations cor
	INNER JOIN s_article_configurator_options co ON (co.id = cor.option_id)
	INNER JOIN s_article_configurator_groups cg ON (cg.id = co.group_id)
	GROUP BY cor.article_id
) v ON (v.article_id = d.id)
LEFT JOIN (
	SELECT
		im.article_detail_id AS articledetailsID,
		NULLIF(CONCAT_WS('.', imm.img, imm.extension), "") AS img
	FROM s_articles_img im
	INNER JOIN s_articles_img imm ON (im.parent_id = imm.id)
) i ON (i.articledetailsID = d.id)
LEFT JOIN s_articles_img ai ON (a.id = ai.articleID)
WHERE
	(d.ean IS NOT NULL AND d.ean != '') AND
	d.active = 1 AND
	da.hm_status NOT IN ('blocked')
SQL;

        // Write header
        $header = array('ean', 'title', 'description', 'short_description', 'picture', 'category', 'mpn');
        fwrite($handler, implode(';', $header) . ";\n");

        while (true) {
            $stmt = $this->connection->executeQuery($sql . sprintf(' LIMIT %d,%d', $offset, $limit));
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $ids = array_column($data, 'article_id', 'id');
            $categories = $this->getCategories($ids);

            // Done
            if (empty($data)) {
                break;
            }

            foreach ($data as $item) {
                if ($item['picture']) {
                    $item['picture'] = $imageDir . $item['picture'];
                }

                $line = array(
                    $this->escape($item['ean']),
                    $this->escape($item['title']),
                    $this->escape($item['description']),
                    $this->escape($item['short_description']),
                    $this->escape($item['picture']),
                    $this->escape($categories[$item['id']]),
                    $this->escape($item['mpn']),
                );

                fwrite($handler, implode(';', $line) . ";\n");
            }

            unset($stmt, $data, $ids, $categories);
            $offset += $limit;
        }

    }

    /**
     * @param string $line
     * @return string
     */
    private function escape($line)
    {
        if (false !== strpos($line, ';')) {
            $line = '"' . str_replace('"', '""', $line) . '"';
        }
        return str_replace(array("\n", "\r"), array('\n', '\t'), $line);
    }

    /**
     * @param array $ids
     * @return array
     */
    private function getCategories($ids)
    {
        $articleIds = array_unique(array_values($ids));
        $mapping = $this->getHmMapping($articleIds);

        $data = array();
        foreach($ids as $detailId => $detailArticleId) {
            if (isset($mapping[$detailArticleId])) {
                $data[$detailId] = $mapping[$detailArticleId];
                unset($ids[$detailId]);
            }
        }

        if (count($ids) > 0) {
            $mapping = $this->getSwMapping($articleIds);
            foreach($ids as $detailId => $detailArticleId) {
                if (isset($mapping[$detailArticleId])) {
                    $data[$detailId] = $mapping[$detailArticleId];
                    unset($ids[$detailId]);
                }
            }

            if (count($ids) > 0) {
                foreach($ids as $detailId => $detailArticleId) {
                    $data[$detailId] = '';
                }
            }
        }

        return $data;
    }

    /**
     * @param array $articleIds
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getHmMapping($articleIds)
    {
        $sql = <<<SQL
SELECT cro.articleID, GROUP_CONCAT(DISTINCT ca.hm_category_title SEPARATOR '~|~')
FROM s_articles_categories_ro cro
LEFT JOIN s_categories_attributes ca ON (cro.categoryID = ca.categoryID)
WHERE cro.articleID IN (?) AND ca.hm_category_title IS NOT NULL
GROUP BY cro.articleID;
SQL;

        $stmt = $this->connection->executeQuery($sql, array($articleIds), array(Connection::PARAM_INT_ARRAY));
        $mapping = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        array_walk($mapping, function(&$value){
            $items = explode('~|~', $value);
            $value = reset($items);
        });

        return $mapping;
    }

    private function getSwMapping($articleIds)
    {
        $sql = <<<SQL
SELECT ac.articleID, GROUP_CONCAT(DISTINCT c.description SEPARATOR '~|~') AS name
FROM s_articles_categories ac
INNER JOIN s_categories c ON (ac.categoryID = c.id)
WHERE ac.articleID IN (?)
GROUP BY ac.articleID;
SQL;

        $stmt = $this->connection->executeQuery($sql, array($articleIds), array(Connection::PARAM_INT_ARRAY));
        $mapping = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        array_walk($mapping, function(&$value){
            $items = explode('~|~', $value);
            $value = reset($items);
        });

        return $mapping;
    }
}