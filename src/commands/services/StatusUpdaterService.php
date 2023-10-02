<?php

namespace vmybook\turbopages\commands\services;

use Yii;
use vmybook\turbopages\constants\DbTables;
use vmybook\turbopages\constants\PageStatus;
use vmybook\turbopages\constants\TaskStatus;
use vmybook\turbopages\YandexTurboApi;
use vmybook\turbopages\YandexTurboModule;
use Throwable;
use \yii\db\Expression;
use \yii\helpers\ArrayHelper;

class StatusUpdaterService
{
    public function getTasksInProgress(): array
    {
        $tasks = Yii::$app->db->createCommand('SELECT * FROM '. DbTables::TASK_LOG_TABLE .' WHERE status=:status')
            ->bindValues(['status' => TaskStatus::STATUS_LOADING])
            ->queryAll();

        return ArrayHelper::getColumn($tasks, 'task_yid');
    }

    /**
     *  [
     *       'mode' =>string(10) "PRODUCTION"
     *       'load_status' => string(7) "WARNING"
     *       'turbo_pages' => [
     *           [
     *               'link' => "https://litportal.ru/avtory/...."
     *               'preview' => "https://yandex.ru/turbo?text=https%3A%2F%2Flitportal.ru%2...."
     *               'title' => "Крутые наследнички"
     *           ]
     *           ...
     *       ],
     *       'errors' => [
     *           [
     *               'error_code' => string(43) "PARSER_ITEM_TURBO_CONTENT_HTML_TAGS_IN_TEXT"
     *               'help_link' => string(115) "https://tech.yandex.ru/turbo/doc/..."
     *               'line' => int(174812)
     *               'column' => int(13)
     *               'text' => string(95) "[CDATA[...."
     *               'context' => NULL
     *               'tag' => NULL
     *           ]
     *       ]
     *       'stats' => [
     *           'pages_count' => int(992)
     *           'errors_count' => int(0)
     *           'warnings_count' => int(1)
     *       ]
     *   ]
     */
    public function updatePageStatus($taskYaId): bool
    {
        $module = YandexTurboModule::getInstance();
        $turbo = new YandexTurboApi($module->token, $module->host, $module->isDebug);
        
        try {
            $userId = $turbo->getIdUser();
            $statusData = $turbo->getStatus($userId, $taskYaId);

            if(!isset($statusData['load_status']) || $statusData['load_status'] === 'ERROR' || !isset($statusData['turbo_pages'])) {
                return false;
            }

            $loadedPages = $statusData['turbo_pages'] ?? [];
            foreach($loadedPages as $key => $page) {
                $pageFromDb = Yii::$app->db->createCommand('SELECT * FROM '. DbTables::PAGE_LOG_TABLE .' WHERE link=:link')
                    ->bindValue(':link', $page['link'])
                    ->queryOne();

                if($pageFromDb !== false) {
                    $pageData = [
                        'ya_link' => $page['preview'] ?? '',
                        'load_count' => new Expression("load_count + 1"),
                        'status' => PageStatus::STATUS_SUCCESS,
                        'updated_at' => time(),

                    ];
                    Yii::$app->db->createCommand()->update(DbTables::PAGE_LOG_TABLE , $pageData, 'link=:link')
                        ->bindValues(['link' => $page['link']]) 
                        ->execute();
                } 
            }

            // blocking protection
            sleep(2);

            return true;
        } catch(Throwable $e) {
            Yii::error($e->getMessage(), 'queue');
            return false;
        }
    }

    public function updateTaskStatus($taskYaId, $status = true): void
    {
        try {
            $taskData = [
                'status' => $status ? TaskStatus::STATUS_SUCCESS : TaskStatus::STATUS_ERROR,
                'updated_at' => time(),
            ];
            
            Yii::$app->db->createCommand()->update(DbTables::TASK_LOG_TABLE , $taskData, 'task_yid=:task_yid')
                ->bindValues(['task_yid' => $taskYaId]) 
                ->execute();
        } catch(Throwable $e) {
            Yii::error($e->getMessage(), 'queue');
            return false;
        }
    }
}