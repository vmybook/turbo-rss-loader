<?php

namespace vmybook\turbopages;

use Yii;
use DateTime;
use DOMDocument;
use Throwable;
use yii\db\Query;
use vmybook\turbopages\jobs\SendToApiJob;
use vmybook\turbopages\exceptions\BookFileNotFoundException;

class YandexTurboRssBuilder
{
    const BATCH_MAX_SIZE = 100;

    public static function buildRssFeed(): void
    {
        $module = YandexTurboModule::getInstance();

        // Max limits according to Yandex turbo API: 10 files and 10 000 items 
        $maxFile = $module->maxFile ?? 1;
        $maxItemsInFile = $module->maxItemsInFile ?? 10;

        $feed = $module->feed;
        $feed::clearLogTable();

        $dirForFiles = self::getDirForFiles();

        for($i = 0; $i < $maxFile; $i++){
            $offset = $maxItemsInFile * $i;
            $fileName = $dirForFiles . 'rss_yandexturbo_' . ($i + 1) . '.xml';
            $feedQuery = $feed::getItems($offset, $maxItemsInFile);

            $fileSaved = self::createXmlFile($feedQuery, $fileName);
            if($fileSaved) {
                Yii::$app->queue->push(new SendToApiJob([
                    'file' => self::packFile($fileName),
                ]));
            }
        }
    }

    public static function buildRssFeedToDel(): void
    {
        $module = YandexTurboModule::getInstance();
        $feed = $module->feed; 
        
        $dirForFiles = self::getDirForFiles();
        $fileName = $dirForFiles . 'rss_yandexturbo_toDel.xml';
        
        $feedQuery = $feed::getItemsToDel();
        $fileSaved = self::createXmlFile($feedQuery, $fileName, true);
        
        if($fileSaved) {
            Yii::$app->queue->push(new SendToApiJob([
                'file' => self::packFile($fileName),
            ]));
        }
    }

    private static function getDirForFiles(): string
    {
        $dirForFiles = \Yii::$app->basePath . '/web/yandexturbo/';
        if (!file_exists($dirForFiles)) {
            mkdir($dirForFiles, 0755, true);
        }

        return $dirForFiles;
    }

    private static function packFile(string $fileName): string
    {
        $archFile = $fileName . '.gz';
        file_put_contents($archFile, gzencode(file_get_contents($fileName), 9));
        @unlink($fileName);

        return $archFile;
    }

    private static function createXmlFile(Query $feedQuery, string $fileName, $toDel = false): bool 
    {
        $module = YandexTurboModule::getInstance();
        $doc = new DOMDocument("1.0", "utf-8");

        $root = $doc->createElement("rss");
        $root->setAttribute('version', '2.0');
        $root->setAttribute('xmlns:yandex', 'http://news.yandex.ru');
        $root->setAttribute('xmlns:media', 'http://search.yahoo.com/mrss/');
        $root->setAttribute('xmlns:turbo', 'http://turbo.yandex.ru');
        $doc->appendChild($root);

        $channelNode = $doc->createElement("channel");
        $root->appendChild($channelNode);

        $titleNode = $doc->createElement("title", $module->title);
        $channelNode->appendChild($titleNode);

        $linkNode = $doc->createElement("link", $module->link);
        $channelNode->appendChild($linkNode);

        $descriptionNode = $doc->createElement("description", $module->description);
        $channelNode->appendChild($descriptionNode);

        $languageNode = $doc->createElement("language", $module->language);
        $channelNode->appendChild($languageNode);

        $lastBuildDateNode = $doc->createElement("lastBuildDate", (new DateTime())->format(DateTime::RFC822));
        $channelNode->appendChild($lastBuildDateNode);

        if (!empty($module->analytics)) {
            $analyticsNode = $doc->createElement("turbo:analytics", $module->analytics);
            $channelNode->appendChild($analyticsNode);
        }

        foreach($module->adNetwork as $adBlock) {
            $adNetworkNode = $doc->createElement("turbo:adNetwork");
            $adNetworkNode->setAttribute('type', 'Yandex');
            $adNetworkNode->setAttribute('id', $adBlock['id']);
            $adNetworkNode->setAttribute('turbo-ad-id', $adBlock['turbo-ad-id']);
            $channelNode->appendChild($adNetworkNode);
        }

        try {
            /**
             * @var FeedItemInterface $item 
             */
            $i = 0;
            $result = false; 
            foreach ($feedQuery->each(self::BATCH_MAX_SIZE) as $item) {
                try{   
                    $itemContent = $item->getContent();
                } catch(BookFileNotFoundException $e) {
                    continue;
                }

                $itemNode = $doc->createElement("item");
                $itemNode->setAttribute('turbo', $toDel === false ? 'true' : 'false');
                $channelNode->appendChild($itemNode);

                $itemExtendedNode = $doc->createElement("turbo:extendedHtml", 'true');
                $itemNode->appendChild($itemExtendedNode);

                $itemTitleNode = $doc->createElement("title", $item->getTitle());
                $itemNode->appendChild($itemTitleNode);

                $itemLinkNode = $doc->createElement("link", $item->getLink());
                $itemNode->appendChild($itemLinkNode);

                $itemDescriptionNode = $doc->createElement("description", $item->getDescription());
                $itemNode->appendChild($itemDescriptionNode);

                $itemContentNode = $doc->createElement("turbo:content");
                $itemNode->appendChild($itemContentNode);

                $contentWrapper = $doc->createCDATASection($itemContent);
                $itemContentNode->appendChild($contentWrapper);

                $itemPubDateNode = $doc->createElement("pubDate", $item->getPubDate());
                $itemNode->appendChild($itemPubDateNode);

                ++$i;

                if($toDel !== true){
                    $item->logPageIntoDb();
                }
            }

            if($i > 0) {
                $result = $doc->save($fileName);
            }
        } catch(Throwable $e) {
            Yii::error($e->getMessage(), 'queue');
            return false;
        }

        if($result === false) {
            Yii::error('Error when save file: ' . $fileName, 'queue');
            return false;
        }

        return (bool)$result;
    }
}