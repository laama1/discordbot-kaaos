<?php

// https://github.com/discord-php

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\User\Activity;
//use Discord\WebSockets\Intents;
//use Discord\WebSockets\Event;

use Dotenv\Dotenv;
use DPHP\Commands\Events;
use DPHP\Commands\Reflect;
use DPHP\Commands\Stats;
use Psr\Http\Message\ResponseInterface;

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Sh\Shell;
use React\Sh\StdioHandler;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;


require __DIR__ . '/vendor/autoload.php';

// Load environment file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required('D_TOKEN', 'LOG_FILE');
$dotenv->required('LOGGER_LEVEL')->allowedValues(array_keys(Logger::getLevels()));

$logger = new Logger('KD_Butt');
$loop = Loop::get();

$browser = new Browser($loop);
//$token = file_get_contents(dirname(__FILE__).'/discord_token.txt');
$discord = new Discord([
	'token' => $_ENV['D_TOKEN'],
	'loop' => $loop,
	'logger' => $logger,
	//'loadAllMembers' => true,
	//'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS // | Intents::GUILD_PRESENCES
]);

$shell = new Shell($loop);

if (strtolower($_ENV['LOG_FILE']) == 'stdout') {
	$logger->pushHandler(new StdioHandler($shell->getStdio()));
} else {
	$logger->pushHandler(new StreamHandler($_ENV['LOG_FILE'], Logger::getLevels()[$_ENV['LOGGER_LEVEL']]));
}

/**
 * Generates help for the bot.
 *
 * @param  Discord $discord
 * @param  array   $commands
 * @return Embed
 */
function generateHelpCommand(Discord $discord, array $commands): Embed
{
    $embed = new Embed($discord);
    $embed->setTitle('DiscordPHP');

    foreach ($commands as $name => $command) {
        $embed->addFieldValues("@{$discord->username} ".$name, $command->getHelp());
    }

    return $embed;
}

$commands = [
	'!reflect' => new Reflect($discord),
	'!info' => new Stats($discord),
	'!events' => new Events($discord),
];

$activity = new Activity($discord, array('name' => 'kaaosradio', 'url' => 'https://kaaosradio.fi:8001/stream', 
										'type' => Activity::TYPE_LISTENING,
										'state' => Activity::STATUS_ONLINE));

$discord->on('ready', function (Discord $discord) use ($commands, $shell, $browser, $activity) {
	$discord->updatePresence($activity, false);
	$discord->on('message', function (Message $message, Discord $discord) use ($browser, $commands) {
		$inputlogfile = dirname(__FILE__). '/logs/input.log';
		$input = file_get_contents("php://input");
		$msg = strtolower($message->content);
		$args = explode(' ', $msg);
		//array_shift($args);

		file_put_contents($inputlogfile, $input.PHP_EOL, FILE_APPEND);
		file_put_contents($inputlogfile, $message->content .PHP_EOL, FILE_APPEND);
		
		//if (count($args) > 0) {
		$command = array_shift($args);
		if (isset($commands[$command])) {
			//array_shift($args);
			file_put_contents($inputlogfile, "Command: ".$command.", args: ".print_r($args, true) .PHP_EOL, FILE_APPEND);
			$commands[$command]->handle($message, $args);
		} else {
			//$embed = generateHelpCommand($discord, $commands);
			//$message->channel->sendEmbed($embed);
		}
		//} else {
		//	$commands['info']->handle($message, $args);
		//}
		//file_put_contents($inputlogfile, "Command: ".$command.", args: ".print_r($args, true) .PHP_EOL, FILE_APPEND);

		//file_put_contents($inputlogfile, PHP_EOL.'REQUEST:'.PHP_EOL, FILE_APPEND);
		//file_put_contents($inputlogfile, print_r($_REQUEST, true), FILE_APPEND);
		//file_put_contents($inputlogfile, PHP_EOL.'message:'.PHP_EOL, FILE_APPEND);
		//file_put_contents($inputlogfile, print_r($message, true), FILE_APPEND);
		switch ($command) {
		case '!joke':
			$browser->get('https://api.chucknorris.io/jokes/random')->then(
				function (ResponseInterface $response) use ($message) {
					$joke = json_decode($response->getBody())->value;
					$message->reply($joke);
				}
			);
			break;
		case '!ping':
			$message->reply('Pong!');
			break;
		case '!url':
			$message->reply("**Live:** https://kaaosradio.fi:8001/stream \n**Chiptune:** https://kaaosradio.fi:8001/chip \n**Chillout:** https://kaaosradio.fi:8001/chill \n**Stream2:** https://kaaosradio.fi:8001/stream2".
						"\n**Video:** https://videostream.kaaosradio.fi");
			break;
		case '!np chill':
			$browser->get('https://kaaosradio.fi/npfile_chill_tags')->then(
				function (ResponseInterface $response) use ($message) {
					$message->reply($response->getBody());
				}
			);
			break;
		case '!np chip':
			$browser->get('https://kaaosradio.fi/npfile_chip_tags')->then(
				function (ResponseInterface $response) use ($message) {
					$message->reply($response->getBody());
				}
			);
			break;
		case '!np':
		case '!np stream2':
			$browser->get('https://kaaosradio.fi/npfile_stream2_tags')->then(
				function (ResponseInterface $response) use ($message) {
					$message->reply($response->getBody());
				}
			);
			break;
		case '!nytsoi':
			$browser->get('https://kaaosradio.fi/nytsoi.txt')->then(
				function (ResponseInterface $response) use ($message) {
					$message->reply($response->getBody());
				}
			);
			break;

		case '!disconnect':
			$message->reply('Quitting! BYe!');
			$discord->close();
			break;
		case '!avatar':
			$url = $message->author->getAvatarAttribute('png', 2048);
			$message->reply($message->author->__toString(). ' avatar: '. $url);
			break;

		case '!server':
				//$message->reply('Tämän servun nimi on: '..');
			break;

		default:
				// code...
			break;
		}
		if (($message->channel_id == $_ENV['LEFFAHENKI_ID'] || $message->channel_id == $_ENV['BOTSPAM_ID']) && preg_match('/!imdb (.*)/', $msg, $matches)) {
			$keyword = $matches[1];
			//file_put_contents($inputlogfile, __LINE__.' searchword1: '.$keyword. PHP_EOL, FILE_APPEND);
			check_imdb($keyword, $message, $browser);
		}
	});
	$shell->setScope(get_defined_vars());
});

function check_imdb($keyword, $message, $browser) {

	$inputlogfile = dirname(__FILE__). '/logs/input.log';
	$type = '';

	if (preg_match('/( sarja)/i', $keyword, $results) || preg_match('/( series)/', $keyword, $results)) {
		$type = '&type=series';
		$keyword = str_replace($results[1], '', $keyword);
	} elseif (preg_match('/( episod[ie])/', $keyword, $results)) {
		$type = '&type=episode';
		$keyword = str_replace($results[1], '', $keyword);
	} elseif (preg_replace('/ season/', $keyword, $results)) {
		$type = '&Season=1';
		$keyword = str_replace($results[1], '', $keyword);
	
	} elseif (preg_match('/( leffa)/', $keyword, $results) || preg_match('/( movie)/', $keyword, $results) || preg_match('/( elokuva)/', $keyword, $results)) {
		$type = '&type=movie';
		$keyword = str_replace($results[1], '', $keyword);
	} else {
		//$type = 's';
	}

	if (preg_match('/\b(18|19|20)(\d{2})/', $keyword, $results)) {
		// year search
		$year = $results[1].$results[2];
		$type = "&y=${year}${type}";
		$keyword = str_replace($year, '', $keyword);
		file_put_contents($inputlogfile, __LINE__.': Year search: '.$year.', Keyword: '.$keyword. PHP_EOL, FILE_APPEND);
	}

	file_put_contents($inputlogfile, __LINE__.': keyword after: '.$keyword. PHP_EOL, FILE_APPEND);
	// OMDB API key
	$apikey = $_ENV['OMDB_APIKEY'];
	$keyword = trim($keyword);
	$apiurl = "https://www.omdbapi.com/?apikey=${apikey}${type}&t=${keyword}";
	file_put_contents($inputlogfile, __LINE__.' URL: '.$apiurl. PHP_EOL, FILE_APPEND);

	$browser->get($apiurl)->then(
		function (ResponseInterface $response) use ($message) {
			$inputlogfile2 = dirname(__FILE__). '/logs/input.json';
			$data = json_decode($response->getBody());
			file_put_contents($inputlogfile2, $response->getBody());
			if ($data->Response == 'True') {
				if (isset($data->totalResults)) {
					$count = $data->totalResults;
					if ($count > 1) {
						$message->reply('Löytyi '.$count. ' hakutulosta.');
					}
				}
				$actors = $data->Actors;
				$title = $data->Title;
				$ryear = $data->Year;
				$released = $data->Released;
				$runtime = $data->Runtime;
				$country = $data->Country;
				$genre = $data->Genre;
				$rating = $data->imdbRating;
				$url = 'https://imdb.com/title/'.$data->imdbID;
				//$response = '**'.$title.'** ('.$released.'), '.$runtime.', **Genre:** '.$genre.', '.**Actors:** '.$actors. ', '**URL:** '.$url;
				$response = '**'.$title.'** ('.$released.'), '.$runtime.', **Genre:** '.$genre.', **Rating:** '.$rating. ', **URL:** '.$url;
				$message->reply($response);
			}
		}
	);
}

$discord->run();
