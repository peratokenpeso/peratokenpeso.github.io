<?php
class JConfig
{
	public $offline = '0';
	public $offline_message = 'This site is down for maintenance.<br />Please check back again soon.';
	public $display_offline_message = '1';
	public $offline_image = '';
	public $sitename = 'PESO - Peratoken';
	public $editor = 'tinymce';
	public $captcha = '0';
	public $list_limit = '20';
	public $access = '1';
	public $debug = '0';
	public $debug_lang = '0';
	public $debug_lang_const = '1';
	public $dbtype = 'pdomysql';
	public $host = 'localhost';
	public $user = 'root';
	public $password = '';
	public $db = 'peratokenpeso_db';
	public $dbprefix = 'joomla_';
	public $live_site = '';
	public $secret = 'D3C2u2y0XFn6tXvU';
	public $gzip = '0';
	public $error_reporting = 'development';
	public $helpurl = 'https://help.joomla.org/proxy?keyref=Help{major}{minor}:{keyref}&lang={langcode}';
	public $ftp_host = '';
	public $ftp_port = '';
	public $ftp_user = '';
	public $ftp_pass = '';
	public $ftp_root = '';
	public $ftp_enable = '0';
	public $offset = 'Asia/Manila';
	public $mailonline = '1';
	public $mailer = 'smtp';
	public $mailfrom = 'admin@pvbi.org';
	public $fromname = 'PESO - Peratoken';
	public $sendmail = '/usr/sbin/sendmail';
	public $smtpauth = '1';
	public $smtpuser = 'admin@pvbi.org';
	public $smtppass = 'PvBi_org@2023';
	public $smtphost = 'smtp.hostinger.com';
	public $smtpsecure = 'none';
	public $smtpport = '587';
	public $caching = '0';
	public $cache_handler = 'file';
	public $cachetime = '15';
	public $cache_platformprefix = '0';
	public $MetaDesc = 'PESO - Peratoken';
	public $MetaKeys = '';
	public $MetaTitle = '1';
	public $MetaAuthor = '1';
	public $MetaVersion = '0';
	public $robots = '';
	public $sef = '1';
	public $sef_rewrite = '1';
	public $sef_suffix = '0';
	public $unicodeslugs = '0';
	public $feed_limit = '10';
	public $feed_email = 'none';
	public $log_path = __DIR__ . DIRECTORY_SEPARATOR . 'administrator' . DIRECTORY_SEPARATOR . 'logs';
	public $tmp_path = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
	public $lifetime = '15000';
	public $session_handler = 'database';
	public $shared_session = '0';
	public $memcache_persist = '1';
	public $memcache_compress = '0';
	public $memcache_server_host = 'localhost';
	public $memcache_server_port = '11211';
	public $memcached_persist = '1';
	public $memcached_compress = '0';
	public $memcached_server_host = 'localhost';
	public $memcached_server_port = '11211';
	public $redis_persist = '1';
	public $redis_server_host = 'localhost';
	public $redis_server_port = '6379';
	public $redis_server_auth = '';
	public $redis_server_db = '0';
	public $proxy_enable = '0';
	public $proxy_host = '';
	public $proxy_port = '';
	public $proxy_user = '';
	public $proxy_pass = '';
	public $massmailoff = '0';
	public $replyto = '';
	public $replytoname = '';
	public $MetaRights = '';
	public $sitename_pagetitles = '0';
	public $force_ssl = '0';
	public $session_memcache_server_host = 'localhost';
	public $session_memcache_server_port = '11211';
	public $session_memcached_server_host = 'localhost';
	public $session_memcached_server_port = '11211';
	public $session_redis_persist = '1';
	public $session_redis_server_host = 'localhost';
	public $session_redis_server_port = '6379';
	public $session_redis_server_auth = '';
	public $session_redis_server_db = '0';
	public $frontediting = '1';
	public $cookie_domain = '';
	public $cookie_path = '';
	public $asset_id = '1';
	public $jumi_redirect_error = 'exch';
	public $behind_loadbalancer = '0';
	public $block_floc = '1';
}