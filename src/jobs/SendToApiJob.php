<?php

namespace vmybook\turbopages\jobs;

use Yii;
use yii\base\BaseObject;
use \yii\queue\JobInterface;
use vmybook\turbopages\YandexTurboApi;
use vmybook\turbopages\constants\DbTables;
use vmybook\turbopages\constants\TaskStatus;
use Throwable;

class SendToApiJob extends BaseObject implements JobInterface
{    
    public $file;

    public function execute($queue)
    {
        $module = \Yii::$app->getModule('turbopages');
        $turbo = new YandexTurboApi($module->token, $module->host, $module->isDebug);
        
        try {
            /** @var integer $user_id */
            $userId = $turbo->getIdUser();
            
            /** @var array $dataUpload */
            $dataUpload = $turbo->getUploadAddress($userId); // Возаращает время жизни и URL.
            
            /** @var array $taskId */
            $taskId = $turbo->uploadFile($dataUpload['upload_address'], $this->file);

            Yii::info('Send file: ' . $this->file . ' with taskId: ' . $taskId['task_id'], 'queue');
            $this->logTask($taskId['task_id'] ?? 'not_found');

            // we can send 1 task per second
            sleep(2);
        } catch(Throwable $e) {
            Yii::error('Error with file: ' . $this->file . ' Error: ' . $e->getMessage(), 'queue');
            sleep(2);
        }
    }

    private function logTask(string $taskId = '')
    {
        $taskData = [
            'task_yid' => $taskId,
            'status' => TaskStatus::STATUS_LOADING,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        Yii::$app->db->createCommand()->insert(DbTables::TASK_LOG_TABLE, $taskData)->execute();
    }
}
