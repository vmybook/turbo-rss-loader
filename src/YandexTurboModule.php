<?php

namespace vmybook\turbopages;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\caching\Cache;
use Throwable;

class YandexTurboModule extends Module implements BootstrapInterface
{
    public $controllerNamespace = 'vmybook\turbopages\controllers';

    public $cacheExpire = 3600;
    public $cacheProviderName = 'cache';
    public $cacheProvider = null;
    public $cacheKey = 'yandexTurbo';

    public $host = '';
    public $token = '';
    public $isDebug = true;

    public $maxFile = 1;
    public $maxItemsInFile = 10;

    /** FeedItemInterface */
    public $feed;

    public $title;
    public $link;
    public $description;
    public $language;
    public $analytics;
    public $adNetwork = [
        // [
        //     'id' => 'R-A-745622-1',
        //     'turbo-ad-id' => 'header_ad_block',
        // ],
        [
            'id' => 'R-A-745622-14',
            'turbo-ad-id' => 'before_text_ad_block',
        ],
        [
            'id' => 'R-A-745622-11',
            'turbo-ad-id' => 'middle_text_ad_block',
        ],
        [
            'id' => 'R-A-745622-6',
            'turbo-ad-id' => 'footer_ad_block',
        ],
    ];

    public function init(): void
    {
        parent::init();

        if (Yii::$app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'app\modules\yandexturbo\commands';
        }

        if (is_string($this->cacheProviderName)) {
            $this->cacheProvider = Yii::$app->get($this->cacheProviderName);
        }

        if (empty($this->cacheProvider) || !$this->cacheProvider instanceof Cache) {
            throw new InvalidConfigException('Invalid `cacheKey` parameter was specified.');
        }

        if (empty($this->title)) {
            $this->title = Yii::$app->name;
        }

        if (empty($this->link)) {
            $this->link = \Yii::$app->params['domainName'];
        }

        if (empty($this->language)) {
            $this->language = Yii::$app->language;
        }
    }

    public function bootstrap($app)
    {
        if ($app instanceof \yii\console\Application) {
        	$this->controllerNamespace = 'vmybook\turbopages\commands';
        }
    }
}