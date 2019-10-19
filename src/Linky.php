<?php

namespace NDC\Linky;

use DateTime;
use DateTimeZone;
use Dotenv\Dotenv;
use Exception;
use RuntimeException;
use stdClass;

/**
 * Class Linky
 *
 * @package NDC\Linky
 */
class Linky
{
    /**
     * @var string
     */
    private $_login;
    /**
     * @var string
     */
    private $_password;
    /**
     * @var false|resource
     */
    private $_client;

    private $_authenticated;
    /**
     * @var DateTimeZone
     */
    private $_DTZone;

    private const _APILoginBaseUrl = 'https://espace-client-connexion.enedis.fr';
    private const _APIBaseUrl = 'https://espace-client-particuliers.enedis.fr/group/espace-particuliers';
    private const _APILoginEP = '/auth/UI/Login';
    private const _APIDataEP = '/suivi-de-consommation';
    private const _cookiesFile = __DIR__.'/EnedisCookiesJar';
    private const _timezone = 'Europe/Paris';
    public const DATA_TYPE_HOURLY = 'urlCdcHeure';
    public const DATA_TYPE_DAILY = 'urlCdcJour';
    public const DATA_TYPE_MONTHLY = 'urlCdcMois';
    public const DATA_TYPE_ANNUALLY = 'urlCdcAn';
    public const ENERGY_METRIC = 'kW';

    /**
     * LinkyV2 constructor.
     */
    public function __construct()
    {
        Dotenv::create(dirname(__DIR__))->load();
        $this->_login = getenv('UTILISATEUR_ENEDIS');
        $this->_password = getenv('MOTDEPASSE_ENEDIS');
        date_default_timezone_set(self::_timezone);
        $this->_DTZone = new DateTimeZone(self::_timezone);
        if (file_exists(self::_cookiesFile)) {
            unlink(self::_cookiesFile);
        }
        $this->_authenticate();
    }

    /**
     * @return bool
     */
    private function _authenticate(): bool
    {
        $this->_authenticated = false;
        $postdata = [
            'IDToken1' => $this->_login,
            'IDToken2' => $this->_password,
            'SunQueryParamsString' => base64_encode('realm=particuliers'),
            'encoded' => 'true',
            'gx_charset' => 'UTF-8'
        ];
        $url = self::_APILoginBaseUrl.self::_APILoginEP;
        $response = $this->_request('POST', $url, $postdata);
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        $cookies = [];
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        if (!array_key_exists('iPlanetDirectoryPro', $cookies)) {
            throw new RuntimeException('Sorry, could not connect. Check your credentials.');
        }
        $this->_authenticated = true;
        $refreshCookie
            = 'https://espace-client-particuliers.enedis.fr/group/espace-particuliers/accueil';
        $this->_request('GET', $refreshCookie);
        return true;
    }

    /**
     * @param  string         $resource
     * @param  DateTime|null  $startDate
     * @param  DateTime|null  $endDate
     *
     * @return object
     */
    private function _getData(
        string $resource,
        ?DateTime $startDate,
        ?DateTime $endDate
    ): object {
        $p_p_id = 'lincspartdisplaycdc_WAR_lincspartcdcportlet';
        $url = self::_APIBaseUrl.self::_APIDataEP;
        $url .= '?p_p_id='.trim($p_p_id);
        $url .= '&p_p_lifecycle=2';
        $url .= '&p_p_mode=view';
        $url .= '&p_p_resource_id='.trim($resource);
        $url .= '&p_p_cacheability=cacheLevelPage';
        $url .= '&p_p_col_id=column-1';
        $url .= '&p_p_col_count=2';
        $postdata = [];
        if ($startDate && $endDate && $resource !== self::DATA_TYPE_ANNUALLY) {
            $postdata = [
                '_'.$p_p_id.'_dateDebut' => $startDate->format('d/m/Y'),
                '_'.$p_p_id.'_dateFin' => $endDate->format('d/m/Y')
            ];
        }
        $response = $this->_request('GET', $url, $postdata);
        return json_decode($response, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  string  $method
     * @param  string  $url
     * @param  array   $postdata
     *
     * @return mixed
     */
    private function _request(string $method, string $url, array $postdata = [])
    {
        if (!isset($this->_client)) {
            $this->_client = curl_init();
            curl_setopt($this->_client, CURLOPT_COOKIEJAR, self::_cookiesFile);
            curl_setopt($this->_client, CURLOPT_COOKIEFILE, self::_cookiesFile);
            curl_setopt($this->_client, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($this->_client, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->_client, CURLOPT_HEADER, true);
            curl_setopt($this->_client, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->_client, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($this->_client, CURLOPT_MAXREDIRS, 20);
            curl_setopt(
                $this->_client,
                CURLOPT_USERAGENT,
                'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36'
            );
        }
        $url = filter_var($url, FILTER_SANITIZE_URL);
        curl_setopt($this->_client, CURLOPT_URL, $url);
        if ($method === 'POST') {
            curl_setopt($this->_client, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->_client, CURLOPT_POST, true);
        } else {
            curl_setopt($this->_client, CURLOPT_POST, false);
        }
        if (!empty($postdata)) {
            curl_setopt($this->_client, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($this->_client, CURLOPT_POSTFIELDS,
                http_build_query($postdata));
        }
        $response = curl_exec($this->_client);
        if (!$response) {
            throw new RuntimeException(curl_error($this->_client));
        }
        if ($this->_authenticated) {
            $header_size = curl_getinfo($this->_client, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $response = substr($response, $header_size);
        }
        return $response;
    }

    /**
     * @return stdClass
     * @throws Exception
     */
    final public function getDataPerYears(): stdClass
    {
        $result = $this->_getData(self::DATA_TYPE_ANNUALLY, null, null);
        $dt = new DateTime('now', $this->_DTZone);
        $returnData = new stdClass();
        $data = $result->graphe->data;
        $c = count($data) - 1;
        $dt->modify('- '.$c.' year');
        foreach ($data as $key => $year) {
            if ($key < $result->graphe->decalage) {
                continue;
            }
            $currentYear = $dt->format('Y');
            $returnData->data[$currentYear] = $this->_formatReturn($year,
                $year->valeur !== -1);
            $dt->modify('+1 year');
        }
        return $returnData;
    }

    /**
     * @param  string  $year
     *
     * @return stdClass
     * @throws Exception
     */
    final public function getDataPerMonths(
        string $year
    ): stdClass {
        if ($year > date('Y')) {
            throw new RuntimeException('The year entered is invalid. It must be less than or equal to '
                .date('Y').'.');
        }
        $startDate = new DateTime($year.'/01/01', $this->_DTZone);
        $endDate = new DateTime($year.'/12/31', $this->_DTZone);
        $result = $this->_getData(self::DATA_TYPE_MONTHLY,
            $startDate, $endDate);
        $returnData = new stdClass();
        $data = $result->graphe->data;
        foreach ($data as $key => $month) {
            if ($key < $result->graphe->decalage
                || ($month->ordre - $result->graphe->decalage)
                > $endDate->format('m')
                || $key > date('m')
            ) {
                continue;
            }
            $currentMonth = $startDate->format('m/Y');
            $returnData->data[$currentMonth] = $this->_formatReturn($month,
                $month->ordre < date('m'));
            $startDate->modify('+1 month');
        }
        return $returnData;
    }

    /**
     * @param  string  $month
     * @param  string  $year
     *
     * @return stdClass
     * @throws Exception
     */
    final public function getDataPerDays(
        string $month = '08',
        string $year = '2019'
    ): stdClass {
        if (!checkdate($month, 30, $year)) {
            throw new RuntimeException('The month or year filled in is invalid.');
        }
        if ($year > date('Y')) {
            throw new RuntimeException('The year entered is invalid. It must be less than or equal to '
                .date('Y').'.');
        }
        if ((new DateTime($year.'/'.$month.'/01', $this->_DTZone))
            > new DateTime('now', $this->_DTZone)
        ) {
            throw new RuntimeException('The combo of month/year filled is greater than the current date. It must be less than or equal to '
                .date('m/Y').'.');
        }
        $startDate = new DateTime($year.'/'.$month.'/01', $this->_DTZone);
        $endDate = (clone $startDate)->modify('last day of this month');
        $result = $this->_getData(self::DATA_TYPE_DAILY,
            $startDate, $endDate);
        $returnData = new stdClass();
        $data = $result->graphe->data;
        foreach ($data as $key => $day) {
            if ($key < $result->graphe->decalage || $key > 29) {
                continue;
            }
            $currentDay = $startDate->format('d/m/Y');
            $returnData->data[$currentDay] = $this->_formatReturn($day,
                $day->valeur !== -1);
            $startDate->modify('+1 day');
        }
        return $returnData;
    }

    /**
     * @param  string  $day
     * @param  string  $month
     * @param  string  $year
     *
     * @return stdClass
     * @throws Exception
     */
    final public function getDataPerHours(
        string $day = '01',
        string $month = '08',
        string $year = '2019'
    ): stdClass {
        if (!checkdate($month, $day, $year)) {
            throw new RuntimeException('The date filled in is invalid.');
        }
        if ($year > date('Y')) {
            throw new RuntimeException('The year entered is invalid. It must be less than or equal to '
                .date('Y').'.');
        }
        if ((new DateTime($year.'/'.$month.'/'.$day, $this->_DTZone))
            > (new DateTime('now',
                $this->_DTZone))->sub(new \DateInterval('P1D'))
        ) {
            throw new RuntimeException('The combo of day/month/year filled is greater than the current date. It must be less than to '
                .date('d/m/Y').'.');
        }
        $startDate = new DateTime($year.'/'.$month.'/'.$day, $this->_DTZone);
        $endDate = (clone $startDate)->add(new \DateInterval('P1D'));
        $result = $this->_getData(self::DATA_TYPE_HOURLY,
            $startDate, $endDate);
        $returnData = new stdClass();
        $startHour = new DateTime('00:00');
        $data = $result->graphe->data;
        foreach ($data as $key => $hour) {
            if ($key < $result->graphe->decalage) {
                continue;
            }
            $currentHour = $startHour->format('H:i');
            $returnData->data[$currentHour] = $this->_formatReturn($hour,
                $hour->valeur !== -1);
            $startHour->modify('+30 mins');
        }
        return $returnData;
    }

    /**
     * @param  object  $data
     * @param  bool    $completed
     *
     * @return object
     */
    private function _formatReturn(object $data, bool $completed): object
    {
        $values = (object)[
            'completed' => $completed,
            'metric' => self::ENERGY_METRIC,
            'value' => $data->valeur === -1 || $data->valeur === -2 ? null
                : $data->valeur
        ];
        return $values;
    }
}
