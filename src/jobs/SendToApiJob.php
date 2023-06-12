<?php

namespace vmybook\turbopages\jobs;

use Yii;
use yii\base\BaseObject;
use \yii\queue\JobInterface;
use vmybook\turbopages\YandexTurboApi;
use vmybook\turbopages\YandexTurboModule;
use Throwable;

class SendToApiJob extends BaseObject implements JobInterface
{    
    public $file;

    public function execute($queue)
    {
        $module = \Yii::$app->getModule('yandexturbo');
        $turbo = new YandexTurboApi($module->token, $module->host, $module->isDebug);
        
        try {
            /** @var integer $user_id */
            $userId = $turbo->getIdUser();
            
            /** @var array $dataUpload */
            $dataUpload = $turbo->getUploadAddress($userId); // Возаращает время жизни и URL.
            
            /** @var array $taskId */
            $taskId = $turbo->uploadFile($dataUpload['upload_address'], $this->file);

            Yii::info('Send file: ' . $this->file . ' with taskId: ' . $taskId['task_id'], 'queue');

            // we can send 1 task per second
            sleep(2);
        } catch(Throwable $e) {
            Yii::error('Error with file: ' . $this->file . ' Error: ' . $e->getMessage(), 'queue');
            sleep(2);
        }
    }
}
