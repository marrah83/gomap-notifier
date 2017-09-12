<?php
/**
 * Sender class.
 */
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class Sender {
    // Configuration file name.
    private $configFile = 'config.json';

    // Configuration. (object)
    private $config;
    // Pokemon name list. (object)
    private $nameList;
    // Move list (array)
    private $moveList;
    // IV list (object).
    private $ivList;
    // Telegram Class.
    private $telegram;
    // Discord Class.
    private $discord;

    /** @var Geocoder $geocoder */
    private $geocoder;

    /**
     * Default constructor.
     */
    public function __construct()
    {
        // First get the configuration.
        $this->getConfiguration();

        // Get the pokemon name list.
        $this->getNameList();

        // Get the move list.
        $this->getMoveList();

        // Get the iv lists.
        $this->getIvLists();

        // Include TelegramBot autoloader.
        require __DIR__ . '/php-telegram-bot/vendor/autoload.php';

        // Get instance of telegram class.
        $this->telegram = new Telegram($this->config->telegram->apiKey, $this->config->telegram->botName);

        // Include discord class.
        require_once('Discord.class.php');

        // Get instance of discord class.
        $this->discord = new Discord();

        // Include geocoder class.
        require_once('Geocoder.class.php');

        // Get instance of geocoder class.
        $this->geocoder = new Geocoder();
    }

    /**
     * Get configuration.
     */
    private function getConfiguration()
    {
        // Get data from json file and set var.
        $this->config = json_decode(file_get_contents($this->configFile));
    }

    /**
     * Get pokemon name list.
     */
    private function getNameList()
    {
        // Get data from json file and set var.
        $this->nameList = json_decode(file_get_contents($this->config->file->nameList));
    }

    /**
     * Get move list.
     */
    private function getMoveList()
    {
        // Get data from json file and set var.
        $this->moveList = json_decode(file_get_contents($this->config->file->moveList));
    }

    /**
     * Get IV lists from json files and store them.
     */
    private function getIvLists()
    {
        // Init empty iv list object.
        $this->ivList = new stdClass();

        // IV list found in configuration.
        if (!empty($this->config->channel->ivList)) {
            // IV list is an array.
            if (is_array($this->config->channel->ivList)) {
                // Check each IV list.
                foreach ($this->config->channel->ivList AS $list) {
                    // IV list is active.
                    if (isset($list->active) && $list->active === true) {
                        // Required fields are found.
                        if (!empty($list->name) && !empty($list->fileName)) {
                            // Get json file.
                            $json = json_decode(file_get_contents($list->fileName));
                            // Valid data loaded.
                            if (!empty($json)) {
                                // Store json in IV list object.
                                $this->ivList->{$list->name} = $json;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get move name.
     * @param $moveId
     * @return mixed
     */
    private function getMoveName($moveId)
    {
        if ($moveId > 137) {
            return $this->moveList[$moveId - 62];
        } else {
            return $this->moveList[$moveId];
        }
    }

    /**
     * Get raid channel Ids.
     * @param $gym
     * @return object
     */
    public function getRaidChannelIds($gym)
    {
        // Init empty chat id object.
        $chatIds = new stdClass();
        $chatIds->telegram = array();
        $chatIds->discord = array();

        if (!empty($gym)) {
            // A raid check is configured.
            if (!empty($this->config->channel->raid)) {
                // Each raid check.
                foreach ($this->config->channel->raid AS $raid) {
                    // Raid check is active.
                    if ($raid->active === true) {
                        // Check if the raid is within given radius.
                        if ($this->withinRadius($gym, $this->config->raids->latitude, $this->config->raids->longitude, $this->config->raids->radiusKm)) {

                            // Telegram messages.
                            if ($this->config->telegram->active === true) {
                                // Get chat id for hometown.
                                if (!empty($raid->telegram->{$this->config->map->homeTown})) {
                                    array_push($chatIds->telegram, $raid->telegram->{$this->config->map->homeTown});
                                }
                            }

                            // Discord messages.
                            if ($this->config->discord->active === true) {
                                // Get chat id for hometown.
                                if (!empty($raid->discord->{$this->config->map->homeTown})) {
                                    array_push($chatIds->discord, $raid->discord->{$this->config->map->homeTown});
                                }
                            }
                        }
                    }
                }
            }
        }

        return $chatIds;
    }

    /**
     * Get mon channel Ids.
     * @param $mon
     * @return object
     */
    public function getMonChannelIds($mon)
    {
        // Init empty chat id object.
        $chatIds = new stdClass();
        $chatIds->telegram = array();
        $chatIds->discord = array();

        if (!empty($mon)) {
            // A IV list check is configured.
            if (!empty($this->config->channel->ivList)) {
                // Each IV list check.
                foreach ($this->config->channel->ivList AS $listChannel) {
                    // IV list check is active and pokemon has high IV.
                    if ($listChannel->active === true && $this->checkForIvList($listChannel->name, $mon)) {

                        // Check if pokemon belongs to a district.
                        $district = $this->checkDistrict($mon);

                        // Telegram messages.
                        if ($this->config->telegram->active === true) {
                            // Get chat id for district/hometown.
                            if (!empty($listChannel->telegram->{$district})) {
                                array_push($chatIds->telegram, $listChannel->telegram->{$district});
                            }
                        }

                        // Discord messages.
                        if ($this->config->discord->active === true) {
                            // Get chat id for district/hometown.
                            if (!empty($listChannel->discord->{$district})) {
                                array_push($chatIds->discord, $listChannel->discord->{$district});
                            }
                        }
                    }
                }
            }

            // A IV check is configured.
            if (!empty($this->config->channel->iv)) {
                // Each IV check.
                foreach ($this->config->channel->iv AS $ivChannel) {
                    // IV check is active and pokemon has high IV.
                    if ($ivChannel->active === true && $this->checkForIv($ivChannel->value, $mon)) {

                        // Check if pokemon belongs to a district.
                        $district = $this->checkDistrict($mon);

                        // Telegram messages.
                        if ($this->config->telegram->active === true) {
                            // Get chat id for district/hometown.
                            if (!empty($ivChannel->telegram->{$district})) {
                                array_push($chatIds->telegram, $ivChannel->telegram->{$district});
                            }
                        }

                        // Discord messages.
                        if ($this->config->discord->active === true) {
                            // Get chat id for district/hometown.
                            if (!empty($ivChannel->discord->{$district})) {
                                array_push($chatIds->discord, $ivChannel->discord->{$district});
                            }
                        }
                    }
                }
            }

            // A pokemonId check is configured.
            if (!empty($this->config->channel->pokemonId)) {
                // Each pokemonId check.
                foreach ($this->config->channel->pokemonId AS $idChannel) {
                    // pokemonId check is active and pokemonId is matching.
                    if ($idChannel->active === true && $this->checkForPokemonId($idChannel->ids, $mon)) {

                        // Check if pokemon belongs to a district.
                        $district = $this->checkDistrict($mon);

                        // Telegram messages.
                        if ($this->config->telegram->active === true) {
                            // Get chat id for district/hometown.
                            if (!empty($idChannel->telegram->{$district})) {
                                array_push($chatIds->telegram, $idChannel->telegram->{$district});
                            }
                        }

                        // Discord messages.
                        if ($this->config->discord->active === true) {
                            // Get chat id for district/hometown.
                            if (!empty($idChannel->discord->{$district})) {
                                array_push($chatIds->discord, $idChannel->discord->{$district});
                            }
                        }
                    }
                }
            }
        }

        return $chatIds;
    }

    /**
     * Check district.
     * @param $mon
     */
    private function checkDistrict($mon)
    {
        // Check for any district.
        if (!empty($this->config->map->districts)) {
            // Now check each district.
            foreach ($this->config->map->districts AS $district) {
                // Active district found.
                if ($district->active === true) {
                    // Check if the mon is within districts radius.
                    $check = $this->withinRadius($mon, $district->latitude, $district->longitude, $district->radiusKm);
                    // Return districts name on first hit.
                    if ($check === true) {
                        return $district->name;
                    }
                }
            }
        }
        // Return hometown name if not within any separate district.
        return $this->config->map->homeTown;
    }

    /**
     * Check if pokemons IV is high enough.
     * @param $listName
     * @param $mon
     * @return bool
     */
    private function checkForIvList($listName, $mon)
    {
        // Init check.
        $check = false;

        // List is required.
        if (!empty($this->ivList) && !empty($this->ivList->{$listName})) {
            // Get selected list data.
            $list = $this->ivList->{$listName};
            // Pokemon id found in IV list.
            if (!empty($list->{$mon->pokemon_id})) {
                // IV value found.
                if (!empty($mon->iv)) {
                    // IV is higher than min.
                    if ($mon->iv >= $list->{$mon->pokemon_id}->minIv) {
                        $check = true;
                    }

                // No IV value found.
                } else {
                    // Display only zero min IV values.
                    If ($list->{$mon->pokemon_id}->minIv == 0) {
                        $check = true;
                    }
                }
            }
        }

        return $check;
    }

    /**
     * Check if a pokemon has a minimum IV value.
     * @param $iv
     * @param $mon
     * @return bool
     */
    private function checkForIv($iv, $mon)
    {
        return !empty($mon->iv) && $mon->iv >= $iv ? true : false;
    }

    /**
     * Check for presence of pokemon Id.
     * @param $ids mixed
     * @param $mon
     * @return bool
     */
    private function checkForPokemonId($ids, $mon)
    {
        // Init check.
        $check = false;

        // Pokemon id is required.
        if (!empty($mon->pokemon_id)) {
            // Check for array of ids.
            if (is_array($ids)) {
                foreach ($ids AS $id) {
                    // Ids are matching.
                    if ($mon->pokemon_id == $id) {
                        $check = true;
                    }
                }

            // Single id.
            } else {
                $id = $ids;
                // Ids are matching.
                if ($mon->pokemon_id == $id) {
                    $check = true;
                }
            }
        }

        return $check;
    }

    /**
     * Build raid telegram message string.
     * @param $raid
     * @return string
     */
    public function buildRaidMessage($raid)
    {
        // Init.
        $message = '';
        $team = '';

        // Raid data is required.
        if (!empty($raid)) {
            // Check required raid data.
            if (!empty($raid->name) && !empty($raid->latitude) && !empty($raid->longitude) && !empty($raid->rb) && !empty($raid->re) && !empty($raid->rpid)) {

                // Get boss name.
                $raid->bossName = $this->nameList->{$raid->rpid};

                // Get times.
                $raid->begin    = date("H:i", $raid->rb);
                $raid->end      = date("H:i", $raid->re);

                // Team id is set.
                if (isset($raid->team_id)) {
                    // Get team by id.
                    switch ($raid->team_id) {
                        case(1):
                            $team = ' (blau)';
                            break;

                        case(2):
                            $team = ' (rot)';
                            break;

                        case(3):
                            $team = ' (gelb)';
                            break;

                        default:
                            $team = '';
                    }
                }

                // Get location array by lat / lng.
                $locArray = $this->geocoder->getLocation($raid->latitude, $raid->longitude);

                // Location is required.
                if (!empty($locArray)) {
                    $message .= sprintf("<b>%s</b>, ", $raid->bossName);
                    $message .= (!empty($locArray['district']) ? $locArray['district'] : '') . (!empty($locArray['street']) ? ", " . $locArray['street'] : "") . ", ";
                    $message .= "von " . $raid->begin . " bis " . $raid->end . ".\n";
                    $message .= sprintf("Arena: <i>%s%s</i>\n", $raid->name, $team);
                    $message .= 'http://maps.google.com/maps?q=' . $raid->latitude . ',' . $raid->longitude;
                }
            }
        }

        // Return message.
        return $message;
    }

    /**
     * Build mon telegram message string.
     * @param $spawn object
     * @return string
     */
    public function buildMonMessage($spawn)
    {
        // Init.
        $message = '';

        // Spawn data is required.
        if (!empty($spawn)) {
            // Transform disappear time.
            if (!empty($spawn->disappear_time)) {
                $time = date('H:i', $spawn->disappear_time);
                $minutes = round(($spawn->disappear_time - time()) / 60);
                $spawn->disappear_time = $time . ' (noch ' . $minutes . ' Minuten)';
            } else {
                // Don't report mons without disappear time.
                return $message;
            }

            // Minimum stay time is 5 minutes.
            if ($minutes >= 5) {
                // Mon should be known.
                if (!empty($spawn->pokemon_id) && !empty($this->nameList) && !empty($this->nameList->{$spawn->pokemon_id})) {
                    // Get pokemon name by id.
                    $spawn->pokemon_name = $this->nameList->{$spawn->pokemon_id};

                    // Get location array by lat / lng.
                    $locArray = $this->geocoder->getLocation($spawn->latitude, $spawn->longitude);

                    // Location is required.
                    if (!empty($locArray)) {
                        $message .= sprintf("<b>%s</b> ", $spawn->pokemon_name);
                        // Add IV text to message if existent.
                        if (!empty($spawn->iv)) {
                            // Build message.
                            $message .= sprintf("(%s%%) ", $spawn->iv);
                        }
                        // Add move text to message if existent.
                        if (!empty($spawn->move1) && !empty($spawn->move2)) {
                            // Build message.
                            $message .= sprintf("mit <i>%s / %s</i> ", $this->getMoveName($spawn->move1), $this->getMoveName($spawn->move2));
                        }
                        $message .= "in " . (!empty($locArray['district']) ? $locArray['district'] : '') . (!empty($locArray['street']) ? ", " . $locArray['street'] : "") . " ";
                        $message .= "bis " . $spawn->disappear_time . ".\n";
                        $message .= 'http://maps.google.com/maps?q=' . $spawn->latitude . ',' . $spawn->longitude;
                    }
                }
            }
        }

        // Return message.
        return $message;
    }

    /**
     * Send telegram.
     * @param $data
     * @param $message
     * @param $chatId
     */
    public function sendTelegram($data, $message, $chatId)
    {
	// Send telegram location.
        $this->sendTelegramLocation($chatId, $data);

        // Send telegram message.
        $this->sendTelegramMessage($chatId, $message);

        // We need to sleep for 1 second.
        sleep(1);
    }

    /**
     * Send telegram message.
     * @param $chatId
     * @param $message
     * @return mixed
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function sendTelegramMessage($chatId, $message)
    {
        // Init.
        $result = false;

        if (!empty($chatId) && !empty($message)) {
            $result = Request::sendMessage([
                'chat_id'       => $chatId,
                'text'          => $message,
                'parse_mode'    => 'HTML'
            ]);
        }

        // Return result.
        return $result;
    }

    /**
     * Send google maps location.
     * @param $chatId
     * @param $data
     * @return mixed
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function sendTelegramLocation($chatId, $data)
    {
        // Init.
        $result = false;

        if (!empty($data) && !empty($chatId)) {
            $result = Request::sendLocation([
                'chat_id'   => $chatId,
                'latitude'  => $data->latitude,
                'longitude' => $data->longitude,
            ]);
        }

        // Return result.
        return $result;
    }

    /**
     * Send discord message.
     * @param $message
     * @param $channelId
     */
    public function sendDiscord($message, $channelId) {
        // Build url.
        $url = $this->config->discord->webhook . $channelId;
        // Set url.
        $this->discord->url($url);
        // Set bot name.
        $this->discord->name($this->config->discord->botName);
        // Set bot avatar.
        $this->discord->avatar($this->config->discord->avatar);
        // Set message.
        $this->discord->message($message);
        // Send to channel.
        $this->discord->send();
    }

    /**
     * Check if a mon is within a radius.
     * @param $mon (object)
     * @param $latitude (string)
     * @param $longitude (string)
     * @param $radiusKm (int)
     * @return bool
     */
    private function withinRadius($mon, $latitude, $longitude, $radiusKm)
    {
        //
        $distance = $this->getDistance($mon->latitude, $mon->longitude, $latitude, $longitude);

        // Check if distance is within radius.
        return $distance < $radiusKm ? true : false;
    }

    /**
     * Get distance between two sets of longitude and latitude.
     * @param $latitude1
     * @param $longitude1
     * @param $latitude2
     * @param $longitude2
     * @return int
     */
    private function getDistance($latitude1, $longitude1, $latitude2, $longitude2)
    {
        $earth_radius = 6371;

        $dLat = deg2rad($latitude2 - $latitude1);
        $dLon = deg2rad($longitude2 - $longitude1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * asin(sqrt($a));
        $d = $earth_radius * $c;

        return $d;
    }
	
    /**
     * Build raid bot POST request string to mimic inline command to avoid bot2bot communication
     * @param $raid object
     * @return string
     */
	public function sendRaidBotMessage($raid)
    {
        // Init result.
        $result = '';

        // Raid bot is activated.
        if (isset($this->config->raidBot->active) && $this->config->raidBot->active === true) {
            // Raid data is required.
            if (!empty($raid)) {
                // Check required raid data.
                if (!empty($raid->name) && !empty($raid->latitude) && !empty($raid->longitude) && !empty($raid->re) && !empty($raid->rpid)) {
                    // Check if the raid is within given radius.
                    if ($this->withinRadius($raid, $this->config->raids->latitude, $this->config->raids->longitude, $this->config->raids->radiusKm)) {

                        // Get location array by lat / lng.
                        $locArray = $this->geocoder->getLocation($raid->latitude, $raid->longitude);

                        // Location is required.
                        if (!empty($locArray) && !empty($locArray['district']) && !empty($locArray['district'])) {

                            // Check district to get the channelId.
                            if (!empty($this->config->channel->raidBot->{$locArray['district']})) {

                                // Get the boss name.
                                $raid->bossName = $this->nameList->{$raid->rpid};

                                // Get minutes left.
                                $minutes = intval(($raid->re - time()) / 60);

                                // Team id found.
                                if (!empty($raid->team_id)) {
                                    // Switch by team id.
                                    switch ($raid->team_id) {
                                        case(1):
                                            $team = 'valor';
                                            break;
                                        case(2):
                                            $team = 'mystic';
                                            break;
                                        case(3):
                                            $team = 'instinct';
                                            break;
                                        default:
                                            $team = '';
                                    }

                                // Team id is missing.
                                } else {
                                    $team = '';
                                }

                                // Build message array.
                                $message = array(
                                    'message' => array(
                                        'chat' => array(
                                            'id' => $this->config->channel->raidBot->{$locArray['district']},
                                            'type' => $this->config->raidBot->chatType
                                        ),
                                        'from' => array(
                                            'id' => $this->config->raidBot->from,
                                            'last_name' => $this->config->raidBot->lastName,
                                            'first_name' => $this->config->raidBot->firstName
                                        ),
                                        'text' => '/raid ' . $raid->bossName . ',' . $raid->latitude . ',' . $raid->longitude . ',' . $minutes . ',' . $team . ',' . str_replace(',','|',$raid->name) . ',' . $locArray['district'] . ',' . $locArray['street']
                                    )
                                );

                                // Create json string.
                                $postFields = json_encode($message);

                                // Send data by curl.
                                $result = $this->curl($this->config->raidBot->url, $postFields);
                            }
                        }
                    }
                }
            }
        }

        // Return message.
        return json_decode($result);
    }

    /**
     * Send data by curl.
     * @param $url string
     * @param $postFields string
     * @return string
     */
    private function curl($url, $postFields)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_HEADER, 0); // Don't return headers.
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }
}