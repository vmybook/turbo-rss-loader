<?php 

namespace vmybook\turbopages\commands;

use Yii;
use vmybook\turbopages\YandexTurboRssBuilder;
use vmybook\turbopages\YandexTurboApi;
use vmybook\turbopages\YandexTurboModule;
use \yii\console\Controller;
use Throwable;

class FeedController extends Controller
{
    public function actionCreate()
    {
        $time1 = time();
        $this->log('Start building feed');
        
        YandexTurboRssBuilder::buildRssFeed();
        
        $this->log('End building feed');
        
        $time2 = time();
        $this->log("TOTAL time " . ($time2 - $time1) . ' sec');
    }

    public function actionDelete()
    {
        $time1 = time();
        $this->log('Start building to del feed');
        
        YandexTurboRssBuilder::buildRssFeedToDel();
        
        $this->log('End building to del feed');
        
        $time2 = time();
        $this->log("TOTAL time " . ($time2 - $time1) . ' sec');
    }

    public function actionStatus($taskId = '3deb4410-fef3-11ed-8af8-4744ebb33324')
    {
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

    protected function log($msg)
    {
        echo $msg . PHP_EOL;
    }
}