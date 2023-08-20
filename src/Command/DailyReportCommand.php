<?php

namespace App\Command;

use App\Domain\Craw\DolcegustoCraw;
use App\Entity\CurrencyRate;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TelegramBot\Api\BotApi;


#[AsCommand(
    name: 'app:daily-report',
    description: 'Send Report to Registered Users.',
    aliases: [],
    hidden: false
)]
class DailyReportCommand extends Command
{
    private ParameterBagInterface $params;
    private BotApi $telegram;
    private HttpClientInterface $client;
    private LoggerInterface $logger;
    private EntityManagerInterface $manager;

    public function __construct(ParameterBagInterface $params, HttpClientInterface $client, LoggerInterface $logger, EntityManagerInterface $manager)
    {
        parent::__construct();
        $this->params = $params;
        $this->client = $client;
        $this->manager = $manager;

        $this->telegram = new BotApi($this->params->get('telegram.token'));
        $this->logger = $logger;
    }


    protected function configure(): void
    {
        $this
            ->setDescription('Send Report to Registered Users.')
            ->addOption('chat_id', '', InputOption::VALUE_OPTIONAL, 'Telegram Chat ID')
            ->addOption('receiver_name', '', InputOption::VALUE_OPTIONAL, 'Telegram Receiver Name');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        if (!empty($input->getOption('chat_id'))) {
            $users = [
                [
                    'name' => $input->getOption('receiver_name'),
                    'chatId' => $input->getOption('chat_id')
                ]
            ];
        } else {
            $file = Yaml::parseFile('telegram-users.yml');
            $users = array_map(fn ($item) => $item, $file['users']);
        }

        if (empty($users)) {
            throw new Exception('Nenhum usuário identificado');
        }


        $usersConfig = $this->getUserConfigs();
        foreach ($users as $username => $user) {
            foreach ($usersConfig as $userConfig) {
                if ($username == $userConfig['username']) {
                    $users[$username] = [...$users[$username], ...$userConfig];
                }
            }
        }
        foreach ($users as $username => $user) {
            if (!array_key_exists('habilitado', $user)) {
                unset($users[$username]);
            }
        }

        $today = new \DateTime();

        $query = $this->manager->createQuery(
            'SELECT cr
            FROM App\Entity\CurrencyRate cr
            WHERE cr.date >= :today
            AND cr.currency = :currency
            ORDER BY cr.date DESC
            '
        )
            ->setParameter('today', $today->format('Y-m-d 00:00:00'))
            ->setParameter('currency', 'USD')
            ->setMaxResults(1);
        $currencyRate = $query->getOneOrNullResult();

        if (empty($currencyRate)) {
            $dollarPrice = round($this->currencyApi(), 2);
            $currencyRate = new CurrencyRate();
            $currencyRate->setCurrency('USD');
            $currencyRate->setDate($today);
            $currencyRate->setRate($dollarPrice);
            $this->manager->persist($currencyRate);
            $this->manager->flush();
        } else {
            $dollarPrice = $currencyRate->getRate();
        }

        $news = $this->getTecmundoNews();
        $newsMsg = $this->buildNewsMessage($news);

        foreach ($users as $user) {
            if (array_key_exists('habilitado', $user) && $user['habilitado'] !== true) {
                continue;
            }

            $msg = "Hey *{$user['name']}*, bom dia!!";
            $this->sendMessage($user['chatId'], $msg, 'markdown');

            if ($user['cotacao_dollar']) {
                $this->logger->info("Enviado Cotação do dollar para o usuário: " . $user['nome']);
                $dollarMsg = "O dolar hoje ({$today->format('d/m/Y')}) está: R$ *{$dollarPrice}*.";
                $this->sendMessage($user['chatId'], $dollarMsg, 'markdown');
            }

            if ($user['noticias_tecmundo']) {
                $this->logger->info("Enviado as notíficas do tecmundo para o usuário: " . $user['nome']);
                $newsMsg = "<b>Aqui estão as notícias mais lidas do dia!</b> \n\n" . $newsMsg;
                $this->sendMessage($user['chatId'], $newsMsg, 'html');
            }

            if ($user['promocao_dolcegusto']) {
                $this->logger->info("Buscando promos dolcegusto para o usuário: " . $user['nome']);
                $saboresEmPromocao = $this->getDolcegustoPromocoes();
                if (!empty($saboresEmPromocao)) {
                    $finalMessage = "<b>Tem promoção de DolceGusto hoje!!!</b>\n\n";
                    foreach ($saboresEmPromocao as $saborEmPromocao => $saborDetalhes) {
                        $finalMessage .= "O sabor <b>{$saborEmPromocao}</b> atingiu a marca de " . number_format($saborDetalhes['value'], 2, ',', '.') . "! Clique no link para ver: <a href=\"" . $saborDetalhes['url'] . "\">LINK</a>\n\n";
                    }
                    $this->sendMessage($user['chatId'], $finalMessage, 'html');
                }
            }
        }


        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable

        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        $this->logger->info('Enviado com sucesso!');
        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }

    public function getTecmundoNews(): array
    {
        $client = new Client();
        $res = $client->request('GET', 'https://www.tecmundo.com.br/mais-lidas');
        $html = $res->getBody()->__toString();

        $crawler = new Crawler($html);

        $news = $crawler->filter('.tec--list__item .tec--card__title__link')->each(function (Crawler $item) {
            return [
                'title' => $item->text(),
                'link' => $item->attr('href')
            ];
        });
        $news = array_slice($news, 0, 8);

        return $news;
    }

    public function getDolcegustoPromocoes(): array
    {
        $craw = new DolcegustoCraw();
        $saboresEmPromocao  = $craw->crawSaboresEmPromocao();
        if (count($saboresEmPromocao) > 0) {
            return $saboresEmPromocao;
        }
        return [];
    }


    public function getUserConfigs()
    {
        $configSheetUrl = $this->params->get('spreadsheet_url');
        $handle = fopen($configSheetUrl, 'r');

        $header = fgetcsv($handle, 1000, ',');

        $final = [];
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $final[] = array_combine($header, $data);
        }
        fclose($handle);

        foreach ($final as $i => $config) {
            foreach ($config as $j => $answer) {
                if (preg_match('/TRUE|VERDADEIRO|SIM|YES/i', $answer)) {
                    $final[$i][$j] = true;
                } elseif (preg_match('/FALSE|FALSO|Nao|Não|No/i', $answer)) {
                    $final[$i][$j] = false;
                }
            }
        }


        # Se o usuário não tiver nenhuma skill habilitada, desabilita o usuário
        foreach ($final as $i => $config) {
            $isDesabilitado = true;
            foreach ($config as $j => $answer) {
                if ($j !== 'habilitado' && is_bool($answer) && $answer === true) {
                    $isDesabilitado = false;
                    break;
                }
            }
            if ($isDesabilitado) {
                $final[$i]['habilitado'] = false;
            }
        }

        return $final;
    }

    public function buildNewsMessage(array $news): string
    {
        $msg = '';
        foreach ($news as $key => $newsItem) {
            $msg .= '<a href="' . $newsItem['link'] . '">' . ++$key . '. ' . $newsItem['title'] . "</a>\n\n";
        }
        return $msg;
    }

    public function currencyApi()
    {
        $this->client = $this->client->withOptions([
            'base_uri' => 'https://api.exchangerate.host/',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'extra' => ['access_key', $this->params->get('exchange_rate.token')]
        ]);


        $response = $this->client->request('GET', 'latest', [
            'query' => [
                'base' => 'USD',
                'symbols' => 'BRL'
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            $content = $response->toArray();
            return (float)$content['rates']['BRL'];
        } else {
            var_export($response->getContent());
            throw new \Exception('Erro ao consultar dollar. Status code: ' . $response->getStatusCode());
        }
    }


    /**
     * @param string $chatId
     * @param string $message
     * @param ?string $parseMode [markdown, html]
     * @return void
     * @throws \TelegramBot\Api\Exception
     * @throws \TelegramBot\Api\InvalidArgumentException
     */
    private function sendMessage(string $chatId, string $message, string $parseMode = null): void
    {

        $this->telegram->sendMessage($chatId, $message, $parseMode, true);
    }

    private function sendPhoto(string $chatId, string $path, string $message = ''): void
    {
        $realpath = new \CURLFile(realpath($path));
        $this->telegram->sendPhoto($chatId, $realpath, $message);
    }
}
