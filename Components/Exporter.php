<?php

namespace ShopwarePlugins\HmMarketplace\Components;

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
     * @return bool|string
     */
    public function getFeed()
    {
        if (file_exists($this->getFilename())) {
            return $this->getFilename();
        }

        $handler = fopen($this->getFilename(), 'w');
        if (!$handler) {
            return false;
        }

        $this->buildFeed($handler);

        fclose($handler);
        return $this->getFilename();
    }

    /**
     *
     */
    public function flushCache()
    {
        if (file_exists($this->getFilename())) {
            @unlink($this->getFilename());
        }
    }

    /**
     * @return string
     */
    private function getFilename()
    {
        if (!file_exists($this->cacheDir . '/hm/')) {
            mkdir($this->cacheDir . '/hm/', 0777);
        }
        return $this->cacheDir . '/hm/product_feed.csv';
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
        fwrite($handler, implode(';', $header).";\n");

        while(true) {
            $stmt = $this->connection->executeQuery($sql . sprintf(' LIMIT %d,%d', $offset, $limit));
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Done
            if (empty($data)) {
                break;
            }

            foreach ($data as $item) {
                if ($item['picture']) {
                    $item['picture'] = $imageDir.$item['picture'];
                }

                $line = array(
                    $this->escape($item['ean']),
                    $this->escape($item['title']),
                    $this->escape($item['description']),
                    $this->escape($item['short_description']),
                    $this->escape($item['picture']),
                    '',//category
                    $this->escape($item['mpn']),
                );

                fwrite($handler, implode(';', $line).";\n");
            }

            unset($stmt, $data);
            $offset += $limit;
        }

    }

    private function escape($line)
    {
        if (false !== strpos($line, ';')) {
            $line = '"'.str_replace('"', '""', $line).'"';
        }
        return str_replace(array("\n","\r"), array('\n','\t'), $line);
    }
}