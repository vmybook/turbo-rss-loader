<?php

namespace vmybook\turbopages\commands\services;

use vmybook\turbopages\YandexTurboModule;
use yii\helpers\ArrayHelper;
use Throwable;

class SettingsService
{
    private function getSettingsFile(): string
    {
        $settingsFile =  \Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . 'turbosettings.txt';
        if(!is_file($settingsFile)) {
            $this->saveInitialSettingsData($settingsFile);
        }

        $settings = file_get_contents($settingsFile);
        if(empty($settings)) {
            $this->saveInitialSettingsData($settingsFile);
        }
        
        return $settingsFile;
    }

    public function getSettings(): array
    {
        $settingsFile = $this->getSettingsFile();
        $settings = file_get_contents($settingsFile);
        $settings = json_decode($settings, true);

        return $settings;
    }

    private function saveInitialSettingsData($settingsFile) 
    {
        $module = YandexTurboModule::getInstance();
        $settingsInitialData = [
            'limit' => $module->totalPageCount,
            'offset' => 0,
            'launchTime' => time(),
        ];
        file_put_contents($settingsFile, json_encode($settingsInitialData));
    }

    public function getValue(string $name)
    {
        return ArrayHelper::getValue($this->getSettings(), $name);
    }

    public function setValue(string $name, $value)
    {
        $settingsFile = $this->getSettingsFile();
        $settings = $this->getSettings();

        ArrayHelper::setValue($settings, $name, $value);
        
        file_put_contents($settingsFile, json_encode($settings));
    }
}