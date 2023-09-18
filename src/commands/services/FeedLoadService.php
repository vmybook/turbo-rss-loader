<?php

namespace vmybook\turbopages\commands\services;

use vmybook\turbopages\YandexTurboModule;
use vmybook\turbopages\YandexTurboApi;
use vmybook\turbopages\commands\services\SettingsService;
use DateTime;
use Throwable;

class FeedLoadService
{
    private $settingsService;

    private function getSettingsService()
    {
        if(!isset($this->settingsService)) {
            $this->settingsService = new SettingsService();    
        }
        
        return $this->settingsService;
    }

    public function checkJobsCanRun(): bool
    {
        $isJobsStillRunning = $this->checkIfJobsRunning();
        if($isJobsStillRunning === true) {
            return false;
        }

        $maxLimitReached = $this->checkMaxLimit();
        $passedAllowedTimeInterval = $this->checkIfTimeIntervalReached();
        if($maxLimitReached === true && $passedAllowedTimeInterval === false) {
            return false;
        }

        return true;
    }
    
    private function checkIfJobsRunning($mode = 'PRODUCTION', $status = 'PROCESSING'): bool
    {
        $module = YandexTurboModule::getInstance();
        $turbo = new YandexTurboApi($module->token, $module->host, $module->isDebug);
        
        try {
            $userId = $turbo->getIdUser();
            $status = $turbo->getJobList($userId, $mode, $status);
            
            $jobStillRunning = isset($status['count']) && (int)$status['count'] > 0;
            
            return $jobStillRunning;
        } catch(Throwable $e) {
            \Yii::error('Error when check jobs: ' . $e->getMessage(), 'queue');
        }
    }

    private function checkMaxLimit(): bool
    {
        $module = YandexTurboModule::getInstance();
        $offset = $module->maxItemsInFile * $module->maxFile;

        $settings = $this->getSettingsService()->getSettings();
        $limit = $settings['limit'] ?? 0;
        $lastOffset = $settings['offset'] ?? 0;

        if(($lastOffset + $offset) > $limit) {
            return true;
        }

        return false;
    }

    private function checkIfTimeIntervalReached(): bool
    {
        $settings = $this->getSettingsService()->getSettings();
        $launchTime = $settings['launchTime'] ?? time(); //timestamp

        $module = YandexTurboModule::getInstance();
        $allowedTimeIntervalInHours = $module->allowedTimeIntervalInHours;

        $launchDate = new DateTime();
        $launchDate->setTimestamp($launchTime);
        $currentDate = new DateTime();
        
        $diff = $currentDate->diff($launchDate);
        $hours = $diff->h;

        if($hours > $allowedTimeIntervalInHours) {
            return true;
        }

        return false;
    }
}