<?php

namespace Lib\Extend;

use Psr\Log\LoggerInterface;
use Facebook\WebDriver\Cookie;
use League\Flysystem\Exception;
use Facebook\WebDriver\WebDriverBy;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Process\Process;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class CrawlerDriver
{
    protected $driver;
    protected $cookies;
    protected $cache;
    protected $cookiePath = 'cookies_selenium';
    protected $history = [];
    protected $process = [];

    public function __construct($config = array())
    {
        //缓存驱动
        $app = app();
        $this->cache = $app->resolve(CacheInterface::class);
        $this->log = $app->resolve(LoggerInterface::class);
        // start Chrome with 5 second timeout
        $host = 'http://127.0.0.1:4444/wd/hub'; // this is the default
        $capabilities = DesiredCapabilities::chrome();
        $this->driver = RemoteWebDriver::create($host, $capabilities, 5000);
    }

    public function __destruct()
    {
        $this->driver->quit();
    }

    /**
     * 检查是否需要登陆
     *
     * @return void
     */
    public function checkLogin()
    {
        return $this->cache->has($this->cookiePath);
    }

    /**
     * 获取当前页面标题
     *
     * @return void
     */
    public function getTitle()
    {
        return $this->driver->getTitle();
    }

    /**
     * 获取当前页面地址
     *
     * @return void
     */
    public function getCurrentURL()
    {
        return $this->driver->getCurrentURL();
    }
    
    /**
     * 刷新当前页面
     *
     * @return void
     */
    public function refresh()
    {
        return $this->driver->navigate()->refresh();
    }

    protected function get($url)
    {
        $this->driver->get($url);
        $this->history[] = $url;
    }

    /**
     * 初始化cookies
     * @return [type] [description]
     */
    protected function initCookies()
    {
        $cookies = $this->cache->get($this->cookiePath, []);
        foreach ($cookies as $cookie) {
            $_cookie = Cookie::createFromArray($cookie);
            $this->driver->manage()->addCookie($_cookie);
        }
    }

    /**
     * 保存cookies
     * @return [type] [description]
     */
    protected function saveCookies()
    {
        $cookies = $this->driver->manage()->getCookies();
        $_cookies = [];
        foreach ($cookies as $cookie) {
            $_cookies[] = $cookie->toArray();
        }
        return $this->cache->set($this->cookiePath, $_cookies);//, 3600
    }

    /**
     * 删除cookies
     * @return [type] [description]
     */
    protected function removeCookies()
    {
        return $this->cache->delete($this->cookiePath);
    }

    /**
     * 等待ajax
     *
     * @param WebDriver $driver
     * @param string $framework
     * @param integer $timeout
     * @return void
     */
    protected function waitForAjax($driver, $framework='jquery', $timeout = 30)
    {
        // javascript framework
        switch($framework){
            case 'jquery':
                $code = "return jQuery.active;"; break;
            case 'prototype':
                $code = "return Ajax.activeRequestCount;"; break;
            case 'dojo':
                $code = "return dojo.io.XMLHTTPTransport.inFlight.length;"; break;
            default:
                throw new Exception('Not supported framework');
        }
        $driver->wait($timeout)->until(
            function () use ($driver, $code) {
                return !$driver->executeScript($code);
            }
        );
    }

    /**
     * 异步发送邮件
     *
     * @param  string $subject 标题
     * @param  string $content 内容
     * @return void
     */
    protected function asyncMail($subject, $content = '')
    {
        $process = new Process('php '.ROOT_PATH.'cli.php command mail to='.$this->config['to'].' subject='.$subject.' content='.($content ?: $this->driver->getCurrentURL()));
        $process->start();
        $this->process[] = $process;
    }
}