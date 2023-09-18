<?php 

namespace vmybook\turbopages\commands;

use Yii;
use vmybook\turbopages\commands\services\FeedLoadService;
use vmybook\turbopages\commands\services\SettingsService;
use vmybook\turbopages\commands\services\StatusUpdaterService;
use vmybook\turbopages\YandexTurboRssBuilder;
use vmybook\turbopages\YandexTurboApi;
use vmybook\turbopages\YandexTurboModule;
use \yii\console\Controller;
use Throwable;

class FeedController extends Controller
{
    private $feedLoadService;
    private $settingsService;

    private function getFeedLoadService(): FeedLoadService
    {
        if(!isset($this->feedLoadService)) {
            $this->feedLoadService = new FeedLoadService;
        }

        return $this->feedLoadService;
    }

    private function getSettingsService(): SettingsService
    {
        if(!isset($this->settingsService)) {
            $this->settingsService = new SettingsService();
        }

        return $this->settingsService;
    }

    public function actionCreate()
    {
        $time1 = time();

        $isJobsStillRunning = !$this->getFeedLoadService()->checkJobsCanRun();
        if($isJobsStillRunning === true) {
            $this->log('Jobs cant be running');    
            exit;
        }
        
        $this->log('Start building feed');

        // Known issue: when tasks are queued but not yet processed and a command [Create] is run, the tasks will not be executed and will be overwritten
        $offset = $this->getSettingsService()->getValue('offset');
        YandexTurboRssBuilder::buildRssFeed($offset); 
        
        $this->log('End building feed');
        
        $time2 = time();
        $this->log("TOTAL time " . ($time2 - $time1) . ' sec');
    }

    public function actionStatus($taskId = '')
    {
        if(empty($taskId)) {
            $this->log('Task ID must to be set');
        }

        $module = YandexTurboModule::getInstance();
        $turbo = new YandexTurboApi($module->token, $module->host, $module->isDebug);
        
        try {
            $userId = $turbo->getIdUser();
            $status = $turbo->getStatus($userId, $taskId);
            var_dump($status);
        } catch(Throwable $e) {
            var_dump($e->getMessage());
        }
    }

    public function actionJobs($mode = 'PRODUCTION', $status = 'PROCESSING')
    {
        //mode:  DEBUG, PRODUCTION, ALL
        //status: PROCESSING, OK, WARNING, ERROR

        $module = YandexTurboModule::getInstance();
        $turbo = new YandexTurboApi($module->token, $module->host, $module->isDebug);
        
        try {
            $userId = $turbo->getIdUser();
            $status = $turbo->getJobList($userId, $mode, $status);
            var_dump($status);
        } catch(Throwable $e) {
            var_dump($e->getMessage());
        }
    }

    public function updatePageStatus()
    {
        // get tasks from task table  where status is 0
        $updater = new StatusUpdaterService();
        $tasksInProgress = $updater->getTasksInProgress();
        
        // send status request to the Ya || if still processing exit
        foreach($tasksInProgress as $taskYaId) {
            $result = $updater->updatePageStatus($taskYaId);
            $updater->updateTaskStatus($taskYaId, $result);
        }
    }

    protected function log($msg)
    {
        echo $msg . PHP_EOL;
    }
}