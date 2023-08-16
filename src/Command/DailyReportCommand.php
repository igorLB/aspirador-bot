<?php

namespace App\Command;

use App\Entity\CurrencyRate;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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


        $today = new \DateTime();

        $query = $this->manager->createQuery(
            'SELECT cr
            FROM App\Entity\CurrencyRate cr
            WHERE cr.date >= :today
            AND cr.currency = :currency'
        )
            ->setParameter('today', $today->format('Y-m-d 00:00:00'))
            ->setParameter('currency', 'USD');
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
            if (array_key_exists('disable', $user) && $user['disable'] === true) {
                continue;
            }

            $msg = "Hey *{$user['name']}*, bom dia!! \n\nO dolar hoje ({$today->format('d/m/Y')}) está: R$ *{$dollarPrice}*.";
            $this->sendMessage($user['chatId'], $msg, 'markdown');

            $newsMsg = "<b>Aqui estão as notícias mais lidas do dia!</b> \n\n" . $newsMsg;
            $this->sendMessage($user['chatId'], $newsMsg, 'html');
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
