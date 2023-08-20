<?php


namespace App\Domain\Craw;


use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DomCrawler\Crawler;

class DolcegustoCraw extends AbstractCraw
{
    const SABORES_TO_CRAW = [
        'mocha' => [
            'url' => 'https://www.nescafe-dolcegusto.com.br/sabores/capsulas-mocha-10',
            'target_price' => 16,
        ],
        'cortado' => [
            'url' => 'https://www.nescafe-dolcegusto.com.br/sabores/capsulas-cortado-10',
            'target_price' => 16,
        ],
        'expresso' => [
            'url' => 'https://www.nescafe-dolcegusto.com.br/sabores/cafes/capsulas-espresso-10',
            'target_price' => 16,
        ],
        'alpino' => [
            'url' => 'https://www.nescafe-dolcegusto.com.br/sabores/chocolate/capsulas-chococino-alpino-10',
            'target_price' => 16,
        ]
    ];

    public function crawSaboresEmPromocao()
    {

        $resultados = [];
        $client = new Client();

        foreach (self::SABORES_TO_CRAW as $sabor => $saborConfig) {
            $res = $client->request('GET', $saborConfig['url'], [
                RequestOptions::HEADERS => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
                ]
            ]);
            $html = $res->getBody()->__toString();

            $crawler = new Crawler($html);
            $craw = $crawler->filter('span[data-price-type="finalPrice"] .price');
            $result = $craw->text();
            $floatValue = (float)str_replace(',', '.', str_replace(['R', '$'], '', $result));

            if ($floatValue <= $saborConfig['target_price']) {
                $resultados[$sabor] = $saborConfig;
                $resultados[$sabor]['value'] = $floatValue;
            }
            sleep(1);
        }

        return $resultados;
    }

    public function crawDGusta()
    {
        $url = 'https://www.nescafe-dolcegusto.com.br/do-seu-jeito#/capsule-selection/100';
        $driver = $this->getWebDriver();
        $driver->get($url);
        sleep(10);
        $html = $driver->getPageSource();
        $crawler = new Crawler($html);
        $prices = $crawler->filter('.spc-product__info--inner')->each(
            function (Crawler $node, $i) {
                // .spc-product__name
                // .spc-product__price--regular
                $drink = $node->filter('.spc-product__name')->text();
                $drinkPrice = $node->filter('.spc-product__price--regular')->text();

                return ['drink' => $drink, 'price' => $drinkPrice];
            }
        );

        /**
         * array:31 [
                        0 => array:2 [
                    "drink" => "Cappuccino Netflix"
                    "price" => "R$2,25"
                ]
                1 => array:2 [
                    "drink" => "Espresso"
                    "price" => "R$1,99"
                ]
                2 => array:2 [
                    "drink" => "Espresso Intenso"
                    "price" => "R$1,99"
                ]
                3 => array:2 [
                    "drink" => "Espresso Barista"
                    "price" => "R$1,99"
                ]
                4 => array:2 [
                    "drink" => "Café Caseiro"
                    "price" => "R$1,99"
                ]
                5 => array:2 [
                    "drink" => "Café Caseiro Intenso"
                    "price" => "R$1,99"
                ]
                6 => array:2 [
                    "drink" => "Caffè Matinal"
                    "price" => "R$1,99"
                ]
                7 => array:2 [
                    "drink" => "Lungo"
                    "price" => "R$1,99"
                ]        4 => array:2 [
                    "drink" => "Café Caseiro"
                    "price" => "R$1,99"
                ]
                5 => array:2 [
                    "drink" => "Café Caseiro Intenso"
                    "price" => "R$1,99"
                ]
                6 => array:2 [
                    "drink" => "Caffè Matinal"
                    "price" => "R$1,99"
                ]
                7 => array:2 [
                    "drink" => "Lungo"
                    "price" => "R$1,99"
                ]
         * 
         * 
         */


        // @TODO continuar função
    }
}