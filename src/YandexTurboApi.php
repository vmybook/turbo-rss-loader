<?php

namespace vmybook\turbopages;

use yii\httpclient\Client;
use yii\httpclient\Request;
use yii\httpclient\Response;

class YandexTurboApi
{
    const BASE_URL = 'https://api.webmaster.yandex.net/v4/';
    const MODE_PRODUCTION = 'PRODUCTION';
    const MODE_DEBUG = 'DEBUG';

    /** @var Client */
    public $client;

    /** @var Request */
    public $request;

    /** @var Response */
    public $response;

    /** @var string */
    public $token;

    /** @var string */
    public $host;

    /** @var string */
    public $mode;

    public function __construct(string $token, string $host, bool $isDebug = false)
    {
        $this->token = $token;
        if ($this->token == null) {
            throw new \Exception('Не указан token', 0);
        }

        $this->host = $host;
        if ($this->host == null) {
            throw new \Exception('Не указан host', 0);
        }

        $this->mode = self::MODE_PRODUCTION;
        if($isDebug === true) {
            $this->mode = self::MODE_DEBUG;
        }

        $this->client = new Client([
            'baseUrl' => self::BASE_URL,
        ]);

        $this->request = $this->client->createRequest();
        $this->request = $this->request->setMethod('GET')->addHeaders([
            'Authorization' => 'OAuth ' . $this->token
        ]);
    }

    /**
     * @return mixed
     * @throws \yii\httpclient\Exception
     */
    public function getIdUser()
    {
        $this->response = $this->request->setUrl('user')->send();

        if ($this->response->getIsOk()) {
            return $this->response->getData()['user_id'];
        }
        throw new \Exception($this->response->getData()['error_message']);
    }

    public function getStatus(int $userId, string $taskId)
    {
        $this->response = $this->request->setUrl('user/' . $userId . '/hosts/' . $this->host . '/turbo/tasks/' . $taskId)->send();

        if ($this->response->getIsOk()) {
            return $this->response->getData();
        }
        throw new \Exception($this->response->getData()['error_message']);
    }

    public function getJobList(int $userId, string $taskTypeFilter = 'ALL', string $loadStatusFilter = '') 
    {
        $url = 'user/' . $userId . '/hosts/' . $this->host . '/turbo/tasks/?task_type_filter' . $taskTypeFilter;

        if(!empty($loadStatusFilter)){
            $url .= '&load_status_filter=' . $loadStatusFilter;
        }

        $this->response = $this->request
            ->setUrl($url)
            ->send();
    
        if ($this->response->getIsOk()) {
            return $this->response->getData();
        }
        throw new \Exception($this->response->getData()['error_message']);
    }

    /**
     * @param int $userId
     * @return mixed
     * @throws \yii\httpclient\Exception
     */
    public function getUploadAddress(int $userId)
    {
        $this->response = $this->request
            ->setUrl('user/' . $userId . '/hosts/' . $this->host . '/turbo/uploadAddress?mode=' . $this->mode)
            ->send();

        if ($this->response->getIsOk()) {
            return $this->response->getData();
        }
        throw new \Exception($this->response->getData()['error_message']);
    }

    /**
     * @param string $url
     * @param string $dirFile Path to the file
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function uploadFile(string $url, string $dirFile)
    {
        $this->response = $this->request->setFullUrl($url)
            ->setMethod('POST')
            ->addHeaders([
                'content-type' => 'application/rss+xml',
                'content-encoding' => 'gzip',
            ])->setContent(file_get_contents($dirFile))->send();

        if ($this->response->getIsOk()) {
            return $this->response->getData();
        }
        throw new \Exception($this->response->getData()['error_message']);
    }
}