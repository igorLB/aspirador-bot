<?php

namespace App\Domain\Craw;

use Facebook\WebDriver\WebDriverBy;
use Stringy\Stringy;
use Symfony\Component\DomCrawler\Crawler;

class APInfoCraw extends AbstractCraw
{
    const BASE_URL = "https://www.apinfo.com/apinfo/index.cfm";

    public function crawVagas(string $termo, \DateTime $startDate, \DateTime $endDate): ?array
    {
        $driver = $this->getWebDriver();
        $driver->get(self::BASE_URL);

        $driver->findElement(WebDriverBy::id('i-busca'))->sendKeys($termo);
        $driver->findElement(WebDriverBy::id('btn-busca'))->click();
        sleep(2);

        $driver->findElement(WebDriverBy::name('ddmmaa1'))->sendKeys($startDate->format('d/m/y'));
        $driver->findElement(WebDriverBy::name('ddmmaa2'))->sendKeys($endDate->format('d/m/y'));
        $driver->findElement(WebDriverBy::className('btn-submit'))->click();
        sleep(2);

        $html = $driver->getPageSource();
        $crawler = new Crawler();
        $crawler->addHtmlContent($html);

        return $crawler->filter('.box-vagas')->each(
            function (Crawler $node, $i) {
                $localData = $node->filter('.info-data')->text();
                $cargo = $node->filter('.cargo')->text();
                $empresaCodigo = $node->filterXPath('(//p)[2]')->text();

                $parts = explode("Código .", $empresaCodigo);
                $cod = preg_replace('/\D+/', "", $parts[1]);
                $emp = preg_replace('/Empresa\s+?\.+?:/i', '', $parts[0]);

                $local = preg_replace('/\d+\/\d+\/\d+/', '', $localData);
                $local = preg_replace('/\s-\s$/', '', $local);
                preg_match('/\d+\/\d+\/\d+/', $localData, $matches);
                $data = $matches[0];

                return [
                    'local' => $local,
                    'data' => \DateTime::createFromFormat('d/m/y', $data, new \DateTimeZone('America/Sao_Paulo')),
                    'codigo' => $cod,
                    'empresa' => $emp,
                    'cargo' => $cargo
                ];
            }
        );
    }

    public function getVagas(string $termo): array
    {
        $startDate = new \DateTime('yesterday');
        $endDate = new \DateTime('today');

        $parts = explode(' ', $termo);
        if ($parts && count($parts) > 1) {
            $termo = $parts[0];
            unset($parts[0]);
            $termoComplemento = $parts;
        }

        $vagas = $this->crawVagas($termo, $startDate, $endDate);

        $final= [];
        foreach ($vagas as $vaga) {
            if (!Stringy::create($vaga['local'])->containsAny(['SP', 'HO', 'Home Office', 'Remoto', 'HomeOffice'])) {
                continue;
            }
            if (
                (empty($termoComplemento))
                || (Stringy::create($vaga['cargo'])->containsAny($termoComplemento))
            ) {
                $final[] = $vaga;
            }
        }

        return $final;
    }
}
