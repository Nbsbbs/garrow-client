<?php

namespace Nbsbbs\Garrow;

use Exception;

class GarrowClient
{
    const SERVER_URL = "https://garrow.wasptube.com/garrow/router.php";
    const TYPE_ACTUAL = 'actual';
    const IPS_DUCKDUCKGO = [
        '23.21.227.69',
        '50.16.241.113',
        '50.16.241.114',
        '50.16.241.117',
        '50.16.247.234',
        '52.204.97.54',
        '52.5.190.19',
        '54.197.234.188',
        '54.208.100.253',
        '54.208.102.37',
        '107.21.1.8',
    ];
    public const BOT_GOOGLE = 1;
    public const BOT_DUCK_DUCK_GO = 2;
    public const BOT_YANDEX = 3;
    public const BOT_BAIDU = 4;
    public const BOT_SOGOU = 5;
    public const BOT_SOSOSPIDER = 6;
    public const BOT_BING = 7;
    public const BOT_SEZNAM = 8;
    public const BOT_EMULATION = 100;
    public const BOT_DEBUG = 101;
    public const BOTS_REPORT = [
        self::BOT_GOOGLE,
    ];

    private string $dataDir = "/tmp";

    private string $domain = "";

    private bool $emulation = false;

    private array $allLinks = [];

    private array $usedLinks = [];

    private array $unusedLinks = [];

    private bool $doAlwaysShowSomething = false;

    public const CACHE_LIFETIME_SEC = 600;

    protected int $bot = 0;

    /**
     * @param string $dataDir
     * @throws Exception
     */
    public function __construct(string $dataDir = "")
    {
        if (!empty($dataDir)) {
            $this->dataDir = $dataDir;
        }
        if (!is_writable($dataDir)) {
            throw new \Exception ("Path " . $this->dataDir . ", which is set as data dir, is not writeable");
        }
        $this->domain = str_replace("www.", "", strtolower($_SERVER['HTTP_HOST']));
    }

    /**
     * @param int $bot
     */
    protected function setBot(int $bot = null)
    {
        if ($bot) {
            $this->bot = $bot;
        }
    }

    public function setDoAlwaysShowSomething(): void
    {
        $this->doAlwaysShowSomething = true;
    }

    protected function getActualLinkState()
    {
        try {
            $allLinks = $this->getLinks();
        } catch (\Exception $e) {
            $allLinks = [];
        }
        try {
            $usedLinks = $this->getUsedLinks();
        } catch (\Exception $e) {
            $usedLinks = [];
        }
        $unusedLinks = [];

        $usedLinksIds = array_keys($usedLinks);
        foreach ($allLinks as $link) {
            if (!in_array($link['id'], $usedLinksIds)) {
                $unusedLinks[] = $link;
            }
        }

        $this->unusedLinks = $unusedLinks;
        $this->usedLinks = $usedLinks;
        $this->allLinks = $allLinks;
    }

    /**
     * @param bool $mode
     */
    public function setEmulation(bool $mode)
    {
        $this->setBot(self::BOT_EMULATION);
        $this->emulation = $mode;
    }

    /**
     * @return bool
     */
    protected function isGoodBot(): bool
    {
        return in_array($this->bot, self::BOTS_REPORT);
    }

    /**
     * @return bool
     */
    public function detectBot()
    {
        if ($this->emulation) {
            return true;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($this->isDebug((string) $userAgent)) {
            $this->setBot(self::BOT_DEBUG);
            return true;
        }

        if ($ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // bots use ipv4
            // continue checking
        } else {
            return false;
        }

        if ($this->isDuckDuckGoBot($userAgent, $ip)) {
            $this->setBot(self::BOT_DUCK_DUCK_GO);
            return true;
        }

        if ($this->isYandexBot($userAgent, $ip)) {
            $this->setBot(self::BOT_YANDEX);
            return true;
        }

        if ($this->isGoogleBot($userAgent, $ip)) {
            $this->setBot(self::BOT_GOOGLE);
            return true;
        }

        if ($this->isBaiduBot($userAgent, $ip)) {
            $this->setBot(self::BOT_BAIDU);
            return true;
        }

        if ($this->isSogouBot($userAgent, $ip)) {
            $this->setBot(self::BOT_SOGOU);
            return true;
        }

        if ($this->isSosospiderBot($userAgent, $ip)) {
            $this->setBot(self::BOT_SOSOSPIDER);
            return true;
        }

        if ($this->isBingBot($userAgent, $ip)) {
            $this->setBot(self::BOT_BING);
            return true;
        }

        if ($this->isSeznamBot($userAgent, $ip)) {
            $this->setBot(self::BOT_SEZNAM);
            return true;
        }

        return false;
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isDuckDuckGoBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#duckduckbot#si", $userAgent) and in_array($ip, self::IPS_DUCKDUCKGO)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @return bool
     */
    protected function isDebug(string $userAgent): bool
    {
        if (preg_match("#debug-Googlebot#s", $userAgent)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isSogouBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#Sogou Web#s", $userAgent)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isSosospiderBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#Sosospider#s", $userAgent)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isSeznamBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#SeznamBot#s", $userAgent)) {
            if ($name = gethostbyaddr($ip)) {
                if (preg_match("#seznam\.cz$#s", $name)) {
                    // perform back check
                    $newIp = gethostbyname($name);
                    if ($newIp == $ip) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isBingBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#bingbot#s", $userAgent)) {
            if ($name = gethostbyaddr($ip)) {
                if (preg_match("#search\.msn\.com$#s", $name)) {
                    // perform back check
                    $newIp = gethostbyname($name);
                    if ($newIp == $ip) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isBaiduBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#Baiduspider#s", $userAgent)) {
            if ($name = gethostbyaddr($ip)) {
                if (preg_match("#baidu\.com$#s", $name) or preg_match("#baidu\.jp#s", $name)) {
                    // perform back check
                    $newIp = gethostbyname($name);
                    if ($newIp == $ip) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isYandexBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#Yandex#s", $userAgent)) {
            if ($name = gethostbyaddr($ip)) {
                if (preg_match("#yandex\.com$#s", $name) or preg_match("#yandex\.ru#s", $name) or preg_match("#yandex\.net$#s", $name)) {
                    // perform back check
                    $newIp = gethostbyname($name);
                    if ($newIp == $ip) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * @param string $userAgent
     * @param string $ip
     * @return bool
     */
    protected function isGoogleBot(string $userAgent, string $ip): bool
    {
        if (preg_match("#Googlebot#s", $userAgent)) {
            if ($name = gethostbyaddr($ip)) {
                if (preg_match("#googlebot\.com$#s", $name) or preg_match("#\.google\.com$#s", $name) or preg_match("#search\.msn\.com$#s", $name)) {
                    // perform back check
                    $newIp = gethostbyname($name);
                    if ($newIp == $ip) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    protected function isNeedWork(): bool
    {
        if ($this->emulation) {
            return true;
        }

        return $this->detectBot();
    }

    public function work()
    {
        try {
            if ($this->detectBot()) {
                if ($link = $this->getActiveLink()) {
                    if (!$this->emulation and $link['type'] === self::TYPE_ACTUAL) {
                        $this->reportLinkSet($link['id'], (($_SERVER['HTTPS']) ? ("https://")
                                : ("http://")) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                    }
                    return $link;
                } else {
                    return [];
                }
            } else {
                return [];
            }
        } catch (Exception $e) {
            // тут какая-то неведомая херня, но мы постараемся её не показать дрону
            return [];
        }
    }

    /**
     * @return string
     */
    protected function currentUrl(): string
    {
        return (($_SERVER['HTTPS']) ? ("https://") : ("http://")) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * @param int $num
     * @return array
     * @throws \ErrorException
     */
    public function getManyNew(int $num = 5): array
    {
        if (!$this->isNeedWork()) {
            return [];
        }

        $links = $this->getLinksChunk($num);

        if ($links) {
            $this->reportSetLinks($links);
            $this->addUsedLinks($links);
        }
        return $links;
    }

    /**
     * @param int $num
     * @return array
     */
    public function getMany(int $num = 1): array
    {
        $result = [];
        if ($link = $this->work()) {
            $result[] = $link;
        }
        return $result;
    }

    /**
     * @param array $links
     * @return bool
     * @throws \ErrorException
     */
    protected function reportSetLinks($links)
    {
        if ($this->emulation) {
            return false;
        }

        if (!$this->isGoodBot()) {
            // do not report link if bot is not good enough
            return false;
        }

        $linksSet = array_filter($links, function ($link) {
            if ($link['type'] == self::TYPE_ACTUAL) {
                return $link;
            } else {
                return null;
            }
        });

        if (!$linksSet) {
            return false;
        }

        $linksIds = [];
        foreach ($linksSet as $link) {
            $linksIds[] = $link['id'];
        }

        $sendData = [
            'domain' => $this->domain,
            'setUrl' => $this->currentUrl(),
            'linkIds' => $linksIds,
            'botType' => $this->bot,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
        ];

        $ch = curl_init(self::SERVER_URL . "?act=reportsetmany");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($sendData));
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data = json_decode($data, 1)) {
            if ($data['status'] == true) {
                return true;
            } else {
                if ($data['code'] == "DOMAIN_NOT_EXISTS") {
                    $this->register();
                }
                return false;
            }
        } else {
            throw new \ErrorException("Curl error");
        }
    }

    /**
     * @param int $size
     * @return array
     */
    protected function getLinksChunk(int $size = 5): array
    {
        $this->getActualLinkState();
        $result = [];
        foreach ($this->unusedLinks as $link) {
            $link['type'] = self::TYPE_ACTUAL;
            $result[] = $link;
            if (sizeof($result) >= $size) {
                return $result;
            }
        }
        foreach ($this->usedLinks as $link) {
            $link['type'] = 'fillup';
            $result[] = $link;
            if (sizeof($result) >= $size) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * @return array
     * @throws \ErrorException
     */
    public function getActiveLink(): array
    {

        $links = $this->getLinks();

        try {
            $usedLinks = $this->getUsedLinks();
        } catch (Exception $e) {
            $usedLinks = [];
        }
        $usedLinkIds = array_keys($usedLinks);
        $size = sizeof($links);
        for ($i = 0; $i < $size; $i++) {
            if (!in_array($links[$i]['id'], $usedLinkIds)) {
                $usedLinks[$links[$i]['id']] = $links[$i];
                $usedLinks[$links[$i]['id']]['timestamp'] = time();
                $this->saveUsedLinks($usedLinks);
                $result = $links[$i];
                $result['type'] = self::TYPE_ACTUAL;
                return $result;
            }
        }

        if ($this->doAlwaysShowSomething) {
            // show already used link if none available
            if ($usedLinks) {
                $result = $usedLinks[array_rand($usedLinks)];
                $result['type'] = 'fillup';
                return $result;
            }
        }

        return [];
    }

    /**
     * @param string $domain
     */
    public function setDomain(string $domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return array
     * @throws \ErrorException
     */
    public function getLinks(): array
    {
        // если файла нет или он старый, получить заново очередь
        if (!file_exists($this->getQueuedLinksFilename()) or filectime($this->getQueuedLinksFilename()) < (time() - self::CACHE_LIFETIME_SEC)) {
            $links = $this->getQueuedLinks();

            if (is_array($links)) {
                $this->saveQueuedLinks($links);
            } else {
                $links = [];
            }
        } else {
            try {
                $links = json_decode(file_get_contents($this->getQueuedLinksFilename()), 1);
                if (!$links) {
                    $links = [];
                }
            } catch (\JsonException $e) {
                $links = [];
            }
        }

        return $links;
    }

    public function saveQueuedLinks($data)
    {
        file_put_contents($this->getQueuedLinksFilename(), json_encode($data));
        chmod($this->getQueuedLinksFilename(), 0666);
    }

    /**
     * @param $link_id
     * @param $set_url
     * @return bool
     * @throws \ErrorException
     */
    public function reportLinkSet($link_id, $set_url)
    {
        if (!$this->isGoodBot()) {
            // do not report link if bot is not good enough
            return false;
        }

        $ch = curl_init(self::SERVER_URL . "?act=reportset");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'domain' => $this->domain,
            "link_id" => $link_id,
            'bot_type' => $this->bot,
            "set_url" => $set_url,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
        ]);
        $data = curl_exec($ch);

        curl_close($ch);
        if ($data = json_decode($data, 1)) {
            if ($data['status'] == true) {
                return true;
            } else {
                if ($data['code'] == "DOMAIN_NOT_EXISTS") {
                    $this->register();
                }
                return false;
            }
        } else {
            throw new \ErrorException("Curl error");
        }
    }

    public function getQueuedLinks()
    {
        $ch = curl_init(self::SERVER_URL . "?act=getqueue");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['domain' => $this->domain]);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data = json_decode($data, 1)) {
            if ($data['status'] == true) {
                return $data['items'];
            } else {
                if ($data['code'] == "DOMAIN_NOT_EXISTS") {
                    $this->register();
                }
                return false;
            }
        } else {
            throw new \ErrorException("Curl error");
        }
    }

    /**
     * @return bool
     */
    public function isRegistered(): bool
    {
        return file_exists($this->dataDir . "/isregistered.flag");
    }

    /**
     * @return bool
     * @throws \ErrorException
     */
    public function register(): bool
    {
        $ch = curl_init(self::SERVER_URL . "?act=register");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['domain' => $this->domain]);
        $data = curl_exec($ch);
        curl_close($ch);

        if ($data = json_decode($data, 1)) {
            if (($data['status'] == true) or ($data['code'] == "ALREADY_EXISTS")) {
                file_put_contents($this->dataDir . "/isregistered.flag", 1);
                return true;
            } else {
                return false;
            }
        } else {
            throw new \ErrorException("Curl error");
        }
    }

    /**
     * @return string
     */
    protected function getUsedLinksFilename(): string
    {
        return $this->dataDir . "/usedlinks.{$this->domain}.dat";
    }

    /**
     * @return string
     */
    protected function getQueuedLinksFilename(): string
    {
        return $this->dataDir . "/queuedlinks.{$this->domain}.dat";
    }

    /**
     * @param array $data
     */
    public function saveUsedLinks($data)
    {
        if ($this->emulation) {
            return;
        }

        if (!$this->isGoodBot()) {
            // do not save link if bot is not good enough
            return;
        }

        $result = [];
        foreach ($data as $link_id => $value) {
            if ($value['timestamp'] >= time() - 600) {
                $result[$value['id']] = $value;
            }
        }

        file_put_contents($this->getUsedLinksFilename(), json_encode($result));
        chmod($this->getUsedLinksFilename(), 0666);
    }

    /**
     * @param array $links
     * @throws Exception
     */
    protected function addUsedLinks(array $links): void
    {
        $usedLinks = $this->getUsedLinks();
        foreach ($links as $link) {
            $usedLinks[$link['id']] = $link;
            $usedLinks[$link['id']]['timestamp'] = time();
        }
        $this->saveUsedLinks($usedLinks);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getUsedLinks(): array
    {
        $this->touchUsedLinksFile();
        if ($data = file_get_contents($this->getUsedLinksFilename())) {
            if ($data2 = json_decode($data, 1)) {
                return $data2;
            } else {
                throw new \Exception("Used links file corrupted: " . $data);
            }
        }
        $this->touchUsedLinksFile(true);
        return [];
    }

    /**
     * @param bool $reset
     */
    protected function touchUsedLinksFile(bool $reset = false)
    {
        if ($reset or !file_exists($this->getUsedLinksFilename())) {
            file_put_contents($this->getUsedLinksFilename(), json_encode([-1]));
        }
    }

    /**
     * @param bool $reset
     */
    protected function touchQueuedLinksFile(bool $reset = false)
    {
        if ($reset or !file_exists($this->getQueueddLinksFilename())) {
            file_put_contents($this->getQueueddLinksFilename(), json_encode([]));
        }
    }
}
