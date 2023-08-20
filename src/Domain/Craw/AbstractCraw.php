<?php


namespace App\Domain\Craw;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

class AbstractCraw
{

    protected function getWebDriver()
    {
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments([
            "--no-sandbox",
            '--verbose',
            "--window-size=1200,1200",
            "--lang=pt",
            "--disable-blink-features=AutomationControlled",
        ]);
        $chromeOptions->setExperimentalOption("excludeSwitches", ["enable-automation"]);
        $chromeOptions->setExperimentalOption('useAutomationExtension', false);
        $chromeOptions->setExperimentalOption(
            'prefs',
            ['enable_referrers' => false, 'intl.accept_languages' => 'pt,pt-BR,en']
        );

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setPlatform('Linux');
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        return RemoteWebDriver::create('chrome4:4444/wd/hub', $capabilities);
    }
}
